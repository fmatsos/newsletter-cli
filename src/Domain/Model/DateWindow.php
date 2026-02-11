<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Domain\Model;

final readonly class DateWindow
{
    public function __construct(
        private \DateTimeImmutable $from,
        private \DateTimeImmutable $to,
        private bool $isMonday,
    ) {
    }

    public function from(): \DateTimeImmutable
    {
        return $this->from;
    }

    public function to(): \DateTimeImmutable
    {
        return $this->to;
    }

    public function isMonday(): bool
    {
        return $this->isMonday;
    }

    public function contains(\DateTimeImmutable $date): bool
    {
        return $date >= $this->from && $date <= $this->to;
    }
}
