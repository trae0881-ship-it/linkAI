<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SocialAccount extends Model
{
    use BelongsToWorkspace;
    use HasUuids;

    protected $fillable = [
        'workspace_id',
        'connected_by',
        'platform',
        'external_account_id',
        'external_account_name',
        'external_account_type',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'granted_scopes',
        'is_active',
        'last_connected_at',
    ];

    // Tokens are never present in array/JSON output — API responses use
    // SocialAccountResource, which explicitly excludes these fields, but
    // $hidden is a second, model-level backstop against an accidental
    // ->toArray()/->toJson() call elsewhere in the codebase.
    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'granted_scopes' => 'array',
            'token_expires_at' => 'datetime',
            'last_connected_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }
}
