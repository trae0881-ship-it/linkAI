<?php

declare(strict_types=1);

namespace App\Services\Social\Adapters;

use App\Models\SocialAccount;
use App\Services\Social\Contracts\ConnectableAccount;
use App\Services\Social\Contracts\PublishResult;
use App\Services\Social\Contracts\SocialPlatformAdapterInterface;

/**
 * INTEGRATION POINT: LinkedIn, Pinterest, and X each have their own
 * OAuth + posting API and are NOT faked here. This adapter documents
 * exactly what implementing one requires, matching
 * SocialPlatformAdapterInterface:
 *
 *  - LinkedIn: OAuth 2.0 (w_member_social / w_organization_social scope),
 *    POST https://api.linkedin.com/rest/posts. Config keys already
 *    present: LINKEDIN_CLIENT_ID/SECRET/REDIRECT_URI.
 *  - Pinterest: OAuth 2.0 (pins:write, boards:read), POST
 *    https://api.pinterest.com/v5/pins. Config keys already present:
 *    PINTEREST_CLIENT_ID/SECRET/REDIRECT_URI.
 *  - X (Twitter): OAuth 2.0 PKCE, POST https://api.x.com/2/tweets +
 *    media upload via v1.1 media/upload. Config keys already present:
 *    X_CLIENT_ID/SECRET/REDIRECT_URI.
 *
 * To add one: implement SocialPlatformAdapterInterface for that
 * platform and add a case for it in SocialAdapterFactory — nothing else
 * in the Social/Publishing domain changes.
 */
final class UnimplementedPlatformAdapter implements SocialPlatformAdapterInterface
{
    public function __construct(private readonly string $platform) {}

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        throw new PlatformNotImplementedException("{$this->platform} is not yet implemented. See UnimplementedPlatformAdapter's docblock.");
    }

    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        throw new PlatformNotImplementedException("{$this->platform} is not yet implemented.");
    }

    public function listConnectableAccounts(string $userAccessToken): array
    {
        throw new PlatformNotImplementedException("{$this->platform} is not yet implemented.");
    }

    public function publish(ConnectableAccount|SocialAccount $account, string $caption, array $mediaUrls): PublishResult
    {
        throw new PlatformNotImplementedException("{$this->platform} is not yet implemented.");
    }
}
