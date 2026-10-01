<?php

declare(strict_types=1);

// Read via config() (not env()) so values survive `php artisan config:cache`.
return [
    'name' => env('ADMIN_NAME', 'Admin'),
    'email' => env('ADMIN_EMAIL'),
    'password' => env('ADMIN_PASSWORD'),
];
