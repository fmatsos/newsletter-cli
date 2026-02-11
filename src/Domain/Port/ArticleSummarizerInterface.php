<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Domain\Port;

use Akawaka\Newsletter\Domain\Model\Article;

interface ArticleSummarizerInterface
{
    /**
     * Generates French summaries for articles.
     *
     * @param list<Article> $articles
     *
     * @return array<string, string> Map of article link => French summary
     */
    public function summarize(array $articles): array;
}
