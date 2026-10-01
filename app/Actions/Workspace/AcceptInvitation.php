<?php

declare(strict_types=1);

namespace App\Actions\Workspace;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AcceptInvitation
{
    public function handle(string $rawToken, User $acceptor): WorkspaceInvitation
    {
        $invitation = WorkspaceInvitation::query()
            ->where('token_hash', hash('sha256', $rawToken))
            ->first();

        if (! $invitation || ! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'token' => ['This invitation is invalid or has expired.'],
            ]);
        }

        // The invite was addressed to a specific email — only that
        // account (once registered/logged in) may redeem it.
        if (Str::lower($invitation->email) !== Str::lower($acceptor->email)) {
            throw ValidationException::withMessages([
                'token' => ['This invitation was sent to a different email address.'],
            ]);
        }

        DB::transaction(function () use ($invitation, $acceptor): void {
            $invitation->workspace->members()->syncWithoutDetaching([
                $acceptor->id => ['id' => (string) Str::uuid(), 'role' => $invitation->role],
            ]);

            $invitation->forceFill(['accepted_at' => now()])->save();

            AuditLog::record(
                workspaceId: $invitation->workspace_id,
                userId: $acceptor->id,
                action: 'workspace.invitation_accepted',
                subjectType: WorkspaceInvitation::class,
                subjectId: $invitation->id,
            );
        });

        return $invitation;
    }
}
