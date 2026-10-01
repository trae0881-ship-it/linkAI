<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Publication extends Model
{
    use BelongsToWorkspace;
    use HasUuids;

    protected $fillable = [
        'workspace_id',
        'content_id',
        'social_account_id',
        'media_asset_id',
        'idempotency_key',
        'platform',
        'status',
        'scheduled_at',
        'published_at',
        'external_post_id',
        'external_post_url',
        'error_message',
        'attempt_count',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public static function buildIdempotencyKey(string $contentId, string $socialAccountId, string $approvedContentHash): string
    {
        return hash('sha256', "{$contentId}:{$socialAccountId}:{$approvedContentHash}");
    }
}
