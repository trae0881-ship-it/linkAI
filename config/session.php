<?php

declare(strict_types=1);

return [
    'driver' => env('SESSION_DRIVER', 'redis'),
    'lifetime' => (int) env('SESSION_LIFETIME', 120),
    'expire_on_close' => false,
    'encrypt' => true,
    // 'default' = Redis connection name; for the database driver use the DB connection (null = default).
    'connection' => env('SESSION_CONNECTION', env('SESSION_DRIVER', 'redis') === 'redis' ? 'default' : null),
    'cookie' => env('SESSION_COOKIE', 'link2social_session'),
    'path' => '/',
    'domain' => env('SESSION_DOMAIN'),
    'secure' => (bool) env('SESSION_SECURE_COOKIE', true),
    'http_only' => true,
    'same_site' => env('SESSION_SAME_SITE', 'lax'),
    'partitioned' => false,
];
