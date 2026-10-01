<?php

declare(strict_types=1);

namespace App\Services\Social\Contracts;

interface MetricsProviderInterface
{
    /**
     * @return array<string, int> metric name => value, e.g. ['impressions' => 1200, 'likes' => 40]
     */
    public function fetchMetrics(string $externalPostId, string $accessToken): array;
}
