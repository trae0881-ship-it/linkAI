<?php

declare(strict_types=1);

namespace App\Http\Requests\Source;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Workspace membership + minimum role (EDITOR) is already
        // enforced by the 'workspace.role' route middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'brand_id' => ['required', 'uuid'],
            // Format-only. The real security boundary is
            // UrlSecurityValidator, run inside the queued job — never
            // trust a URL just because it passed a regex.
            'url' => ['required', 'string', 'max:2048', 'url'],
        ];
    }
}
