<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Tests\Infrastructure\Renderer;

use Akawaka\Newsletter\Application\DTO\NewsletterResult;
use Akawaka\Newsletter\Domain\Model\Article;
use Akawaka\Newsletter\Domain\Model\Category;
use Akawaka\Newsletter\Domain\Model\DateWindow;
use Akawaka\Newsletter\Infrastructure\Renderer\TwigNewsletterRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

#[CoversClass(TwigNewsletterRenderer::class)]
final class TwigNewsletterRendererTest extends TestCase
{
    private TwigNewsletterRenderer $renderer;

    protected function setUp(): void
    {
        $templatesPath = \dirname(__DIR__, 3) . '/templates';

        if (!is_dir($templatesPath)) {
            self::markTestSkipped('Templates directory not found.');
        }

        $loader = new FilesystemLoader($templatesPath);
        $twig = new Environment($loader, [
            'autoescape' => false,
            'strict_variables' => true,
        ]);

        $this->renderer = new TwigNewsletterRenderer($twig);
    }

    #[Test]
    public function it_renders_newsletter_with_articles(): void
    {
        $category = new Category('php', 'PHP', '#4B82E8', '#fff', '', '', 'https://img.jpg', '#4B82E8', '#E9F0FD', '#2558CC');
        $article = new Article('Test Article', 'https://example.com', 'A description', new \DateTimeImmutable('2026-02-10T10:00:00Z'), 'Source', 'php', 'Un résumé');
        $dateWindow = new DateWindow(new \DateTimeImmutable('2026-02-09T00:00:00Z'), new \DateTimeImmutable('2026-02-10T23:59:59Z'), false);

        $result = new NewsletterResult(
            ['php' => [$article]],
            [$category],
            $dateWindow,
            'Mardi 10 février 2026',
            'Akawaka Veille Tech – Mardi 10 février 2026',
        );

        $html = $this->renderer->render($result);

        self::assertStringContainsString('Akawaka Veille Tech', $html);
        self::assertStringContainsString('Test Article', $html);
        self::assertStringContainsString('Un résumé', $html);
        self::assertStringContainsString('Source', $html);
        self::assertStringContainsString('PHP', $html);
    }

    #[Test]
    public function it_renders_empty_state_for_category_without_articles(): void
    {
        $category = new Category('tools', 'Outils', '#8456B0', '#fff', '', '', 'https://img.jpg', '#8456B0', '#F0E7F6', '#6D3F9B');
        $dateWindow = new DateWindow(new \DateTimeImmutable('2026-02-09T00:00:00Z'), new \DateTimeImmutable('2026-02-10T23:59:59Z'), false);

        $result = new NewsletterResult(
            ['tools' => []],
            [$category],
            $dateWindow,
            'Mardi 10 février 2026',
            'Akawaka Veille Tech – Mardi 10 février 2026',
        );

        $html = $this->renderer->render($result);

        self::assertStringContainsString('Aucun nouvel article', $html);
    }

    #[Test]
    public function it_renders_monday_empty_state(): void
    {
        $category = new Category('ai', 'IA', '#C24B5A', '#fff', '', '', 'https://img.jpg', '#C24B5A', '#FCEBED', '#A33344');
        $dateWindow = new DateWindow(new \DateTimeImmutable('2026-02-06T00:00:00Z'), new \DateTimeImmutable('2026-02-09T10:00:00Z'), true);

        $result = new NewsletterResult(
            ['ai' => []],
            [$category],
            $dateWindow,
            'Lundi 9 février 2026',
            'Akawaka Veille Tech – Lundi 9 février 2026',
        );

        $html = $this->renderer->render($result);

        self::assertStringContainsString('week-end', $html);
    }

    #[Test]
    public function it_minifies_html_output(): void
    {
        $category = new Category('php', 'PHP', '#4B82E8', '#fff', '', '', '', '#4B82E8', '#E9F0FD', '#2558CC');
        $dateWindow = new DateWindow(new \DateTimeImmutable('2026-02-09T00:00:00Z'), new \DateTimeImmutable('2026-02-10T23:59:59Z'), false);

        $result = new NewsletterResult(
            [],
            [$category],
            $dateWindow,
            'Mardi 10 février 2026',
            'Subject',
        );

        $html = $this->renderer->render($result);

        // No blank lines between content
        self::assertDoesNotMatchRegularExpression('/\n\s*\n/', $html);
    }
}
