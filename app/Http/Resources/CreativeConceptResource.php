<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CreativeConceptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_profile_id' => $this->business_profile_id,
            'title' => $this->title,
            'reasoning' => $this->reasoning,
            'pros' => $this->pros,
            'cons' => $this->cons,
            'risks' => $this->risks,
            'platform_suitability' => $this->platform_suitability,
            'trend_references' => $this->trend_references,
            'media_brief' => $this->media_brief,
            'status' => $this->status,
            'selected_at' => $this->selected_at,
            'created_at' => $this->created_at,
        ];
    }
}
