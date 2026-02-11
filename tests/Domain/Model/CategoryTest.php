<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Tests\Domain\Model;

use Akawaka\Newsletter\Domain\Model\Category;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Category::class)]
final class CategoryTest extends TestCase
{
    #[Test]
    public function it_exposes_all_properties(): void
    {
        $category = new Category(
            id: 'php',
            label: 'Ecosysteme PHP',
            headerBg: '#4B82E8',
            headerColor: '#ffffff',
            headerGradient: 'linear-gradient(135deg, #4B82E8 0%, #2563EB 100%)',
            headerGradientEnd: '#2563EB',
            headerImage: 'https://images.unsplash.com/photo.jpg',
            accentColor: '#4B82E8',
            tagBg: '#E9F0FD',
            tagColor: '#2558CC',
        );

        self::assertSame('php', $category->id());
        self::assertSame('Ecosysteme PHP', $category->label());
        self::assertSame('#4B82E8', $category->headerBg());
        self::assertSame('#ffffff', $category->headerColor());
        self::assertSame('linear-gradient(135deg, #4B82E8 0%, #2563EB 100%)', $category->headerGradient());
        self::assertSame('#2563EB', $category->headerGradientEnd());
        self::assertSame('https://images.unsplash.com/photo.jpg', $category->headerImage());
        self::assertSame('#4B82E8', $category->accentColor());
        self::assertSame('#E9F0FD', $category->tagBg());
        self::assertSame('#2558CC', $category->tagColor());
    }
}
