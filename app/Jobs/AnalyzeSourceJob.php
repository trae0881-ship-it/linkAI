<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Source\BusinessProfileExtractor;
use App\Domain\Source\ExtractionValidationException;
use App\Models\AuditLog;
use App\Models\BusinessProfile;
use App\Models\Source;
use App\Services\Crawler\FetchException;
use App\Services\Crawler\HtmlSanitizer;
use App\Services\Crawler\SafeHttpFetcher;
use App\Services\Security\UrlSecurityException;
use App\Services\Security\UrlSecurityValidator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class AnalyzeSourceJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 90;

    public function __construct(private readonly string $sourceId) {}

    public function handle(
        UrlSecurityValidator $validator,
        SafeHttpFetcher $fetcher,
        HtmlSanitizer $sanitizer,
        BusinessProfileExtractor $extractor,
    ): void {
        $source = Source::query()->find($this->sourceId);

        if (! $source) {
            return; // deleted before the job ran; nothing to do
        }

        try {
            $source->update(['status' => 'validating']);
            $validator->validate($source->submitted_url); // throws on SSRF risk

            $source->update(['status' => 'fetching']);
            $result = $fetcher->fetch($source->submitted_url, $source->workspace_id);

            $source->update(['status' => 'sanitizing']);
            $sanitizedText = $sanitizer->sanitize($result->body);

            if (trim($sanitizedText) === '') {
                $this->markFailed($source, 'empty_content', 'No readable text content found on the page.');

                return;
            }

            $source->update([
                'status' => 'analyzing',
                'normalized_url' => $result->finalUrl,
                'sanitized_text' => $sanitizedText,
                'fetched_at' => now(),
            ]);

            $extracted = $extractor->extract($sanitizedText, $result->finalUrl);

            BusinessProfile::query()->updateOrCreate(
                ['source_id' => $source->id],
                [
                    'workspace_id' => $source->workspace_id,
                    'brand_id' => $source->brand_id,
                    'brand_data' => $extracted['brand'],
                    'offer_data' => $extracted['offer'],
                    'audience_data' => $extracted['audience'],
                    'seo_data' => $extracted['seo'],
                    'claims' => $extracted['claims'],
                    'analyzed_at' => now(),
                ],
            );

            $source->update(['status' => 'analyzed']);

            AuditLog::record(
                workspaceId: $source->workspace_id,
                userId: $source->submitted_by,
                action: 'source.analyzed',
                subjectType: Source::class,
                subjectId: $source->id,
            );
        } catch (UrlSecurityException $e) {
            $this->markFailed($source, $e->reasonCode, $e->getMessage());
        } catch (FetchException $e) {
            $this->markFailed($source, 'fetch_failed', $e->getMessage());
        } catch (ExtractionValidationException $e) {
            $this->markFailed($source, 'extraction_failed', $e->getMessage());
        } catch (\Throwable $e) {
            // Never leave a source stuck in an in-progress state; keep details in logs, not in the UI.
            \Illuminate\Support\Facades\Log::error('Source analysis crashed.', ['source_id' => $source->id, 'error' => $e->getMessage()]);
            $this->markFailed($source, 'internal_error', 'Analysis failed unexpectedly.');
        }
    }

    private function markFailed(Source $source, string $reasonCode, string $message): void
    {
        $source->update([
            'status' => 'failed',
            'rejection_reason' => $reasonCode,
        ]);

        AuditLog::record(
            workspaceId: $source->workspace_id,
            userId: $source->submitted_by,
            action: 'source.analysis_failed',
            subjectType: Source::class,
            subjectId: $source->id,
            meta: ['reason' => $reasonCode, 'message' => $message],
        );
    }
}
