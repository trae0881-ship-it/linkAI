<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\ConceptController;
use App\Http\Controllers\Api\V1\ContentController;
use App\Http\Controllers\Api\V1\ContentEditController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\PublishController;
use App\Http\Controllers\Api\V1\SecurityEventController;
use App\Http\Controllers\Api\V1\SocialAccountController;
use App\Http\Controllers\Api\V1\SocialConnectionController;
use App\Http\Controllers\Api\V1\SourceController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use App\Http\Controllers\Api\V1\WorkspaceMemberController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/workspaces', [WorkspaceController::class, 'index']);
    Route::post('/workspaces', [WorkspaceController::class, 'store']);
    Route::post('/invitations/accept', [InvitationController::class, 'accept']);

    // {workspace} is route-model-bound by UUID (Laravel resolves it to a
    // Workspace or 404s before any handler runs). 'workspace.role:VIEWER'
    // guarantees the caller is at least a member before show/index run;
    // write endpoints require a higher minimum role.
    Route::middleware('workspace.role:VIEWER')->prefix('/workspaces/{workspace}')->group(function (): void {
        Route::get('/', [WorkspaceController::class, 'show']);
        Route::get('/members', [WorkspaceMemberController::class, 'index']);
        Route::get('/brands', [BrandController::class, 'index']);
        Route::get('/brands/{brandId}', [BrandController::class, 'show']);
        Route::get('/brands/{brandId}/sources', [SourceController::class, 'index']);
        Route::get('/brands/{brandId}/sources/{sourceId}', [SourceController::class, 'show']);
        Route::get('/brands/{brandId}/content', [ContentController::class, 'index']);
        Route::get('/brands/{brandId}/business-profiles/{businessProfileId}/concepts', [ConceptController::class, 'index']);
        Route::get('/brands/{brandId}/content/{contentId}', [ContentController::class, 'show']);
        Route::get('/social-accounts', [SocialAccountController::class, 'index']);
        Route::get('/brands/{brandId}/content/{contentId}/publications', [PublishController::class, 'index']);
        Route::get('/brands/{brandId}/analytics', [AnalyticsController::class, 'show']);
    });

    // Audit/security visibility is ADMIN/OWNER-only — it can reveal
    // who did what, including failed access attempts by other members.
    Route::middleware('workspace.role:ADMIN')->prefix('/workspaces/{workspace}')->group(function (): void {
        Route::get('/billing/plans', [BillingController::class, 'plans']);
        Route::get('/billing/payments', [BillingController::class, 'payments']);
        Route::post('/billing/orders', [BillingController::class, 'createOrder'])->middleware('throttle:20,1');
        Route::post('/billing/verify', [BillingController::class, 'verify'])->middleware('throttle:20,1');
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
        Route::get('/security-events', [SecurityEventController::class, 'index']);
    });

    Route::middleware('workspace.role:ADMIN')->prefix('/workspaces/{workspace}')->group(function (): void {
        Route::post('/members', [WorkspaceMemberController::class, 'store']);
    });

    Route::middleware('workspace.role:EDITOR')->prefix('/workspaces/{workspace}')->group(function (): void {
        Route::post('/brands', [BrandController::class, 'store']);
        Route::post('/sources', [SourceController::class, 'store']);
        Route::post('/brands/{brandId}/sources/{sourceId}/generate-concepts', [ConceptController::class, 'generate']);
    });

    // Selecting a concept is an APPROVER-or-above action, distinct from
    // EDITOR-level content creation — matches the blueprint's separation
    // between "can propose/edit" and "can approve/select" roles.
    Route::middleware('workspace.role:APPROVER')->prefix('/workspaces/{workspace}')->group(function (): void {
        Route::post('/brands/{brandId}/concepts/{conceptId}/select', [ConceptController::class, 'select']);
        Route::post('/brands/{brandId}/content/{contentId}/approve', [ContentEditController::class, 'approve']);
        Route::post('/brands/{brandId}/content/{contentId}/publish', [PublishController::class, 'store']);
    });

    Route::middleware('workspace.role:EDITOR')->prefix('/workspaces/{workspace}')->group(function (): void {
        Route::post('/brands/{brandId}/concepts/{conceptId}/content', [ContentController::class, 'store']);
        Route::post('/brands/{brandId}/content/{contentId}/captions', [ContentEditController::class, 'generateCaptions']);
        Route::patch('/brands/{brandId}/content/{contentId}', [ContentEditController::class, 'update']);
    });

    // The OAuth callback is platform-driven (no {workspace} in the
    // redirect URI you register with each platform), so workspace
    // identity travels inside the signed `state`/`selection_token`
    // instead of the URL — callback() validates it against that.
    Route::get('/social/{platformFamily}/callback', [SocialConnectionController::class, 'callback']);

    Route::middleware('workspace.role:ADMIN')->prefix('/workspaces/{workspace}')->group(function (): void {
        Route::post('/social/{platformFamily}/connect', [SocialConnectionController::class, 'connect']);
        Route::post('/social-accounts/activate', [SocialConnectionController::class, 'activate']);
        Route::delete('/social-accounts/{socialAccountId}', [SocialAccountController::class, 'destroy']);
    });
});
