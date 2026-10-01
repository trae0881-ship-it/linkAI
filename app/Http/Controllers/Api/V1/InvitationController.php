<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Workspace\AcceptInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\AcceptInvitationRequest;
use App\Http\Resources\WorkspaceResource;
use Illuminate\Http\JsonResponse;

final class InvitationController extends Controller
{
    public function accept(AcceptInvitationRequest $request, AcceptInvitation $action): JsonResponse
    {
        $invitation = $action->handle($request->string('token')->toString(), $request->user());

        return (new WorkspaceResource($invitation->workspace->fresh()))->response();
    }
}
