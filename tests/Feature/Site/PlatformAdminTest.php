<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Models\BillingPlan;
use App\Models\User;
use App\Services\Billing\PaymentSettings;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['is_platform_admin' => true])->save();

        return $u;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SiteContentSeeder::class);
    }

    public function test_regular_users_cannot_use_admin_endpoints(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/admin/pages')->assertForbidden();
        $this->actingAs($user)->putJson('/api/v1/admin/payment-gateway', ['key_id' => 'rzp_test_abc123'])->assertForbidden();
        $this->assertDatabaseHas('security_events', ['event_type' => 'admin_access_denied']);
    }

    public function test_is_platform_admin_cannot_be_self_assigned_through_mass_assignment(): void
    {
        $u = User::query()->create(['name' => 'X', 'email' => 'x@example.com', 'password' => 'a-long-Passw0rd!', 'is_platform_admin' => true]);

        $this->assertFalse((bool) $u->fresh()->is_platform_admin);
    }

    public function test_admin_edits_a_page_and_it_appears_on_the_public_site(): void
    {
        $this->actingAs($this->admin())->putJson('/api/v1/admin/pages/about-us', [
            'title' => 'About Us', 'body' => "# Our story\nWe started in Delhi.", 'is_published' => true,
        ])->assertOk();

        $this->get('/about-us')->assertOk()->assertSee('We started in Delhi.');
    }

    public function test_admin_can_add_a_plan_and_it_shows_on_services_page(): void
    {
        $this->actingAs($this->admin())->postJson('/api/v1/admin/plans', [
            'key' => 'agency', 'name' => 'Agency', 'amount_paise' => 499900, 'days' => 30, 'is_active' => true,
        ])->assertCreated();

        $this->get('/services')->assertOk()->assertSee('Agency');
    }

    public function test_plan_validation_and_key_is_immutable(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/api/v1/admin/plans', ['key' => 'tiny', 'name' => 'Tiny', 'amount_paise' => 5, 'days' => 30, 'is_active' => true])->assertStatus(422);

        $plan = BillingPlan::query()->firstOrFail();
        $this->actingAs($admin)->putJson("/api/v1/admin/plans/{$plan->id}", [
            'key' => 'hacked', 'name' => 'X', 'amount_paise' => 1000, 'days' => 30, 'is_active' => true,
        ])->assertStatus(422);
    }

    public function test_gateway_secrets_are_encrypted_at_rest_and_never_returned(): void
    {
        $admin = $this->admin();

        $res = $this->actingAs($admin)->putJson('/api/v1/admin/payment-gateway', [
            'key_id' => 'rzp_test_AbC123', 'key_secret' => 'super-secret-value-9999', 'webhook_secret' => 'whsec-top-secret',
        ])->assertOk();

        $this->assertStringNotContainsString('super-secret-value-9999', $res->getContent());
        $this->assertStringNotContainsString('whsec-top-secret', $res->getContent());
        $this->assertSame('••••9999', $res->json('data.key_secret_hint'));

        $stored = DB::table('site_settings')->where('name', 'razorpay_key_secret')->value('value');
        $this->assertNotSame('super-secret-value-9999', $stored);
        $this->assertStringNotContainsString('super-secret', (string) $stored);

        $settings = app(PaymentSettings::class);
        $this->assertSame('super-secret-value-9999', $settings->keySecret());
        $this->assertTrue($settings->configured());

        $this->actingAs($admin)->getJson('/api/v1/admin/payment-gateway')->assertOk()->assertJsonMissingPath('data.key_secret');
    }

    public function test_gateway_rejects_a_malformed_key_id_and_blank_fields_keep_existing_secret(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->putJson('/api/v1/admin/payment-gateway', ['key_id' => 'not-a-razorpay-key'])->assertStatus(422);

        $this->actingAs($admin)->putJson('/api/v1/admin/payment-gateway', ['key_id' => 'rzp_test_AbC123', 'key_secret' => 'first-secret-1111'])->assertOk();
        $this->actingAs($admin)->putJson('/api/v1/admin/payment-gateway', ['key_id' => 'rzp_test_AbC123', 'key_secret' => ''])->assertOk();

        $this->assertSame('first-secret-1111', app(PaymentSettings::class)->keySecret());
    }
}
