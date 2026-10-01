<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\PaymentAdminController;
use App\Http\Controllers\Api\V1\Admin\SiteAdminController;
use Illuminate\Support\Facades\Route;

// Platform-admin only (users.is_platform_admin). Distinct from per-workspace roles.
Route::middleware(['auth:sanctum', 'platform.admin', 'throttle:60,1'])->prefix('/admin')->group(function (): void {
    Route::get('/pages', [SiteAdminController::class, 'pages']);
    Route::put('/pages/{slug}', [SiteAdminController::class, 'updatePage']);
    Route::get('/settings', [SiteAdminController::class, 'settings']);
    Route::put('/settings', [SiteAdminController::class, 'updateSettings']);
    Route::get('/contact-messages', [SiteAdminController::class, 'messages']);
    Route::patch('/contact-messages/{id}', [SiteAdminController::class, 'markMessage']);

    Route::get('/plans', [PaymentAdminController::class, 'plans']);
    Route::post('/plans', [PaymentAdminController::class, 'storePlan']);
    Route::put('/plans/{id}', [PaymentAdminController::class, 'updatePlan']);
    Route::get('/payment-gateway', [PaymentAdminController::class, 'gateway']);
    Route::put('/payment-gateway', [PaymentAdminController::class, 'updateGateway']);
    Route::get('/payments', [PaymentAdminController::class, 'payments']);
});
