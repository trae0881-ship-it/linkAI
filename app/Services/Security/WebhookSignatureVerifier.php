<?php

declare(strict_types=1);

namespace App\Services\Security;

final class WebhookSignatureVerifier
{
    /**
     * Verifies an HMAC-SHA256 webhook signature using a constant-time
     * comparison (hash_equals) — a naive `===` here would leak timing
     * information an attacker could use to forge a valid signature
     * byte-by-byte.
     *
     * @param string $header e.g. "sha256=abcdef..." (Meta's format) or a bare hex digest
     */
    public function verify(string $rawBody, string $header, string $secret): bool
    {
        $providedDigest = str_contains($header, '=') ? explode('=', $header, 2)[1] : $header;
        $expectedDigest = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expectedDigest, $providedDigest);
    }
}
