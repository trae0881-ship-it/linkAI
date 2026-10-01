<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Billing\ConfirmPaymentAction;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\SecurityEvent;
use App\Services\Billing\PaymentSettings;
use App\Services\Billing\RazorpaySignature;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RazorpayWebhookController extends Controller
{
    public function __invoke(Request $request, RazorpaySignature $signature, ConfirmPaymentAction $confirm, PaymentSettings $settings): JsonResponse
    {
        $secret = $settings->webhookSecret();

        if ($secret === '') {
            return response()->json(['message' => 'Webhook not configured.'], 503);
        }

        $raw = $request->getContent(); // signature is over the exact raw bytes

        if (! $signature->verifyWebhook($raw, (string) $request->header('X-Razorpay-Signature'), $secret)) {
            SecurityEvent::record(eventType: 'webhook_signature_failed', severity: 'critical', context: ['provider' => 'razorpay'], ipAddress: $request->ip());

            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $payload = json_decode($raw, true) ?? [];
        $event = (string) ($payload['event'] ?? '');
        $entity = $payload['payload']['payment']['entity'] ?? [];
        $orderId = $entity['order_id'] ?? null;

        if (in_array($event, ['payment.captured', 'order.paid'], true) && $orderId && isset($entity['id'])) {
            try {
                $confirm->handle((string) $orderId, (string) $entity['id'], isset($entity['amount']) ? (int) $entity['amount'] : null);
            } catch (ModelNotFoundException) {
                // Not one of our orders (same Razorpay account may serve other apps) - acknowledge and ignore.
            }
        }

        if ($event === 'payment.failed' && $orderId) {
            Payment::query()->where('razorpay_order_id', $orderId)->where('status', 'created')->update(['status' => 'failed']);
        }

        return response()->json(['status' => 'ok']);
    }
}
