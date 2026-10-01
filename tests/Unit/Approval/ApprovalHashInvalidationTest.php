<?php

declare(strict_types=1);

namespace Tests\Unit\Approval;

use App\Domain\Approval\ApproveContentAction;
use App\Domain\Approval\UpdateContentAction;
use App\Models\Brand;
use App\Models\BusinessProfile;
use App\Models\Content;
use App\Models\CreativeConcept;
use App\Models\MediaAsset;
use App\Models\Source;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ApprovalHashInvalidationTest extends TestCase
{
    use RefreshDatabase;

    private function makeApprovedContent(): Content
    {
        $owner = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'Acme', 'slug' => 'acme-'.Str::random(6), 'owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['id' => (string) Str::uuid(), 'role' => 'OWNER']);

        $brand = Brand::query()->create(['workspace_id' => $workspace->id, 'name' => 'Acme Brand']);
        $source = Source::query()->create([
            'workspace_id' => $workspace->id, 'brand_id' => $brand->id, 'submitted_by' => $owner->id,
            'submitted_url' => 'https://acme.example', 'status' => 'analyzed',
        ]);
        $profile = BusinessProfile::query()->create([
            'workspace_id' => $workspace->id, 'source_id' => $source->id, 'brand_id' => $brand->id,
            'brand_data' => ['name' => 'Acme'], 'offer_data' => [], 'audience_data' => [], 'seo_data' => [],
        ]);
        $concept = CreativeConcept::query()->create([
            'workspace_id' => $workspace->id, 'brand_id' => $brand->id, 'business_profile_id' => $profile->id,
            'title' => 'x', 'reasoning' => 'x', 'media_brief' => ['format' => 'image'], 'status' => 'selected',
        ]);
        $content = Content::query()->create([
            'workspace_id' => $workspace->id, 'brand_id' => $brand->id, 'creative_concept_id' => $concept->id,
            'requested_quantity' => 1, 'media_format' => 'image',
            'generation_prompts' => ['a prompt'], 'platform_copy' => ['instagram' => ['caption' => 'hi']],
            'status' => 'generated',
        ]);
        MediaAsset::query()->create([
            'workspace_id' => $workspace->id, 'content_id' => $content->id, 'type' => 'image',
            'storage_path' => 'media/x.png', 'mime_type' => 'image/png', 'sha256' => hash('sha256', 'abc'),
            'status' => 'ready',
        ]);

        $approved = (new ApproveContentAction)->handle($content, $owner);

        return $approved; // has approved_content_hash set, status=approved
    }

    public function test_approving_pins_a_non_null_hash(): void
    {
        $content = $this->makeApprovedContent();

        $this->assertNotNull($content->approved_content_hash);
        $this->assertSame('approved', $content->status);
    }

    public function test_editing_prompts_after_approval_clears_the_hash_and_unapproves(): void
    {
        $content = $this->makeApprovedContent();
        $editor = User::factory()->create();

        $updated = (new UpdateContentAction)->handle(
            $content,
            ['generation_prompts' => ['a DIFFERENT prompt']],
            $editor,
        );

        $this->assertNull($updated->approved_content_hash);
        $this->assertSame('pending_review', $updated->status);
        $this->assertNull($updated->approved_by);
    }

    public function test_recomputed_hash_differs_after_prompt_change(): void
    {
        $content = $this->makeApprovedContent();
        $originalHash = $content->approved_content_hash;

        $content->update(['generation_prompts' => ['a DIFFERENT prompt']]);

        $this->assertNotSame($originalHash, $content->fresh()->computeContentHash());
    }
}
