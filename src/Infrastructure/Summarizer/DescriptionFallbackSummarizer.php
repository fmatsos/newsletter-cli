<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Infrastructure\Summarizer;

use Akawaka\Newsletter\Domain\Model\Article;
use Akawaka\Newsletter\Domain\Port\ArticleSummarizerInterface;

final readonly class DescriptionFallbackSummarizer implements ArticleSummarizerInterface
{
    private const int MAX_LENGTH = 200;

    #[\Override]
    public function summarize(array $articles): array
    {
        $summaries = [];
        foreach ($articles as $article) {
            $summaries[$article->link()] = $this->truncate($article->description());
        }

        return $summaries;
    }

    private function truncate(string $text): string
    {
        if ('' === $text) {
            return '';
        }

        if (mb_strlen($text) <= self::MAX_LENGTH) {
            return $text;
        }

        return mb_substr($text, 0, self::MAX_LENGTH) . '...';
    }
}
