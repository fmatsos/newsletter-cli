<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Infrastructure\Summarizer;

use Akawaka\Newsletter\Domain\Model\Article;
use Akawaka\Newsletter\Domain\Port\ArticleSummarizerInterface;
use Symfony\Component\Process\Process;

final readonly class CopilotArticleSummarizer implements ArticleSummarizerInterface
{
    private const string MODEL = 'gpt-4.1';
    private const int TIMEOUT = 120;

    #[\Override]
    public function summarize(array $articles): array
    {
        if ([] === $articles) {
            return [];
        }

        $prompt = $this->buildPrompt($articles);

        try {
            $process = new Process(['gh', 'models', 'run', self::MODEL]);
            $process->setInput($prompt);
            $process->setTimeout(self::TIMEOUT);
            $process->run();

            if (!$process->isSuccessful()) {
                return $this->fallbackSummaries($articles);
            }

            return $this->parseResponse($process->getOutput(), $articles);
        } catch (\Throwable) {
            return $this->fallbackSummaries($articles);
        }
    }

    /**
     * @param list<Article> $articles
     */
    private function buildPrompt(array $articles): string
    {
        $articlesList = '';
        foreach ($articles as $index => $article) {
            $articlesList .= sprintf(
                "\n---\nArticle %d:\nURL: %s\nTitre: %s\nSource: %s\nDescription: %s\n",
                $index + 1,
                $article->link(),
                $article->title(),
                $article->source(),
                mb_substr($article->description(), 0, 300),
            );
        }

        return <<<PROMPT
            Tu es un rédacteur tech francophone. Pour chaque article ci-dessous, rédige un résumé concis en français (2-3 phrases max, ~50 mots).

            Le résumé doit :
            - Être informatif et factuel
            - Être en français courant, professionnel
            - Expliquer ce que l'article annonce ou traite
            - Ne pas dépasser 3 phrases

            Réponds UNIQUEMENT au format JSON (sans balises markdown) :
            {
              "<url_article_1>": "résumé en français",
              "<url_article_2>": "résumé en français"
            }

            Articles :{$articlesList}
            PROMPT;
    }

    /**
     * @param list<Article> $articles
     *
     * @return array<string, string>
     */
    private function parseResponse(string $responseText, array $articles): array
    {
        $cleaned = trim($responseText);
        $cleaned = (string) preg_replace('/^```json\s*/s', '', $cleaned);
        $cleaned = (string) preg_replace('/\s*```$/s', '', $cleaned);

        // Extract JSON object from response (model may include surrounding text)
        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $cleaned, $matches)) {
            $cleaned = $matches[0];
        }

        try {
            /** @var array<string, string> $summaries */
            $summaries = json_decode($cleaned, true, 512, \JSON_THROW_ON_ERROR);

            if (!\is_array($summaries)) {
                return $this->fallbackSummaries($articles);
            }

            return $summaries;
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
