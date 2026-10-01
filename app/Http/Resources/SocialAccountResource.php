<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SocialAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform,
            'external_account_name' => $this->external_account_name,
            'external_account_type' => $this->external_account_type,
            'is_active' => $this->is_active,
            'token_expired' => $this->isTokenExpired(),
            'last_connected_at' => $this->last_connected_at,
            // access_token / refresh_token intentionally never included
        ];
    }
}
