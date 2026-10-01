<?php

declare(strict_types=1);

namespace Tests\Feature\Workspace;

use App\Models\Brand;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CrossTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_outsider_cannot_view_or_create_brands_in_a_workspace_they_do_not_belong_to(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();

        $workspace = Workspace::query()->create([
            'name' => 'Acme',
            'slug' => 'acme-'.Str::random(6),
            'owner_id' => $owner->id,
        ]);
        $workspace->members()->attach($owner->id, ['id' => (string) Str::uuid(), 'role' => 'OWNER']);

        Brand::query()->create(['workspace_id' => $workspace->id, 'name' => 'Acme Brand']);

        $this->actingAs($outsider)
            ->getJson("/api/v1/workspaces/{$workspace->id}/brands")
            ->assertStatus(403);

        $this->actingAs($outsider)
            ->postJson("/api/v1/workspaces/{$workspace->id}/brands", ['name' => 'Injected Brand'])
            ->assertStatus(403);

        $this->assertDatabaseMissing('brands', ['name' => 'Injected Brand']);

        $this->assertDatabaseHas('security_events', [
            'event_type' => 'cross_tenant_denied',
        ]);
    }

    public function test_member_with_viewer_role_cannot_create_brands(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();

        $workspace = Workspace::query()->create([
            'name' => 'Acme',
            'slug' => 'acme-'.Str::random(6),
            'owner_id' => $owner->id,
        ]);
        $workspace->members()->attach($owner->id, ['id' => (string) Str::uuid(), 'role' => 'OWNER']);
        $workspace->members()->attach($viewer->id, ['id' => (string) Str::uuid(), 'role' => 'VIEWER']);

        $this->actingAs($viewer)
            ->postJson("/api/v1/workspaces/{$workspace->id}/brands", ['name' => 'Nope'])
            ->assertStatus(403);
    }
}
