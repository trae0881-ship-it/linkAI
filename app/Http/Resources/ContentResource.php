<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ContentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'creative_concept_id' => $this->creative_concept_id,
            'requested_quantity' => $this->requested_quantity,
            'media_format' => $this->media_format,
            'generation_prompts' => $this->generation_prompts,
            'platform_copy' => $this->platform_copy,
            'status' => $this->status,
            'approved_at' => $this->approved_at,
            'media_assets' => MediaAssetResource::collection($this->whenLoaded('mediaAssets')),
            'created_at' => $this->created_at,
        ];
    }
}
