<?php

declare(strict_types=1);

namespace App\Services\Billing;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/** Thin client for Razorpay's official Orders API (https://razorpay.com/docs/api/orders/). */
final class RazorpayClient
{
    public function __construct(
        private readonly Client $http,
        private readonly string $keyId,
        private readonly string $keySecret,
        private readonly string $baseUrl,
    ) {}

    /** @return array<string, mixed> the created order (contains 'id') */
    public function createOrder(int $amountPaise, string $currency, string $receipt, array $notes = []): array
    {
        try {
            $response = $this->http->post($this->baseUrl.'/orders', [
                'auth' => [$this->keyId, $this->keySecret],
                'json' => ['amount' => $amountPaise, 'currency' => $currency, 'receipt' => $receipt, 'notes' => $notes],
                'timeout' => 20,
            ]);
        } catch (GuzzleException $e) {
            // Log the failure but never the credentials; do not leak provider details to the client.
            Log::error('Razorpay order creation failed.', ['error' => $e->getMessage()]);

            throw new RazorpayException('Could not create the payment order. Please try again.', 0, $e);
        }

        $data = json_decode((string) $response->getBody(), true);

        if (! is_array($data) || ! isset($data['id'])) {
            throw new RazorpayException('Unexpected response from the payment provider.');
        }

        return $data;
    }
}
