<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\RazorpayWebhookController;
use Illuminate\Support\Facades\Route;

// Public (no session/auth): authenticity comes from the HMAC signature check.
Route::post('/webhooks/razorpay', RazorpayWebhookController::class)->middleware('throttle:120,1');
