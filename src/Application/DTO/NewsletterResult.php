<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Application\DTO;

use Akawaka\Newsletter\Domain\Model\Article;
use Akawaka\Newsletter\Domain\Model\Category;
use Akawaka\Newsletter\Domain\Model\DateWindow;

final readonly class NewsletterResult
{
    /**
     * @param array<string, list<Article>> $articlesByCategory Map of category_id => articles
     * @param list<Category>               $categories
     */
    public function __construct(
        private array $articlesByCategory,
        private array $categories,
        private DateWindow $dateWindow,
        private string $dateLabel,
        private string $subject,
    ) {
    }

    /** @return array<string, list<Article>> */
    public function articlesByCategory(): array
    {
        return $this->articlesByCategory;
    }

    /** @return list<Category> */
    public function categories(): array
    {
        return $this->categories;
    }

    public function dateWindow(): DateWindow
    {
        return $this->dateWindow;
    }

    public function dateLabel(): string
    {
        return $this->dateLabel;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    /** @return list<Article> */
    public function articlesForCategory(string $categoryId): array
    {
        return $this->articlesByCategory[$categoryId] ?? [];
    }

    public function totalArticles(): int
    {
        $total = 0;
        foreach ($this->articlesByCategory as $articles) {
            $total += \count($articles);
        }

        return $total;
    }

    /** @return list<Article> */
    public function allArticles(): array
    {
        $all = [];
        foreach ($this->articlesByCategory as $articles) {
            $all = [...$all, ...$articles];
        }

        return $all;
    }
}
