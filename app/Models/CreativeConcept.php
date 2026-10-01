<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CreativeConcept extends Model
{
    use BelongsToWorkspace;
    use HasUuids;

    protected $fillable = [
        'workspace_id',
        'brand_id',
        'business_profile_id',
        'campaign_id',
        'title',
        'reasoning',
        'pros',
        'cons',
        'risks',
        'platform_suitability',
        'trend_references',
        'media_brief',
        'status',
        'selected_by',
        'selected_at',
    ];

    protected function casts(): array
    {
        return [
            'pros' => 'array',
            'cons' => 'array',
            'risks' => 'array',
            'platform_suitability' => 'array',
            'trend_references' => 'array',
            'media_brief' => 'array',
            'selected_at' => 'datetime',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function businessProfile(): BelongsTo
    {
        return $this->belongsTo(BusinessProfile::class);
    }
}
