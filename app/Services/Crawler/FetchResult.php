<?php

declare(strict_types=1);

namespace App\Services\Crawler;

final class FetchResult
{
    public function __construct(
        public readonly string $finalUrl,
        public readonly int $statusCode,
        public readonly string $contentType,
        public readonly string $body,
    ) {}
}
