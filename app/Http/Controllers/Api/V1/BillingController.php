<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Billing\ConfirmPaymentAction;
use App\Domain\Billing\CreatePaymentOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CreateOrderRequest;
use App\Http\Requests\Billing\VerifyPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\BillingPlan;
use App\Models\SecurityEvent;
use App\Models\Workspace;
use App\Services\Billing\PaymentSettings;
use App\Services\Billing\RazorpayException;
use App\Services\Billing\RazorpaySignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final class BillingController extends Controller
{
    public function __construct(private readonly PaymentSettings $settings) {}

    public function plans(Workspace $workspace): JsonResponse
    {
        $plans = [];
        foreach (BillingPlan::query()->active()->get() as $plan) {
            $plans[] = ['key' => $plan->key, 'name' => $plan->name, 'description' => $plan->description, 'amount_paise' => $plan->amount_paise, 'days' => $plan->days];
        }

        return response()->json([
            'currency' => config('billing.currency'),
            'configured' => $this->configured(),
            'plans' => $plans,
            'current_plan' => $workspace->plan,
            'plan_expires_at' => $workspace->plan_expires_at,
        ]);
    }

    public function payments(Workspace $workspace): JsonResponse
    {
        return PaymentResource::collection($workspace->payments()->orderByDesc('created_at')->limit(50)->get())->response();
    }

    public function createOrder(CreateOrderRequest $request, Workspace $workspace, CreatePaymentOrderAction $action): JsonResponse
    {
        if (! $this->configured()) {
            return response()->json(['message' => 'Payments are not configured yet.'], 503);
        }

        try {
            $result = $action->handle($workspace, $request->user(), $request->string('plan')->toString());
        } catch (RazorpayException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json([
            'order_id' => $result['order']['id'],
            'amount' => $result['payment']->amount_paise,
            'currency' => $result['payment']->currency,
            'key_id' => $this->settings->keyId(), // public id only; the secret never leaves the server
        ], 201);
    }

    public function verify(VerifyPaymentRequest $request, Workspace $workspace, RazorpaySignature $signature, ConfirmPaymentAction $confirm): JsonResponse
    {
        // Tenant isolation: the order must belong to THIS workspace.
        $payment = $workspace->payments()->where('razorpay_order_id', $request->input('razorpay_order_id'))->firstOrFail();

        $valid = $signature->verifyPayment(
            $request->input('razorpay_order_id'),
            $request->input('razorpay_payment_id'),
            $request->input('razorpay_signature'),
            $this->settings->keySecret(),
        );

        if (! $valid) {
            SecurityEvent::record(
                eventType: 'payment_signature_invalid',
                severity: 'critical',
                context: ['payment_id' => $payment->id],
                workspaceId: $workspace->id,
                userId: $request->user()->id,
                ipAddress: $request->ip(),
            );

            throw ValidationException::withMessages(['razorpay_signature' => ['Payment could not be verified.']]);
        }

        $payment = $confirm->handle($payment->razorpay_order_id, $request->input('razorpay_payment_id'));

        return (new PaymentResource($payment))->response();
    }

    private function configured(): bool
    {
        return $this->settings->configured();
    }
}
