<?php

declare(strict_types=1);

namespace App\Services\Media\Providers;

use App\Services\Media\Contracts\ImageProviderInterface;
use GuzzleHttp\Client;

final class OpenAiImageProvider implements ImageProviderInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly string $apiKey,
    ) {}

    public function generate(string $prompt, string $size = '1024x1024'): array
    {
        $response = $this->client->post('https://api.openai.com/v1/images/generations', [
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'gpt-image-1',
                'prompt' => $prompt,
                'size' => $size,
                'n' => 1,
                'response_format' => 'b64_json',
            ],
            'timeout' => 120,
        ]);

        $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $b64 = $data['data'][0]['b64_json'] ?? null;

        if ($b64 === null) {
            throw new ImageProviderException('Image provider returned no image data.');
        }

        return [
            'binary' => base64_decode($b64, strict: true),
            'mime_type' => 'image/png',
        ];
    }
}
