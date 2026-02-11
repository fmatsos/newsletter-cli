<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Domain\Service;

use Akawaka\Newsletter\Domain\Model\Article;
use Akawaka\Newsletter\Domain\Model\DateWindow;
use Akawaka\Newsletter\Domain\Port\FeedFetcherInterface;
use Akawaka\Newsletter\Domain\Port\FeedParserInterface;

final readonly class ArticleCollector
{
    public function __construct(
        private FeedFetcherInterface $fetcher,
        private FeedParserInterface $parser,
    ) {
    }

    /**
     * Collects articles from feeds, filters by date window, deduplicates and limits.
     *
     * @param list<string>         $feedUrls
     * @param array<string,string> $sourceNames Map of URL pattern => human-friendly name
     *
     * @return list<Article>
     */
    public function collect(
        array $feedUrls,
        string $categoryId,
        DateWindow $dateWindow,
        array $sourceNames,
        int $maxPerFeed = 5,
        int $maxPerCategory = 8,
    ): array {
        $allArticles = [];

        foreach ($feedUrls as $url) {
            $sourceName = $this->deriveSourceName($url, $sourceNames);

            $rawXml = $this->fetcher->fetch($url);
            if (null === $rawXml) {
                continue;
            }

            $parsed = $this->parser->parse($rawXml, $sourceName, $categoryId);
            $filtered = $this->filterByDateWindow($parsed, $dateWindow);
            $sorted = $this->sortByDateDesc($filtered);
            $limited = \array_slice($sorted, 0, $maxPerFeed);

            $allArticles = [...$allArticles, ...$limited];
        }

        $deduped = $this->deduplicate($allArticles);
        $sorted = $this->sortByDateDesc($deduped);

        return \array_slice($sorted, 0, $maxPerCategory);
    }

    /**
     * @param list<Article> $articles
     *
     * @return list<Article>
     */
    private function filterByDateWindow(array $articles, DateWindow $dateWindow): array
    {
        return array_values(array_filter(
            $articles,
            static fn (Article $article): bool => $article->hasDate() && $dateWindow->contains($article->date()),
        ));
    }

    /**
     * @param list<Article> $articles
     *
     * @return list<Article>
     */
    private function sortByDateDesc(array $articles): array
    {
        $sorted = $articles;
        usort(
            $sorted,
            static fn (Article $a, Article $b): int => ($b->date() ?? new \DateTimeImmutable('1970-01-01')) <=> ($a->date() ?? new \DateTimeImmutable('1970-01-01')),
        );

        return $sorted;
    }

    /**
     * @param list<Article> $articles
     *
     * @return list<Article>
     */
    private function deduplicate(array $articles): array
    {
        $seen = [];
        $result = [];

        foreach ($articles as $article) {
            if (isset($seen[$article->link()])) {
                continue;
            }

            $seen[$article->link()] = true;
            $result[] = $article;
        }

        return $result;
    }

    /**
     * @param array<string,string> $sourceNames
     */
    private function deriveSourceName(string $url, array $sourceNames): string
    {
        $clean = preg_replace('#^https?://#', '', $url);
        $clean = rtrim($clean, '/');

        foreach ($sourceNames as $pattern => $name) {
            if (str_contains($clean, $pattern)) {
                return $name;
            }
        }

        $domain = explode('/', $clean)[0];
        $domain = str_replace('www.', '', $domain);

        return ucfirst(explode('.', $domain)[0]);
    }
}
