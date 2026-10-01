<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Brand;
use App\Models\Workspace;
use App\Policies\BrandPolicy;
use App\Policies\WorkspacePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::policy(Workspace::class, WorkspacePolicy::class);
        // For Brand, `create` has no model instance yet — Laravel's
        // policy resolution still routes $user->can('create', [Brand::class, $workspaceId])
        // to BrandPolicy::create($user, $workspaceId) because Brand::class
        // is the first array element; no separate Gate::define needed.
        Gate::policy(Brand::class, BrandPolicy::class);
    }
}
