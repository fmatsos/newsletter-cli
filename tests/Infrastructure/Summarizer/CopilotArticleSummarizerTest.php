<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Tests\Infrastructure\Summarizer;

use Akawaka\Newsletter\Domain\Model\Article;
use Akawaka\Newsletter\Infrastructure\Summarizer\CopilotArticleSummarizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CopilotArticleSummarizer::class)]
final class CopilotArticleSummarizerTest extends TestCase
{
    #[Test]
    public function it_returns_empty_for_no_articles(): void
    {
        $summarizer = new CopilotArticleSummarizer();
        $result = $summarizer->summarize([]);

        self::assertSame([], $result);
    }

    #[Test]
    public function it_builds_french_prompt_for_articles(): void
    {
        $summarizer = new CopilotArticleSummarizer();

        $method = new \ReflectionMethod($summarizer, 'buildPrompt');

        $articles = [
            new Article('Title 1', 'https://a.com/1', 'Desc 1', new \DateTimeImmutable(), 'Source 1', 'php'),
        ];

        $prompt = $method->invoke($summarizer, $articles);

        self::assertStringContainsString('rédacteur tech francophone', $prompt);
        self::assertStringContainsString('https://a.com/1', $prompt);
        self::assertStringContainsString('Title 1', $prompt);
        self::assertStringContainsString('JSON', $prompt);
    }

    #[Test]
    public function it_parses_valid_json_response(): void
    {
        $summarizer = new CopilotArticleSummarizer();

        $method = new \ReflectionMethod($summarizer, 'parseResponse');

        $articles = [
            new Article('Title', 'https://a.com/1', 'Desc', new \DateTimeImmutable(), 'Src', 'php'),
        ];

        $json = json_encode(['https://a.com/1' => 'Résumé en français'], \JSON_THROW_ON_ERROR);

        $result = $method->invoke($summarizer, $json, $articles);

        self::assertSame('Résumé en français', $result['https://a.com/1']);
    }

    #[Test]
    public function it_parses_json_wrapped_in_markdown_fences(): void
    {
        $summarizer = new CopilotArticleSummarizer();

        $method = new \ReflectionMethod($summarizer, 'parseResponse');

        $articles = [
            new Article('Title', 'https://a.com/1', 'Desc', new \DateTimeImmutable(), 'Src', 'php'),
        ];

        $response = "```json\n{\"https://a.com/1\": \"Résumé\"}\n```";

        $result = $method->invoke($summarizer, $response, $articles);

        self::assertSame('Résumé', $result['https://a.com/1']);
    }

    #[Test]
    public function it_extracts_json_from_surrounding_text(): void
    {
        $summarizer = new CopilotArticleSummarizer();

        $method = new \ReflectionMethod($summarizer, 'parseResponse');

        $articles = [
            new Article('Title', 'https://a.com/1', 'Desc', new \DateTimeImmutable(), 'Src', 'php'),
        ];

        $response = "Here are the summaries:\n{\"https://a.com/1\": \"Résumé\"}\nDone!";

        $result = $method->invoke($summarizer, $response, $articles);

        self::assertSame('Résumé', $result['https://a.com/1']);
    }

    #[Test]
    public function it_falls_back_on_invalid_json(): void
    {
        $summarizer = new CopilotArticleSummarizer();

        $method = new \ReflectionMethod($summarizer, 'parseResponse');

        $articles = [
            new Article('Title', 'https://a.com/1', 'Fallback desc', new \DateTimeImmutable(), 'Src', 'php'),
        ];

        $result = $method->invoke($summarizer, 'not json at all', $articles);

        self::assertSame('Fallback desc', $result['https://a.com/1']);
    }

    #[Test]
    public function it_falls_back_when_response_is_not_array(): void
    {
        $summarizer = new CopilotArticleSummarizer();

        $method = new \ReflectionMethod($summarizer, 'parseResponse');

        $articles = [
            new Article('Title', 'https://a.com/1', 'Fallback', new \DateTimeImmutable(), 'Src', 'php'),
        ];

        $result = $method->invoke($summarizer, '"just a string"', $articles);

        self::assertSame('Fallback', $result['https://a.com/1']);
    }
}
