<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\AuditLog;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PasswordResetController extends Controller
{
    /**
     * Always returns a generic 202, whether or not the email exists —
     * prevents account enumeration via the "forgot password" endpoint.
     */
    public function sendResetLink(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        return response()->json(
            ['message' => 'If that email is registered, a reset link has been sent.'],
            202,
        );
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($request): void {
                $user->forceFill([
                    'password' => Hash::make($password), // model cast would double-hash; explicit here
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                ])->save();

                $user->tokens()->delete(); // revoke all existing API sessions on reset

                event(new PasswordReset($user));

                AuditLog::record(
                    workspaceId: null,
                    userId: $user->id,
                    action: 'user.password_reset',
                    ipAddress: $request->ip(),
                );
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            SecurityEvent::record(
                eventType: 'password_reset_token_invalid',
                severity: 'warning',
                context: ['email' => Str::lower((string) $request->input('email'))],
                ipAddress: $request->ip(),
            );

            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return response()->json(['message' => __($status)]);
    }
}
