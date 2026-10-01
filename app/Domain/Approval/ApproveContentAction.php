<?php

declare(strict_types=1);

namespace App\Domain\Approval;

use App\Models\AuditLog;
use App\Models\Content;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class ApproveContentAction
{
    public function handle(Content $content, User $approver): Content
    {
        if ($content->status !== 'pending_review' && $content->status !== 'generated') {
            throw ValidationException::withMessages([
                'status' => ["Content must be generated and reviewed before approval (current status: {$content->status})."],
            ]);
        }

        if (! $content->mediaAssets()->where('status', 'ready')->exists()) {
            throw ValidationException::withMessages([
                'status' => ['At least one ready media asset is required before approval.'],
            ]);
        }

        $hash = $content->computeContentHash();

        $content->update([
            'status' => 'approved',
            'approved_content_hash' => $hash,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        AuditLog::record(
            workspaceId: $content->workspace_id,
            userId: $approver->id,
            action: 'content.approved',
            subjectType: Content::class,
            subjectId: $content->id,
            meta: ['content_hash' => $hash],
        );

        return $content->fresh();
    }
}
