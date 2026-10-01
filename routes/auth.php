<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use Illuminate\Support\Facades\Route;

// Public (Sanctum SPA cookie flow: the frontend hits GET /sanctum/csrf-cookie
// first, then these endpoints — CSRF is enforced automatically for the
// 'web' guard's stateful domains, configured in config/sanctum.php).
Route::post('/register', RegisterController::class)
    ->middleware('throttle:6,1');

Route::post('/login', [SessionController::class, 'store'])
    ->middleware('throttle:10,1');

Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])
    ->middleware('throttle:6,1');

Route::post('/reset-password', [PasswordResetController::class, 'reset'])
    ->middleware('throttle:6,1');

// Authenticated
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [SessionController::class, 'destroy']);

    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');

    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend']);
});
