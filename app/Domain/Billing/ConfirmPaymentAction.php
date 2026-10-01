<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Models\AuditLog;
use App\Models\BillingPlan;
use App\Models\Payment;
use App\Models\SecurityEvent;
use Illuminate\Support\Facades\DB;

/**
 * Marks a payment paid and activates/extends the workspace plan. Shared by the
 * browser verify endpoint and the webhook, and idempotent: whichever arrives
 * second (or a Razorpay retry) is a no-op.
 */
final class ConfirmPaymentAction
{
    /**
     * @param int|null $amountPaid when known (webhook), must equal the amount we ordered
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException when the order is not ours
     */
    public function handle(string $orderId, string $paymentId, ?int $amountPaid = null): Payment
    {
        return DB::transaction(function () use ($orderId, $paymentId, $amountPaid): Payment {
            $payment = Payment::query()->where('razorpay_order_id', $orderId)->lockForUpdate()->firstOrFail();

            if ($payment->status === 'paid') {
                return $payment;
            }

            if ($amountPaid !== null && $amountPaid !== $payment->amount_paise) {
                SecurityEvent::record(
                    eventType: 'payment_amount_mismatch',
                    severity: 'critical',
                    context: ['payment_id' => $payment->id, 'expected' => $payment->amount_paise, 'received' => $amountPaid],
                    workspaceId: $payment->workspace_id,
                );
                $payment->update(['status' => 'failed']);

                return $payment;
            }

            $payment->update([
                'status' => 'paid',
                'razorpay_payment_id' => $paymentId,
                'paid_at' => now(),
            ]);

            $workspace = $payment->workspace()->lockForUpdate()->first();
            $days = (int) (BillingPlan::query()->where('key', $payment->plan)->value('days') ?? 30);
            $base = ($workspace->plan_expires_at && $workspace->plan_expires_at->isFuture()) ? $workspace->plan_expires_at : now();

            $workspace->forceFill(['plan' => $payment->plan, 'plan_expires_at' => $base->copy()->addDays($days)])->save();

            AuditLog::record(
                workspaceId: $payment->workspace_id,
                userId: $payment->user_id,
                action: 'billing.payment_confirmed',
                subjectType: Payment::class,
                subjectId: $payment->id,
                meta: ['plan' => $payment->plan, 'amount_paise' => $payment->amount_paise],
            );

            return $payment;
        });
    }
}
