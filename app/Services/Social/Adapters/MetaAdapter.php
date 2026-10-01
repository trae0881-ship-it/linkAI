<?php

declare(strict_types=1);

namespace App\Services\Social\Adapters;

use App\Models\SocialAccount;
use App\Services\Social\Contracts\ConnectableAccount;
use App\Services\Social\Contracts\PublishResult;
use App\Services\Social\Contracts\SocialPlatformAdapterInterface;
use GuzzleHttp\Client;

/**
 * Meta Graph API adapter covering Facebook Pages and their linked
 * Instagram Business accounts under a single OAuth grant.
 *
 * "Connect all pages in one flow": after the OAuth callback exchanges
 * the short-lived user token, GET /me/accounts returns every Page the
 * authorizing user administers in ONE call (each with its own
 * page-scoped access token already attached) — listConnectableAccounts()
 * surfaces all of them so the user picks which to activate, instead of
 * re-running the OAuth dance per page.
 */
final class MetaAdapter implements SocialPlatformAdapterInterface
{
    private const string GRAPH_VERSION = 'v21.0';

    public function __construct(
        private readonly Client $client,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {}

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        $params = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => 'pages_show_list,pages_manage_posts,pages_read_engagement,instagram_basic,instagram_content_publish',
            'response_type' => 'code',
        ]);

        return "https://www.facebook.com/".self::GRAPH_VERSION."/dialog/oauth?{$params}";
    }

    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        $response = $this->client->get('https://graph.facebook.com/'.self::GRAPH_VERSION.'/oauth/access_token', [
            'query' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $redirectUri,
                'code' => $code,
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        // Exchange for a long-lived user token (~60 days) immediately —
        // never store the short-lived one.
        $longLived = $this->client->get('https://graph.facebook.com/'.self::GRAPH_VERSION.'/oauth/access_token', [
            'query' => [
                'grant_type' => 'fb_exchange_token',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'fb_exchange_token' => $data['access_token'],
            ],
        ]);
        $longLivedData = json_decode((string) $longLived->getBody(), true, flags: JSON_THROW_ON_ERROR);

        return [
            'access_token' => $longLivedData['access_token'],
            'refresh_token' => null, // Meta long-lived tokens are refreshed by re-exchange, not a refresh_token grant
            'expires_at' => isset($longLivedData['expires_in'])
                ? (new \DateTimeImmutable())->modify("+{$longLivedData['expires_in']} seconds")
                : null,
        ];
    }

    public function listConnectableAccounts(string $userAccessToken): array
    {
        $response = $this->client->get('https://graph.facebook.com/'.self::GRAPH_VERSION.'/me/accounts', [
            'query' => [
                'access_token' => $userAccessToken,
                'fields' => 'id,name,access_token,instagram_business_account{id,name}',
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $accounts = [];

        foreach ($data['data'] ?? [] as $page) {
            $accounts[] = new ConnectableAccount(
                externalAccountId: $page['id'],
                name: $page['name'],
                type: 'page',
                accessToken: $page['access_token'], // page-scoped token, doesn't expire while the page grant is active
            );

            if (isset($page['instagram_business_account']['id'])) {
                $accounts[] = new ConnectableAccount(
                    externalAccountId: $page['instagram_business_account']['id'],
                    name: ($page['instagram_business_account']['name'] ?? $page['name']).' (Instagram)',
                    type: 'instagram_business_account',
                    accessToken: $page['access_token'], // IG publishing uses the linked Page's token
                );
            }
        }

        return $accounts;
    }

    public function publish(ConnectableAccount|SocialAccount $account, string $caption, array $mediaUrls): PublishResult
    {
        $accessToken = $account instanceof SocialAccount ? $account->access_token : $account->accessToken;
        $type = $account instanceof SocialAccount ? $account->external_account_type : $account->type;
        $id = $account instanceof SocialAccount ? $account->external_account_id : $account->externalAccountId;

        return match ($type) {
            'page' => $this->publishToPage($id, $accessToken, $caption, $mediaUrls),
            'instagram_business_account' => $this->publishToInstagram($id, $accessToken, $caption, $mediaUrls),
            default => throw new SocialPublishException("Unsupported Meta account type: {$type}"),
        };
    }

    private function publishToPage(string $pageId, string $accessToken, string $caption, array $mediaUrls): PublishResult
    {
        $endpoint = $mediaUrls === []
            ? "https://graph.facebook.com/".self::GRAPH_VERSION."/{$pageId}/feed"
            : "https://graph.facebook.com/".self::GRAPH_VERSION."/{$pageId}/photos";

        $body = $mediaUrls === []
            ? ['message' => $caption, 'access_token' => $accessToken]
            : ['url' => $mediaUrls[0], 'caption' => $caption, 'access_token' => $accessToken];

        $response = $this->client->post($endpoint, ['form_params' => $body]);
        $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        if (! isset($data['id']) && ! isset($data['post_id'])) {
            throw new SocialPublishException('Meta publish failed: '.(string) $response->getBody());
        }

        $postId = $data['post_id'] ?? $data['id'];

        return new PublishResult(
            externalPostId: $postId,
            externalPostUrl: "https://www.facebook.com/{$postId}",
        );
    }

    private function publishToInstagram(string $igAccountId, string $accessToken, string $caption, array $mediaUrls): PublishResult
    {
        if ($mediaUrls === []) {
            throw new SocialPublishException('Instagram publishing requires at least one media asset.');
        }

        // Two-step Graph API publish flow: create a media container, then publish it.
        $container = $this->client->post(
            "https://graph.facebook.com/".self::GRAPH_VERSION."/{$igAccountId}/media",
            ['form_params' => ['image_url' => $mediaUrls[0], 'caption' => $caption, 'access_token' => $accessToken]],
        );
        $containerData = json_decode((string) $container->getBody(), true, flags: JSON_THROW_ON_ERROR);

        if (! isset($containerData['id'])) {
            throw new SocialPublishException('Instagram container creation failed: '.(string) $container->getBody());
        }

        $publish = $this->client->post(
            "https://graph.facebook.com/".self::GRAPH_VERSION."/{$igAccountId}/media_publish",
            ['form_params' => ['creation_id' => $containerData['id'], 'access_token' => $accessToken]],
        );
        $publishData = json_decode((string) $publish->getBody(), true, flags: JSON_THROW_ON_ERROR);

        if (! isset($publishData['id'])) {
            throw new SocialPublishException('Instagram publish failed: '.(string) $publish->getBody());
        }

        return new PublishResult(externalPostId: $publishData['id']);
    }
}
