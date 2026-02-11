<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Tests\Domain\Model;

use Akawaka\Newsletter\Domain\Model\DateWindow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DateWindow::class)]
final class DateWindowTest extends TestCase
{
    #[Test]
    public function it_exposes_all_properties(): void
    {
        $from = new \DateTimeImmutable('2026-02-09T00:00:00Z');
        $to = new \DateTimeImmutable('2026-02-10T12:00:00Z');

        $window = new DateWindow($from, $to, true);

        self::assertSame($from, $window->from());
        self::assertSame($to, $window->to());
        self::assertTrue($window->isMonday());
    }

    #[Test]
    #[DataProvider('containsProvider')]
    public function it_checks_containment(string $dateStr, bool $expected): void
    {
        $window = new DateWindow(
            new \DateTimeImmutable('2026-02-09T00:00:00Z'),
            new \DateTimeImmutable('2026-02-10T23:59:59Z'),
            false,
        );

        $date = new \DateTimeImmutable($dateStr);

        self::assertSame($expected, $window->contains($date));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function containsProvider(): iterable
    {
        yield 'before window' => ['2026-02-08T23:59:59Z', false];
        yield 'at start of window' => ['2026-02-09T00:00:00Z', true];
        yield 'middle of window' => ['2026-02-10T12:00:00Z', true];
        yield 'at end of window' => ['2026-02-10T23:59:59Z', true];
        yield 'after window' => ['2026-02-11T00:00:00Z', false];
    }
}
