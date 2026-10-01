<?php

declare(strict_types=1);

return [
    'domain' => env('HORIZON_DOMAIN'),
    'path' => env('HORIZON_PATH', 'horizon'),
    'use' => 'default',

    'prefix' => env('HORIZON_PREFIX', 'link2social_horizon:'),

    'middleware' => ['web', 'auth:sanctum'],

    'waits' => ['redis:default' => 60],

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    'silenced' => [],

    'metrics' => ['trim_snapshots' => ['job' => 24, 'queue' => 24]],

    'fast_termination' => false,

    'memory_limit' => 128,

    // Distinct queues so a slow AnalyzeSourceJob (external fetch) can't
    // starve time-sensitive publishing jobs, and vice versa.
    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default', 'analysis', 'media', 'publishing', 'metrics'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 6,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 180,
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-1' => [
                'maxProcesses' => (int) env('HORIZON_MAX_PROCESSES', 10),
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
        ],
        'local' => [
            'supervisor-1' => [
                'maxProcesses' => 3,
            ],
        ],
    ],
];
