<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class WorkspaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'role' => $this->when(
                $request->user() !== null,
                fn () => $this->roleFor($request->user()),
            ),
            'plan' => $this->plan,
            'plan_expires_at' => $this->plan_expires_at,
            'created_at' => $this->created_at,
        ];
    }
}
