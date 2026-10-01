<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Creative\ConceptGenerationException;
use App\Domain\Creative\ConceptGenerator;
use App\Models\AuditLog;
use App\Models\BusinessProfile;
use App\Models\CreativeConcept;
use App\Services\Trends\Contracts\TrendsProviderInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class GenerateConceptsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(private readonly string $businessProfileId) {}

    public function handle(TrendsProviderInterface $trendsProvider, ConceptGenerator $generator): void
    {
        $profile = BusinessProfile::query()->with('brand')->find($this->businessProfileId);

        if (! $profile) {
            return;
        }

        $keywords = $profile->seo_data['primary_keywords'] ?? [];
        $trends = $trendsProvider->currentTrends($keywords);

        try {
            $concepts = $generator->generate(
                businessProfile: [
                    'brand' => $profile->brand_data,
                    'offer' => $profile->offer_data,
                    'audience' => $profile->audience_data,
                    'seo' => $profile->seo_data,
                ],
                trends: $trends,
                brandName: $profile->brand->name,
            );
        } catch (ConceptGenerationException $e) {
            Log::error('Concept generation failed schema validation.', [
                'business_profile_id' => $profile->id,
                'error' => $e->getMessage(),
            ]);

            AuditLog::record(
                workspaceId: $profile->workspace_id,
                userId: null,
                action: 'concepts.generation_failed',
                subjectType: BusinessProfile::class,
                subjectId: $profile->id,
                meta: ['error' => $e->getMessage()],
            );

            return;
        }

        foreach ($concepts as $concept) {
            CreativeConcept::query()->create([
                'workspace_id' => $profile->workspace_id,
                'brand_id' => $profile->brand_id,
                'business_profile_id' => $profile->id,
                'title' => $concept['title'],
                'reasoning' => $concept['reasoning'],
                'pros' => $concept['pros'],
                'cons' => $concept['cons'],
                'risks' => $concept['risks'],
                'platform_suitability' => $concept['platform_suitability'],
                'trend_references' => $concept['trend_references'] ?? [],
                'media_brief' => $concept['media_brief'],
                'status' => 'proposed',
            ]);
        }

        AuditLog::record(
            workspaceId: $profile->workspace_id,
            userId: null,
            action: 'concepts.generated',
            subjectType: BusinessProfile::class,
            subjectId: $profile->id,
            meta: ['count' => count($concepts)],
        );
    }
}
