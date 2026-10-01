<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Brand extends Model
{
    use BelongsToWorkspace;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'name',
        'website_url',
        'tone',
        'brand_colors',
    ];

    protected function casts(): array
    {
        return [
            'tone' => 'array',
            'brand_colors' => 'array',
        ];
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(Source::class);
    }

    public function concepts(): HasMany
    {
        return $this->hasMany(CreativeConcept::class);
    }

    public function content(): HasMany
    {
        return $this->hasMany(Content::class);
    }
}
