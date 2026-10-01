<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

final class MediaAsset extends Model
{
    use BelongsToWorkspace;
    use HasUuids;

    protected $fillable = [
        'workspace_id',
        'content_id',
        'type',
        'storage_disk',
        'storage_path',
        'mime_type',
        'byte_size',
        'sha256',
        'status',
        'provider',
        'provider_job_id',
    ];

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    /** Signed, time-limited URL — storage_path itself is never public. */
    public function temporaryUrl(): string
    {
        $ttl = (int) config('media.signed_url_ttl_minutes', 15);

        return Storage::disk($this->storage_disk)
            ->temporaryUrl($this->storage_path, now()->addMinutes($ttl));
    }
}
