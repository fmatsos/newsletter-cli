<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Tests\Infrastructure\Publisher;

use Akawaka\Newsletter\Infrastructure\Publisher\GithubDiscussionPublisher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GithubDiscussionPublisher::class)]
final class GithubDiscussionPublisherTest extends TestCase
{
    #[Test]
    public function it_wraps_html_in_markdown_details(): void
    {
        $publisher = new GithubDiscussionPublisher('owner/repo', 'General');

        // Use reflection to test the private method
        $method = new \ReflectionMethod($publisher, 'wrapHtmlInMarkdown');

        $result = $method->invoke($publisher, '<p>Newsletter HTML</p>');

        self::assertStringContainsString('<!-- newsletter-digest -->', $result);
        self::assertStringContainsString('<details>', $result);
        self::assertStringContainsString('<p>Newsletter HTML</p>', $result);
        self::assertStringContainsString('</details>', $result);
        self::assertStringContainsString('GitHub Actions', $result);
    }

    #[Test]
    public function it_extracts_discussion_number_from_url(): void
    {
        $publisher = new GithubDiscussionPublisher('owner/repo');

        $method = new \ReflectionMethod($publisher, 'extractDiscussionNumber');

        self::assertSame('42', $method->invoke($publisher, 'https://github.com/owner/repo/discussions/42'));
        self::assertSame('123', $method->invoke($publisher, '123'));
        self::assertNull($method->invoke($publisher, 'not-a-url'));
    }
}
