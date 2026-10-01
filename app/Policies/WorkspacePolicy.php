<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

final class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->roleFor($user) !== null;
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return in_array($workspace->roleFor($user), ['OWNER', 'ADMIN'], true);
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return $workspace->roleFor($user) === 'OWNER';
    }

    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return in_array($workspace->roleFor($user), ['OWNER', 'ADMIN'], true);
    }

    /**
     * Only an OWNER/ADMIN may grant OWNER or ADMIN — an EDITOR/APPROVER
     * inviting people can never hand out more power than they have, and
     * an ADMIN can never mint another OWNER.
     */
    public function assignRole(User $actor, Workspace $workspace, string $role): bool
    {
        $actorRole = $workspace->roleFor($actor);

        if (! in_array($actorRole, ['OWNER', 'ADMIN'], true)) {
            return false;
        }

        if ($role === 'OWNER') {
            return $actorRole === 'OWNER';
        }

        return true;
    }
}
