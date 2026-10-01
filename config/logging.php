<?php

declare(strict_types=1);

return [
    'default' => env('LOG_CHANNEL', 'stderr'),

    'channels' => [
        'stderr' => [
            'driver' => 'monolog',
            'handler' => Monolog\Handler\StreamHandler::class,
            'with' => ['stream' => 'php://stderr'],
            'level' => env('LOG_LEVEL', 'info'),
        ],

        // Dedicated channels for audit/security — separate from
        // application error logs so they're easy to ship to a SIEM
        // later without noise. Actual persistence is the audit_logs /
        // security_events tables; these channels are a secondary trail.
        'audit' => [
            'driver' => 'monolog',
            'handler' => Monolog\Handler\StreamHandler::class,
            'with' => ['stream' => 'php://stderr'],
            'level' => 'info',
        ],
        'security' => [
            'driver' => 'monolog',
            'handler' => Monolog\Handler\StreamHandler::class,
            'with' => ['stream' => 'php://stderr'],
            'level' => 'warning',
        ],
    ],
];
