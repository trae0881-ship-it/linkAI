<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Approval\ApproveContentAction;
use App\Domain\Approval\UpdateContentAction;
use App\Domain\Caption\GenerateCaptionsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\UpdateContentRequest;
use App\Http\Resources\ContentResource;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Domain\Caption\CaptionGenerationException;

final class ContentEditController extends Controller
{
    public function generateCaptions(Workspace $workspace, string $brandId, string $contentId, GenerateCaptionsAction $action): JsonResponse
    {
        $brand = $workspace->brands()->findOrFail($brandId);
        $content = $brand->content()->findOrFail($contentId);

        try {
            $updated = $action->handle($content);
        } catch (CaptionGenerationException $e) {
            return response()->json(['message' => 'Caption generation failed: '.$e->getMessage()], 502);
        }

        return (new ContentResource($updated))->response();
    }

    public function update(UpdateContentRequest $request, Workspace $workspace, string $brandId, string $contentId, UpdateContentAction $action): JsonResponse
    {
        $brand = $workspace->brands()->findOrFail($brandId);
        $content = $brand->content()->findOrFail($contentId);

        $updated = $action->handle($content, $request->validated(), $request->user());

        return (new ContentResource($updated))->response();
    }

    public function approve(Request $request, Workspace $workspace, string $brandId, string $contentId, ApproveContentAction $action): JsonResponse
    {
        $brand = $workspace->brands()->findOrFail($brandId);
        $content = $brand->content()->findOrFail($contentId);

        $updated = $action->handle($content, $request->user());

        return (new ContentResource($updated))->response();
    }
}
