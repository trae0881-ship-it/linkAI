<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

final class RegisterUser
{
    public function handle(array $safeData, ?string $ip): User
    {
        $user = User::query()->create($safeData);

        event(new Registered($user));

        AuditLog::record(
            workspaceId: null,
            userId: $user->id,
            action: 'user.registered',
            subjectType: User::class,
            subjectId: $user->id,
            meta: [], // never log the password, even hashed
            ipAddress: $ip,
        );

        return $user;
    }
}
