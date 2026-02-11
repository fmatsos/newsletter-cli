<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Tests\Infrastructure\Feed;

use Akawaka\Newsletter\Infrastructure\Feed\HttpFeedFetcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(HttpFeedFetcher::class)]
final class HttpFeedFetcherTest extends TestCase
{
    #[Test]
    public function it_returns_content_on_success(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn('<rss>content</rss>');

        $client = $this->createMock(HttpClientInterface::class);
        $client->expects(self::once())
            ->method('request')
            ->with('GET', 'https://feed.example.com/rss', self::callback(
                static fn (array $options): bool => 15 === $options['timeout']
                    && isset($options['headers']['User-Agent']),
            ))
            ->willReturn($response);

        $fetcher = new HttpFeedFetcher($client);
        $result = $fetcher->fetch('https://feed.example.com/rss');

        self::assertSame('<rss>content</rss>', $result);
    }

    #[Test]
    public function it_returns_null_on_failure(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willThrowException(new \RuntimeException('Network error'));

        $fetcher = new HttpFeedFetcher($client);
        $result = $fetcher->fetch('https://broken.example.com');

        self::assertNull($result);
    }

    #[Test]
    public function it_returns_null_on_http_error(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willThrowException(
            new \Symfony\Component\HttpClient\Exception\ClientException($response),
        );

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $fetcher = new HttpFeedFetcher($client);
        $result = $fetcher->fetch('https://404.example.com');

        self::assertNull($result);
    }
}
