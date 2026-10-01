<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MediaAsset;
use App\Services\Media\Contracts\VideoProviderInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class PollVideoAssetJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const int MAX_POLL_MINUTES = 20;

    public function __construct(private readonly string $mediaAssetId) {}

    public function handle(VideoProviderInterface $videos): void
    {
        $asset = MediaAsset::query()->find($this->mediaAssetId);

        if (! $asset || $asset->status !== 'generating' || ! $asset->provider_job_id) {
            return;
        }

        if ($asset->created_at->diffInMinutes(now()) > self::MAX_POLL_MINUTES) {
            $asset->update(['status' => 'failed']);

            return;
        }

        $result = $videos->poll($asset->provider_job_id);

        match ($result['status']) {
            'completed' => GenerateSingleMediaAssetJob::storeGeneratedBinary(
                $asset,
                (string) $result['binary'],
                (string) ($result['mime_type'] ?? 'video/mp4'),
                (string) $asset->provider,
            ),
            'failed' => (function () use ($asset): void {
                $asset->update(['status' => 'failed']);
                GenerateSingleMediaAssetJob::refreshContentStatus($asset->content_id);
            })(),
            default => self::dispatch($this->mediaAssetId)->delay(now()->addSeconds(20)),
        };
    }
}
