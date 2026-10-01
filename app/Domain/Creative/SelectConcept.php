<?php

declare(strict_types=1);

namespace App\Domain\Creative;

use App\Models\AuditLog;
use App\Models\CreativeConcept;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SelectConcept
{
    public function handle(CreativeConcept $concept, User $selector): CreativeConcept
    {
        DB::transaction(function () use ($concept, $selector): void {
            // Only one selected concept per business profile at a time —
            // reject any concurrently-selected sibling first.
            CreativeConcept::query()
                ->where('business_profile_id', $concept->business_profile_id)
                ->where('status', 'selected')
                ->where('id', '!=', $concept->id)
                ->update(['status' => 'rejected']);

            $concept->forceFill([
                'status' => 'selected',
                'selected_by' => $selector->id,
                'selected_at' => now(),
            ])->save();

            AuditLog::record(
                workspaceId: $concept->workspace_id,
                userId: $selector->id,
                action: 'concept.selected',
                subjectType: CreativeConcept::class,
                subjectId: $concept->id,
            );
        });

        return $concept->fresh();
    }
}
