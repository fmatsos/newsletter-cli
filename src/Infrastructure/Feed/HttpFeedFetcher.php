<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Infrastructure\Feed;

use Akawaka\Newsletter\Domain\Port\FeedFetcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpFeedFetcher implements FeedFetcherInterface
{
    private const int DEFAULT_TIMEOUT = 15;

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    #[\Override]
    public function fetch(string $url): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => self::DEFAULT_TIMEOUT,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (compatible; AkawakaDigest/1.0)',
                    'Accept' => 'application/rss+xml, application/atom+xml, application/xml, text/xml, */*',
                ],
            ]);

            return $response->getContent();
        } catch (\Throwable) {
            return null;
        }
    }
}
