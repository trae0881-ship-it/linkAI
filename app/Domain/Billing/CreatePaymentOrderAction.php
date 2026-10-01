<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Models\BillingPlan;
use App\Models\Payment;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\RazorpayClient;
use App\Services\Billing\RazorpayException;
use Illuminate\Validation\ValidationException;

final class CreatePaymentOrderAction
{
    public function __construct(private readonly RazorpayClient $razorpay) {}

    /** @return array{payment: Payment, order: array<string, mixed>} */
    public function handle(Workspace $workspace, User $user, string $planKey): array
    {
        $plan = BillingPlan::query()->active()->where('key', $planKey)->first();

        if (! $plan) {
            throw ValidationException::withMessages(['plan' => ['Unknown plan.']]);
        }

        // Amount comes from server config only - the browser never supplies a price.
        $payment = Payment::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'plan' => $planKey,
            'amount_paise' => $plan->amount_paise,
            'currency' => (string) config('billing.currency', 'INR'),
            'status' => 'created',
        ]);

        try {
            $order = $this->razorpay->createOrder(
                $payment->amount_paise,
                $payment->currency,
                $payment->id, // receipt (max 40 chars; a UUID is 36)
                ['workspace_id' => $workspace->id, 'plan' => $planKey],
            );
        } catch (RazorpayException $e) {
            $payment->update(['status' => 'failed']);

            throw $e;
        }

        $payment->update(['razorpay_order_id' => (string) $order['id']]);

        return ['payment' => $payment->fresh(), 'order' => $order];
    }
}
