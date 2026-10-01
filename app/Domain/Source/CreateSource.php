<?php

declare(strict_types=1);

namespace App\Domain\Source;

use App\Jobs\AnalyzeSourceJob;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Source;
use App\Models\User;

final class CreateSource
{
    public function handle(Brand $brand, string $submittedUrl, User $submitter): Source
    {
        $source = Source::query()->create([
            'workspace_id' => $brand->workspace_id,
            'brand_id' => $brand->id,
            'submitted_by' => $submitter->id,
            'submitted_url' => $submittedUrl,
            'status' => 'pending',
        ]);

        AuditLog::record(
            workspaceId: $brand->workspace_id,
            userId: $submitter->id,
            action: 'source.submitted',
            subjectType: Source::class,
            subjectId: $source->id,
            meta: ['url' => $submittedUrl],
        );

        AnalyzeSourceJob::dispatch($source->id);

        return $source;
    }
}
