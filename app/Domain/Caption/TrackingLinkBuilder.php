<?php

declare(strict_types=1);

namespace App\Domain\Caption;

final class TrackingLinkBuilder
{
    /**
     * Builds a UTM-tagged destination link per platform, deterministically
     * from known identifiers — never trust the LLM to construct a URL.
     */
    public function build(string $destinationUrl, string $contentId, string $platform): string
    {
        $query = http_build_query([
            'utm_source' => $platform,
            'utm_medium' => 'social',
            'utm_campaign' => 'content_'.$contentId,
        ]);

        $separator = str_contains($destinationUrl, '?') ? '&' : '?';

        return $destinationUrl.$separator.$query;
    }

    /** @param string[] $platforms */
    public function buildForPlatforms(string $destinationUrl, string $contentId, array $platforms): array
    {
        $links = [];
        foreach ($platforms as $platform) {
            $links[$platform] = $this->build($destinationUrl, $contentId, $platform);
        }

        return $links;
    }
}
