<?php

declare(strict_types=1);

namespace App\Domain\Media;

use App\Services\Ai\Contracts\LlmProviderInterface;

/**
 * Turns one selected CreativeConcept + a requested quantity into N
 * distinct, detailed image/video generation prompts — the "prompt
 * generation" step between concept selection and media generation.
 */
final class PromptBuilder
{
    private const string SYSTEM_PROMPT = <<<'PROMPT'
You are a prompt engineer for AI image/video generation. You will be
given a creative concept's brief (format, visual direction, key message,
tone) and a brand description. Produce production-ready generation
prompts for an image/video model. Return ONLY JSON, no markdown fences:

{"prompts": [string, string, ...]}

Rules:
- Return exactly the requested quantity of prompts.
- Every prompt must be self-contained (no "same as above") and visually
  specific: subject, composition, lighting, color palette, style, mood.
- Prompts must be distinct variations on the concept, not copies.
- Never include real brand logos, trademarked characters, or real
  people's likenesses in the prompt.
- Keep each prompt under 400 characters.
PROMPT;

    public function __construct(
        private readonly LlmProviderInterface $llm,
    ) {}

    public function build(array $mediaBrief, string $brandDescription, int $quantity): array
    {
        $payload = json_encode([
            'media_brief' => $mediaBrief,
            'brand_description' => $brandDescription,
            'requested_quantity' => $quantity,
        ]);

        $userPrompt = "<DATA>\n{$payload}\n</DATA>\n\nGenerate exactly {$quantity} prompt(s) now.";

        $raw = trim($this->llm->complete(self::SYSTEM_PROMPT, $userPrompt, jsonSchemaHint: 'prompts_v1'));
        $raw = preg_replace('/^```(json)?|```$/m', '', $raw) ?? $raw;

        try {
            $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new PromptBuildException('LLM did not return valid JSON: '.$e->getMessage());
        }

        if (! isset($data['prompts']) || ! is_array($data['prompts']) || count($data['prompts']) !== $quantity) {
            throw new PromptBuildException("Expected exactly {$quantity} prompts, got ".count($data['prompts'] ?? []));
        }

        foreach ($data['prompts'] as $prompt) {
            if (! is_string($prompt) || trim($prompt) === '') {
                throw new PromptBuildException('Every prompt must be a non-empty string.');
            }
        }

        return $data['prompts'];
    }
}
