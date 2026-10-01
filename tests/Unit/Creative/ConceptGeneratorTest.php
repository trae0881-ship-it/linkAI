<?php

declare(strict_types=1);

namespace Tests\Unit\Creative;

use App\Domain\Creative\ConceptGenerationException;
use App\Domain\Creative\ConceptGenerator;
use App\Services\Ai\Contracts\LlmProviderInterface;
use Tests\TestCase;

final class ConceptGeneratorTest extends TestCase
{
    private function validConcept(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Behind the scenes',
            'reasoning' => 'Builds authenticity',
            'pros' => ['authentic'],
            'cons' => ['time-intensive'],
            'risks' => ['may reveal process details competitors could copy'],
            'platform_suitability' => [
                'instagram' => ['score' => 8, 'note' => 'strong fit'],
                'linkedin' => ['score' => 4, 'note' => 'weaker fit'],
                'pinterest' => ['score' => 3, 'note' => 'weak fit'],
                'x' => ['score' => 6, 'note' => 'ok fit'],
                'tiktok' => ['score' => 9, 'note' => 'strong fit'],
            ],
            'trend_references' => [],
            'media_brief' => ['format' => 'video', 'visual_direction' => 'candid', 'key_message' => 'authenticity', 'tone' => 'warm'],
        ], $overrides);
    }

    public function test_parses_valid_concepts(): void
    {
        $llm = $this->createMock(LlmProviderInterface::class);
        $llm->method('complete')->willReturn(json_encode(['concepts' => [$this->validConcept()]]));

        $result = (new ConceptGenerator($llm))->generate([], [], 'Acme');

        $this->assertCount(1, $result);
        $this->assertSame('Behind the scenes', $result[0]['title']);
    }

    public function test_rejects_concept_missing_risks(): void
    {
        $concept = $this->validConcept(['risks' => []]);

        $llm = $this->createMock(LlmProviderInterface::class);
        $llm->method('complete')->willReturn(json_encode(['concepts' => [$concept]]));

        $this->expectException(ConceptGenerationException::class);
        $this->expectExceptionMessage('must list at least one risk');

        (new ConceptGenerator($llm))->generate([], [], 'Acme');
    }

    public function test_rejects_concept_missing_a_platform_score(): void
    {
        $concept = $this->validConcept();
        unset($concept['platform_suitability']['tiktok']);

        $llm = $this->createMock(LlmProviderInterface::class);
        $llm->method('complete')->willReturn(json_encode(['concepts' => [$concept]]));

        $this->expectException(ConceptGenerationException::class);

        (new ConceptGenerator($llm))->generate([], [], 'Acme');
    }

    public function test_rejects_empty_concepts_array(): void
    {
        $llm = $this->createMock(LlmProviderInterface::class);
        $llm->method('complete')->willReturn(json_encode(['concepts' => []]));

        $this->expectException(ConceptGenerationException::class);

        (new ConceptGenerator($llm))->generate([], [], 'Acme');
    }
}
