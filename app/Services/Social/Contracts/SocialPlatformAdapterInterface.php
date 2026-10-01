<?php

declare(strict_types=1);

namespace App\Services\Social\Contracts;

interface SocialPlatformAdapterInterface
{
    public function authorizationUrl(string $state, string $redirectUri): string;

    /** @return array{access_token: string, refresh_token: ?string, expires_at: ?\DateTimeInterface} */
    public function exchangeCodeForToken(string $code, string $redirectUri): array;

    /**
     * Fetches EVERY page/profile/account the just-granted token can
     * manage, in one call — the user picks which to activate afterward,
     * rather than repeating the OAuth flow once per page.
     *
     * @return ConnectableAccount[]
     */
    public function listConnectableAccounts(string $userAccessToken): array;

    public function publish(ConnectableAccount|\App\Models\SocialAccount $account, string $caption, array $mediaUrls): PublishResult;
}
