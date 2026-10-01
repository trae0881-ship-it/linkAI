<?php

declare(strict_types=1);

namespace App\Domain\Caption;

use App\Models\AuditLog;
use App\Models\Content;

final class GenerateCaptionsAction
{
    public function __construct(
        private readonly CaptionGenerator $captionGenerator,
        private readonly TrackingLinkBuilder $trackingLinkBuilder,
    ) {}

    public function handle(Content $content): Content
    {
        $concept = $content->concept()->with('businessProfile.brand')->first();
        $brandData = $concept->businessProfile->brand_data ?? [];
        $offerData = $concept->businessProfile->offer_data ?? [];
        $websiteUrl = $concept->businessProfile->brand->website_url ?? null;

        $copy = $this->captionGenerator->generate($concept->media_brief, $brandData, $offerData);

        if ($websiteUrl) {
            $platforms = array_keys($copy);
            $links = $this->trackingLinkBuilder->buildForPlatforms($websiteUrl, $content->id, $platforms);

            foreach ($links as $platform => $url) {
                $copy[$platform]['tracking_url'] = $url;
            }
        }

        $content->update([
            'platform_copy' => $copy,
            // Any regeneration invalidates a prior approval — the
            // approved hash no longer matches the new content.
            'status' => $content->status === 'approved' ? 'pending_review' : $content->status,
            'approved_content_hash' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        AuditLog::record(
            workspaceId: $content->workspace_id,
            userId: null,
            action: 'content.captions_generated',
            subjectType: Content::class,
            subjectId: $content->id,
        );

        return $content->fresh();
    }
}
