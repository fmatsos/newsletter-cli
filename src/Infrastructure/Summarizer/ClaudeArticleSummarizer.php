<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Infrastructure\Summarizer;

use Akawaka\Newsletter\Domain\Model\Article;
use Akawaka\Newsletter\Domain\Port\ArticleSummarizerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ClaudeArticleSummarizer implements ArticleSummarizerInterface
{
    private const string API_URL = 'https://api.anthropic.com/v1/messages';
    private const string MODEL = 'claude-sonnet-4-20250514';
    private const int MAX_TOKENS = 4096;

    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter]
        private string $apiKey,
    ) {
    }

    #[\Override]
    public function summarize(array $articles): array
    {
        if ([] === $articles) {
            return [];
        }

        $articlesPayload = array_map(
            static fn (Article $article): array => [
                'url' => $article->link(),
                'title' => $article->title(),
                'description' => mb_substr($article->description(), 0, 300),
                'source' => $article->source(),
            ],
            $articles,
        );

        $prompt = $this->buildPrompt($articlesPayload);

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => self::MODEL,
                    'max_tokens' => self::MAX_TOKENS,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ],
                'timeout' => 60,
            ]);

            $data = $response->toArray();
            $text = $data['content'][0]['text'] ?? '';

            return $this->parseResponse($text, $articles);
        } catch (\Throwable) {
            return $this->fallbackSummaries($articles);
        }
    }

    /**
     * @param list<array{url: string, title: string, description: string, source: string}> $articles
     */
    private function buildPrompt(array $articles): string
    {
        $articlesList = '';
        foreach ($articles as $index => $article) {
            $articlesList .= sprintf(
                "\n---\nArticle %d:\nURL: %s\nTitre: %s\nSource: %s\nDescription: %s\n",
                $index + 1,
                $article['url'],
                $article['title'],
                $article['source'],
                $article['description'],
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
