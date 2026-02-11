<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Infrastructure\Publisher;

use Akawaka\Newsletter\Domain\Port\NewsletterPublisherInterface;

final readonly class CompositeNewsletterPublisher implements NewsletterPublisherInterface
{
    /** @param list<NewsletterPublisherInterface> $publishers */
    public function __construct(
        private array $publishers,
    ) {
    }

    #[\Override]
    public function publish(string $title, string $htmlContent, string $label): string
    {
        $result = '';

        foreach ($this->publishers as $publisher) {
            $output = $publisher->publish($title, $htmlContent, $label);

            if ('' === $result && '' !== $output) {
                $result = $output;
            }
        }

        return $result;
    }
}
