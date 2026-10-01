<?php

declare(strict_types=1);

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RequestContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // workspace.role:EDITOR route middleware handles this
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'media_format' => ['required', Rule::in(['image', 'video', 'carousel'])],
        ];
    }
}
