<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GatewayRequest;
use App\Http\Requests\Admin\PlanRequest;
use App\Models\AuditLog;
use App\Models\BillingPlan;
use App\Models\Payment;
use App\Models\SiteSetting;
use App\Services\Billing\PaymentSettings;
use Illuminate\Http\JsonResponse;

final class PaymentAdminController extends Controller
{
    public function plans(): JsonResponse
    {
        return response()->json(['data' => BillingPlan::query()->orderBy('sort_order')->orderBy('amount_paise')->get()]);
    }

    public function storePlan(PlanRequest $request): JsonResponse
    {
        $plan = BillingPlan::query()->create([
            'key' => $request->string('key')->lower()->toString(),
            'name' => $request->string('name')->trim()->toString(),
            'description' => $request->input('description'),
            'amount_paise' => $request->integer('amount_paise'),
            'days' => $request->integer('days'),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $request->integer('sort_order'),
        ]);

        AuditLog::record(null, $request->user()->id, 'billing.plan_created', BillingPlan::class, $plan->id, ['key' => $plan->key, 'amount_paise' => $plan->amount_paise], $request->ip());

        return response()->json(['data' => $plan], 201);
    }

    public function updatePlan(PlanRequest $request, string $id): JsonResponse
    {
        $plan = BillingPlan::query()->findOrFail($id);
        $plan->update([
            'name' => $request->string('name')->trim()->toString(),
            'description' => $request->input('description'),
            'amount_paise' => $request->integer('amount_paise'),
            'days' => $request->integer('days'),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $request->integer('sort_order'),
        ]);

        AuditLog::record(null, $request->user()->id, 'billing.plan_updated', BillingPlan::class, $plan->id, ['key' => $plan->key, 'amount_paise' => $plan->amount_paise, 'is_active' => $plan->is_active], $request->ip());

        return response()->json(['data' => $plan]);
    }

    /** Never returns a secret: only whether it is set, and the last 4 characters of the secret. */
    public function gateway(PaymentSettings $settings): JsonResponse
    {
        return response()->json(['data' => $this->gatewayState($settings)]);
    }

    public function updateGateway(GatewayRequest $request, PaymentSettings $settings): JsonResponse
    {
        $changed = [];

        // Empty/missing = leave unchanged; secrets are write-only.
        foreach (['key_id' => false, 'key_secret' => true, 'webhook_secret' => true] as $field => $secret) {
            if ($request->filled($field)) {
                SiteSetting::write('razorpay_'.$field, $request->string($field)->trim()->toString(), $secret);
                $changed[] = $field;
            }
        }

        AuditLog::record(null, $request->user()->id, 'billing.gateway_updated', null, null, ['fields' => $changed], $request->ip());

        return response()->json(['data' => $this->gatewayState($settings)]);
    }

    public function payments(): JsonResponse
    {
        $rows = Payment::query()
            ->join('workspaces', 'workspaces.id', '=', 'payments.workspace_id')
            ->orderByDesc('payments.created_at')
            ->select('payments.id', 'payments.plan', 'payments.amount_paise', 'payments.currency', 'payments.status', 'payments.paid_at', 'payments.created_at', 'workspaces.name as workspace')
            ->paginate(30);

        return response()->json($rows);
    }

    private function gatewayState(PaymentSettings $settings): array
    {
        $secret = $settings->keySecret();

        return [
            'source' => $settings->source(), // admin | env | none
            'key_id' => $settings->keyId(),
            'key_secret_set' => $secret !== '',
            'key_secret_hint' => $secret !== '' ? '••••'.substr($secret, -4) : null,
            'webhook_secret_set' => $settings->webhookSecret() !== '',
            'webhook_url' => rtrim((string) config('app.url'), '/').'/api/v1/webhooks/razorpay',
        ];
    }
}
