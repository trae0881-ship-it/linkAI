<?php

declare(strict_types=1);

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

final class RequestPublishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // workspace.role:APPROVER route middleware handles this — publishing is an approver-level action
    }

    public function rules(): array
    {
        return [
            'social_account_ids' => ['required', 'array', 'min:1'],
            'social_account_ids.*' => ['string', 'uuid'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
