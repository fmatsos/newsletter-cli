<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Tests\Infrastructure\Publisher;

use Akawaka\Newsletter\Infrastructure\Publisher\FileNewsletterArchiver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileNewsletterArchiver::class)]
final class FileNewsletterArchiverTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/newsletter_archiver_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            if (false !== $files) {
                array_map('unlink', $files);
            }
            rmdir($this->tempDir);
        }
    }

    #[Test]
    public function it_creates_archive_directory_if_not_exists(): void
    {
        $archiver = new FileNewsletterArchiver($this->tempDir);

        $archiver->publish('Test Newsletter', '<html><body>Hello</body></html>', 'newsletter');

        self::assertDirectoryExists($this->tempDir);
    }

    #[Test]
    public function it_saves_html_file_with_date_name(): void
    {
        $archiver = new FileNewsletterArchiver($this->tempDir);
        $htmlContent = '<html><body><h1>Newsletter</h1></body></html>';

        $filepath = $archiver->publish('Test Newsletter', $htmlContent, 'newsletter');

        $expectedFilename = (new \DateTimeImmutable())->format('Y-m-d') . '.html';
        self::assertStringEndsWith($expectedFilename, $filepath);
        self::assertFileExists($filepath);
        self::assertSame($htmlContent, file_get_contents($filepath));
    }

    #[Test]
    public function it_returns_filepath(): void
    {
        $archiver = new FileNewsletterArchiver($this->tempDir);

        $result = $archiver->publish('Title', '<p>Content</p>', 'label');

        self::assertStringStartsWith($this->tempDir, $result);
        self::assertStringEndsWith('.html', $result);
    }

    #[Test]
    public function it_overwrites_existing_file_for_same_day(): void
    {
        $archiver = new FileNewsletterArchiver($this->tempDir);

        $archiver->publish('First', '<p>First</p>', 'newsletter');
        $filepath = $archiver->publish('Second', '<p>Second</p>', 'newsletter');

        self::assertSame('<p>Second</p>', file_get_contents($filepath));
    }

    #[Test]
    public function it_handles_trailing_slash_in_directory(): void
    {
        $archiver = new FileNewsletterArchiver($this->tempDir . '/');

        $filepath = $archiver->publish('Title', '<p>Content</p>', 'newsletter');

        self::assertStringNotContainsString('//', str_replace($this->tempDir, '', $filepath));
        self::assertFileExists($filepath);
    }
}
