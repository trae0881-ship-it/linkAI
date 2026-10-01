<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Services\Billing\RazorpaySignature;
use PHPUnit\Framework\TestCase;

final class RazorpaySignatureTest extends TestCase
{
    public function test_accepts_valid_payment_signature(): void
    {
        $sig = hash_hmac('sha256', 'order_1|pay_1', 'secret');

        $this->assertTrue((new RazorpaySignature)->verifyPayment('order_1', 'pay_1', $sig, 'secret'));
    }

    public function test_rejects_swapped_payment_id_and_wrong_secret(): void
    {
        $sig = hash_hmac('sha256', 'order_1|pay_1', 'secret');
        $v = new RazorpaySignature;

        $this->assertFalse($v->verifyPayment('order_1', 'pay_2', $sig, 'secret'));
        $this->assertFalse($v->verifyPayment('order_1', 'pay_1', $sig, 'other'));
        $this->assertFalse($v->verifyPayment('order_1', 'pay_1', $sig, ''));
    }

    public function test_webhook_signature_is_over_raw_body(): void
    {
        $body = '{"event":"payment.captured"}';
        $sig = hash_hmac('sha256', $body, 'whsec');
        $v = new RazorpaySignature;

        $this->assertTrue($v->verifyWebhook($body, $sig, 'whsec'));
        $this->assertFalse($v->verifyWebhook($body.' ', $sig, 'whsec'));
    }
}
