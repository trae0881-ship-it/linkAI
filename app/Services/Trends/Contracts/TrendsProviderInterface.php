<?php

declare(strict_types=1);

namespace App\Services\Trends\Contracts;

interface TrendsProviderInterface
{
    /**
     * @param string[] $keywords seed keywords/topics (e.g. from BusinessProfile seo_data)
     * @return array<int, array{topic: string, momentum: string, summary: string, source: string}>
     */
    public function currentTrends(array $keywords, string $industryHint = ''): array;
}
