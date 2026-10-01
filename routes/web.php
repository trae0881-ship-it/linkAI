<?php

declare(strict_types=1);

use App\Http\Controllers\Site\ContactController;
use App\Http\Controllers\Site\PageController;
use Illuminate\Support\Facades\Route;

// Public marketing/legal site (server-rendered for SEO). The app itself lives at /app/.
Route::get('/', [PageController::class, 'home']);

Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', 'about-us|services|contact-us|privacy-policy|refund-policy|terms-and-conditions|disclaimer');

Route::post('/contact-us', [ContactController::class, 'store'])->middleware('throttle:5,10');
