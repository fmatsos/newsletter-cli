<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Tests\Infrastructure\Summarizer;

use Akawaka\Newsletter\Domain\Model\Article;
use Akawaka\Newsletter\Infrastructure\Summarizer\DescriptionFallbackSummarizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DescriptionFallbackSummarizer::class)]
final class DescriptionFallbackSummarizerTest extends TestCase
{
    #[Test]
    public function it_returns_empty_for_no_articles(): void
    {
        $summarizer = new DescriptionFallbackSummarizer();
        $result = $summarizer->summarize([]);

        self::assertSame([], $result);
    }

    #[Test]
    public function it_returns_short_descriptions_unchanged(): void
    {
        $articles = [
            new Article('Title', 'https://a.com/1', 'Short description', new \DateTimeImmutable(), 'Src', 'php'),
        ];

        $summarizer = new DescriptionFallbackSummarizer();
        $result = $summarizer->summarize($articles);

        self::assertSame('Short description', $result['https://a.com/1']);
    }

    #[Test]
    public function it_truncates_long_descriptions_with_ellipsis(): void
    {
        $longDescription = str_repeat('A', 250);
        $articles = [
            new Article('Title', 'https://a.com/1', $longDescription, new \DateTimeImmutable(), 'Src', 'php'),
        ];

        $summarizer = new DescriptionFallbackSummarizer();
        $result = $summarizer->summarize($articles);

        self::assertSame(mb_substr($longDescription, 0, 200) . '...', $result['https://a.com/1']);
        self::assertSame(203, mb_strlen($result['https://a.com/1']));
    }

    #[Test]
    public function it_does_not_truncate_description_at_exactly_max_length(): void
    {
        $exactDescription = str_repeat('B', 200);
        $articles = [
            new Article('Title', 'https://a.com/1', $exactDescription, new \DateTimeImmutable(), 'Src', 'php'),
        ];

        $summarizer = new DescriptionFallbackSummarizer();
        $result = $summarizer->summarize($articles);

        self::assertSame($exactDescription, $result['https://a.com/1']);
    }

    #[Test]
    public function it_handles_empty_descriptions(): void
    {
        $articles = [
            new Article('Title', 'https://a.com/1', '', new \DateTimeImmutable(), 'Src', 'php'),
        ];

        $summarizer = new DescriptionFallbackSummarizer();
        $result = $summarizer->summarize($articles);

        self::assertSame('', $result['https://a.com/1']);
    }

    #[Test]
    public function it_handles_multiple_articles(): void
    {
        $articles = [
            new Article('Title 1', 'https://a.com/1', 'Desc 1', new \DateTimeImmutable(), 'Src', 'php'),
            new Article('Title 2', 'https://a.com/2', str_repeat('C', 300), new \DateTimeImmutable(), 'Src', 'ai'),
        ];

        $summarizer = new DescriptionFallbackSummarizer();
        $result = $summarizer->summarize($articles);

        self::assertSame('Desc 1', $result['https://a.com/1']);
        self::assertStringEndsWith('...', $result['https://a.com/2']);
    }

    #[Test]
    public function it_handles_multibyte_characters(): void
    {
        $multibyteDescription = str_repeat('é', 250);
        $articles = [
            new Article('Title', 'https://a.com/1', $multibyteDescription, new \DateTimeImmutable(), 'Src', 'php'),
        ];

        $summarizer = new DescriptionFallbackSummarizer();
        $result = $summarizer->summarize($articles);

        self::assertSame(mb_substr($multibyteDescription, 0, 200) . '...', $result['https://a.com/1']);
    }
}
