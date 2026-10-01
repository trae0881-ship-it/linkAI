<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Media\Contracts\ImageProviderInterface;
use App\Services\Media\Contracts\VideoProviderInterface;
use App\Services\Media\Providers\NullVideoProvider;
use App\Services\Media\Providers\OpenAiImageProvider;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;

final class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ImageProviderInterface::class, function (): ImageProviderInterface {
            $provider = (string) config('services.image.provider', 'openai');

            return match ($provider) {
                'openai' => new OpenAiImageProvider(new Client, (string) config('services.image.key')),
                default => throw new \RuntimeException("Unsupported image provider: {$provider}"),
            };
        });

        // No video vendor is chosen for you (see NullVideoProvider) — add
        // a case here once one is selected and configured.
        $this->app->bind(VideoProviderInterface::class, fn (): VideoProviderInterface => new NullVideoProvider);
    }
}
