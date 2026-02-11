<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Domain\Model;

final class Article
{
    public function __construct(
        private readonly string $title,
        private readonly string $link,
        private readonly string $description,
        private readonly ?\DateTimeImmutable $date,
        private readonly string $source,
        private readonly string $categoryId,
        private string $summary = '',
    ) {
    }

    public function title(): string
    {
        return $this->title;
    }

    public function link(): string
    {
        return $this->link;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function date(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function categoryId(): string
    {
        return $this->categoryId;
    }

    public function summary(): string
    {
        return $this->summary;
    }

    public function withSummary(string $summary): self
    {
        $clone = clone $this;
        $clone->summary = $summary;

        return $clone;
    }

    public function hasDate(): bool
    {
        return null !== $this->date;
    }
}
