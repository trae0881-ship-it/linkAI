<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class SecurityEvent extends Model
{
    use HasUuids;

    public $timestamps = true;
    const UPDATED_AT = null;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'event_type',
        'severity',
        'context',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    public static function record(
        string $eventType,
        string $severity = 'info',
        array $context = [],
        ?string $workspaceId = null,
        ?string $userId = null,
        ?string $ipAddress = null,
    ): self {
        return self::create([
            'workspace_id' => $workspaceId,
            'user_id' => $userId,
            'event_type' => $eventType,
            'severity' => $severity,
            'context' => $context,
            'ip_address' => $ipAddress,
        ]);
    }
}
