<?php

declare(strict_types=1);

namespace App\Services\Security;

/**
 * Validates a URL is safe to fetch server-side, defending against SSRF:
 * reaching internal services, cloud metadata endpoints, or arbitrary
 * hosts the crawler shouldn't touch.
 *
 * This is called TWICE per request lifecycle: once on the user-submitted
 * URL, and again on every redirect Location header the fetcher follows —
 * a URL that resolves safely can still redirect to 169.254.169.254.
 */
final class UrlSecurityValidator
{
    private const array ALLOWED_SCHEMES = ['http', 'https'];

    /** Cloud metadata endpoints — never reachable regardless of "private" classification. */
    private const array BLOCKED_HOSTS = [
        '169.254.169.254', // AWS/GCP/Azure/DigitalOcean metadata
        'metadata.google.internal',
        'metadata.internal',
    ];

    /**
     * @throws UrlSecurityException when the URL must not be fetched
     * @return array{host: string, ips: string[]} the validated host and its resolved IPs
     */
    public function validate(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['host'])) {
            throw new UrlSecurityException('invalid_url', 'The URL could not be parsed.');
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if (! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new UrlSecurityException('invalid_scheme', 'Only http/https URLs are allowed.');
        }

        // Reject embedded credentials (http://user:pass@host) — a common
        // SSRF/phishing-adjacent trick and never legitimate here.
        if (! empty($parts['user']) || ! empty($parts['pass'])) {
            throw new UrlSecurityException('credentials_in_url', 'URLs with embedded credentials are not allowed.');
        }

        $host = strtolower($parts['host']);

        if (in_array($host, self::BLOCKED_HOSTS, true)) {
            throw new UrlSecurityException('blocked_host', 'This host is not allowed.');
        }

        if ($this->isNonRoutableLiteral($host)) {
            throw new UrlSecurityException('blocked_host', 'This host is not allowed.');
        }

        $ips = $this->resolve($host);

        if (empty($ips)) {
            throw new UrlSecurityException('dns_resolution_failed', 'The host could not be resolved.');
        }

        foreach ($ips as $ip) {
            if ($this->isDisallowedIp($ip)) {
                throw new UrlSecurityException(
                    'private_ip_blocked',
                    'This URL resolves to a private, loopback, or reserved address.',
                );
            }
        }

        return ['host' => $host, 'ips' => $ips];
    }

    private function isNonRoutableLiteral(string $host): bool
    {
        return in_array($host, ['localhost', '0.0.0.0', '[::1]', '::1'], true)
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.internal')
            || str_ends_with($host, '.local');
    }

    /** @return string[] */
    private function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $recordsA = @dns_get_record($host, DNS_A) ?: [];
        $recordsAAAA = @dns_get_record($host, DNS_AAAA) ?: [];

        $ips = [];
        foreach (array_merge($recordsA, $recordsAAAA) as $record) {
            $ips[] = $record['ip'] ?? $record['ipv6'] ?? null;
        }

        return array_values(array_filter($ips));
    }

    private function isDisallowedIp(string $ip): bool
    {
        // Blocks loopback, private (RFC1918), link-local (incl. 169.254/16
        // cloud metadata range), and reserved ranges for both IPv4/IPv6.
        $isPublicUnicast = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;

        if (! $isPublicUnicast) {
            return true;
        }

        // Explicit extra check: IPv4-mapped IPv6 loopback/private
        // (::ffff:127.0.0.1 etc.) that some FILTER_VALIDATE_IP builds miss.
        if (str_starts_with($ip, '::ffff:')) {
            $mapped = substr($ip, 7);
            if (filter_var($mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                && ! filter_var($mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return true;
            }
        }

        return false;
    }
}
