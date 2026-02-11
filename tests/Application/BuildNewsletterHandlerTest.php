<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Tests\Application;

use Akawaka\Newsletter\Application\BuildNewsletterHandler;
use Akawaka\Newsletter\Application\DTO\NewsletterConfiguration;
use Akawaka\Newsletter\Domain\Model\Article;
use Akawaka\Newsletter\Domain\Model\Category;
use Akawaka\Newsletter\Domain\Model\DateWindow;
use Akawaka\Newsletter\Domain\Port\ArticleSummarizerInterface;
use Akawaka\Newsletter\Domain\Port\NewsletterPublisherInterface;
use Akawaka\Newsletter\Domain\Port\NewsletterRendererInterface;
use Akawaka\Newsletter\Domain\Service\ArticleCollector;
use Akawaka\Newsletter\Domain\Service\DateWindowCalculator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BuildNewsletterHandler::class)]
final class BuildNewsletterHandlerTest extends TestCase
{
    private DateWindowCalculator&\PHPUnit\Framework\MockObject\MockObject $dateWindowCalculator;
    private ArticleCollector&\PHPUnit\Framework\MockObject\MockObject $articleCollector;
    private ArticleSummarizerInterface&\PHPUnit\Framework\MockObject\MockObject $summarizer;
    private NewsletterRendererInterface&\PHPUnit\Framework\MockObject\MockObject $renderer;
    private NewsletterPublisherInterface&\PHPUnit\Framework\MockObject\MockObject $publisher;
    private BuildNewsletterHandler $handler;

    protected function setUp(): void
    {
        $this->dateWindowCalculator = $this->createMock(DateWindowCalculator::class);
        $this->articleCollector = $this->createMock(ArticleCollector::class);
        $this->summarizer = $this->createMock(ArticleSummarizerInterface::class);
        $this->renderer = $this->createMock(NewsletterRendererInterface::class);
        $this->publisher = $this->createMock(NewsletterPublisherInterface::class);

        $this->handler = new BuildNewsletterHandler(
            $this->dateWindowCalculator,
            $this->articleCollector,
            $this->summarizer,
            $this->renderer,
            $this->publisher,
        );
    }

    #[Test]
    public function it_builds_and_publishes_newsletter(): void
    {
        $dateWindow = new DateWindow(
            new \DateTimeImmutable('2026-02-09T00:00:00Z'),
            new \DateTimeImmutable('2026-02-10T10:00:00Z'),
            false,
        );

        $this->dateWindowCalculator->method('compute')->willReturn($dateWindow);

        $article = new Article('Title', 'https://a.com/1', 'Desc', new \DateTimeImmutable(), 'Src', 'php');

        $this->articleCollector->method('collect')->willReturn([$article]);

        $this->summarizer->method('summarize')->willReturn([
            'https://a.com/1' => 'Résumé en français',
        ]);

        $this->renderer->method('render')->willReturn('<html>newsletter</html>');

        $this->publisher->expects(self::once())
            ->method('publish')
            ->with(
                self::stringContains('Akawaka Veille Tech'),
                '<html>newsletter</html>',
                'newsletter',
            )
            ->willReturn('https://github.com/repo/discussions/1');

        $config = $this->createConfig();
        $result = $this->handler->handle($config);

        self::assertStringContainsString('Akawaka Veille Tech', $result->subject());
        self::assertSame(1, $result->totalArticles());
        self::assertSame('Résumé en français', $result->articlesForCategory('php')[0]->summary());
    }

    #[Test]
    public function it_handles_empty_feeds(): void
    {
        $dateWindow = new DateWindow(
            new \DateTimeImmutable('2026-02-09T00:00:00Z'),
            new \DateTimeImmutable('2026-02-10T10:00:00Z'),
            false,
        );

        $this->dateWindowCalculator->method('compute')->willReturn($dateWindow);
        $this->articleCollector->method('collect')->willReturn([]);
        $this->summarizer->expects(self::never())->method('summarize');
        $this->renderer->method('render')->willReturn('<html>empty</html>');
        $this->publisher->expects(self::once())->method('publish');

        $config = $this->createConfig();
        $result = $this->handler->handle($config);

        self::assertSame(0, $result->totalArticles());
    }

