<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Infrastructure\Publisher;

use Akawaka\Newsletter\Domain\Port\NewsletterPublisherInterface;

final readonly class FileNewsletterArchiver implements NewsletterPublisherInterface
{
    public function __construct(
        private string $archiveDirectory,
        private string $fileExtension = 'html',
    ) {
    }

    #[\Override]
    public function publish(string $title, string $content, string $label): string
    {
        if (!is_dir($this->archiveDirectory)) {
            mkdir($this->archiveDirectory, 0o755, true);
        }

        $filename = sprintf('%s.%s', (new \DateTimeImmutable())->format('Y-m-d'), $this->fileExtension);
        $filepath = rtrim($this->archiveDirectory, '/') . '/' . $filename;

        file_put_contents($filepath, $content);

        return $filepath;
    }
}
