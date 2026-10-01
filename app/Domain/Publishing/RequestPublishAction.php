<?php

declare(strict_types=1);

namespace App\Domain\Publishing;

use App\Jobs\PublishContentJob;
use App\Models\AuditLog;
use App\Models\Content;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RequestPublishAction
{
    /** @param string[] $socialAccountIds */
    public function handle(Content $content, array $socialAccountIds, ?\DateTimeInterface $scheduledAt, User $requester): array
    {
        if ($content->status !== 'approved' || ! $content->approved_content_hash) {
            throw ValidationException::withMessages([
                'status' => ['Content must be approved before it can be published.'],
            ]);
        }

        return DB::transaction(function () use ($content, $socialAccountIds, $scheduledAt, $requester) {
            $publications = [];

            foreach ($socialAccountIds as $accountId) {
                $account = SocialAccount::query()
                    ->where('workspace_id', $content->workspace_id)
                    ->where('is_active', true)
                    ->findOrFail($accountId);

                $idempotencyKey = Publication::buildIdempotencyKey($content->id, $account->id, $content->approved_content_hash);

                // updateOrCreate on the unique idempotency_key: a repeat
                // request for the same content+account+approved-hash is a
                // no-op that returns the existing row rather than
                // creating (or publishing) a duplicate.
                $publication = Publication::query()->firstOrCreate(
                    ['idempotency_key' => $idempotencyKey],
                    [
                        'workspace_id' => $content->workspace_id,
                        'content_id' => $content->id,
                        'social_account_id' => $account->id,
                        'platform' => $account->platform,
                        'status' => 'scheduled',
                        'scheduled_at' => $scheduledAt,
                    ],
                );

                if ($publication->wasRecentlyCreated) {
                    if ($scheduledAt === null || $scheduledAt->getTimestamp() <= now()->getTimestamp()) {
                        PublishContentJob::dispatch($publication->id);
                    } else {
                        PublishContentJob::dispatch($publication->id)->delay($scheduledAt);
                    }
                }

                $publications[] = $publication;
            }

            AuditLog::record(
                workspaceId: $content->workspace_id,
                userId: $requester->id,
                action: 'content.publish_requested',
                subjectType: Content::class,
                subjectId: $content->id,
                meta: ['account_count' => count($socialAccountIds), 'scheduled' => $scheduledAt !== null],
            );

            return $publications;
        });
    }
}
