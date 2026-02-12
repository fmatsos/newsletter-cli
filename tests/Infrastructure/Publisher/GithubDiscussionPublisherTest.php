<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Tests\Infrastructure\Publisher;

use Akawaka\Newsletter\Infrastructure\Publisher\GithubDiscussionPublisher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(GithubDiscussionPublisher::class)]
final class GithubDiscussionPublisherTest extends TestCase
{
    #[Test]
    public function it_wraps_html_in_markdown_details(): void
    {
        $publisher = new GithubDiscussionPublisher(
            new MockHttpClient(),
            'owner/repo',
            'General',
            'token',
        );

        $method = new \ReflectionMethod($publisher, 'wrapHtmlInMarkdown');

        $result = $method->invoke($publisher, '<p>Newsletter HTML</p>');

        self::assertStringContainsString('<!-- newsletter-digest -->', $result);
        self::assertStringContainsString('<details>', $result);
        self::assertStringContainsString('<p>Newsletter HTML</p>', $result);
        self::assertStringContainsString('</details>', $result);
        self::assertStringContainsString('GitHub Actions', $result);
    }

    #[Test]
    public function it_creates_discussion_via_graphql(): void
    {
        $responseBodies = [
            json_encode([
                'data' => [
                    'repository' => [
                        'id' => 'repo-id',
                        'discussionCategory' => ['id' => 'category-id'],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'data' => [
                    'repository' => [
                        'label' => null,
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'data' => [
                    'createLabel' => [
                        'label' => ['id' => 'label-id'],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'data' => [
                    'createDiscussion' => [
                        'discussion' => [
                            'id' => 'discussion-id',
                            'url' => 'https://github.com/owner/repo/discussions/314159',
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'data' => [
                    'addLabelsToLabelable' => [
                        'clientMutationId' => 'mutation-id',
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
        ];

        $callCount = 0;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$responseBodies, &$callCount) {
            ++$callCount;
            $body = array_shift($responseBodies);

            if (!is_string($body)) {
                throw new \RuntimeException('Unexpected request for GitHub GraphQL.');
            }

            return new MockResponse($body, ['http_code' => 200]);
        });

        $publisher = new GithubDiscussionPublisher(
            $httpClient,
            'owner/repo',
            'General',
            'token',
        );

        $discussionUrl = $publisher->publish('Title', '<p>Newsletter HTML</p>', 'newsletter');

        self::assertSame('https://github.com/owner/repo/discussions/314159', $discussionUrl);
        self::assertSame(5, $callCount);
    }
}
