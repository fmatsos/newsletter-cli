<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Tests\Infrastructure\Summarizer;

use Akawaka\Newsletter\Domain\Model\Article;
use Akawaka\Newsletter\Infrastructure\Summarizer\JsonBriefImporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonBriefImporter::class)]
final class JsonBriefImporterTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/newsletter-test-' . uniqid();
        mkdir($this->tempDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir . '/*') ?: []);
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    #[Test]
    public function it_returns_empty_for_no_articles(): void
    {
        $importer = new JsonBriefImporter($this->tempDir . '/briefs.json');
        $result = $importer->summarize([]);

        self::assertSame([], $result);
    }

    #[Test]
    public function it_reads_briefs_from_json_file(): void
    {
        $briefs = [
            'https://a.com/1' => 'Résumé de l\'article 1',
            'https://a.com/2' => 'Résumé de l\'article 2',
        ];

        $path = $this->tempDir . '/briefs.json';
        file_put_contents($path, json_encode($briefs, \JSON_THROW_ON_ERROR));

        $articles = [
            new Article('Title 1', 'https://a.com/1', 'Desc 1', new \DateTimeImmutable(), 'Src', 'php'),
            new Article('Title 2', 'https://a.com/2', 'Desc 2', new \DateTimeImmutable(), 'Src', 'ai'),
        ];

        $importer = new JsonBriefImporter($path);
        $result = $importer->summarize($articles);

        self::assertSame('Résumé de l\'article 1', $result['https://a.com/1']);
        self::assertSame('Résumé de l\'article 2', $result['https://a.com/2']);
    }

    #[Test]
    public function it_falls_back_to_descriptions_when_file_not_found(): void
    {
        $articles = [
            new Article('Title', 'https://a.com/1', 'Fallback desc', new \DateTimeImmutable(), 'Src', 'php'),
        ];

        $importer = new JsonBriefImporter($this->tempDir . '/nonexistent.json');
        $result = $importer->summarize($articles);

        self::assertSame('Fallback desc', $result['https://a.com/1']);
    }

    #[Test]
    public function it_falls_back_to_descriptions_on_invalid_json(): void
    {
        $path = $this->tempDir . '/invalid.json';
        file_put_contents($path, 'not json at all');

        $articles = [
            new Article('Title', 'https://a.com/1', 'Fallback', new \DateTimeImmutable(), 'Src', 'php'),
        ];

        $importer = new JsonBriefImporter($path);
        $result = $importer->summarize($articles);

        self::assertSame('Fallback', $result['https://a.com/1']);
    }

    #[Test]
    public function it_falls_back_when_json_is_not_an_object(): void
    {
        $path = $this->tempDir . '/array.json';
        file_put_contents($path, '"just a string"');

        $articles = [
            new Article('Title', 'https://a.com/1', 'Fallback', new \DateTimeImmutable(), 'Src', 'php'),
        ];

        $importer = new JsonBriefImporter($path);
        $result = $importer->summarize($articles);

        self::assertSame('Fallback', $result['https://a.com/1']);
    }

    #[Test]
    public function it_handles_partial_briefs(): void
    {
        $briefs = [
            'https://a.com/1' => 'Only this one has a brief',
        ];

        $path = $this->tempDir . '/partial.json';
        file_put_contents($path, json_encode($briefs, \JSON_THROW_ON_ERROR));

        $articles = [
            new Article('Title 1', 'https://a.com/1', 'Desc 1', new \DateTimeImmutable(), 'Src', 'php'),
            new Article('Title 2', 'https://a.com/2', 'Desc 2', new \DateTimeImmutable(), 'Src', 'ai'),
        ];

        $importer = new JsonBriefImporter($path);
        $result = $importer->summarize($articles);

        self::assertSame('Only this one has a brief', $result['https://a.com/1']);
        self::assertArrayNotHasKey('https://a.com/2', $result);
    }
}
