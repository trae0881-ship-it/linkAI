<?php

declare(strict_types=1);

return [
    'currency' => 'INR',

    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),          // public id, safe to send to the browser
        'key_secret' => env('RAZORPAY_KEY_SECRET'),  // server-side only, never exposed
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        'base_url' => 'https://api.razorpay.com/v1',
    ],

    // PLACEHOLDER plans - amounts are in paise (100 paise = Rs 1). Set your real pricing
    // here or via env. Prices are ALWAYS taken from this server-side config, never from the browser.
    'plans' => [
        'starter' => ['name' => 'Starter', 'amount_paise' => (int) env('BILLING_STARTER_PAISE', 49900), 'days' => 30],
        'pro' => ['name' => 'Pro', 'amount_paise' => (int) env('BILLING_PRO_PAISE', 149900), 'days' => 30],
    ],
];