    #[Test]
    public function it_skips_categories_without_feeds(): void
    {
        $dateWindow = new DateWindow(
            new \DateTimeImmutable('2026-02-09T00:00:00Z'),
            new \DateTimeImmutable('2026-02-10T10:00:00Z'),
            false,
        );

        $this->dateWindowCalculator->method('compute')->willReturn($dateWindow);
        $this->articleCollector->expects(self::never())->method('collect');
        $this->renderer->method('render')->willReturn('<html/>');
        $this->publisher->method('publish')->willReturn('url');

        $config = new NewsletterConfiguration(
            recipients: [],
            categories: [new Category('empty', 'Empty', '#000', '#fff', '', '', '', '#000', '#fff', '#000')],
            feedsByCategoryId: [],
            sourceNames: [],
        );

        $result = $this->handler->handle($config);

        self::assertSame(0, $result->totalArticles());
    }

    #[Test]
    public function it_preserves_articles_without_summaries(): void
    {
        $dateWindow = new DateWindow(
            new \DateTimeImmutable('2026-02-09T00:00:00Z'),
            new \DateTimeImmutable('2026-02-10T10:00:00Z'),
            false,
        );

        $this->dateWindowCalculator->method('compute')->willReturn($dateWindow);

        $article = new Article('Title', 'https://a.com/1', 'Original desc', new \DateTimeImmutable(), 'Src', 'php');
        $this->articleCollector->method('collect')->willReturn([$article]);

        // Summarizer returns nothing for this article
        $this->summarizer->method('summarize')->willReturn([]);
        $this->renderer->method('render')->willReturn('<html/>');
        $this->publisher->method('publish')->willReturn('url');

        $config = $this->createConfig();
        $result = $this->handler->handle($config);

        // Article should still have empty summary (not overwritten)
        self::assertSame('', $result->articlesForCategory('php')[0]->summary());
    }

    #[Test]
    #[DataProvider('frenchDateProvider')]
    public function it_formats_dates_in_french(string $dateStr, string $expected): void
    {
        $date = new \DateTimeImmutable($dateStr);
        $result = BuildNewsletterHandler::formatDateFrench($date);

        self::assertSame($expected, $result);
    }

    /** @return iterable<string, array{string, string}> */
    public static function frenchDateProvider(): iterable
    {
        yield 'monday' => ['2026-02-09T10:00:00Z', 'Lundi 9 février 2026'];
        yield 'tuesday' => ['2026-02-10T10:00:00Z', 'Mardi 10 février 2026'];
        yield 'wednesday' => ['2026-02-11T10:00:00Z', 'Mercredi 11 février 2026'];
        yield 'january' => ['2026-01-15T10:00:00Z', 'Jeudi 15 janvier 2026'];
        yield 'december' => ['2025-12-25T10:00:00Z', 'Jeudi 25 décembre 2025'];
    }

    #[Test]
    #[DataProvider('frenchDateShortProvider')]
    public function it_formats_short_dates_in_french(string $dateStr, string $expected): void
    {
        $date = new \DateTimeImmutable($dateStr);
        $result = BuildNewsletterHandler::formatDateFrenchShort($date);

        self::assertSame($expected, $result);
    }

    /** @return iterable<string, array{string, string}> */
    public static function frenchDateShortProvider(): iterable
    {
        yield 'february' => ['2026-02-10T10:00:00Z', '10 février 2026'];
        yield 'august' => ['2026-08-01T10:00:00Z', '1 août 2026'];
    }

    private function createConfig(): NewsletterConfiguration
    {
        return new NewsletterConfiguration(
            recipients: ['test@example.com'],
            categories: [
                new Category('php', 'PHP', '#4B82E8', '#fff', '', '', '', '#4B82E8', '#E9F0FD', '#2558CC'),
            ],
            feedsByCategoryId: [
                'php' => ['https://feed.example.com/rss'],
            ],
            sourceNames: [],
        );
    }
}
