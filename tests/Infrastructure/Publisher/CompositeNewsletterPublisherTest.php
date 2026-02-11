<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Tests\Infrastructure\Publisher;

use Akawaka\Newsletter\Domain\Port\NewsletterPublisherInterface;
use Akawaka\Newsletter\Infrastructure\Publisher\CompositeNewsletterPublisher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CompositeNewsletterPublisher::class)]
final class CompositeNewsletterPublisherTest extends TestCase
{
    #[Test]
    public function it_delegates_to_all_publishers(): void
    {
        $publisher1 = $this->createMock(NewsletterPublisherInterface::class);
        $publisher1->expects(self::once())
            ->method('publish')
            ->with('Title', '<html/>', 'label')
            ->willReturn('result-1');

        $publisher2 = $this->createMock(NewsletterPublisherInterface::class);
        $publisher2->expects(self::once())
            ->method('publish')
            ->with('Title', '<html/>', 'label')
            ->willReturn('result-2');

        $composite = new CompositeNewsletterPublisher([$publisher1, $publisher2]);

        $result = $composite->publish('Title', '<html/>', 'label');

        self::assertSame('result-1', $result);
    }

    #[Test]
    public function it_returns_first_non_empty_result(): void
    {
        $publisher1 = $this->createMock(NewsletterPublisherInterface::class);
        $publisher1->method('publish')->willReturn('');

        $publisher2 = $this->createMock(NewsletterPublisherInterface::class);
        $publisher2->method('publish')->willReturn('second-result');

        $composite = new CompositeNewsletterPublisher([$publisher1, $publisher2]);

        self::assertSame('second-result', $composite->publish('T', 'H', 'L'));
    }

    #[Test]
    public function it_returns_empty_string_when_no_publishers(): void
    {
        $composite = new CompositeNewsletterPublisher([]);

        self::assertSame('', $composite->publish('T', 'H', 'L'));
    }

    #[Test]
    public function it_returns_empty_string_when_all_publishers_return_empty(): void
    {
        $publisher = $this->createMock(NewsletterPublisherInterface::class);
        $publisher->method('publish')->willReturn('');

        $composite = new CompositeNewsletterPublisher([$publisher]);

        self::assertSame('', $composite->publish('T', 'H', 'L'));
    }
}
