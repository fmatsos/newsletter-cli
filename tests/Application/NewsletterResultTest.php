<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Tests\Application;

use Akawaka\Newsletter\Application\DTO\NewsletterResult;
use Akawaka\Newsletter\Domain\Model\Article;
use Akawaka\Newsletter\Domain\Model\Category;
use Akawaka\Newsletter\Domain\Model\DateWindow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NewsletterResult::class)]
final class NewsletterResultTest extends TestCase
{
    #[Test]
    public function it_computes_total_articles(): void
    {
        $result = $this->createResult([
            'php' => [$this->createArticle('A'), $this->createArticle('B')],
            'ai' => [$this->createArticle('C')],
        ]);

        self::assertSame(3, $result->totalArticles());
    }

    #[Test]
    public function it_returns_all_articles_flattened(): void
    {
        $result = $this->createResult([
            'php' => [$this->createArticle('A')],
            'ai' => [$this->createArticle('B')],
        ]);

        $all = $result->allArticles();
        self::assertCount(2, $all);
    }

    #[Test]
    public function it_returns_empty_for_unknown_category(): void
    {
        $result = $this->createResult(['php' => [$this->createArticle('A')]]);

        self::assertSame([], $result->articlesForCategory('nonexistent'));
    }

    #[Test]
    public function it_handles_empty_result(): void
    {
        $result = $this->createResult([]);

        self::assertSame(0, $result->totalArticles());
        self::assertSame([], $result->allArticles());
    }

    /**
     * @param array<string, list<Article>> $articlesByCategory
     */
    private function createResult(array $articlesByCategory): NewsletterResult
    {
        return new NewsletterResult(
            $articlesByCategory,
            [new Category('php', 'PHP', '#000', '#fff', '', '', '', '#000', '#fff', '#000')],
            new DateWindow(new \DateTimeImmutable(), new \DateTimeImmutable(), false),
            'Test',
            'Subject',
        );
    }

    private function createArticle(string $title): Article
    {
        return new Article($title, "https://example.com/{$title}", 'Desc', new \DateTimeImmutable(), 'Src', 'php');
    }
}
