<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;

return [
    // Frontend origins allowed to use the cookie-based SPA auth flow.
    // Must be an exact host allowlist — never a wildcard in production.
    'stateful' => explode(',', (string) env(
        'SANCTUM_STATEFUL_DOMAINS',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1'
    )),

    'guard' => ['web'],

    // Personal access tokens (used for CLI/integration access, not the
    // browser SPA flow) expire by default rather than living forever.
    'expiration' => 60 * 24 * 30,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
