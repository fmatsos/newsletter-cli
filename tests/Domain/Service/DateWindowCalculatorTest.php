<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Tests\Domain\Service;

use Akawaka\Newsletter\Domain\Service\DateWindowCalculator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DateWindowCalculator::class)]
final class DateWindowCalculatorTest extends TestCase
{
    private DateWindowCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new DateWindowCalculator();
    }

    #[Test]
    public function monday_covers_friday_through_now(): void
    {
        // 2026-02-09 is a Monday
        $now = new \DateTimeImmutable('2026-02-09T10:00:00Z');
        $window = $this->calculator->compute($now);

        self::assertTrue($window->isMonday());
        self::assertSame('2026-02-06', $window->from()->format('Y-m-d'));
        self::assertSame('00:00:00', $window->from()->format('H:i:s'));
        self::assertSame($now, $window->to());
    }

    #[Test]
    #[DataProvider('nonMondayProvider')]
    public function non_monday_covers_previous_day(string $nowStr, string $expectedFromDate): void
    {
        $now = new \DateTimeImmutable($nowStr);
        $window = $this->calculator->compute($now);

        self::assertFalse($window->isMonday());
        self::assertSame($expectedFromDate, $window->from()->format('Y-m-d'));
        self::assertSame('00:00:00', $window->from()->format('H:i:s'));
        self::assertSame($now, $window->to());
    }

    /** @return iterable<string, array{string, string}> */
    public static function nonMondayProvider(): iterable
    {
        yield 'tuesday' => ['2026-02-10T10:00:00Z', '2026-02-09'];
        yield 'wednesday' => ['2026-02-11T10:00:00Z', '2026-02-10'];
        yield 'thursday' => ['2026-02-12T10:00:00Z', '2026-02-11'];
        yield 'friday' => ['2026-02-13T10:00:00Z', '2026-02-12'];
        yield 'saturday' => ['2026-02-14T10:00:00Z', '2026-02-13'];
        yield 'sunday' => ['2026-02-15T10:00:00Z', '2026-02-14'];
    }

    #[Test]
    public function default_now_uses_current_time(): void
    {
        $window = $this->calculator->compute();

        self::assertInstanceOf(\DateTimeImmutable::class, $window->from());
        self::assertInstanceOf(\DateTimeImmutable::class, $window->to());
        self::assertLessThanOrEqual(new \DateTimeImmutable('now', new \DateTimeZone('UTC')), $window->to());
    }
}
