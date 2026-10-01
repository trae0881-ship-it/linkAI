<?php

declare(strict_types=1);

namespace App\Http\Requests\Workspace;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

final class StoreWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // any authenticated user may create a workspace (becomes its OWNER)
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
        ];
    }

    public function safeData(): array
    {
        $name = $this->string('name')->trim()->toString();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)), // uniqueness without a race-prone lookup
            'owner_id' => $this->user()->id,
        ];
    }
}
