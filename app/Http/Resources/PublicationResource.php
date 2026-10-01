<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PublicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content_id' => $this->content_id,
            'social_account_id' => $this->social_account_id,
            'platform' => $this->platform,
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at,
            'published_at' => $this->published_at,
            'external_post_id' => $this->external_post_id,
            'external_post_url' => $this->external_post_url,
            'error_message' => $this->error_message,
        ];
    }
}
