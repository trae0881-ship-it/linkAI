<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Content;
use App\Models\MediaAsset;
use App\Services\Media\Contracts\ImageProviderInterface;
use App\Services\Media\Contracts\VideoProviderInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class GenerateSingleMediaAssetJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 15;

    public int $timeout = 180;

    public function __construct(
        private readonly string $mediaAssetId,
        private readonly string $prompt,
        private readonly string $mediaFormat,
    ) {}

    public function handle(ImageProviderInterface $images, VideoProviderInterface $videos): void
    {
        $asset = MediaAsset::query()->find($this->mediaAssetId);

        if (! $asset) {
            return;
        }

        $asset->update(['status' => 'generating']);

        try {
            if ($this->mediaFormat === 'video') {
                $jobId = $videos->submit($this->prompt);
                $asset->update(['provider' => (string) config('services.video.provider'), 'provider_job_id' => $jobId]);
                PollVideoAssetJob::dispatch($asset->id)->delay(now()->addSeconds(20));

                return;
            }

            $result = $images->generate($this->prompt);
            $this->storeGeneratedBinary($asset, $result['binary'], $result['mime_type'], (string) config('services.image.provider'));
        } catch (\Throwable $e) {
            Log::error('Media generation failed.', ['media_asset_id' => $asset->id, 'error' => $e->getMessage()]);
            $asset->update(['status' => 'failed']);
            $this->checkContentCompletion($asset->content_id);
        }
    }

    public static function storeGeneratedBinary(MediaAsset $asset, string $binary, string $mimeType, string $provider): void
    {
        $extension = str_contains($mimeType, 'video') ? 'mp4' : 'png';
        $path = "media/{$asset->workspace_id}/{$asset->content_id}/".Str::uuid().'.'.$extension;

        Storage::disk($asset->storage_disk)->put($path, $binary, ['visibility' => 'private']);

        $asset->update([
            'storage_path' => $path,
            'mime_type' => $mimeType,
            'byte_size' => strlen($binary),
            'sha256' => hash('sha256', $binary),
            'status' => 'ready',
            'provider' => $provider,
        ]);

        self::checkContentCompletionStatic($asset->content_id);
    }

    private function checkContentCompletion(string $contentId): void
    {
        self::checkContentCompletionStatic($contentId);
    }

    public static function refreshContentStatus(string $contentId): void
    {
        self::checkContentCompletionStatic($contentId);
    }

    private static function checkContentCompletionStatic(string $contentId): void
    {
        $content = Content::query()->with('mediaAssets')->find($contentId);

        if (! $content) {
            return;
        }

        $statuses = $content->mediaAssets->pluck('status');

        if ($statuses->every(fn ($s) => in_array($s, ['ready', 'failed'], true))) {
            $newStatus = $statuses->contains('ready') ? 'generated' : 'failed';
            $content->update(['status' => $newStatus]);

            AuditLog::record(
                workspaceId: $content->workspace_id,
                userId: null,
                action: 'content.generation_completed',
                subjectType: Content::class,
                subjectId: $content->id,
                meta: ['status' => $newStatus, 'ready_count' => $statuses->filter(fn ($s) => $s === 'ready')->count()],
            );
        }
    }
}
