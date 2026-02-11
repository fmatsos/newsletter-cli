<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Domain\Service;

use Akawaka\Newsletter\Domain\Model\Article;
use Akawaka\Newsletter\Domain\Model\DateWindow;
use Akawaka\Newsletter\Domain\Model\FeedSource;
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
     * @param list<FeedSource> $feedSources
     *
     * @return list<Article>
     */
    public function collect(
        array $feedSources,
        string $categoryId,
        DateWindow $dateWindow,
        int $maxPerFeed = 5,
        int $maxPerCategory = 8,
    ): array {
        $allArticles = [];

        foreach ($feedSources as $feedSource) {
            $url = $feedSource->url();
            $sourceName = $feedSource->name() ?: $this->deriveSourceNameFromUrl($url);

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
     */
    private function deriveSourceNameFromUrl(string $url): string
    {
        $clean = preg_replace('#^https?://#', '', $url);
        $clean = rtrim($clean, '/');

        $domain = explode('/', $clean)[0];
        $domain = str_replace('www.', '', $domain);

        return ucfirst(explode('.', $domain)[0]);
    }
}
