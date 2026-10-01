<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Ai\Contracts\LlmProviderInterface;
use App\Services\Ai\Providers\OpenAiLlmProvider;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;

final class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LlmProviderInterface::class, function (): LlmProviderInterface {
            $provider = (string) config('services.llm.provider', 'openai');

            return match ($provider) {
                'openai' => new OpenAiLlmProvider(
                    client: new Client,
                    apiKey: (string) config('services.llm.key'),
                    model: (string) config('services.llm.model', 'gpt-4o-mini'),
                ),
                default => throw new \RuntimeException("Unsupported LLM provider: {$provider}"),
            };
        });
    }
}
