<?php

declare(strict_types=1);

namespace App\Services\Social\Contracts;

final class PublishResult
{
    public function __construct(
        public readonly string $externalPostId,
        public readonly ?string $externalPostUrl = null,
    ) {}
}
