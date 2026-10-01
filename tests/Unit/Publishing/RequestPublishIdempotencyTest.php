<?php

declare(strict_types=1);

namespace Tests\Unit\Publishing;

use App\Domain\Publishing\RequestPublishAction;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue as QueueFacade;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RequestPublishIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function makeApprovedContentWithAccount(): array
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
            'generation_prompts' => ['p'], 'platform_copy' => ['instagram' => ['caption' => 'hi']],
            'status' => 'approved', 'approved_content_hash' => 'fixed-test-hash', 'approved_by' => $owner->id, 'approved_at' => now(),
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

        return [$content, $account, $owner];
    }

    public function test_repeated_publish_requests_create_only_one_publication(): void
    {
        QueueFacade::fake();

        [$content, $account, $owner] = $this->makeApprovedContentWithAccount();
        $action = new RequestPublishAction;

        $first = $action->handle($content, [$account->id], null, $owner);
        $second = $action->handle($content, [$account->id], null, $owner);

        $this->assertSame($first[0]->id, $second[0]->id);
        $this->assertSame(1, Publication::query()->count());
    }

    public function test_idempotency_key_changes_if_content_is_reapproved_with_different_hash(): void
    {
        QueueFacade::fake();

        [$content, $account, $owner] = $this->makeApprovedContentWithAccount();
        $action = new RequestPublishAction;

        $action->handle($content, [$account->id], null, $owner);

        $content->update(['approved_content_hash' => 'a-different-hash-after-reapproval']);
        $action->handle($content, [$account->id], null, $owner);

        $this->assertSame(2, Publication::query()->count());
    }
}
