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
use Akawaka\Newsletter\Infrastructure\Summarizer\DescriptionFallbackSummarizer;
use Akawaka\Newsletter\Infrastructure\Summarizer\JsonBriefImporter;
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
        #[Option(name: 'github-token', description: 'GitHub token for GraphQL API (or set GITHUB_TOKEN env)')]
        ?string $githubToken = null,
        #[Option(name: 'discussion', description: 'Publish to GitHub Discussions')]
        bool $enableDiscussion = false,
        #[Option(name: 'dry-run', description: 'Build without publishing')]
        bool $dryRun = false,
        #[Option(name: 'anthropic-api-key', description: 'Anthropic API key (or set ANTHROPIC_API_KEY env)')]
        ?string $anthropicApiKey = null,
        #[Option(name: 'export-articles', description: 'Export collected articles to JSON file and exit')]
        ?string $exportArticles = null,
        #[Option(name: 'import-briefs', description: 'Import briefs from JSON file (url => brief mapping)')]
        ?string $importBriefs = null,
        #[Option(name: 'archive-dir', description: 'Directory to archive newsletter HTML files')]
        ?string $archiveDir = null,
    ): int {
        $io->title('Akawaka Newsletter CLI');

        $apiKey = $anthropicApiKey ?? (string) getenv('ANTHROPIC_API_KEY');

        $needsRepository = !$dryRun && null === $exportArticles && $enableDiscussion;

        if ($needsRepository && '' === $repository) {
            $io->error('Repository is required when publishing to GitHub Discussions. Use --repository, --no-discussion, or --dry-run.');

            return Command::FAILURE;
        }

        try {
            $configLoader = new YamlConfigurationLoader();
            $newsletterConfig = $configLoader->load($config);

            $io->section('Configuration loaded');
            $io->text(sprintf('Categories: %d', \count($newsletterConfig->categories())));
            $io->text(sprintf('Recipients: %s', implode(', ', $newsletterConfig->recipients())));

            $httpClient = HttpClient::create();

            $dateWindowCalculator = new DateWindowCalculator();
            $articleCollector = new ArticleCollector(
                fetcher: new HttpFeedFetcher($httpClient),
                parser: new XmlFeedParser(),
            );

            // Export articles mode: collect articles, write JSON, and exit
            if (null !== $exportArticles) {
                return $this->exportArticles($io, $newsletterConfig, $dateWindowCalculator, $articleCollector, $exportArticles);
            }

            $publishers = [];
            $token = $githubToken ?? (string) getenv('GITHUB_TOKEN');

            if ($enableDiscussion && !$dryRun) {
                if ('' === trim($token)) {
                    $io->error('GitHub token is required when publishing discussions. Set --github-token or the GITHUB_TOKEN env var.');

                    return Command::FAILURE;
                }

                $publishers[] = new GithubDiscussionPublisher(
                    HttpClient::create(),
                    $repository,
                    $discussionCategory,
                    $token,
                );
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
                null !== $importBriefs => new JsonBriefImporter($importBriefs),
                '' !== $apiKey => new ClaudeArticleSummarizer($httpClient, $apiKey),
                default => new DescriptionFallbackSummarizer(),
            };

            $briefMode = match (true) {
                null !== $importBriefs => sprintf('import (%s)', $importBriefs),
                '' !== $apiKey => 'anthropic (Claude API)',
                default => 'none (truncated descriptions)',
            };
            $io->text(sprintf('Brief generation: %s', $briefMode));

            $handler = new BuildNewsletterHandler(
                dateWindowCalculator: $dateWindowCalculator,
                articleCollector: $articleCollector,
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

    private function exportArticles(
        SymfonyStyle $io,
        \Akawaka\Newsletter\Application\DTO\NewsletterConfiguration $config,
        DateWindowCalculator $dateWindowCalculator,
        ArticleCollector $articleCollector,
        string $exportPath,
    ): int {
        $dateWindow = $dateWindowCalculator->compute();

        $allArticles = [];
        foreach ($config->categories() as $category) {
            $feeds = $config->feedsForCategory($category->id());
            if ([] === $feeds) {
                continue;
            }

            $articles = $articleCollector->collect(
                feedSources: $feeds,
                categoryId: $category->id(),
                dateWindow: $dateWindow,
                maxPerFeed: $config->maxArticlesPerFeed(),
                maxPerCategory: $config->maxArticlesPerCategory(),
            );

            foreach ($articles as $article) {
                $allArticles[] = [
                    'url' => $article->link(),
                    'title' => $article->title(),
                    'description' => $article->description(),
                    'source' => $article->source(),
                    'category' => $article->categoryId(),
                    'date' => $article->date()?->format(\DateTimeInterface::ATOM),
                ];
            }
        }

        file_put_contents($exportPath, json_encode($allArticles, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));

        $io->success(sprintf('Exported %d articles to %s', \count($allArticles), $exportPath));

        return Command::SUCCESS;
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
