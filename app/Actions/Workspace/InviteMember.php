<?php

declare(strict_types=1);

namespace App\Actions\Workspace;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class InviteMember
{
    public function handle(Workspace $workspace, string $email, string $role, User $inviter): WorkspaceInvitation|User
    {
        $existingUser = User::query()->where('email', $email)->first();

        if ($existingUser) {
            $workspace->members()->syncWithoutDetaching([
                $existingUser->id => ['id' => (string) Str::uuid(), 'role' => $role],
            ]);

            AuditLog::record(
                workspaceId: $workspace->id,
                userId: $inviter->id,
                action: 'workspace.member_added',
                subjectType: User::class,
                subjectId: $existingUser->id,
                meta: ['role' => $role],
            );

            return $existingUser;
        }

        $rawToken = Str::random(64);

        $invitation = WorkspaceInvitation::query()->create([
            'workspace_id' => $workspace->id,
            'invited_by' => $inviter->id,
            'email' => $email,
            'role' => $role,
            'token_hash' => hash('sha256', $rawToken), // raw token never persisted
            'expires_at' => now()->addDays(7),
        ]);

        $invitation->notify(new WorkspaceInvitationNotification($invitation, $rawToken));

        AuditLog::record(
            workspaceId: $workspace->id,
            userId: $inviter->id,
            action: 'workspace.member_invited',
            subjectType: WorkspaceInvitation::class,
            subjectId: $invitation->id,
            meta: ['role' => $role, 'email' => $email],
        );

        return $invitation;
    }
}
