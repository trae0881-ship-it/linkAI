<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MediaAssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'mime_type' => $this->mime_type,
            'byte_size' => $this->byte_size,
            'url' => $this->status === 'ready' ? $this->temporaryUrl() : null,
            'created_at' => $this->created_at,
        ];
    }
}
