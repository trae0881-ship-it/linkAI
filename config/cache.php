<?php

declare(strict_types=1);

use Illuminate\Support\Str;

return [
    'default' => env('CACHE_STORE', 'redis'),

    'stores' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'lock_connection' => 'default',
        ],
        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION'),
            'table' => 'cache',
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
            'lock_table' => 'cache_locks',
        ],
        'array' => ['driver' => 'array'],
    ],

    'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'link2social'), '_').'_cache_'),
];
