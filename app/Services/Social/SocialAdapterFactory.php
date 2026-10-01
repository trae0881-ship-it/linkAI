<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Services\Social\Adapters\MetaAdapter;
use App\Services\Social\Adapters\UnimplementedPlatformAdapter;
use App\Services\Social\Contracts\SocialPlatformAdapterInterface;
use GuzzleHttp\Client;

final class SocialAdapterFactory
{
    /** 'meta' covers both meta_facebook_page and meta_instagram — one OAuth grant, two account types. */
    public function make(string $platformFamily): SocialPlatformAdapterInterface
    {
        return match ($platformFamily) {
            'meta' => new MetaAdapter(
                new Client,
                (string) config('services.meta.client_id'),
                (string) config('services.meta.client_secret'),
            ),
            'linkedin', 'pinterest', 'x' => new UnimplementedPlatformAdapter($platformFamily),
            default => throw new \InvalidArgumentException("Unknown platform family: {$platformFamily}"),
        };
    }

    /** social_accounts.platform values (meta_facebook_page, meta_instagram, ...) map to their OAuth family. */
    public function familyForAccountPlatform(string $accountPlatform): string
    {
        return match (true) {
            str_starts_with($accountPlatform, 'meta_') => 'meta',
            default => $accountPlatform,
        };
    }
}
