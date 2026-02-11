<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Tests\Infrastructure\Configuration;

use Akawaka\Newsletter\Infrastructure\Configuration\YamlConfigurationLoader;
use Akawaka\Newsletter\Domain\Model\FeedSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(YamlConfigurationLoader::class)]
final class YamlConfigurationLoaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/newsletter_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tempDir . '/*');
        if (\is_array($files)) {
            array_map('unlink', $files);
        }
        rmdir($this->tempDir);
    }

    #[Test]
    public function it_loads_valid_configuration(): void
    {
        $yaml = <<<'YAML'
            recipients:
              - test@example.com
            categories:
              - id: php
                label: PHP
                header_bg: '#4B82E8'
                header_color: '#ffffff'
                accent_color: '#4B82E8'
                tag_bg: '#E9F0FD'
                tag_color: '#2558CC'
            feeds:
              - category_id: php
                sources:
                  'Example Feed': 'https://feed.example.com/rss'
            max_articles_per_feed: 3
            max_articles_per_category: 5
            YAML;

        $path = $this->tempDir . '/newsletter.yaml';
        file_put_contents($path, $yaml);

        $loader = new YamlConfigurationLoader();
        $config = $loader->load($path);

        self::assertSame(['test@example.com'], $config->recipients());
        self::assertCount(1, $config->categories());
        self::assertSame('php', $config->categories()[0]->id());
        self::assertSame('PHP', $config->categories()[0]->label());
        $feeds = $config->feedsForCategory('php');
        self::assertCount(1, $feeds);
        self::assertInstanceOf(FeedSource::class, $feeds[0]);
        self::assertSame('Example Feed', $feeds[0]->name());
        self::assertSame('https://feed.example.com/rss', $feeds[0]->url());
        self::assertSame([], $config->feedsForCategory('nonexistent'));
        self::assertSame(3, $config->maxArticlesPerFeed());
        self::assertSame(5, $config->maxArticlesPerCategory());
    }

    #[Test]
    public function it_throws_on_missing_file(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not found/');

        $loader = new YamlConfigurationLoader();
        $loader->load('/nonexistent/path/newsletter.yaml');
    }

    #[Test]
    public function it_handles_minimal_configuration(): void
    {
        $yaml = <<<'YAML'
            recipients: []
            categories: []
            feeds: []
            YAML;

        $path = $this->tempDir . '/minimal.yaml';
        file_put_contents($path, $yaml);

        $loader = new YamlConfigurationLoader();
        $config = $loader->load($path);

        self::assertSame([], $config->recipients());
        self::assertSame([], $config->categories());
        self::assertSame(5, $config->maxArticlesPerFeed());
        self::assertSame(8, $config->maxArticlesPerCategory());
    }

    #[Test]
    public function it_uses_defaults_for_missing_category_fields(): void
    {
        $yaml = <<<'YAML'
            recipients: []
            categories:
              - id: test
                label: Test
            feeds: []
            YAML;

        $path = $this->tempDir . '/defaults.yaml';
        file_put_contents($path, $yaml);

        $loader = new YamlConfigurationLoader();
        $config = $loader->load($path);

        $cat = $config->categories()[0];
        self::assertSame('#333333', $cat->headerBg());
        self::assertSame('#ffffff', $cat->headerColor());
        self::assertSame('#333333', $cat->accentColor());
    }

    #[Test]
    public function find_category_returns_null_for_unknown_id(): void
    {
        $yaml = <<<'YAML'
            recipients: []
            categories:
              - id: php
                label: PHP
            feeds: []
            YAML;

        $path = $this->tempDir . '/find.yaml';
        file_put_contents($path, $yaml);

        $loader = new YamlConfigurationLoader();
        $config = $loader->load($path);

        self::assertNotNull($config->findCategory('php'));
        self::assertNull($config->findCategory('nonexistent'));
    }
}
