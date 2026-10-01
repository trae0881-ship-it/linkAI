<?php

declare(strict_types=1);

namespace App\Domain\Approval;

use App\Models\AuditLog;
use App\Models\Content;
use App\Models\User;

final class UpdateContentAction
{
    public function handle(Content $content, array $changes, User $editor): Content
    {
        $wasApproved = $content->status === 'approved';

        $content->fill(array_intersect_key($changes, array_flip(['generation_prompts', 'platform_copy'])));

        // Editing after approval un-approves it — the whole point of
        // content-hash pinning is that a human never re-approves silently.
        if ($wasApproved) {
            $content->approved_content_hash = null;
            $content->approved_by = null;
            $content->approved_at = null;
            $content->status = 'pending_review';
        }

        $content->save();

        AuditLog::record(
            workspaceId: $content->workspace_id,
            userId: $editor->id,
            action: 'content.edited',
            subjectType: Content::class,
            subjectId: $content->id,
            meta: ['fields' => array_keys($changes), 'un_approved' => $wasApproved],
        );

        return $content->fresh();
    }
}
