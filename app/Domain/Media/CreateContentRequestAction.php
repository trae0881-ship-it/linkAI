<?php

declare(strict_types=1);

namespace App\Domain\Media;

use App\Jobs\GenerateMediaJob;
use App\Models\AuditLog;
use App\Models\Content;
use App\Models\CreativeConcept;
use App\Models\User;

final class CreateContentRequestAction
{
    public function __construct(
        private readonly PromptBuilder $promptBuilder,
    ) {}

    public function handle(CreativeConcept $concept, int $quantity, string $mediaFormat, User $requester): Content
    {
        $brandDescription = $concept->businessProfile->brand_data['description'] ?? '';

        $prompts = $this->promptBuilder->build($concept->media_brief, $brandDescription, $quantity);

        $content = Content::query()->create([
            'workspace_id' => $concept->workspace_id,
            'brand_id' => $concept->brand_id,
            'creative_concept_id' => $concept->id,
            'requested_quantity' => $quantity,
            'media_format' => $mediaFormat,
            'generation_prompts' => $prompts,
            'status' => 'generating',
        ]);

        foreach ($prompts as $prompt) {
            $content->mediaAssets()->create([
                'workspace_id' => $concept->workspace_id,
                'type' => $mediaFormat === 'video' ? 'video' : 'image',
                'storage_disk' => (string) config('filesystems.default'),
                'storage_path' => '', // filled in once the provider returns bytes
                'mime_type' => '',
                'status' => 'pending',
            ]);
        }

        GenerateMediaJob::dispatch($content->id);

        AuditLog::record(
            workspaceId: $concept->workspace_id,
            userId: $requester->id,
            action: 'content.requested',
            subjectType: Content::class,
            subjectId: $content->id,
            meta: ['quantity' => $quantity, 'media_format' => $mediaFormat],
        );

        return $content;
    }
}
