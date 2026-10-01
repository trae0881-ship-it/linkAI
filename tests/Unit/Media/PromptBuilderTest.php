<?php

declare(strict_types=1);

namespace Tests\Unit\Media;

use App\Domain\Media\PromptBuildException;
use App\Domain\Media\PromptBuilder;
use App\Services\Ai\Contracts\LlmProviderInterface;
use Tests\TestCase;

final class PromptBuilderTest extends TestCase
{
    public function test_returns_exact_requested_quantity(): void
    {
        $llm = $this->createMock(LlmProviderInterface::class);
        $llm->method('complete')->willReturn(json_encode(['prompts' => ['a detailed prompt one', 'a detailed prompt two', 'a detailed prompt three']]));

        $prompts = (new PromptBuilder($llm))->build(['format' => 'image'], 'Acme sells widgets', 3);

        $this->assertCount(3, $prompts);
    }

    public function test_rejects_wrong_quantity(): void
    {
        $llm = $this->createMock(LlmProviderInterface::class);
        $llm->method('complete')->willReturn(json_encode(['prompts' => ['only one']]));

        $this->expectException(PromptBuildException::class);

        (new PromptBuilder($llm))->build(['format' => 'image'], 'Acme sells widgets', 3);
    }

    public function test_rejects_empty_prompt_strings(): void
    {
        $llm = $this->createMock(LlmProviderInterface::class);
        $llm->method('complete')->willReturn(json_encode(['prompts' => ['valid one', '   ']]));

        $this->expectException(PromptBuildException::class);

        (new PromptBuilder($llm))->build(['format' => 'image'], 'Acme sells widgets', 2);
    }
}
