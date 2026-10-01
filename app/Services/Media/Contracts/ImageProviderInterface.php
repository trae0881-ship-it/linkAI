<?php

declare(strict_types=1);

namespace App\Services\Media\Contracts;

interface ImageProviderInterface
{
    /**
     * @return array{binary: string, mime_type: string} raw image bytes + content type
     */
    public function generate(string $prompt, string $size = '1024x1024'): array;
}
