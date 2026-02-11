<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Infrastructure\Publisher;

use Akawaka\Newsletter\Domain\Port\NewsletterPublisherInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final readonly class GithubDiscussionPublisher implements NewsletterPublisherInterface
{
    public function __construct(
        private string $repository,
        private string $discussionCategory = 'General',
    ) {
    }

    #[\Override]
    public function publish(string $title, string $htmlContent, string $label): string
    {
        $this->ensureLabelExists($label);

        $bodyMarkdown = $this->wrapHtmlInMarkdown($htmlContent);
        $discussionUrl = $this->createDiscussion($title, $bodyMarkdown);
        $this->addLabel($discussionUrl, $label);

        return $discussionUrl;
    }

    private function createDiscussion(string $title, string $body): string
    {
        $process = new Process([
            'gh', 'discussion', 'create',
            '--repo', $this->repository,
            '--title', $title,
            '--body', $body,
            '--category', $this->discussionCategory,
        ]);

        $process->setTimeout(30);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return trim($process->getOutput());
    }

    private function ensureLabelExists(string $label): void
    {
        $check = new Process([
            'gh', 'label', 'list',
            '--repo', $this->repository,
            '--search', $label,
            '--json', 'name',
        ]);
        $check->setTimeout(15);
        $check->run();

        $output = trim($check->getOutput());
        $labels = json_decode($output, true);

        $exists = false;
        if (\is_array($labels)) {
            foreach ($labels as $existing) {
                if (isset($existing['name']) && strtolower($existing['name']) === strtolower($label)) {
                    $exists = true;
                    break;
                }
            }
        }

        if (!$exists) {
            $create = new Process([
                'gh', 'label', 'create', $label,
                '--repo', $this->repository,
                '--color', '0075ca',
                '--description', 'Auto-generated newsletter digest',
            ]);
            $create->setTimeout(15);
            $create->run();
        }
    }

    private function addLabel(string $discussionUrl, string $label): void
    {
        $discussionNumber = $this->extractDiscussionNumber($discussionUrl);
        if (null === $discussionNumber) {
            return;
        }

        $process = new Process([
            'gh', 'discussion', 'edit', $discussionNumber,
            '--repo', $this->repository,
            '--add-label', $label,
        ]);
        $process->setTimeout(15);
        $process->run();
    }

    private function extractDiscussionNumber(string $url): ?string
    {
        if (preg_match('#/discussions/(\d+)#', $url, $matches)) {
            return $matches[1];
        }

        if (ctype_digit(trim($url))) {
            return trim($url);
        }

        return null;
    }

    private function wrapHtmlInMarkdown(string $html): string
    {
        return sprintf(
            "<!-- newsletter-digest -->\n\n<details>\n<summary>📧 Newsletter HTML (cliquer pour déplier)</summary>\n\n%s\n\n</details>\n\n---\n_Newsletter générée automatiquement par GitHub Actions._",
            $html,
        );
    }
}
