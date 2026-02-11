<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Tests\Domain\Service;

use Akawaka\Newsletter\Domain\Model\Article;
use Akawaka\Newsletter\Domain\Model\DateWindow;
use Akawaka\Newsletter\Domain\Port\FeedFetcherInterface;
use Akawaka\Newsletter\Domain\Port\FeedParserInterface;
use Akawaka\Newsletter\Domain\Service\ArticleCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArticleCollector::class)]
final class ArticleCollectorTest extends TestCase
{
    private FeedFetcherInterface&\PHPUnit\Framework\MockObject\MockObject $fetcher;
    private FeedParserInterface&\PHPUnit\Framework\MockObject\MockObject $parser;
    private ArticleCollector $collector;
    private DateWindow $dateWindow;

    protected function setUp(): void
    {
        $this->fetcher = $this->createMock(FeedFetcherInterface::class);
        $this->parser = $this->createMock(FeedParserInterface::class);
        $this->collector = new ArticleCollector($this->fetcher, $this->parser);

        $this->dateWindow = new DateWindow(
            new \DateTimeImmutable('2026-02-09T00:00:00Z'),
            new \DateTimeImmutable('2026-02-10T23:59:59Z'),
            false,
        );
    }

    #[Test]
    public function it_collects_articles_from_feeds(): void
    {
        $xml = '<rss>...</rss>';

        $this->fetcher->method('fetch')->willReturn($xml);
        $this->parser->method('parse')->willReturn([
            new Article('A1', 'https://a.com/1', 'Desc', new \DateTimeImmutable('2026-02-10T08:00:00Z'), 'Src', 'php'),
        ]);

        $result = $this->collector->collect(
            ['https://feed.example.com/rss'],
            'php',
            $this->dateWindow,
            [],
        );

        self::assertCount(1, $result);
        self::assertSame('A1', $result[0]->title());
    }

    #[Test]
    public function it_skips_failed_feeds(): void
    {
        $this->fetcher->method('fetch')->willReturn(null);
        $this->parser->expects(self::never())->method('parse');

        $result = $this->collector->collect(
            ['https://broken.example.com/rss'],
            'php',
            $this->dateWindow,
            [],
        );

        self::assertSame([], $result);
    }

    #[Test]
    public function it_filters_articles_outside_date_window(): void
    {
        $this->fetcher->method('fetch')->willReturn('<xml/>');
        $this->parser->method('parse')->willReturn([
            new Article('Old', 'https://a.com/old', '', new \DateTimeImmutable('2026-01-01T00:00:00Z'), 'Src', 'php'),
            new Article('Current', 'https://a.com/current', '', new \DateTimeImmutable('2026-02-10T10:00:00Z'), 'Src', 'php'),
        ]);

        $result = $this->collector->collect(['https://feed.example.com'], 'php', $this->dateWindow, []);

        self::assertCount(1, $result);
        self::assertSame('Current', $result[0]->title());
    }

    #[Test]
    public function it_filters_articles_without_date(): void
    {
        $this->fetcher->method('fetch')->willReturn('<xml/>');
        $this->parser->method('parse')->willReturn([
            new Article('No date', 'https://a.com/nodate', '', null, 'Src', 'php'),
        ]);

        $result = $this->collector->collect(['https://feed.example.com'], 'php', $this->dateWindow, []);

        self::assertSame([], $result);
    }

    #[Test]
    public function it_deduplicates_by_link(): void
    {
        $this->fetcher->method('fetch')->willReturn('<xml/>');
        $this->parser->method('parse')->willReturn([
            new Article('A1', 'https://a.com/same', '', new \DateTimeImmutable('2026-02-10T08:00:00Z'), 'Feed1', 'php'),
            new Article('A1 dup', 'https://a.com/same', '', new \DateTimeImmutable('2026-02-10T09:00:00Z'), 'Feed2', 'php'),
        ]);

        $result = $this->collector->collect(['https://feed1.com', 'https://feed2.com'], 'php', $this->dateWindow, []);

        self::assertCount(1, $result);
    }

    #[Test]
    public function it_limits_per_feed_and_per_category(): void
    {
        $articles = [];
        for ($i = 0; $i < 20; ++$i) {
            $articles[] = new Article(
                "Art {$i}",
                "https://a.com/{$i}",
                '',
                new \DateTimeImmutable(sprintf('2026-02-10T%02d:00:00Z', $i % 24)),
                'Src',
                'php',
            );
        }

        $this->fetcher->method('fetch')->willReturn('<xml/>');
        $this->parser->method('parse')->willReturn($articles);

        $result = $this->collector->collect(
            ['https://feed.example.com'],
            'php',
            $this->dateWindow,
            [],
            maxPerFeed: 3,
            maxPerCategory: 2,
        );

        self::assertCount(2, $result);
    }

    #[Test]
    public function it_sorts_by_date_descending(): void
    {
        $this->fetcher->method('fetch')->willReturn('<xml/>');
        $this->parser->method('parse')->willReturn([
            new Article('Old', 'https://a.com/1', '', new \DateTimeImmutable('2026-02-09T08:00:00Z'), 'Src', 'php'),
            new Article('New', 'https://a.com/2', '', new \DateTimeImmutable('2026-02-10T20:00:00Z'), 'Src', 'php'),
            new Article('Mid', 'https://a.com/3', '', new \DateTimeImmutable('2026-02-10T10:00:00Z'), 'Src', 'php'),
        ]);

        $result = $this->collector->collect(['https://feed.example.com'], 'php', $this->dateWindow, []);

        self::assertSame('New', $result[0]->title());
        self::assertSame('Mid', $result[1]->title());
        self::assertSame('Old', $result[2]->title());
    }

    #[Test]
    public function it_derives_source_name_from_map(): void
    {
        $this->fetcher->method('fetch')->willReturn('<xml/>');
        $this->parser->expects(self::once())
            ->method('parse')
            ->with('<xml/>', 'Symfony Blog', 'php')
            ->willReturn([]);

        $this->collector->collect(
            ['https://feeds.feedburner.com/symfony/blog'],
            'php',
            $this->dateWindow,
            ['feeds.feedburner.com/symfony/blog' => 'Symfony Blog'],
        );
    }

    #[Test]
    public function it_derives_source_name_from_domain_when_not_mapped(): void
    {
        $this->fetcher->method('fetch')->willReturn('<xml/>');
        $this->parser->expects(self::once())
            ->method('parse')
            ->with('<xml/>', 'Unknown', 'php')
            ->willReturn([]);

        $this->collector->collect(
            ['https://unknown.example.com/feed'],
            'php',
            $this->dateWindow,
            [],
        );
    }

    #[Test]
    public function it_handles_multiple_feeds(): void
    {
        $this->fetcher->method('fetch')
            ->willReturnMap([
                ['https://feed1.com', '<xml1/>'],
                ['https://feed2.com', '<xml2/>'],
            ]);

        $this->parser->method('parse')
            ->willReturnCallback(function (string $xml, string $source, string $catId): array {
                if ('<xml1/>' === $xml) {
                    return [new Article('From feed1', 'https://a.com/1', '', new \DateTimeImmutable('2026-02-10T10:00:00Z'), $source, $catId)];
                }

                return [new Article('From feed2', 'https://a.com/2', '', new \DateTimeImmutable('2026-02-10T11:00:00Z'), $source, $catId)];
            });

        $result = $this->collector->collect(
            ['https://feed1.com', 'https://feed2.com'],
            'php',
            $this->dateWindow,
            [],
        );

        self::assertCount(2, $result);
    }
}
