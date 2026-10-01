<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Creative\SelectConcept;
use App\Http\Controllers\Controller;
use App\Http\Resources\CreativeConceptResource;
use App\Jobs\GenerateConceptsJob;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ConceptController extends Controller
{
    /** Kick off generation for an analyzed source's business profile. */
    public function generate(Workspace $workspace, string $brandId, string $sourceId): JsonResponse
    {
        $brand = $workspace->brands()->findOrFail($brandId);
        $source = $brand->sources()->with('businessProfile')->findOrFail($sourceId);

        if ($source->status !== 'analyzed' || ! $source->businessProfile) {
            return response()->json(['message' => 'Source has not finished analysis yet.'], 409);
        }

        GenerateConceptsJob::dispatch($source->businessProfile->id);

        return response()->json(['status' => 'generating'], 202);
    }

    public function index(Workspace $workspace, string $brandId, string $businessProfileId): JsonResponse
    {
        $brand = $workspace->brands()->findOrFail($brandId);

        $concepts = $brand->concepts()
            ->where('business_profile_id', $businessProfileId)
            ->orderByDesc('created_at')
            ->get();

        return CreativeConceptResource::collection($concepts)->response();
    }

    public function select(Request $request, Workspace $workspace, string $brandId, string $conceptId, SelectConcept $action): JsonResponse
    {
        $brand = $workspace->brands()->findOrFail($brandId);
        $concept = $brand->concepts()->findOrFail($conceptId);

        $updated = $action->handle($concept, $request->user());

        return (new CreativeConceptResource($updated))->response();
    }
}
