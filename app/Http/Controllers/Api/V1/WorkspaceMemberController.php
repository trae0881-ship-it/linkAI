<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Workspace\InviteMember;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\InviteMemberRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WorkspaceMemberController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        return response()->json([
            'data' => $workspace->members()->get(['users.id', 'name', 'email'])
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->pivot->role,
                ]),
        ]);
    }

    public function store(InviteMemberRequest $request, Workspace $workspace, InviteMember $action): JsonResponse
    {
        // Authorization (manageMembers + assignRole) already ran inside
        // InviteMemberRequest::authorize()/withValidator().
        $result = $action->handle(
            $workspace,
            \Illuminate\Support\Str::lower($request->string('email')->toString()),
            $request->string('role')->toString(),
            $request->user(),
        );

        $status = $result instanceof WorkspaceInvitation ? 'invited' : 'added';

        return response()->json(['status' => $status], 201);
    }
}
