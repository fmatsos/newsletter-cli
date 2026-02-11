<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Infrastructure\Console;

use Akawaka\Newsletter\Application\BuildNewsletterHandler;
use Akawaka\Newsletter\Domain\Service\ArticleCollector;
use Akawaka\Newsletter\Domain\Service\DateWindowCalculator;
use Akawaka\Newsletter\Infrastructure\Configuration\YamlConfigurationLoader;
use Akawaka\Newsletter\Infrastructure\Feed\HttpFeedFetcher;
use Akawaka\Newsletter\Infrastructure\Feed\XmlFeedParser;
use Akawaka\Newsletter\Infrastructure\Publisher\CompositeNewsletterPublisher;
use Akawaka\Newsletter\Infrastructure\Publisher\FileNewsletterArchiver;
use Akawaka\Newsletter\Infrastructure\Publisher\GithubDiscussionPublisher;
use Akawaka\Newsletter\Infrastructure\Renderer\TwigNewsletterRenderer;
use Akawaka\Newsletter\Infrastructure\Summarizer\ClaudeArticleSummarizer;
use Akawaka\Newsletter\Infrastructure\Summarizer\CopilotArticleSummarizer;
use Akawaka\Newsletter\Infrastructure\Summarizer\DescriptionFallbackSummarizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

#[AsCommand(
    name: 'newsletter:build',
    description: 'Build and publish the daily tech newsletter digest',
)]
final class BuildNewsletterCommand extends Command
{
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Path to newsletter.yaml', shortcut: 'c')]
        string $config = 'config/newsletter.yaml',
        #[Option(description: 'Path to templates directory', shortcut: 't')]
        string $templates = 'templates',
        #[Option(description: 'Save HTML output to file', shortcut: 'o')]
        ?string $output = null,
        #[Option(description: 'GitHub repository (owner/repo)', shortcut: 'r')]
        string $repository = '',
        #[Option(name: 'discussion-category', description: 'GitHub Discussion category')]
        string $discussionCategory = 'General',
        #[Option(name: 'dry-run', description: 'Build without publishing')]
        bool $dryRun = false,
        #[Option(name: 'anthropic-api-key', description: 'Anthropic API key (or set ANTHROPIC_API_KEY env)')]
        ?string $anthropicApiKey = null,
        #[Option(name: 'copilot', description: 'Use GitHub Copilot (gh models run gpt-4.1) for brief generation')]
        bool $copilot = false,
        #[Option(name: 'archive-dir', description: 'Directory to archive newsletter HTML files')]
        ?string $archiveDir = null,
    ): int {
        $io->title('Akawaka Newsletter CLI');

        $apiKey = $anthropicApiKey ?? (string) getenv('ANTHROPIC_API_KEY');

        if (!$dryRun && '' === $repository) {
            $io->error('Repository is required when not in dry-run mode. Use --repository or --dry-run.');

            return Command::FAILURE;
        }

        try {
            $configLoader = new YamlConfigurationLoader();
            $newsletterConfig = $configLoader->load($config);

            $io->section('Configuration loaded');
            $io->text(sprintf('Categories: %d', \count($newsletterConfig->categories())));
            $io->text(sprintf('Recipients: %s', implode(', ', $newsletterConfig->recipients())));

            $httpClient = HttpClient::create();

            $publishers = [];

            if (!$dryRun) {
                $publishers[] = new GithubDiscussionPublisher($repository, $discussionCategory);
            }

            if (null !== $archiveDir) {
                $publishers[] = new FileNewsletterArchiver($archiveDir);
                $io->text(sprintf('Archive directory: %s', $archiveDir));
            }

            $publisher = match (true) {
                [] === $publishers => new NullPublisher(),
                1 === \count($publishers) => $publishers[0],
                default => new CompositeNewsletterPublisher($publishers),
            };

            $twig = $this->createTwigEnvironment($templates);

            $summarizer = match (true) {
                $copilot => new CopilotArticleSummarizer(),
                '' !== $apiKey => new ClaudeArticleSummarizer($httpClient, $apiKey),
                default => new DescriptionFallbackSummarizer(),
            };

            $briefMode = match (true) {
                $copilot => 'copilot (gh models run gpt-4.1)',
                '' !== $apiKey => 'anthropic (Claude API)',
                default => 'none (truncated descriptions)',
            };
            $io->text(sprintf('Brief generation: %s', $briefMode));

            $handler = new BuildNewsletterHandler(
                dateWindowCalculator: new DateWindowCalculator(),
                articleCollector: new ArticleCollector(
                    fetcher: new HttpFeedFetcher($httpClient),
                    parser: new XmlFeedParser(),
                ),
                summarizer: $summarizer,
                renderer: new TwigNewsletterRenderer($twig),
                publisher: $publisher,
            );

            $result = $handler->handle($newsletterConfig);

            $io->section('Results');
            $io->text(sprintf('Subject: %s', $result->subject()));
            $io->text(sprintf('Total articles: %d', $result->totalArticles()));

            foreach ($result->categories() as $category) {
                $articles = $result->articlesForCategory($category->id());
                $io->text(sprintf('  [%s] %s: %d articles', $category->id(), $category->label(), \count($articles)));
            }

            if (null !== $output) {
                $renderer = new TwigNewsletterRenderer($twig);
                $html = $renderer->render($result);
                file_put_contents($output, $html);
                $io->success(sprintf('HTML saved to %s', $output));
            }

            if ($dryRun) {
                $io->note('Dry run: newsletter was not published.');
            } else {
                $io->success('Newsletter published successfully!');
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error(sprintf('Failed: %s', $e->getMessage()));

            if ($io->isVerbose()) {
                $io->text($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    private function createTwigEnvironment(string $templatesPath): Environment
    {
        $loader = new FilesystemLoader($templatesPath);

        $twig = new Environment($loader, [
            'autoescape' => false,
            'strict_variables' => true,
        ]);

        $twig->addFunction(new TwigFunction(
            'format_date_short',
            static fn (\DateTimeImmutable $date): string => BuildNewsletterHandler::formatDateFrenchShort($date),
        ));

        return $twig;
    }
}
