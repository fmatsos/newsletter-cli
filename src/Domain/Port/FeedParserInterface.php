<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Domain\Port;

use Akawaka\Newsletter\Domain\Model\Article;

interface FeedParserInterface
{
    /**
     * Parses raw XML feed content into Article objects.
     *
     * @return list<Article>
     */
    public function parse(string $xmlContent, string $sourceName, string $categoryId): array;
}
