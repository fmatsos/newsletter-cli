<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Infrastructure\Console;

use Akawaka\Newsletter\Domain\Port\NewsletterPublisherInterface;

final class NullPublisher implements NewsletterPublisherInterface
{
    #[\Override]
    public function publish(string $title, string $htmlContent, string $label): string
    {
        return 'dry-run://not-published';
    }
}
