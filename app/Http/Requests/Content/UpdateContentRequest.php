<?php

declare(strict_types=1);

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // workspace.role:EDITOR route middleware handles this
    }

    public function rules(): array
    {
        return [
            'generation_prompts' => ['sometimes', 'array', 'min:1'],
            'generation_prompts.*' => ['string', 'max:2000'],
            'platform_copy' => ['sometimes', 'array'],
        ];
    }
}
