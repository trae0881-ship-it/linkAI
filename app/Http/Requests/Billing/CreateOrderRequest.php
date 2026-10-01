<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Models\BillingPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // workspace.role:ADMIN route middleware
    }

    public function rules(): array
    {
        return ['plan' => ['required', 'string', Rule::in(BillingPlan::query()->active()->pluck('key')->all())]];
    }
}
