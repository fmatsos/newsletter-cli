<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Tests\Infrastructure\Console;

use Akawaka\Newsletter\Infrastructure\Console\NullPublisher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullPublisher::class)]
final class NullPublisherTest extends TestCase
{
    #[Test]
    public function it_returns_dry_run_url(): void
    {
        $publisher = new NullPublisher();

        $result = $publisher->publish('Title', '<html/>', 'newsletter');

        self::assertSame('dry-run://not-published', $result);
    }
}
