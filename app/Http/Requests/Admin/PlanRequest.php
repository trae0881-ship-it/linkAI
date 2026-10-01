<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creating = $this->isMethod('POST');

        return [
            // The key is what payments reference, so it is set once at creation and never edited.
            'key' => $creating ? ['required', 'string', 'max:60', 'alpha_dash', 'unique:billing_plans,key'] : ['prohibited'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'amount_paise' => ['required', 'integer', 'min:100', 'max:10000000'], // Rs 1 .. Rs 1,00,000
            'days' => ['required', 'integer', 'min:1', 'max:3660'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
        ];
    }
}
