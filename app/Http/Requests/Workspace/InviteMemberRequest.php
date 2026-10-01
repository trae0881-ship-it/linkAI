<?php

declare(strict_types=1);

namespace App\Http\Requests\Workspace;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InviteMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageMembers', $this->route('workspace'));
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'role' => ['required', Rule::in(['OWNER', 'ADMIN', 'EDITOR', 'APPROVER', 'VIEWER'])],
        ];
    }

    /**
     * Policy-level check (not just "is a valid role string") — an ADMIN
     * inviting someone as OWNER must still be rejected even though
     * 'OWNER' passes the Rule::in above.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->user()->can('assignRole', [$this->route('workspace'), $this->input('role')])) {
                $validator->errors()->add('role', 'You cannot grant this role.');
            }
        });
    }
}
