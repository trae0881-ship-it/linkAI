<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Billing\PaymentSettings;
use App\Services\Billing\RazorpayClient;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;

final class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RazorpayClient::class, fn (): RazorpayClient => new RazorpayClient(
            new Client,
            $this->app->make(PaymentSettings::class)->keyId(),
            $this->app->make(PaymentSettings::class)->keySecret(),
            (string) config('billing.razorpay.base_url'),
        ));
    }
}
