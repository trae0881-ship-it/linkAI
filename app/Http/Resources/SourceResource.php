<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SourceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'brand_id' => $this->brand_id,
            'submitted_url' => $this->submitted_url,
            'normalized_url' => $this->normalized_url,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'fetched_at' => $this->fetched_at,
            'business_profile' => new BusinessProfileResource($this->whenLoaded('businessProfile')),
            'created_at' => $this->created_at,
        ];
    }
}
