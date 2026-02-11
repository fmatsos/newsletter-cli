<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Tests\Infrastructure\Feed;

use Akawaka\Newsletter\Infrastructure\Feed\XmlFeedParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(XmlFeedParser::class)]
final class XmlFeedParserTest extends TestCase
{
    private XmlFeedParser $parser;

    protected function setUp(): void
    {
        $this->parser = new XmlFeedParser();
    }

    #[Test]
    public function it_parses_rss_feed(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0">
                <channel>
                    <item>
                        <title>Symfony 7.2 released</title>
                        <link>https://symfony.com/blog/symfony-7-2</link>
                        <description>&lt;p&gt;New features in Symfony 7.2&lt;/p&gt;</description>
                        <pubDate>Mon, 10 Feb 2026 08:00:00 +0000</pubDate>
                    </item>
                    <item>
                        <title>Another article</title>
                        <link>https://symfony.com/blog/another</link>
                        <description>Plain text description</description>
                        <pubDate>Sun, 09 Feb 2026 12:00:00 +0000</pubDate>
                    </item>
                </channel>
            </rss>
            XML;

        $articles = $this->parser->parse($xml, 'Symfony Blog', 'php');

        self::assertCount(2, $articles);
        self::assertSame('Symfony 7.2 released', $articles[0]->title());
        self::assertSame('https://symfony.com/blog/symfony-7-2', $articles[0]->link());
        self::assertSame('New features in Symfony 7.2', $articles[0]->description());
        self::assertSame('Symfony Blog', $articles[0]->source());
        self::assertSame('php', $articles[0]->categoryId());
        self::assertNotNull($articles[0]->date());
    }

    #[Test]
    public function it_parses_atom_feed(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <feed xmlns="http://www.w3.org/2005/Atom">
                <entry>
                    <title>Claude 4 Update</title>
                    <link href="https://anthropic.com/news/claude-4"/>
                    <summary>Major AI advancement</summary>
                    <updated>2026-02-10T10:00:00Z</updated>
                </entry>
            </feed>
            XML;

        $articles = $this->parser->parse($xml, 'Anthropic News', 'ai');

        self::assertCount(1, $articles);
        self::assertSame('Claude 4 Update', $articles[0]->title());
        self::assertSame('https://anthropic.com/news/claude-4', $articles[0]->link());
        self::assertSame('Major AI advancement', $articles[0]->description());
        self::assertSame('Anthropic News', $articles[0]->source());
        self::assertSame('ai', $articles[0]->categoryId());
    }

    #[Test]
    public function it_parses_atom_with_content_instead_of_summary(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <feed xmlns="http://www.w3.org/2005/Atom">
                <entry>
                    <title>Post</title>
                    <link href="https://example.com/post"/>
                    <content>Full content here</content>
                    <published>2026-02-10T10:00:00Z</published>
                </entry>
            </feed>
            XML;

        $articles = $this->parser->parse($xml, 'Source', 'tools');

        self::assertCount(1, $articles);
        self::assertSame('Full content here', $articles[0]->description());
    }

    #[Test]
    public function it_returns_empty_for_malformed_xml(): void
    {
        $articles = $this->parser->parse('not xml at all', 'Source', 'php');

        self::assertSame([], $articles);
    }

    #[Test]
    public function it_returns_empty_for_empty_feed(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0">
                <channel>
                    <title>Empty Feed</title>
                </channel>
            </rss>
            XML;

        $articles = $this->parser->parse($xml, 'Source', 'php');

        self::assertSame([], $articles);
    }

    #[Test]
    public function it_strips_html_from_description(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0">
                <channel>
                    <item>
                        <title>Title</title>
                        <link>https://example.com</link>
                        <description>&lt;p&gt;Hello &lt;strong&gt;world&lt;/strong&gt;&lt;/p&gt;</description>
                        <pubDate>Mon, 10 Feb 2026 08:00:00 +0000</pubDate>
                    </item>
                </channel>
            </rss>
            XML;

        $articles = $this->parser->parse($xml, 'Src', 'php');

        self::assertSame('Hello world', $articles[0]->description());
    }

    #[Test]
    public function it_truncates_long_descriptions(): void
    {
        $longDesc = str_repeat('A', 1000);
        $xml = sprintf(
            '<?xml version="1.0"?><rss version="2.0"><channel><item><title>T</title><link>https://x.com</link><description>%s</description><pubDate>Mon, 10 Feb 2026 08:00:00 +0000</pubDate></item></channel></rss>',
            $longDesc,
        );

        $articles = $this->parser->parse($xml, 'Src', 'php');

        self::assertLessThanOrEqual(500, mb_strlen($articles[0]->description()));
    }

    #[Test]
    public function it_limits_to_max_items(): void
    {
        $items = '';
        for ($i = 0; $i < 15; ++$i) {
            $items .= sprintf(
                '<item><title>Art %d</title><link>https://x.com/%d</link><description>Desc</description><pubDate>Mon, 10 Feb 2026 08:00:00 +0000</pubDate></item>',
                $i,
                $i,
            );
        }

        $xml = sprintf('<?xml version="1.0"?><rss version="2.0"><channel>%s</channel></rss>', $items);
        $articles = $this->parser->parse($xml, 'Src', 'php');

        self::assertCount(10, $articles);
    }

    #[Test]
    public function it_handles_missing_pub_date_gracefully(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0"?>
            <rss version="2.0">
                <channel>
                    <item>
                        <title>No Date</title>
                        <link>https://example.com</link>
                        <description>Desc</description>
                    </item>
                </channel>
            </rss>
            XML;

        $articles = $this->parser->parse($xml, 'Src', 'php');

        self::assertCount(1, $articles);
        self::assertNull($articles[0]->date());
    }

    #[Test]
    public function it_handles_invalid_date_format(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0"?>
            <rss version="2.0">
                <channel>
                    <item>
                        <title>Bad Date</title>
                        <link>https://example.com</link>
                        <description>Desc</description>
                        <pubDate>not-a-date</pubDate>
                    </item>
                </channel>
            </rss>
            XML;

        $articles = $this->parser->parse($xml, 'Src', 'php');

        self::assertCount(1, $articles);
        // The parser should handle this gracefully — either parse it or return null
    }
}
