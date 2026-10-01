<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Analytics\AnalyticsSummary;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class AnalyticsController extends Controller
{
    public function show(Request $request, Workspace $workspace, string $brandId, AnalyticsSummary $summary): JsonResponse
    {
        $brand = $workspace->brands()->findOrFail($brandId);
        $since = $request->filled('since') ? Carbon::parse($request->input('since')) : null;

        return response()->json($summary->forBrand($brand, $since));
    }
}
