<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\Social\SocialAdapterFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SocialConnectionController extends Controller
{
    public function __construct(private readonly SocialAdapterFactory $factory) {}

    /**
     * Step 1: redirect the user to the platform's consent screen. The
     * `state` is a random, single-use token bound server-side (via
     * cache, 10 min TTL) to this exact workspace + platform + user —
     * the callback re-validates it, which is the CSRF protection for
     * the whole OAuth dance.
     */
    public function connect(Request $request, Workspace $workspace, string $platformFamily): JsonResponse
    {
        $state = Str::random(40);
        Cache::put("oauth_state:{$state}", [
            'workspace_id' => $workspace->id,
            'user_id' => $request->user()->id,
            'platform_family' => $platformFamily,
        ], now()->addMinutes(10));

        $adapter = $this->factory->make($platformFamily);
        $redirectUri = (string) config("services.{$platformFamily}.redirect");

        return response()->json([
            'authorization_url' => $adapter->authorizationUrl($state, $redirectUri),
        ]);
    }

    /**
     * Step 2: exchange the code, then fetch EVERY connectable
     * page/profile in one call and return them for the user to pick
     * from — nothing is persisted yet.
     */
    public function callback(Request $request, string $platformFamily): JsonResponse
    {
        $request->validate(['code' => ['required', 'string'], 'state' => ['required', 'string']]);

        $stateData = Cache::pull("oauth_state:{$request->string('state')}");

        if (! $stateData || $stateData['platform_family'] !== $platformFamily || $stateData['user_id'] !== $request->user()->id) {
            throw ValidationException::withMessages(['state' => ['Invalid or expired OAuth state.']]);
        }

        $adapter = $this->factory->make($platformFamily);
        $redirectUri = (string) config("services.{$platformFamily}.redirect");

        $token = $adapter->exchangeCodeForToken($request->string('code')->toString(), $redirectUri);
        $accounts = $adapter->listConnectableAccounts($token['access_token']);

        // Held server-side, short TTL, keyed by a fresh token — the
        // frontend never sees or round-trips the raw access token.
        $selectionToken = Str::random(40);
        Cache::put("oauth_selection:{$selectionToken}", [
            'workspace_id' => $stateData['workspace_id'],
            'platform_family' => $platformFamily,
            'user_access_token' => $token['access_token'],
            'accounts' => $accounts,
        ], now()->addMinutes(10));

        return response()->json([
            'selection_token' => $selectionToken,
            'accounts' => array_map(
                fn ($a) => ['external_account_id' => $a->externalAccountId, 'name' => $a->name, 'type' => $a->type],
                $accounts,
            ),
        ]);
    }

    /** Step 3: user picks which of the listed accounts to activate — any subset, including all of them at once. */
    public function activate(Request $request, Workspace $workspace): JsonResponse
    {
        $request->validate([
            'selection_token' => ['required', 'string'],
            'external_account_ids' => ['required', 'array', 'min:1'],
            'external_account_ids.*' => ['string'],
        ]);

        $selection = Cache::get("oauth_selection:{$request->string('selection_token')}");

        if (! $selection || $selection['workspace_id'] !== $workspace->id) {
            throw ValidationException::withMessages(['selection_token' => ['Invalid or expired selection.']]);
        }

        $platform = $selection['platform_family'];
        $created = [];

        foreach ($selection['accounts'] as $account) {
            if (! in_array($account->externalAccountId, $request->input('external_account_ids'), true)) {
                continue;
            }

            $created[] = SocialAccount::query()->updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'platform' => "{$platform}_{$account->type}",
                    'external_account_id' => $account->externalAccountId,
                ],
                [
                    'connected_by' => $request->user()->id,
                    'external_account_name' => $account->name,
                    'external_account_type' => $account->type,
                    'access_token' => $account->accessToken,
                    'refresh_token' => $account->refreshToken,
                    'token_expires_at' => $account->tokenExpiresAt,
                    'is_active' => true,
                    'last_connected_at' => now(),
                ],
            );
        }

        AuditLog::record(
            workspaceId: $workspace->id,
            userId: $request->user()->id,
            action: 'social_account.connected',
            meta: ['platform' => $platform, 'count' => count($created)],
        );

        return response()->json(['connected' => count($created)], 201);
    }
}
