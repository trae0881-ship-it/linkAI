<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class AuthenticateUser
{
    private const int MAX_ATTEMPTS = 5;

    private const int LOCKOUT_MINUTES = 15;

    /**
     * Verifies credentials with account-level lockout (DB-backed, so it
     * survives across requests/workers, unlike a request-only throttle).
     * Route-level RateLimiter (see routes/api.php) provides the IP-based
     * layer on top of this.
     */
    public function handle(string $email, string $password, ?string $ip): User
    {
        $user = User::query()->where('email', $email)->first();

        if ($user && $user->locked_until && $user->locked_until->isFuture()) {
            SecurityEvent::record(
                eventType: 'login_lockout_active',
                severity: 'warning',
                context: ['email' => $email],
                userId: $user->id,
                ipAddress: $ip,
            );

            throw ValidationException::withMessages([
                'email' => ['Too many failed attempts. Try again later.'],
            ]);
        }

        if (! $user || ! Hash::check($password, $user->password)) {
            if ($user) {
                $this->registerFailedAttempt($user, $ip);
            }

            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if ($user->failed_login_attempts > 0 || $user->locked_until) {
            $user->forceFill([
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ])->save();
        }

        return $user;
    }

    private function registerFailedAttempt(User $user, ?string $ip): void
    {
        $attempts = $user->failed_login_attempts + 1;
        $lockedUntil = $attempts >= self::MAX_ATTEMPTS
            ? now()->addMinutes(self::LOCKOUT_MINUTES)
            : null;

        $user->forceFill([
            'failed_login_attempts' => $attempts,
            'locked_until' => $lockedUntil,
        ])->save();

        if ($lockedUntil !== null) {
            SecurityEvent::record(
                eventType: 'login_lockout_triggered',
                severity: 'warning',
                context: ['email' => $user->email, 'attempts' => $attempts],
                userId: $user->id,
                ipAddress: $ip,
            );
        }
    }
}
