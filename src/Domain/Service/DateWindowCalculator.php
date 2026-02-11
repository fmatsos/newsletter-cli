<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Domain\Service;

use Akawaka\Newsletter\Domain\Model\DateWindow;

final class DateWindowCalculator
{
    /**
     * Computes the date window for article filtering.
     *
     * Monday: Friday 00:00 UTC → now (covers Fri+Sat+Sun)
     * Tue-Fri: previous day 00:00 UTC → now
     */
    public function compute(?\DateTimeImmutable $now = null): DateWindow
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $weekday = (int) $now->format('N'); // 1=Monday ... 7=Sunday

        if (1 === $weekday) {
            $from = $now->modify('-3 days')->setTime(0, 0, 0);
            $isMonday = true;
        } else {
            $from = $now->modify('-1 day')->setTime(0, 0, 0);
            $isMonday = false;
        }

        return new DateWindow($from, $now, $isMonday);
    }
}
