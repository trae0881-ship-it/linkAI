<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Social\SocialAdapterFactory;
use Illuminate\Support\ServiceProvider;

final class SocialServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SocialAdapterFactory::class);
    }
}
