<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class AuditLog extends Model
{
    use HasUuids;

    public $timestamps = true;
    const UPDATED_AT = null; // audit rows are append-only

    protected $fillable = [
        'workspace_id',
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'meta',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    /**
     * Record an audit entry. $meta must never contain tokens, passwords,
     * or other secrets — callers are responsible for passing only
     * non-sensitive, already-redacted context.
     */
    public static function record(
        ?string $workspaceId,
        ?string $userId,
        string $action,
        ?string $subjectType = null,
        ?string $subjectId = null,
        array $meta = [],
        ?string $ipAddress = null,
    ): self {
        return self::create([
            'workspace_id' => $workspaceId,
            'user_id' => $userId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'meta' => $meta,
            'ip_address' => $ipAddress,
        ]);
    }
}
