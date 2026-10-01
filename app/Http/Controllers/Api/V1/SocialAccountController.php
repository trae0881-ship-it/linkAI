<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SocialAccountResource;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;

final class SocialAccountController extends Controller
{
    public function index(Workspace $workspace): JsonResponse
    {
        return SocialAccountResource::collection(
            $workspace->socialAccounts()->orderBy('platform')->get()
        )->response();
    }

    public function destroy(Workspace $workspace, string $socialAccountId): JsonResponse
    {
        $account = $workspace->socialAccounts()->findOrFail($socialAccountId);
        $account->update(['is_active' => false]);

        return response()->json(status: 204);
    }
}
