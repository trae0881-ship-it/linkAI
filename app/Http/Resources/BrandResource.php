<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'name' => $this->name,
            'website_url' => $this->website_url,
            'tone' => $this->tone,
            'brand_colors' => $this->brand_colors,
            'created_at' => $this->created_at,
        ];
    }
}
