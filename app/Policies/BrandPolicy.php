<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;

/**
 * Every domain Policy follows this pattern: resolve the caller's role for
 * the resource's workspace (never a client-supplied role) and authorize
 * from that. Controllers must load the resource through
 * $workspace->brands()->findOrFail($id) so a mismatched workspace_id
 * 404s before the policy is even consulted.
 */
final class BrandPolicy
{
    private const array WRITE_ROLES = ['OWNER', 'ADMIN', 'EDITOR'];

    private const array READ_ROLES = ['OWNER', 'ADMIN', 'EDITOR', 'APPROVER', 'VIEWER'];

    public function view(User $user, Brand $brand): bool
    {
        return in_array($brand->workspace->roleFor($user), self::READ_ROLES, true);
    }

    public function create(User $user, string $workspaceId): bool
    {
        $workspace = $user->workspaces()->whereKey($workspaceId)->first();

        return $workspace !== null
            && in_array($workspace->pivot->role, self::WRITE_ROLES, true);
    }

    public function update(User $user, Brand $brand): bool
    {
        return in_array($brand->workspace->roleFor($user), self::WRITE_ROLES, true);
    }

    public function delete(User $user, Brand $brand): bool
    {
        return in_array($brand->workspace->roleFor($user), ['OWNER', 'ADMIN'], true);
    }
}
