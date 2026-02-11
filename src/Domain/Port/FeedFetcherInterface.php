<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Domain\Port;

interface FeedFetcherInterface
{
    /**
     * Fetches raw XML content from a feed URL.
     *
     * @return string|null Raw XML string, or null on failure
     */
    public function fetch(string $url): ?string;
}
