<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Publishing\RequestPublishAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\RequestPublishRequest;
use App\Http\Resources\PublicationResource;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

final class PublishController extends Controller
{
    public function store(RequestPublishRequest $request, Workspace $workspace, string $brandId, string $contentId, RequestPublishAction $action): JsonResponse
    {
        $brand = $workspace->brands()->findOrFail($brandId);
        $content = $brand->content()->findOrFail($contentId);

        $scheduledAt = $request->filled('scheduled_at') ? Carbon::parse($request->input('scheduled_at')) : null;

        $publications = $action->handle(
            $content,
            $request->input('social_account_ids'),
            $scheduledAt,
            $request->user(),
        );

        return PublicationResource::collection(collect($publications))->response()->setStatusCode(202);
    }

    public function index(Workspace $workspace, string $brandId, string $contentId): JsonResponse
    {
        $brand = $workspace->brands()->findOrFail($brandId);
        $content = $brand->content()->findOrFail($contentId);

        return PublicationResource::collection($content->publications()->get())->response();
    }
}
