<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Media\CreateContentRequestAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\RequestContentRequest;
use App\Http\Resources\ContentResource;
use App\Models\Workspace;
use App\Domain\Media\PromptBuildException;
use Illuminate\Http\JsonResponse;

final class ContentController extends Controller
{
    public function store(
        RequestContentRequest $request,
        Workspace $workspace,
        string $brandId,
        string $conceptId,
        CreateContentRequestAction $action,
    ): JsonResponse {
        $brand = $workspace->brands()->findOrFail($brandId);
        $concept = $brand->concepts()->where('status', 'selected')->findOrFail($conceptId);

        try {
            $content = $action->handle(
                $concept,
                (int) $request->input('quantity'),
                (string) $request->input('media_format'),
                $request->user(),
            );
        } catch (PromptBuildException $e) {
            return response()->json(['message' => 'Prompt generation failed: '.$e->getMessage()], 502);
        }

        return (new ContentResource($content))->response()->setStatusCode(202);
    }

    public function index(Workspace $workspace, string $brandId): JsonResponse
    {
        $brand = $workspace->brands()->findOrFail($brandId);

        return ContentResource::collection(
            $brand->content()->with('mediaAssets')->orderByDesc('created_at')->limit(50)->get()
        )->response();
    }

    public function show(Workspace $workspace, string $brandId, string $contentId): JsonResponse
    {
        $brand = $workspace->brands()->findOrFail($brandId);
        $content = $brand->content()->with('mediaAssets')->findOrFail($contentId);

        return (new ContentResource($content))->response();
    }
}
