<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class BusinessProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource === null) {
            return [];
        }

        return [
            'id' => $this->id,
            'brand' => $this->brand_data,
            'offer' => $this->offer_data,
            'audience' => $this->audience_data,
            'seo' => $this->seo_data,
            'claims' => $this->claims,
            'analyzed_at' => $this->analyzed_at,
        ];
    }
}
