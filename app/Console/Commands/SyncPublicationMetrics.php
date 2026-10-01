<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncPublicationMetricsJob;
use App\Models\Publication;
use Illuminate\Console\Command;

final class SyncPublicationMetrics extends Command
{
    protected $signature = 'link2social:sync-metrics';

    protected $description = 'Dispatch a metrics sync job for every publication published in the last 30 days.';

    public function handle(): int
    {
        $count = 0;

        Publication::query()
            ->where('status', 'published')
            ->where('published_at', '>=', now()->subDays(30))
            ->chunkById(200, function ($publications) use (&$count): void {
                foreach ($publications as $publication) {
                    SyncPublicationMetricsJob::dispatch($publication->id);
                    $count++;
                }
            });

        $this->info("Dispatched metrics sync for {$count} publications.");

        return self::SUCCESS;
    }
}
