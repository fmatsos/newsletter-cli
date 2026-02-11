<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Infrastructure\Publisher;

use Akawaka\Newsletter\Domain\Port\NewsletterPublisherInterface;

final readonly class FileNewsletterArchiver implements NewsletterPublisherInterface
{
    public function __construct(
        private string $archiveDirectory,
    ) {
    }

    #[\Override]
    public function publish(string $title, string $htmlContent, string $label): string
    {
        if (!is_dir($this->archiveDirectory)) {
            mkdir($this->archiveDirectory, 0o755, true);
        }

        $filename = sprintf('%s.html', (new \DateTimeImmutable())->format('Y-m-d'));
        $filepath = rtrim($this->archiveDirectory, '/') . '/' . $filename;

        file_put_contents($filepath, $htmlContent);

        return $filepath;
    }
}
