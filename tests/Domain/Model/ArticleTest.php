<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Tests\Domain\Model;

use Akawaka\Newsletter\Domain\Model\Article;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Article::class)]
final class ArticleTest extends TestCase
{
    #[Test]
    public function it_exposes_all_properties(): void
    {
        $date = new \DateTimeImmutable('2026-02-10T10:00:00Z');

        $article = new Article(
            title: 'Test Title',
            link: 'https://example.com/article',
            description: 'A description',
            date: $date,
            source: 'Example Blog',
            categoryId: 'php',
        );

        self::assertSame('Test Title', $article->title());
        self::assertSame('https://example.com/article', $article->link());
        self::assertSame('A description', $article->description());
        self::assertSame($date, $article->date());
        self::assertSame('Example Blog', $article->source());
        self::assertSame('php', $article->categoryId());
        self::assertSame('', $article->summary());
        self::assertTrue($article->hasDate());
    }

    #[Test]
    public function it_handles_null_date(): void
    {
        $article = new Article(
            title: 'No Date',
            link: 'https://example.com',
            description: '',
            date: null,
            source: 'Source',
            categoryId: 'ai',
        );

        self::assertNull($article->date());
        self::assertFalse($article->hasDate());
    }

    #[Test]
    public function with_summary_returns_new_instance(): void
    {
        $article = new Article(
            title: 'Title',
            link: 'https://example.com',
            description: 'Desc',
            date: new \DateTimeImmutable(),
            source: 'Src',
            categoryId: 'tools',
        );

        $withSummary = $article->withSummary('Un résumé en français');

        self::assertSame('', $article->summary());
        self::assertSame('Un résumé en français', $withSummary->summary());
        self::assertSame($article->title(), $withSummary->title());
        self::assertNotSame($article, $withSummary);
    }
}
