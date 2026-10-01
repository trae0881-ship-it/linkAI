<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AnalyticsEvent;
use App\Models\Publication;
use App\Services\Social\Adapters\MetaMetricsProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SyncPublicationMetricsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(private readonly string $publicationId) {}

    public function handle(): void
    {
        $publication = Publication::query()->with('socialAccount')->find($this->publicationId);

        if (! $publication || $publication->status !== 'published' || ! $publication->external_post_id) {
            return;
        }

        // Only Meta has a wired metrics provider today — same
        // integration-point pattern as publishing itself. Other
        // platforms silently no-op here until their provider exists.
        if (! str_starts_with($publication->platform, 'meta_')) {
            return;
        }

        $provider = new MetaMetricsProvider(new \GuzzleHttp\Client);
        $metrics = $provider->fetchMetrics($publication->external_post_id, $publication->socialAccount->access_token);

        foreach ($metrics as $metric => $value) {
            AnalyticsEvent::query()->create([
                'workspace_id' => $publication->workspace_id,
                'publication_id' => $publication->id,
                'metric' => $metric,
                'value' => $value,
                'source' => 'platform_api',
                'recorded_at' => now(),
            ]);
        }
    }
}
