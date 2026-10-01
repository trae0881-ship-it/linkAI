<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use App\Models\Brand;
use Illuminate\Support\Facades\DB;

final class AnalyticsSummary
{
    /**
     * Latest value per (publication, metric) — analytics_events is an
     * append-only time series, so "current total" is the most recent
     * snapshot per metric, not a sum across snapshots.
     */
    public function forBrand(Brand $brand, ?\DateTimeInterface $since = null): array
    {
        $since ??= now()->subDays(30);

        $latestPerMetric = DB::table('analytics_events as ae')
            ->join('publications as p', 'p.id', '=', 'ae.publication_id')
            ->join('content as c', 'c.id', '=', 'p.content_id')
            ->where('p.workspace_id', $brand->workspace_id)
            ->where('c.brand_id', $brand->id)
            ->where('ae.recorded_at', '>=', $since)
            ->select('p.id as publication_id', 'p.platform', 'p.content_id', 'ae.metric', 'ae.value', 'ae.recorded_at')
            ->orderByDesc('ae.recorded_at')
            ->get()
            ->groupBy(fn ($row) => "{$row->publication_id}:{$row->metric}")
            ->map(fn ($rows) => $rows->first());

        $byPlatform = [];
        $byContent = [];

        foreach ($latestPerMetric as $row) {
            $byPlatform[$row->platform][$row->metric] = ($byPlatform[$row->platform][$row->metric] ?? 0) + $row->value;
            $byContent[$row->content_id][$row->metric] = ($byContent[$row->content_id][$row->metric] ?? 0) + $row->value;
        }

        return ['by_platform' => $byPlatform, 'by_content' => $byContent];
    }
}
