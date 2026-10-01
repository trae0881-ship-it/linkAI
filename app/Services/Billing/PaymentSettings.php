<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\SiteSetting;

/**
 * Razorpay credentials: values saved by the platform admin (encrypted in the database)
 * take precedence; otherwise the RAZORPAY_* environment variables are used.
 */
final class PaymentSettings
{
    public function keyId(): string
    {
        return (string) (SiteSetting::read('razorpay_key_id') ?? config('billing.razorpay.key_id'));
    }

    public function keySecret(): string
    {
        return (string) (SiteSetting::read('razorpay_key_secret') ?? config('billing.razorpay.key_secret'));
    }

    public function webhookSecret(): string
    {
        return (string) (SiteSetting::read('razorpay_webhook_secret') ?? config('billing.razorpay.webhook_secret'));
    }

    public function configured(): bool
    {
        return $this->keyId() !== '' && $this->keySecret() !== '';
    }

    public function source(): string
    {
        if (SiteSetting::read('razorpay_key_id') !== null) {
            return 'admin';
        }

        return filled(config('billing.razorpay.key_id')) ? 'env' : 'none';
    }
}
