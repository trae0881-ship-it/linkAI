<?php

declare(strict_types=1);

namespace App\Actions\Workspace;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

final class CreateWorkspace
{
    public function handle(array $safeData, User $creator): Workspace
    {
        return DB::transaction(function () use ($safeData, $creator): Workspace {
            $workspace = Workspace::query()->create($safeData);

            $workspace->members()->attach($creator->id, ['id' => (string) \Illuminate\Support\Str::uuid(), 'role' => 'OWNER']);

            AuditLog::record(
                workspaceId: $workspace->id,
                userId: $creator->id,
                action: 'workspace.created',
                subjectType: Workspace::class,
                subjectId: $workspace->id,
            );

            return $workspace;
        });
    }
}
