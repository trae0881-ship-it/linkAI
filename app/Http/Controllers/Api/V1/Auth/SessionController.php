<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\AuthenticateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class SessionController extends Controller
{
    public function store(LoginRequest $request, AuthenticateUser $action): JsonResponse
    {
        $key = $request->throttleKey();

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => ["Too many login attempts. Try again in {$seconds} seconds."],
            ]);
        }

        [$email, $password] = array_values($request->credentials());

        try {
            $user = $action->handle($email, $password, $request->ip());
        } catch (ValidationException $e) {
            RateLimiter::hit($key, decaySeconds: 60);

            throw $e;
        }

        RateLimiter::clear($key);

        if ($request->hasSession()) {
            $request->session()->regenerate(); // prevent session fixation
        }
        Auth::login($user, remember: false);

        AuditLog::record(
            workspaceId: null,
            userId: $user->id,
            action: 'user.login',
            ipAddress: $request->ip(),
        );

        return (new UserResource($user))->response();
    }

    public function destroy(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        AuditLog::record(
            workspaceId: null,
            userId: $userId,
            action: 'user.logout',
            ipAddress: $request->ip(),
        );

        return response()->json(status: 204);
    }
}
