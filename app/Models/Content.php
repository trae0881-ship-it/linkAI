<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Content extends Model
{
    use BelongsToWorkspace;
    use HasUuids;

    protected $table = 'content';

    protected $fillable = [
        'workspace_id',
        'brand_id',
        'creative_concept_id',
        'requested_quantity',
        'media_format',
        'generation_prompts',
        'platform_copy',
        'status',
        'approved_content_hash',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'generation_prompts' => 'array',
            'platform_copy' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function concept(): BelongsTo
    {
        return $this->belongsTo(CreativeConcept::class, 'creative_concept_id');
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    /**
     * The exact payload a human approved. Publishing recomputes this at
     * send-time and refuses to publish if it no longer matches
     * approved_content_hash — an edit after approval un-approves it.
     */
    public function computeContentHash(): string
    {
        $readyAssetHashes = $this->mediaAssets()
            ->where('status', 'ready')
            ->orderBy('id')
            ->pluck('sha256')
            ->all();

        $canonical = function (mixed $v) use (&$canonical): mixed {
            if (! is_array($v)) {
                return $v;
            }
            $v = array_map($canonical, $v);
            if (! array_is_list($v)) {
                ksort($v); // MySQL's JSON type reorders object keys; hash must not depend on that
            }

            return $v;
        };

        $payload = json_encode([
            'generation_prompts' => $canonical($this->generation_prompts),
            'platform_copy' => $canonical($this->platform_copy),
            'media_asset_hashes' => $readyAssetHashes,
        ]);

        return hash('sha256', (string) $payload);
    }
}
