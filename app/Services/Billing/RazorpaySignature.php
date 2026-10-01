<?php

declare(strict_types=1);

namespace App\Services\Billing;

final class RazorpaySignature
{
    /** Checkout callback: HMAC-SHA256 of "order_id|payment_id" with the API key secret. */
    public function verifyPayment(string $orderId, string $paymentId, string $signature, string $keySecret): bool
    {
        return $keySecret !== '' && hash_equals(hash_hmac('sha256', $orderId.'|'.$paymentId, $keySecret), $signature);
    }

    /** Webhook: HMAC-SHA256 of the RAW request body with the webhook secret (X-Razorpay-Signature). */
    public function verifyWebhook(string $rawBody, string $signature, string $webhookSecret): bool
    {
        return $webhookSecret !== '' && hash_equals(hash_hmac('sha256', $rawBody, $webhookSecret), $signature);
    }
}
