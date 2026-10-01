<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\SecurityEvent;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route: ->middleware('workspace.role:EDITOR') combined with a
 * {workspace} route-model-bound param. Resolves the caller's role from
 * the workspace_user pivot in the DB — never from a header, query
 * param, or request body — and 403s (never a silent redirect) on
 * mismatch. A workspace_id that doesn't belong to the user, or doesn't
 * exist, is indistinguishable from "not a member" to the caller.
 */
final class EnsureWorkspaceRole
{
    private const array RANK = [
        'VIEWER' => 0,
        'APPROVER' => 1,
        'EDITOR' => 2,
        'ADMIN' => 3,
        'OWNER' => 4,
    ];

    public function handle(Request $request, Closure $next, string $minimumRole): Response
    {
        /** @var Workspace|null $workspace */
        $workspace = $request->route('workspace');
        $user = $request->user();

        if (is_string($workspace)) {
            $workspace = Workspace::query()->find($workspace);
        }

        $role = ($workspace && $user) ? $workspace->roleFor($user) : null;

        if ($role === null || (self::RANK[$role] ?? -1) < (self::RANK[$minimumRole] ?? PHP_INT_MAX)) {
            SecurityEvent::record(
                eventType: 'cross_tenant_denied',
                severity: 'warning',
                context: [
                    'workspace_id' => $workspace?->id,
                    'required_role' => $minimumRole,
                    'actual_role' => $role,
                ],
                userId: $user?->id,
                ipAddress: $request->ip(),
            );

            abort(403, 'You do not have access to this workspace.');
        }

        return $next($request);
    }
}
