<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\SecurityEvent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_platform_admin) {
            SecurityEvent::record(
                eventType: 'admin_access_denied',
                severity: 'warning',
                context: ['path' => $request->path()],
                userId: $user?->id,
                ipAddress: $request->ip(),
            );

            abort(403, 'Administrator access required.');
        }

        return $next($request);
    }
}
