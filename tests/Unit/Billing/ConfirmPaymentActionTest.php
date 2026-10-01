<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Domain\Billing\ConfirmPaymentAction;
use App\Models\Payment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ConfirmPaymentActionTest extends TestCase
{
    use RefreshDatabase;

    private function makePayment(): array
    {
        $user = User::factory()->create();
        $ws = Workspace::query()->create(['name' => 'Acme', 'slug' => 'acme-'.Str::random(6), 'owner_id' => $user->id]);
        $payment = Payment::query()->create([
            'workspace_id' => $ws->id, 'user_id' => $user->id, 'plan' => 'starter', 'amount_paise' => 49900,
            'currency' => 'INR', 'status' => 'created', 'razorpay_order_id' => 'order_X',
        ]);

        return [$ws, $payment];
    }

    public function test_confirms_once_and_is_idempotent(): void
    {
        [$ws] = $this->makePayment();
        $action = new ConfirmPaymentAction;

        $action->handle('order_X', 'pay_X');
        $firstExpiry = $ws->fresh()->plan_expires_at;
        $action->handle('order_X', 'pay_X'); // webhook retry / double callback

        $this->assertSame('starter', $ws->fresh()->plan);
        $this->assertEquals($firstExpiry, $ws->fresh()->plan_expires_at); // not extended twice
        $this->assertSame('paid', Payment::query()->first()->status);
    }

    public function test_amount_mismatch_fails_payment_and_does_not_activate_plan(): void
    {
        [$ws] = $this->makePayment();

        $result = (new ConfirmPaymentAction)->handle('order_X', 'pay_X', 100);

        $this->assertSame('failed', $result->status);
        $this->assertNull($ws->fresh()->plan);
        $this->assertDatabaseHas('security_events', ['event_type' => 'payment_amount_mismatch']);
    }
}
