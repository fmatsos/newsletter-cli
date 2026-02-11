<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Infrastructure\Configuration;

use Akawaka\Newsletter\Application\DTO\NewsletterConfiguration;
use Akawaka\Newsletter\Domain\Model\Category;
use Symfony\Component\Yaml\Yaml;

final class YamlConfigurationLoader
{
    public function load(string $filePath): NewsletterConfiguration
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException(sprintf('Configuration file not found: %s', $filePath));
        }

        /** @var array<string, mixed> $raw */
        $raw = Yaml::parseFile($filePath);

        return $this->buildConfiguration($raw);
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function buildConfiguration(array $raw): NewsletterConfiguration
    {
        /** @var list<string> $recipients */
        $recipients = $raw['recipients'] ?? [];

        $categories = array_map(
            static fn (array $cat): Category => new Category(
                id: (string) $cat['id'],
                label: (string) $cat['label'],
                headerBg: (string) ($cat['header_bg'] ?? '#333333'),
                headerColor: (string) ($cat['header_color'] ?? '#ffffff'),
                headerGradient: (string) ($cat['header_gradient'] ?? ''),
                headerGradientEnd: (string) ($cat['header_gradient_end'] ?? ''),
                headerImage: (string) ($cat['header_image'] ?? ''),
                accentColor: (string) ($cat['accent_color'] ?? '#333333'),
                tagBg: (string) ($cat['tag_bg'] ?? '#f0f0f0'),
                tagColor: (string) ($cat['tag_color'] ?? '#333333'),
            ),
            $raw['categories'] ?? [],
        );

        $feedsByCategoryId = [];
        foreach ($raw['feeds'] ?? [] as $feedGroup) {
            $categoryId = (string) $feedGroup['category_id'];
            $feedsByCategoryId[$categoryId] = array_map('strval', $feedGroup['sources'] ?? []);
        }

        /** @var array<string, string> $sourceNames */
        $sourceNames = $raw['source_names'] ?? [];
        $maxPerFeed = (int) ($raw['max_articles_per_feed'] ?? 5);
        $maxPerCategory = (int) ($raw['max_articles_per_category'] ?? 8);

        return new NewsletterConfiguration(
            recipients: $recipients,
            categories: $categories,
            feedsByCategoryId: $feedsByCategoryId,
            sourceNames: $sourceNames,
            maxArticlesPerFeed: $maxPerFeed,
            maxArticlesPerCategory: $maxPerCategory,
        );
    }
}
