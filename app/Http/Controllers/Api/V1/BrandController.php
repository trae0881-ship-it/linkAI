<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Brand\StoreBrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;

final class BrandController extends Controller
{
    public function index(Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        return BrandResource::collection(
            $workspace->brands()->orderByDesc('created_at')->get()
        )->response();
    }

    public function store(StoreBrandRequest $request, Workspace $workspace): JsonResponse
    {
        // $workspace is re-derived from the route, matching the same
        // {workspace} the request authorized against — a brand can never
        // be created against a different workspace than the URL states.
        $brand = $workspace->brands()->create($request->safeData());

        return (new BrandResource($brand))->response()->setStatusCode(201);
    }

    public function show(Workspace $workspace, string $brandId): JsonResponse
    {
        $this->authorize('view', $workspace);

        $brand = $workspace->brands()->findOrFail($brandId);

        return (new BrandResource($brand))->response();
    }
}
