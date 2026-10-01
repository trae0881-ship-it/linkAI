<?php

declare(strict_types=1);

use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureWorkspaceRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            \Illuminate\Support\Facades\Route::middleware('api')
                ->prefix('api/v1')
                ->group(function (): void {
                    require __DIR__.'/../routes/auth.php';
                    require __DIR__.'/../routes/workspaces.php';
                    require __DIR__.'/../routes/webhooks.php';
                    require __DIR__.'/../routes/admin.php';
                });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->statefulApi(); // Sanctum SPA cookie auth on all api/* routes

        $middleware->alias([
            'workspace.role' => EnsureWorkspaceRole::class,
            'platform.admin' => EnsurePlatformAdmin::class,
        ]);

        $middleware->trustProxies(at: '*'); // Railway sits behind a proxy/load balancer
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Secure error handling: log full detail server-side, never leak
        // stack traces/internals to the client outside local/debug env.
        $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*') || $request->expectsJson());
    })
    ->create();

if ($publicPath = env('APP_PUBLIC_PATH')) {
    $app->usePublicPath($publicPath);
}

return $app;
