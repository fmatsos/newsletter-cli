<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Domain\Port;

use Akawaka\Newsletter\Application\DTO\NewsletterResult;

interface NewsletterRendererInterface
{
    /**
     * Renders the newsletter to an HTML string.
     */
    public function render(NewsletterResult $result): string;
}
