<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Services\Security\UrlSecurityException;
use App\Services\Security\UrlSecurityValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class UrlSecurityValidatorTest extends TestCase
{
    private UrlSecurityValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new UrlSecurityValidator;
    }

    #[DataProvider('blockedUrls')]
    public function test_blocks_unsafe_urls(string $url, string $expectedReason): void
    {
        try {
            $this->validator->validate($url);
            $this->fail("Expected {$url} to be blocked but it was allowed.");
        } catch (UrlSecurityException $e) {
            $this->assertSame($expectedReason, $e->reasonCode);
        }
    }

    public static function blockedUrls(): array
    {
        return [
            'loopback ip' => ['http://127.0.0.1/', 'private_ip_blocked'],
            'loopback ipv6' => ['http://[::1]/', 'blocked_host'],
            'localhost literal' => ['http://localhost/', 'blocked_host'],
            'private class A' => ['http://10.0.0.5/', 'private_ip_blocked'],
            'private class B' => ['http://172.16.5.5/', 'private_ip_blocked'],
            'private class C' => ['http://192.168.1.10/', 'private_ip_blocked'],
            'link-local / cloud metadata range' => ['http://169.254.169.254/', 'blocked_host'],
            'link-local other' => ['http://169.254.1.1/', 'private_ip_blocked'],
            'gcp metadata hostname' => ['http://metadata.google.internal/', 'blocked_host'],
            'internal tld' => ['http://service.internal/', 'blocked_host'],
            'file scheme' => ['file:///etc/passwd', 'invalid_scheme'],
            'gopher scheme' => ['gopher://127.0.0.1:25/', 'invalid_scheme'],
            'ftp scheme' => ['ftp://example.com/', 'invalid_scheme'],
            'embedded credentials' => ['http://admin:pass@example.com/', 'credentials_in_url'],
            'unparseable' => ['not a url at all', 'invalid_url'],
        ];
    }

    public function test_allows_a_normal_public_url(): void
    {
        // example.com resolves to a public IP; this documents intended
        // behavior. If offline in CI, mark this test skipped rather than
        // let a DNS failure be misread as a security regression.
        try {
            $result = $this->validator->validate('https://example.com/');
        } catch (UrlSecurityException $e) {
            if ($e->reasonCode === 'dns_resolution_failed') {
                $this->markTestSkipped('No DNS resolution available in this environment.');
            }
            throw $e;
        }

        $this->assertSame('example.com', $result['host']);
        $this->assertNotEmpty($result['ips']);
    }
}
