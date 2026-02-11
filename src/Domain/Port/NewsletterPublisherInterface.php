<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Domain\Port;

interface NewsletterPublisherInterface
{
    /**
     * Publishes the newsletter HTML.
     *
     * @return string Identifier of the published resource (e.g. discussion URL)
     */
    public function publish(string $title, string $htmlContent, string $label): string;
}
