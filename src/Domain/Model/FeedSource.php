<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Domain\Model;

final readonly class FeedSource
{
    public function __construct(
        private string $name,
        private string $url,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function url(): string
    {
        return $this->url;
    }
}
