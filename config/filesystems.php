<?php

declare(strict_types=1);

return [
    'default' => env('FILESYSTEM_DISK', 's3'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
        ],

        // Fallback when no S3-compatible bucket is available (e.g. basic
        // shared hosting): files are readable directly over HTTP. Set
        // FILESYSTEM_DISK=public in that case. S3 is still recommended
        // when available.
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'serve' => true,
            'throw' => false,
        ],

        // S3-compatible: AWS S3, Cloudflare R2, or any endpoint that
        // speaks the S3 API (Railway's own bucket offering included).
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'auto'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => (bool) env('AWS_USE_PATH_STYLE_ENDPOINT', true),
            'throw' => false,
            'visibility' => 'private', // media is never public by default — always served via temporaryUrl()
        ],
    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
