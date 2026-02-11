<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Application\DTO;

use Akawaka\Newsletter\Domain\Model\Category;

final readonly class NewsletterConfiguration
{
    /**
     * @param list<string>                         $recipients
     * @param list<Category>                       $categories
     * @param array<string, list<string>>           $feedsByCategoryId  Map of category_id => feed URLs
     * @param array<string, string>                $sourceNames        Map of URL pattern => human-friendly name
     */
    public function __construct(
        private array $recipients,
        private array $categories,
        private array $feedsByCategoryId,
        private array $sourceNames,
        private int $maxArticlesPerFeed = 5,
        private int $maxArticlesPerCategory = 8,
    ) {
    }

    /** @return list<string> */
    public function recipients(): array
    {
        return $this->recipients;
    }

    /** @return list<Category> */
    public function categories(): array
    {
        return $this->categories;
    }

    /** @return list<string> */
    public function feedsForCategory(string $categoryId): array
    {
        return $this->feedsByCategoryId[$categoryId] ?? [];
    }

    /** @return array<string, string> */
    public function sourceNames(): array
    {
        return $this->sourceNames;
    }

    public function maxArticlesPerFeed(): int
    {
        return $this->maxArticlesPerFeed;
    }

    public function maxArticlesPerCategory(): int
    {
        return $this->maxArticlesPerCategory;
    }

    public function findCategory(string $id): ?Category
    {
        foreach ($this->categories as $category) {
            if ($category->id() === $id) {
                return $category;
            }
        }

        return null;
    }
}
