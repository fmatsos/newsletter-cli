<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Tests\Infrastructure\Summarizer;

use Akawaka\Newsletter\Domain\Model\Article;
use Akawaka\Newsletter\Infrastructure\Summarizer\ClaudeArticleSummarizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(ClaudeArticleSummarizer::class)]
final class ClaudeArticleSummarizerTest extends TestCase
{
    #[Test]
    public function it_returns_empty_for_no_articles(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects(self::never())->method('request');

        $summarizer = new ClaudeArticleSummarizer($client, 'test-key');
        $result = $summarizer->summarize([]);

        self::assertSame([], $result);
    }

    #[Test]
    public function it_parses_valid_json_response(): void
    {
        $articles = [
            new Article('Title 1', 'https://a.com/1', 'Desc 1', new \DateTimeImmutable(), 'Src', 'php'),
            new Article('Title 2', 'https://a.com/2', 'Desc 2', new \DateTimeImmutable(), 'Src', 'ai'),
        ];

        $apiResponse = json_encode([
            'https://a.com/1' => 'Résumé de l\'article 1',
            'https://a.com/2' => 'Résumé de l\'article 2',
        ], \JSON_THROW_ON_ERROR);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'content' => [['type' => 'text', 'text' => $apiResponse]],
        ]);

        $client = $this->createMock(HttpClientInterface::class);
        $client->expects(self::once())
            ->method('request')
            ->with('POST', 'https://api.anthropic.com/v1/messages', self::callback(
                static fn (array $options): bool => 'test-key' === $options['headers']['x-api-key']
                    && isset($options['json']['model'])
                    && isset($options['json']['messages']),
            ))
            ->willReturn($response);

        $summarizer = new ClaudeArticleSummarizer($client, 'test-key');
        $result = $summarizer->summarize($articles);

        self::assertSame('Résumé de l\'article 1', $result['https://a.com/1']);
        self::assertSame('Résumé de l\'article 2', $result['https://a.com/2']);
    }

    #[Test]
    public function it_handles_json_wrapped_in_markdown_fences(): void
    {
        $articles = [
            new Article('T', 'https://a.com/1', 'D', new \DateTimeImmutable(), 'S', 'php'),
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'content' => [['type' => 'text', 'text' => "```json\n{\"https://a.com/1\": \"Résumé\"}\n```"]],
        ]);

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $summarizer = new ClaudeArticleSummarizer($client, 'key');
        $result = $summarizer->summarize($articles);

        self::assertSame('Résumé', $result['https://a.com/1']);
    }

    #[Test]
    public function it_falls_back_to_descriptions_on_api_error(): void
    {
        $articles = [
            new Article('Title', 'https://a.com/1', 'Fallback desc', new \DateTimeImmutable(), 'Src', 'php'),
        ];

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willThrowException(new \RuntimeException('API error'));

        $summarizer = new ClaudeArticleSummarizer($client, 'key');
        $result = $summarizer->summarize($articles);

        self::assertSame('Fallback desc', $result['https://a.com/1']);
    }

    #[Test]
    public function it_falls_back_on_invalid_json_response(): void
    {
        $articles = [
            new Article('Title', 'https://a.com/1', 'Fallback', new \DateTimeImmutable(), 'Src', 'php'),
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'content' => [['type' => 'text', 'text' => 'not json at all']],
        ]);

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $summarizer = new ClaudeArticleSummarizer($client, 'key');
        $result = $summarizer->summarize($articles);

        self::assertSame('Fallback', $result['https://a.com/1']);
    }

    #[Test]
    public function it_falls_back_when_response_is_not_an_array(): void
    {
        $articles = [
            new Article('Title', 'https://a.com/1', 'Fallback', new \DateTimeImmutable(), 'Src', 'php'),
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'content' => [['type' => 'text', 'text' => '"just a string"']],
        ]);

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $summarizer = new ClaudeArticleSummarizer($client, 'key');
        $result = $summarizer->summarize($articles);

        self::assertSame('Fallback', $result['https://a.com/1']);
    }

    #[Test]
    public function it_falls_back_when_content_array_is_empty(): void
    {
        $articles = [
            new Article('Title', 'https://a.com/1', 'Fallback', new \DateTimeImmutable(), 'Src', 'php'),
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'content' => [],
        ]);

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $summarizer = new ClaudeArticleSummarizer($client, 'key');
        $result = $summarizer->summarize($articles);

        self::assertSame('Fallback', $result['https://a.com/1']);
    }
}
