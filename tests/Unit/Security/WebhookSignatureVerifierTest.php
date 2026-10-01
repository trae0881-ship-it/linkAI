<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Services\Security\WebhookSignatureVerifier;
use Tests\TestCase;

final class WebhookSignatureVerifierTest extends TestCase
{
    public function test_accepts_a_valid_signature(): void
    {
        $body = '{"event":"post.published"}';
        $secret = 'whsec_test';
        $signature = 'sha256='.hash_hmac('sha256', $body, $secret);

        $this->assertTrue((new WebhookSignatureVerifier)->verify($body, $signature, $secret));
    }

    public function test_rejects_a_tampered_body(): void
    {
        $body = '{"event":"post.published"}';
        $secret = 'whsec_test';
        $signature = 'sha256='.hash_hmac('sha256', $body, $secret);

        $tamperedBody = '{"event":"post.deleted"}';

        $this->assertFalse((new WebhookSignatureVerifier)->verify($tamperedBody, $signature, $secret));
    }

    public function test_rejects_wrong_secret(): void
    {
        $body = '{"event":"post.published"}';
        $signature = 'sha256='.hash_hmac('sha256', $body, 'correct_secret');

        $this->assertFalse((new WebhookSignatureVerifier)->verify($body, $signature, 'wrong_secret'));
    }
}
