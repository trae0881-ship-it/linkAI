<?php

declare(strict_types=1);

namespace App\Http\Requests\Workspace;

use Illuminate\Foundation\Http\FormRequest;

final class AcceptInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // token possession is the proof; enforced in the action
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
        ];
    }
}
