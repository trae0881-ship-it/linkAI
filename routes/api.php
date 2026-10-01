<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Domain route files (brands, sources, concepts, media, social, publishing,
// analytics, admin) are registered here module-by-module as they're built.
// Auth routes are mounted separately in bootstrap/app.php from routes/auth.php.

Route::middleware('auth:sanctum')->get('/user', fn (Request $request) => $request->user());
