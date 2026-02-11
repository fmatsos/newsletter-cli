<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Infrastructure\Summarizer;

use Akawaka\Newsletter\Domain\Model\Article;
use Akawaka\Newsletter\Domain\Port\ArticleSummarizerInterface;

final readonly class JsonBriefImporter implements ArticleSummarizerInterface
{
    public function __construct(
        private string $jsonPath,
    ) {
    }

    #[\Override]
    public function summarize(array $articles): array
    {
        if ([] === $articles) {
            return [];
        }

        $content = @file_get_contents($this->jsonPath);
        if (false === $content) {
            return $this->fallbackSummaries($articles);
        }

        try {
            /** @var array<string, string> $briefs */
            $briefs = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

            if (!\is_array($briefs)) {
                return $this->fallbackSummaries($articles);
            }

            return $briefs;
        } catch (\JsonException) {
            return $this->fallbackSummaries($articles);
        }
    }

    /**
     * @param list<Article> $articles
     *
     * @return array<string, string>
     */
    private function fallbackSummaries(array $articles): array
    {
        $summaries = [];
        foreach ($articles as $article) {
            $summaries[$article->link()] = $article->description();
        }

        return $summaries;
    }
}
