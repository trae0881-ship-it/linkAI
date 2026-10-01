<?php

declare(strict_types=1);

namespace App\Services\Social\Adapters;

use App\Services\Social\Contracts\MetricsProviderInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

final class MetaMetricsProvider implements MetricsProviderInterface
{
    private const string GRAPH_VERSION = 'v21.0';

    public function __construct(private readonly Client $client) {}

    public function fetchMetrics(string $externalPostId, string $accessToken): array
    {
        try {
            $response = $this->client->get(
                'https://graph.facebook.com/'.self::GRAPH_VERSION."/{$externalPostId}/insights",
                ['query' => ['metric' => 'post_impressions,post_engaged_users,post_clicks', 'access_token' => $accessToken]],
            );
        } catch (GuzzleException $e) {
            Log::warning('Meta metrics fetch failed.', ['post_id' => $externalPostId, 'error' => $e->getMessage()]);

            return [];
        }

        $data = json_decode((string) $response->getBody(), true) ?? [];
        $metrics = [];

        foreach ($data['data'] ?? [] as $entry) {
            $value = $entry['values'][0]['value'] ?? null;
            if (is_int($value)) {
                $metrics[$this->normalizeMetricName($entry['name'])] = $value;
            }
        }

        return $metrics;
    }

    private function normalizeMetricName(string $metaMetric): string
    {
        return match ($metaMetric) {
            'post_impressions' => 'impressions',
            'post_engaged_users' => 'engaged_users',
            'post_clicks' => 'clicks',
            default => $metaMetric,
        };
    }
}
