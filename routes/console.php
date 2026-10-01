<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Metrics sync — every 6 hours, only for posts published in the last 30
// days (see SyncPublicationMetrics::handle()).
Schedule::command('link2social:sync-metrics')
    ->everySixHours()
    ->withoutOverlapping();

// Laravel's built-in cleanup for expired password-reset tokens and
// (if the database session driver is swapped in later) stale sessions.
Schedule::command('auth:clear-resets')->daily();
