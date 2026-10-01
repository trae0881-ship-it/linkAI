<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Trends\Contracts\TrendsProviderInterface;
use App\Services\Trends\Providers\NullTrendsProvider;
use App\Services\Trends\Providers\SerpApiTrendsProvider;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;

final class TrendsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TrendsProviderInterface::class, function (): TrendsProviderInterface {
            $provider = (string) config('services.trends.provider', '');
            $key = config('services.trends.key');

            return match (true) {
                $provider === 'serpapi' && ! empty($key) => new SerpApiTrendsProvider(new Client, (string) $key),
                default => new NullTrendsProvider,
            };
        });
    }
}
