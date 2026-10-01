<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Publication;
use App\Models\SecurityEvent;
use App\Services\Social\SocialAdapterFactory;
use App\Services\Social\Adapters\SocialPublishException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class PublishContentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 60;

    public function __construct(private readonly string $publicationId) {}

    public function handle(SocialAdapterFactory $factory): void
    {
        $publication = Publication::query()
            ->with(['content.mediaAssets', 'socialAccount'])
            ->find($this->publicationId);

        if (! $publication || $publication->status === 'published') {
            return; // already done — idempotent no-op, including on queue redelivery
        }

        $content = $publication->content;

        // THE critical re-check: the content a human approved must be
        // byte-for-byte what's about to be sent. Any edit after approval
        // already nulled approved_content_hash (see UpdateContentAction),
        // so this also catches "approved, edited, approved again for a
        // DIFFERENT hash, but this job was queued against the old one."
        $currentHash = $content->computeContentHash();

        if ($content->approved_content_hash === null || $currentHash !== $content->approved_content_hash) {
            $publication->update([
                'status' => 'failed',
                'error_message' => 'Approved content hash no longer matches current content. Publish blocked.',
            ]);

            SecurityEvent::record(
                eventType: 'publish_blocked_hash_mismatch',
                severity: 'critical',
                context: ['content_id' => $content->id, 'publication_id' => $publication->id],
                workspaceId: $publication->workspace_id,
            );

            return;
        }

        $publication->update(['status' => 'publishing']);
        $publication->increment('attempt_count');

        try {
            $account = $publication->socialAccount;
            $family = (new SocialAdapterFactory)->familyForAccountPlatform($account->platform);
            $adapter = $factory->make($family);

            $caption = $content->platform_copy[$this->platformKey($account->platform)]['caption'] ?? '';
            $mediaUrls = $content->mediaAssets
                ->where('status', 'ready')
                ->map(fn ($asset) => $asset->temporaryUrl())
                ->values()
                ->all();

            $result = $adapter->publish($account, $caption, $mediaUrls);

            $publication->update([
                'status' => 'published',
                'published_at' => now(),
                'external_post_id' => $result->externalPostId,
                'external_post_url' => $result->externalPostUrl,
            ]);

            AuditLog::record(
                workspaceId: $publication->workspace_id,
                userId: null,
                action: 'content.published',
                subjectType: Publication::class,
                subjectId: $publication->id,
                meta: ['platform' => $account->platform, 'external_post_id' => $result->externalPostId],
            );
        } catch (SocialPublishException|\Throwable $e) {
            $publication->update(['status' => 'failed', 'error_message' => $e->getMessage()]);

            AuditLog::record(
                workspaceId: $publication->workspace_id,
                userId: null,
                action: 'content.publish_failed',
                subjectType: Publication::class,
                subjectId: $publication->id,
                meta: ['error' => $e->getMessage()],
            );
        }
    }

    private function platformKey(string $accountPlatform): string
    {
        return match (true) {
            str_contains($accountPlatform, 'instagram') => 'instagram',
            str_contains($accountPlatform, 'facebook') => 'instagram', // Meta feed post copy reuses the Instagram-style caption; adjust if Page voice should differ
            str_contains($accountPlatform, 'linkedin') => 'linkedin',
            str_contains($accountPlatform, 'pinterest') => 'pinterest',
            str_contains($accountPlatform, 'x') => 'x',
            default => 'instagram',
        };
    }
}
