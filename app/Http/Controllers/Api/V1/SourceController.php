<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Source\CreateSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Source\StoreSourceRequest;
use App\Http\Resources\SourceResource;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;

final class SourceController extends Controller
{
    public function store(StoreSourceRequest $request, Workspace $workspace, CreateSource $action): JsonResponse
    {
        // Brand must belong to this same workspace — findOrFail on the
        // workspace relation, not a bare Brand::find($id).
        $brand = $workspace->brands()->findOrFail($request->input('brand_id'));

        $source = $action->handle($brand, $request->input('url'), $request->user());

        return (new SourceResource($source))->response()->setStatusCode(202);
    }

    public function index(Workspace $workspace, string $brandId): JsonResponse
    {
        $brand = $workspace->brands()->findOrFail($brandId);

        return SourceResource::collection(
            $brand->sources()->with('businessProfile')->orderByDesc('created_at')->limit(50)->get()
        )->response();
    }

    /** Poll endpoint — the frontend calls this while status is pending/validating/.../analyzing. */
    public function show(Workspace $workspace, string $brandId, string $sourceId): JsonResponse
    {
        $brand = $workspace->brands()->findOrFail($brandId);
        $source = $brand->sources()->with('businessProfile')->findOrFail($sourceId);

        return (new SourceResource($source))->response();
    }
}
