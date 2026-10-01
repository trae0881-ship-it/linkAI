<?php

declare(strict_types=1);

namespace App\Services\Trends\Providers;

use App\Services\Trends\Contracts\TrendsProviderInterface;
use Illuminate\Support\Facades\Log;

/**
 * Used when TRENDS_PROVIDER is unset. Returns no trends rather than
 * fabricating plausible-looking ones — creative concept generation
 * degrades to "brand + audience only" and the UI should say so, instead
 * of silently presenting invented trend data as real research.
 */
final class NullTrendsProvider implements TrendsProviderInterface
{
    public function currentTrends(array $keywords, string $industryHint = ''): array
    {
        Log::warning('Trend research requested but no TRENDS_PROVIDER is configured.', [
            'keywords' => $keywords,
        ]);

        return [];
    }
}
