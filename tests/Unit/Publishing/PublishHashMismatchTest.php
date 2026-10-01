<?php

declare(strict_types=1);

namespace Tests\Unit\Publishing;

use App\Jobs\PublishContentJob;
use App\Models\Brand;
use App\Models\BusinessProfile;
use App\Models\Content;
use App\Models\CreativeConcept;
use App\Models\MediaAsset;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\Source;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\SocialAdapterFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PublishHashMismatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_job_blocks_and_fails_when_content_was_edited_after_approval(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'Acme', 'slug' => 'acme-'.Str::random(6), 'owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['id' => (string) Str::uuid(), 'role' => 'OWNER']);
        $brand = Brand::query()->create(['workspace_id' => $workspace->id, 'name' => 'Acme']);
        $source = Source::query()->create([
            'workspace_id' => $workspace->id, 'brand_id' => $brand->id, 'submitted_by' => $owner->id,
            'submitted_url' => 'https://acme.example', 'status' => 'analyzed',
        ]);
        $profile = BusinessProfile::query()->create([
            'workspace_id' => $workspace->id, 'source_id' => $source->id, 'brand_id' => $brand->id,
            'brand_data' => [], 'offer_data' => [], 'audience_data' => [], 'seo_data' => [],
        ]);
        $concept = CreativeConcept::query()->create([
            'workspace_id' => $workspace->id, 'brand_id' => $brand->id, 'business_profile_id' => $profile->id,
            'title' => 'x', 'reasoning' => 'x', 'media_brief' => [], 'status' => 'selected',
        ]);
        $content = Content::query()->create([
            'workspace_id' => $workspace->id, 'brand_id' => $brand->id, 'creative_concept_id' => $concept->id,
            'requested_quantity' => 1, 'media_format' => 'image',
            'generation_prompts' => ['original prompt'], 'platform_copy' => ['instagram' => ['caption' => 'hi']],
            'status' => 'approved', 'approved_by' => $owner->id, 'approved_at' => now(),
        ]);
        MediaAsset::query()->create([
            'workspace_id' => $workspace->id, 'content_id' => $content->id, 'type' => 'image',
            'storage_path' => 'x.png', 'mime_type' => 'image/png', 'sha256' => 'abc', 'status' => 'ready',
        ]);
        $account = SocialAccount::query()->create([
            'workspace_id' => $workspace->id, 'connected_by' => $owner->id, 'platform' => 'meta_page',
            'external_account_id' => 'page_123', 'external_account_name' => 'Acme Page',
            'access_token' => 'token', 'is_active' => true,
        ]);

        // Approve against the ORIGINAL prompt's hash...
        $content->update(['approved_content_hash' => $content->computeContentHash()]);

        $publication = Publication::query()->create([
            'workspace_id' => $workspace->id, 'content_id' => $content->id, 'social_account_id' => $account->id,
            'idempotency_key' => Publication::buildIdempotencyKey($content->id, $account->id, $content->approved_content_hash),
            'platform' => $account->platform, 'status' => 'scheduled',
        ]);

        // ...then the content changes WITHOUT going through UpdateContentAction
        // (simulating any path that mutates it after approval) so the
        // stored approved_content_hash is now stale relative to computeContentHash().
        $content->update(['generation_prompts' => ['a DIFFERENT prompt entirely']]);

        (new PublishContentJob($publication->id))->handle(app(SocialAdapterFactory::class));

        $publication->refresh();
        $this->assertSame('failed', $publication->status);
        $this->assertStringContainsString('no longer matches', $publication->error_message);
    }
}
