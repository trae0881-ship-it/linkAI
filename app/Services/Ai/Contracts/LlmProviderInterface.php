<?php

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

interface LlmProviderInterface
{
    /**
     * Sends a system prompt (trusted, written by us) plus a user payload
     * (which itself embeds untrusted source content, clearly delimited)
     * and returns the raw text response. Callers are responsible for
     * schema-validating the result — this interface makes no guarantee
     * the provider actually returned valid JSON.
     */
    public function complete(string $systemPrompt, string $userPrompt, ?string $jsonSchemaHint = null): string;
}
