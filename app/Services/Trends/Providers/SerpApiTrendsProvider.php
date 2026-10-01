<?php

declare(strict_types=1);

namespace App\Services\Trends\Providers;

use App\Services\Trends\Contracts\TrendsProviderInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Integration point: SerpApi's Google Trends engine
 * (https://serpapi.com/google-trends-api). Requires TRENDS_PROVIDER_KEY.
 * Chosen as the example because it's an official, ToS-compliant API
 * rather than scraping Google Trends directly. Swap for another
 * provider by implementing TrendsProviderInterface and updating the
 * TrendsServiceProvider binding — nothing else in the app changes.
 */
final class SerpApiTrendsProvider implements TrendsProviderInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly string $apiKey,
    ) {}

    public function currentTrends(array $keywords, string $industryHint = ''): array
    {
        if ($keywords === []) {
            return [];
        }

        try {
            $response = $this->client->get('https://serpapi.com/search.json', [
                'query' => [
                    'engine' => 'google_trends',
                    'q' => implode(',', array_slice($keywords, 0, 5)),
                    'data_type' => 'TIMESERIES',
                    'api_key' => $this->apiKey,
                ],
                'timeout' => 15,
            ]);
        } catch (GuzzleException $e) {
            Log::warning('SerpApi trends request failed.', ['error' => $e->getMessage()]);

            return [];
        }

        $data = json_decode((string) $response->getBody(), true) ?? [];
        $series = $data['interest_over_time']['timeline_data'] ?? [];

        if ($series === []) {
            return [];
        }

        $latest = end($series);
        $trends = [];

        foreach ($keywords as $i => $keyword) {
            $value = $latest['values'][$i]['extracted_value'] ?? null;

            if ($value === null) {
                continue;
            }

            $trends[] = [
                'topic' => $keyword,
                'momentum' => $value >= 60 ? 'rising' : ($value >= 30 ? 'steady' : 'low'),
                'summary' => "Relative search interest score: {$value}/100 (Google Trends).",
                'source' => 'google_trends_via_serpapi',
            ];
        }

        return $trends;
    }
}
