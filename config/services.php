<?php

declare(strict_types=1);

return [
    'llm' => [
        'provider' => env('LLM_PROVIDER', 'openai'),
        'key' => env('LLM_PROVIDER_KEY'),
        'model' => env('LLM_PROVIDER_MODEL', 'gpt-4o-mini'),
    ],

    'image' => [
        'provider' => env('IMAGE_PROVIDER', 'openai'),
        'key' => env('IMAGE_PROVIDER_KEY'),
    ],

    'video' => [
        'provider' => env('VIDEO_PROVIDER'),
        'key' => env('VIDEO_PROVIDER_KEY'),
    ],

    'trends' => [
        'provider' => env('TRENDS_PROVIDER'),
        'key' => env('TRENDS_PROVIDER_KEY'),
    ],

    // Social platform OAuth — populated when the Social module is built.
    'meta' => [
        'client_id' => env('META_CLIENT_ID'),
        'client_secret' => env('META_CLIENT_SECRET'),
        'redirect' => env('META_REDIRECT_URI'),
    ],
    'linkedin' => [
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'redirect' => env('LINKEDIN_REDIRECT_URI'),
    ],
    'pinterest' => [
        'client_id' => env('PINTEREST_CLIENT_ID'),
        'client_secret' => env('PINTEREST_CLIENT_SECRET'),
        'redirect' => env('PINTEREST_REDIRECT_URI'),
    ],
    'x' => [
        'client_id' => env('X_CLIENT_ID'),
        'client_secret' => env('X_CLIENT_SECRET'),
        'redirect' => env('X_REDIRECT_URI'),
    ],
];
