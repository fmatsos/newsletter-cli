<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Application;

use Akawaka\Newsletter\Application\DTO\NewsletterConfiguration;
use Akawaka\Newsletter\Application\DTO\NewsletterResult;
use Akawaka\Newsletter\Domain\Model\Article;
use Akawaka\Newsletter\Domain\Port\ArticleSummarizerInterface;
use Akawaka\Newsletter\Domain\Port\NewsletterPublisherInterface;
use Akawaka\Newsletter\Domain\Port\NewsletterRendererInterface;
use Akawaka\Newsletter\Domain\Service\ArticleCollector;
use Akawaka\Newsletter\Domain\Service\DateWindowCalculator;

final readonly class BuildNewsletterHandler
{
    private const string FRENCH_DATE_FORMAT = 'l j F Y';

    private const array FRENCH_DAYS = [
        'Monday' => 'Lundi',
        'Tuesday' => 'Mardi',
        'Wednesday' => 'Mercredi',
        'Thursday' => 'Jeudi',
        'Friday' => 'Vendredi',
        'Saturday' => 'Samedi',
        'Sunday' => 'Dimanche',
    ];

    private const array FRENCH_MONTHS = [
        'January' => 'janvier',
        'February' => 'février',
        'March' => 'mars',
        'April' => 'avril',
        'May' => 'mai',
        'June' => 'juin',
        'July' => 'juillet',
        'August' => 'août',
        'September' => 'septembre',
        'October' => 'octobre',
        'November' => 'novembre',
        'December' => 'décembre',
    ];

    public function __construct(
        private DateWindowCalculator $dateWindowCalculator,
        private ArticleCollector $articleCollector,
        private ArticleSummarizerInterface $summarizer,
        private NewsletterRendererInterface $renderer,
        private NewsletterPublisherInterface $publisher,
    ) {
    }

    public function handle(NewsletterConfiguration $config): NewsletterResult
    {
        $dateWindow = $this->dateWindowCalculator->compute();

        $articlesByCategory = [];
        foreach ($config->categories() as $category) {
            $feeds = $config->feedsForCategory($category->id());
            if ([] === $feeds) {
                continue;
            }

            $articlesByCategory[$category->id()] = $this->articleCollector->collect(
                feedSources: $feeds,
                categoryId: $category->id(),
                dateWindow: $dateWindow,
                maxPerFeed: $config->maxArticlesPerFeed(),
                maxPerCategory: $config->maxArticlesPerCategory(),
            );
        }

        $allArticles = array_merge(...array_values($articlesByCategory));
        $summaries = [] !== $allArticles ? $this->summarizer->summarize($allArticles) : [];

        foreach ($articlesByCategory as $categoryId => $articles) {
            $articlesByCategory[$categoryId] = array_map(
                static fn (Article $article): Article => isset($summaries[$article->link()])
                    ? $article->withSummary($summaries[$article->link()])
                    : $article,
                $articles,
            );
        }

        $dateLabel = $this->formatDateFrench($dateWindow->to());
        $subject = sprintf('Akawaka Veille Tech – %s', $dateLabel);

        $result = new NewsletterResult(
            articlesByCategory: $articlesByCategory,
            categories: $config->categories(),
            dateWindow: $dateWindow,
            dateLabel: $dateLabel,
            subject: $subject,
        );

        $html = $this->renderer->render($result);
        $this->publisher->publish($subject, $html, 'newsletter');

        return $result;
    }

    public static function formatDateFrench(\DateTimeImmutable $date): string
    {
        $dayName = self::FRENCH_DAYS[$date->format('l')] ?? $date->format('l');
        $monthName = self::FRENCH_MONTHS[$date->format('F')] ?? $date->format('F');

        return sprintf('%s %s %s %s', $dayName, $date->format('j'), $monthName, $date->format('Y'));
    }

    public static function formatDateFrenchShort(\DateTimeImmutable $date): string
    {
        $monthName = self::FRENCH_MONTHS[$date->format('F')] ?? $date->format('F');

        return sprintf('%s %s %s', $date->format('j'), $monthName, $date->format('Y'));
    }
}
