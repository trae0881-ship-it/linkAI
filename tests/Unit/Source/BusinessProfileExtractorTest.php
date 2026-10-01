<?php

declare(strict_types=1);

namespace Tests\Unit\Source;

use App\Domain\Source\BusinessProfileExtractor;
use App\Domain\Source\ExtractionValidationException;
use App\Services\Ai\Contracts\LlmProviderInterface;
use Tests\TestCase;

final class BusinessProfileExtractorTest extends TestCase
{
    public function test_parses_well_formed_llm_response(): void
    {
        $validJson = json_encode([
            'brand' => ['name' => 'Acme', 'description' => 'Widgets', 'tone' => ['friendly'], 'colors' => []],
            'offer' => ['products_or_services' => ['widgets'], 'value_proposition' => 'fast', 'pricing_signals' => []],
            'audience' => ['segments' => ['SMBs'], 'pain_points' => [], 'demographics_hint' => ''],
            'seo' => ['primary_keywords' => ['widgets'], 'meta_description_candidate' => ''],
            'claims' => [['claim' => 'Founded in 2020', 'status' => 'VERIFIED_FROM_SOURCE', 'evidence' => 'footer text']],
        ]);

        $llm = $this->createMock(LlmProviderInterface::class);
        $llm->method('complete')->willReturn($validJson);

        $result = (new BusinessProfileExtractor($llm))->extract('some sanitized text', 'https://acme.example');

        $this->assertSame('Acme', $result['brand']['name']);
        $this->assertSame('VERIFIED_FROM_SOURCE', $result['claims'][0]['status']);
    }

    public function test_rejects_non_json_response(): void
    {
        $llm = $this->createMock(LlmProviderInterface::class);
        $llm->method('complete')->willReturn('Sure! Here is the info you wanted: not actually json');

        $this->expectException(ExtractionValidationException::class);

        (new BusinessProfileExtractor($llm))->extract('text', 'https://acme.example');
    }

    public function test_rejects_response_with_invalid_claim_status(): void
    {
        $json = json_encode([
            'brand' => ['name' => '', 'description' => '', 'tone' => [], 'colors' => []],
            'offer' => ['products_or_services' => [], 'value_proposition' => '', 'pricing_signals' => []],
            'audience' => ['segments' => [], 'pain_points' => [], 'demographics_hint' => ''],
            'seo' => ['primary_keywords' => [], 'meta_description_candidate' => ''],
            'claims' => [['claim' => 'x', 'status' => 'DEFINITELY_TRUE_TRUST_ME', 'evidence' => '']],
        ]);

        $llm = $this->createMock(LlmProviderInterface::class);
        $llm->method('complete')->willReturn($json);

        $this->expectException(ExtractionValidationException::class);

        (new BusinessProfileExtractor($llm))->extract('text', 'https://acme.example');
    }

    public function test_rejects_response_missing_required_keys(): void
    {
        $llm = $this->createMock(LlmProviderInterface::class);
        $llm->method('complete')->willReturn(json_encode(['brand' => []]));

        $this->expectException(ExtractionValidationException::class);

        (new BusinessProfileExtractor($llm))->extract('text', 'https://acme.example');
    }
}
