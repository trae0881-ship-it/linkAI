<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Content;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class GenerateMediaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(private readonly string $contentId) {}

    public function handle(): void
    {
        $content = Content::query()->with('mediaAssets')->find($this->contentId);

        if (! $content) {
            return;
        }

        // Prompts and asset rows were created in the same order — pair
        // them positionally (both are ordered by creation).
        $assets = $content->mediaAssets()->orderBy('created_at')->get();
        $prompts = $content->generation_prompts;

        foreach ($assets as $i => $asset) {
            $prompt = $prompts[$i] ?? null;

            if ($prompt === null) {
                continue;
            }

            GenerateSingleMediaAssetJob::dispatch($asset->id, $prompt, $content->media_format);
        }
    }
}
