<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Infrastructure\Renderer;

use Akawaka\Newsletter\Application\DTO\NewsletterResult;
use Akawaka\Newsletter\Domain\Port\NewsletterRendererInterface;
use Twig\Environment;

final readonly class TwigNewsletterRenderer implements NewsletterRendererInterface
{
    public const FORMAT_HTML = 'html';
    public const FORMAT_MARKDOWN = 'markdown';

    public function __construct(
        private Environment $twig,
        private string $format = self::FORMAT_HTML,
    ) {
    }

    #[\Override]
    public function render(NewsletterResult $result): string
    {
        $templateVars = [
            'result' => $result,
            'date_label' => $result->dateLabel(),
            'is_monday' => $result->dateWindow()->isMonday(),
            'categories' => $result->categories(),
            'articles_by_category' => $result->articlesByCategory(),
        ];

        if (self::FORMAT_MARKDOWN === $this->format) {
            $markdown = $this->twig->render('newsletter/layout.md.twig', $templateVars);

            return $this->normalizeMarkdown($markdown);
        }

        $html = $this->twig->render('newsletter/layout.html.twig', $templateVars);

        return $this->minifyHtml($html);
    }

    private function normalizeMarkdown(string $markdown): string
    {
        // Remove excessive blank lines (more than 2 consecutive newlines become 2)
        $markdown = (string) preg_replace('/\n{3,}/', "\n\n", $markdown);

        // Trim leading/trailing whitespace
        return trim($markdown) . "\n";
    }

    private function minifyHtml(string $html): string
    {
        $html = (string) preg_replace('/<!--(?!\[if)(?!<!\[endif).*?-->/s', '', $html);
        $html = (string) preg_replace('/>\s+</', '> <', $html);

        $lines = array_filter(
            array_map('trim', explode("\n", $html)),
            static fn (string $line): bool => '' !== $line,
        );

        return implode("\n", $lines);
    }
}
