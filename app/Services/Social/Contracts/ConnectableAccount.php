<?php

declare(strict_types=1);

namespace App\Services\Social\Contracts;

final class ConnectableAccount
{
    public function __construct(
        public readonly string $externalAccountId,
        public readonly string $name,
        public readonly string $type, // page|business_account|profile
        public readonly string $accessToken, // account-scoped token, if different from the user token
        public readonly ?string $refreshToken = null,
        public readonly ?\DateTimeInterface $tokenExpiresAt = null,
    ) {}
}
