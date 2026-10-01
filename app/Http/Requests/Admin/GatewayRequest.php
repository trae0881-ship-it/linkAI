<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class GatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key_id' => ['nullable', 'string', 'max:100', 'regex:/^rzp_(test|live)_[A-Za-z0-9]+$/'],
            'key_secret' => ['nullable', 'string', 'max:200'],
            'webhook_secret' => ['nullable', 'string', 'max:200'],
        ];
    }
}
