<?php

declare(strict_types=1);

namespace App\Services\Media\Contracts;

interface VideoProviderInterface
{
    /**
     * Video generation is typically async on the provider side.
     * Returns a provider job id to poll, not the finished asset.
     */
    public function submit(string $prompt, int $durationSeconds = 8): string;

    /**
     * @return array{status: string, binary: ?string, mime_type: ?string} status in pending|processing|completed|failed
     */
    public function poll(string $providerJobId): array;
}
