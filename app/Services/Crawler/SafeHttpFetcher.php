<?php

declare(strict_types=1);

namespace App\Services\Crawler;

use App\Models\SecurityEvent;
use App\Services\Security\UrlSecurityException;
use App\Services\Security\UrlSecurityValidator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Fetches a URL the way an SSRF-hardened crawler must:
 *   - Guzzle's own redirect-following is disabled (allow_redirects=false).
 *   - Every redirect Location header is re-validated from scratch through
 *     UrlSecurityValidator before being followed — a URL that resolves
 *     safely can still 302 to an internal address (DNS rebinding /
 *     redirect-based SSRF).
 *   - Response body is capped; the connection is aborted once the cap is
 *     exceeded rather than buffering an unbounded response into memory.
 *   - Only http(s), a small, explicit User-Agent, and strict timeouts.
 */
final class SafeHttpFetcher
{
    public function __construct(
        private readonly UrlSecurityValidator $validator,
    ) {}

    public function fetch(string $url, ?string $workspaceId = null): FetchResult
    {
        $maxRedirects = (int) config('crawler.max_redirects', 5);
        $maxBytes = (int) config('crawler.max_html_bytes', 5 * 1024 * 1024);

        $currentUrl = $url;

        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            try {
                $validated = $this->validator->validate($currentUrl);
            } catch (UrlSecurityException $e) {
                SecurityEvent::record(
                    eventType: 'ssrf_attempt',
                    severity: 'critical',
                    context: ['url' => $currentUrl, 'reason' => $e->reasonCode, 'hop' => $hop],
                    workspaceId: $workspaceId,
                );

                throw new FetchException("Blocked: {$e->getMessage()}", previous: $e);
            }

            $response = $this->rawGet($currentUrl, $maxBytes, $validated['host'], $validated['ips'][0]);

            if (in_array($response->statusCode, [301, 302, 303, 307, 308], true)) {
                $location = $response->body; // rawGet returns Location in body slot for redirects, see below
                if ($location === '') {
                    throw new FetchException('Redirect with no Location header.');
                }

                $currentUrl = $this->resolveRedirect($currentUrl, $location);

                continue;
            }

            if ($response->statusCode >= 400) {
                throw new FetchException("The site responded with HTTP {$response->statusCode}.");
            }

            if (! str_starts_with(strtolower($response->contentType), 'text/html')
                && ! str_starts_with(strtolower($response->contentType), 'application/xhtml')) {
                throw new FetchException("Unsupported content type: {$response->contentType}");
            }

            return new FetchResult($currentUrl, $response->statusCode, $response->contentType, $response->body);
        }

        throw new FetchException('Too many redirects.');
    }

    private function resolveRedirect(string $baseUrl, string $location): string
    {
        if (parse_url($location, PHP_URL_SCHEME) !== null) {
            return $location; // absolute
        }

        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'] ?? '';
        $port = isset($base['port']) ? ':'.$base['port'] : '';

        return $scheme.'://'.$host.$port.'/'.ltrim($location, '/');
    }

    /**
     * @internal Guzzle's own redirect handling is off; a 3xx response
     * lands here with the Location header as the "body" so the caller
     * can re-validate it before ever connecting to it.
     */
    private function rawGet(string $url, int $maxBytes, string $host, string $pinnedIp): FetchResult
    {
        // Pin the connection to the exact IP that passed validation, so DNS cannot
        // change between the check and the connect (DNS rebinding / TOCTOU).
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $port = (int) (parse_url($url, PHP_URL_PORT) ?: ($scheme === 'https' ? 443 : 80));

        $client = new Client([
            'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$pinnedIp}"]],
            'timeout' => (int) config('crawler.total_timeout', 25),
            'connect_timeout' => (int) config('crawler.connect_timeout', 8),
            'allow_redirects' => false,
            'http_errors' => false,
            'headers' => [
                'User-Agent' => (string) config('crawler.user_agent', 'Link2SocialBot/1.0'),
                'Accept' => 'text/html,application/xhtml+xml',
            ],
            // Stream so we can enforce a byte cap instead of buffering
            // an attacker-controlled, arbitrarily large response.
            'stream' => true,
        ]);

        try {
            $response = $client->get($url);
        } catch (GuzzleException $e) {
            throw new FetchException('Could not fetch the URL: '.$e->getMessage(), previous: $e);
        }

        $status = $response->getStatusCode();

        if (in_array($status, [301, 302, 303, 307, 308], true)) {
            return new FetchResult($url, $status, '', (string) $response->getHeaderLine('Location'));
        }

        $contentType = $response->getHeaderLine('Content-Type');
        $body = $this->readCapped($response->getBody(), $maxBytes);

        return new FetchResult($url, $status, $contentType, $body);
    }

    private function readCapped(\Psr\Http\Message\StreamInterface $stream, int $maxBytes): string
    {
        $buffer = '';
        while (! $stream->eof()) {
            $buffer .= $stream->read(8192);
            if (strlen($buffer) > $maxBytes) {
                $stream->close();

                throw new FetchException('Response exceeded the maximum allowed size.');
            }
        }

        return $buffer;
    }
}
