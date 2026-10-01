<?php

declare(strict_types=1);

namespace App\Services\Media\Providers;

use App\Services\Media\Contracts\VideoProviderInterface;

/**
 * INTEGRATION POINT: no video generation vendor is wired by default.
 * The blueprint doesn't specify one, and none was chosen for you rather
 * than inventing a fake integration. To add one (e.g. Runway ML, Google
 * Veo via Vertex AI, Kling): implement VideoProviderInterface with a
 * submit()/poll() pair matching that vendor's async job model, set
 * VIDEO_PROVIDER + VIDEO_PROVIDER_KEY in .env, and add a case for it in
 * MediaServiceProvider.
 */
final class NullVideoProvider implements VideoProviderInterface
{
    public function submit(string $prompt, int $durationSeconds = 8): string
    {
        throw new VideoProviderNotConfiguredException(
            'No video generation provider is configured. Set VIDEO_PROVIDER and VIDEO_PROVIDER_KEY.',
        );
    }

    public function poll(string $providerJobId): array
    {
        throw new VideoProviderNotConfiguredException('No video generation provider is configured.');
    }
}
