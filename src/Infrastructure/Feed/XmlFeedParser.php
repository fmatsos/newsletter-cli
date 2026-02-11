<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Infrastructure\Feed;

use Akawaka\Newsletter\Domain\Model\Article;
use Akawaka\Newsletter\Domain\Port\FeedParserInterface;

final class XmlFeedParser implements FeedParserInterface
{
    private const string ATOM_NS = 'http://www.w3.org/2005/Atom';
    private const int MAX_ITEMS = 10;
    private const int MAX_DESCRIPTION_LENGTH = 500;

    #[\Override]
    public function parse(string $xmlContent, string $sourceName, string $categoryId): array
    {
        $previousUseErrors = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($xmlContent);
            if (false === $xml) {
                return [];
            }

            $articles = $this->parseRssItems($xml, $sourceName, $categoryId);
            if ([] !== $articles) {
                return $articles;
            }

            return $this->parseAtomEntries($xml, $sourceName, $categoryId);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseErrors);
        }
    }

    /**
     * @return list<Article>
     */
    private function parseRssItems(\SimpleXMLElement $xml, string $sourceName, string $categoryId): array
    {
        $items = $xml->xpath('//item');
        if (false === $items || [] === $items) {
            return [];
        }

        $articles = [];
        foreach (\array_slice($items, 0, self::MAX_ITEMS) as $item) {
            $title = $this->sanitize((string) ($item->title ?? ''));
            $link = $this->sanitize((string) ($item->link ?? ''));
            $description = $this->sanitize((string) ($item->description ?? ''));
            $pubDateStr = trim((string) ($item->pubDate ?? ''));

            $date = $this->parseRssDate($pubDateStr);
            $cleanDescription = $this->stripHtmlTags($description);

            $articles[] = new Article(
                title: $title,
                link: $link,
                description: mb_substr($cleanDescription, 0, self::MAX_DESCRIPTION_LENGTH),
                date: $date,
                source: $sourceName,
                categoryId: $categoryId,
            );
        }

        return $articles;
    }

    /**
     * @return list<Article>
     */
    private function parseAtomEntries(\SimpleXMLElement $xml, string $sourceName, string $categoryId): array
    {
        $xml->registerXPathNamespace('atom', self::ATOM_NS);

        $entries = $xml->xpath('//atom:entry');
        if (false === $entries || [] === $entries) {
            $entries = $xml->xpath('//entry');
        }
        if (false === $entries || [] === $entries) {
            return [];
        }

        $articles = [];
        foreach (\array_slice($entries, 0, self::MAX_ITEMS) as $entry) {
            $entry->registerXPathNamespace('atom', self::ATOM_NS);

            $title = $this->sanitize($this->getAtomText($entry, 'title'));
            $link = $this->getAtomLink($entry);
            $description = $this->sanitize($this->getAtomDescription($entry));

            $dateStr = $this->getAtomText($entry, 'updated')
                ?: $this->getAtomText($entry, 'published');

            $date = $this->parseAtomDate($dateStr);
            $cleanDescription = $this->stripHtmlTags($description);

            $articles[] = new Article(
                title: $title,
                link: $link,
                description: mb_substr($cleanDescription, 0, self::MAX_DESCRIPTION_LENGTH),
                date: $date,
                source: $sourceName,
                categoryId: $categoryId,
            );
        }

        return $articles;
    }

    private function getAtomText(\SimpleXMLElement $entry, string $field): string
    {
        $entry->registerXPathNamespace('atom', self::ATOM_NS);

        $nodes = $entry->xpath("atom:{$field}");
        if (false !== $nodes && [] !== $nodes) {
            return trim((string) $nodes[0]);
        }

        $nodes = $entry->xpath($field);
        if (false !== $nodes && [] !== $nodes) {
            return trim((string) $nodes[0]);
        }

        return '';
    }

    private function getAtomLink(\SimpleXMLElement $entry): string
    {
        $entry->registerXPathNamespace('atom', self::ATOM_NS);

        $links = $entry->xpath('atom:link');
        if (false !== $links && [] !== $links) {
            return $this->sanitize((string) ($links[0]['href'] ?? ''));
        }

        $links = $entry->xpath('link');
        if (false !== $links && [] !== $links) {
            $href = (string) ($links[0]['href'] ?? '');
            if ('' !== $href) {
                return $this->sanitize($href);
            }

            return $this->sanitize(trim((string) $links[0]));
        }

        return '';
    }

    private function getAtomDescription(\SimpleXMLElement $entry): string
    {
        foreach (['summary', 'content'] as $field) {
            $text = $this->getAtomText($entry, $field);
            if ('' !== $text) {
                return $text;
            }
        }

        return '';
    }

    private function parseRssDate(string $dateStr): ?\DateTimeImmutable
    {
        if ('' === $dateStr) {
            return null;
        }

        try {
            $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC2822, $dateStr);
            if (false !== $date) {
                return $date;
            }
        } catch (\Throwable) {
            // Fall through
        }

        try {
            return new \DateTimeImmutable($dateStr, new \DateTimeZone('UTC'));
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseAtomDate(string $dateStr): ?\DateTimeImmutable
    {
        if ('' === $dateStr) {
            return null;
        }

        try {
            return new \DateTimeImmutable($dateStr, new \DateTimeZone('UTC'));
        } catch (\Throwable) {
            return null;
        }
    }

    private function sanitize(string $text): string
    {
        if ('' === $text) {
            return '';
        }

        return mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }

    private function stripHtmlTags(string $text): string
    {
        return trim(strip_tags($text));
    }
}
