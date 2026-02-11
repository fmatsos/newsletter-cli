<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Infrastructure\Renderer;

use Akawaka\Newsletter\Application\DTO\NewsletterResult;
use Akawaka\Newsletter\Domain\Port\NewsletterRendererInterface;
use Twig\Environment;

final readonly class TwigNewsletterRenderer implements NewsletterRendererInterface
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    #[\Override]
    public function render(NewsletterResult $result): string
    {
        $html = $this->twig->render('newsletter/layout.html.twig', [
            'result' => $result,
            'date_label' => $result->dateLabel(),
            'is_monday' => $result->dateWindow()->isMonday(),
            'categories' => $result->categories(),
            'articles_by_category' => $result->articlesByCategory(),
        ]);

        return $this->minifyHtml($html);
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
