<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\Payment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RazorpayWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function post(string $body, string $sig)
    {
        return $this->call('POST', '/api/v1/webhooks/razorpay', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => $sig, 'HTTP_ACCEPT' => 'application/json',
        ], $body);
    }

    public function test_rejects_bad_signature_and_logs_security_event(): void
    {
        config(['billing.razorpay.webhook_secret' => 'whsec']);

        $this->post('{"event":"payment.captured"}', 'deadbeef')->assertStatus(400);
        $this->assertDatabaseHas('security_events', ['event_type' => 'webhook_signature_failed']);
    }

    public function test_valid_signature_confirms_payment(): void
    {
        config(['billing.razorpay.webhook_secret' => 'whsec']);
        $user = User::factory()->create();
        $ws = Workspace::query()->create(['name' => 'Acme', 'slug' => 'acme-'.Str::random(6), 'owner_id' => $user->id]);
        Payment::query()->create([
            'workspace_id' => $ws->id, 'user_id' => $user->id, 'plan' => 'pro', 'amount_paise' => 149900,
            'currency' => 'INR', 'status' => 'created', 'razorpay_order_id' => 'order_W',
        ]);

        $body = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => ['id' => 'pay_W', 'order_id' => 'order_W', 'amount' => 149900]]]]);
        $this->post($body, hash_hmac('sha256', $body, 'whsec'))->assertOk();

        $this->assertSame('paid', Payment::query()->first()->status);
        $this->assertSame('pro', $ws->fresh()->plan);
    }
}
