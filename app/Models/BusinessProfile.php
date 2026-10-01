<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BusinessProfile extends Model
{
    use BelongsToWorkspace;
    use HasUuids;

    protected $fillable = [
        'workspace_id',
        'source_id',
        'brand_id',
        'brand_data',
        'offer_data',
        'audience_data',
        'seo_data',
        'claims',
        'confidence',
        'analyzed_at',
    ];

    protected function casts(): array
    {
        return [
            'brand_data' => 'array',
            'offer_data' => 'array',
            'audience_data' => 'array',
            'seo_data' => 'array',
            'claims' => 'array',
            'confidence' => 'decimal:3',
            'analyzed_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
