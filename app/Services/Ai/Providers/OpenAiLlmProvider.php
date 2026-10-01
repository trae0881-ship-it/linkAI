<?php

declare(strict_types=1);

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\LlmProviderInterface;
use GuzzleHttp\Client;

final class OpenAiLlmProvider implements LlmProviderInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly string $apiKey,
        private readonly string $model,
    ) {}

    public function complete(string $systemPrompt, string $userPrompt, ?string $jsonSchemaHint = null): string
    {
        $response = $this->client->post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->model,
                'temperature' => 0.2,
                'response_format' => $jsonSchemaHint !== null ? ['type' => 'json_object'] : ['type' => 'text'],
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ],
            'timeout' => 60,
        ]);

        $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        return $data['choices'][0]['message']['content'] ?? '';
    }
}
