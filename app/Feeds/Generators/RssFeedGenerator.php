<?php

declare(strict_types=1);

namespace Indieinabox\Feeds\Generators;

use DateTimeZone;
use Indieinabox\Entry\Entry;
use Indieinabox\Feeds\FeedGeneratorInterface;
use Indieinabox\Site\Site;
use XMLWriter;

/**
 * Generates an RSS 2.0 feed from Entry objects.
 */
class RssFeedGenerator implements FeedGeneratorInterface
{
    #[\Override]
    public function getFilename(): string
    {
        return 'rss.xml';
    }

    /**
     * @param Entry[] $entries
     */
    #[\Override]
    public function generate(array $entries, string $outputPath, Site $site, string $lang = 'en'): void
    {
        $limit = (int) ($site->options->feed_limit ?? 20);
        $feedEntries = $this->prepareEntries($entries, $limit);

        $fqdn = rtrim($site->metadata->fqdn ?? 'http://localhost', '/');
        $siteTitle = $site->metadata->sitename ?? 'Site';
        $siteDesc = $site->metadata->description ?? '';

        $feedUrl = $fqdn . ($lang !== ($site->localization->defaultLang ?? 'en') ? '/' . $lang : '') . '/' . $this->getFilename();

        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $xml->writeAttribute('xmlns:content', 'http://purl.org/rss/1.0/modules/content/');

        $xml->startElement('channel');
        $xml->writeElement('title', $siteTitle);
        $xml->writeElement('description', $siteDesc);
        $xml->writeElement('link', $fqdn . '/');
        $xml->writeElement('generator', 'Indieinabox');
        $xml->writeElement('lastBuildDate', gmdate('r'));

        // Atom self-link
        $xml->startElement('atom:link');
        $xml->writeAttribute('href', $feedUrl);
        $xml->writeAttribute('rel', 'self');
        $xml->writeAttribute('type', 'application/rss+xml');
        $xml->endElement();

        foreach ($feedEntries as $entry) {
            $postUrl = $this->resolveEntryUrl($entry, $fqdn);
            $pubDate = $entry->getPublishedAt()->setTimezone(new DateTimeZone('UTC'))->format('r');

            $xml->startElement('item');
            $xml->writeElement('title', $entry->getTitle() ?? substr(strip_tags($entry->getContent()), 0, 60));
            $xml->writeElement('link', $postUrl);
            $xml->writeElement('guid', $postUrl);
            $xml->writeElement('pubDate', $pubDate);

            // Author if present
            $author = $entry->getAuthor();
            if (!empty($author['name'])) {
                $xml->writeElement('author', $author['name']);
            }

            // Description / Content (including poll fallback if present)
            $contentHtml = $entry->getContent();
            if ($entry->hasPoll()) {
                /** @psalm-suppress InvalidArgument — getPoll shape is a superset of what renderPollFallback expects */
                $contentHtml .= $this->renderPollFallback($entry->getPoll());
            }

            $xml->startElement('description');
            $xml->writeCdata($contentHtml);
            $xml->endElement();

            // Enclosures (media attachments)
            foreach ($entry->getAttachments() as $attachment) {
                if (!empty($attachment['url']) && !empty($attachment['mime'])) {
                    $xml->startElement('enclosure');
                    $xml->writeAttribute('url', $attachment['url']);
                    $xml->writeAttribute('type', $attachment['mime']);
                    $xml->writeAttribute('length', (string) ($attachment['size'] ?? 0));
                    $xml->endElement();
                }
            }

            $xml->endElement(); // item
        }

        $xml->endElement(); // channel
        $xml->endElement(); // rss

        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($outputPath, $xml->outputMemory());
    }

    /**
     * @param Entry[] $entries
     * @return Entry[]
     */
    private function prepareEntries(array $entries, int $limit): array
    {
        $filtered = array_filter($entries, function (Entry $entry) {
            if (!$entry->shouldSyndicateTo('rss')) {
                return false;
            }

            $hideOnRss = $entry->getMetadataItem('hide_on_rss');
            if ($hideOnRss === true || strtolower((string) $hideOnRss) === 'yes') {
                return false;
            }

            return true;
        });

        // Newest first
        usort($filtered, function (Entry $a, Entry $b) {
            return $b->getPublishedAt() <=> $a->getPublishedAt();
        });

        if ($limit > 0) {
            $filtered = array_slice($filtered, 0, $limit);
        }

        return $filtered;
    }

    private function resolveEntryUrl(Entry $entry, string $fqdn): string
    {
        if ($entry->isFederated() && filter_var($entry->getSourceUrl(), FILTER_VALIDATE_URL)) {
            return $entry->getSourceUrl();
        }

        return $fqdn . '/' . ltrim($entry->getSlug(), '/');
    }

    /**
     * @param array{options?: array<array{title: string, votes?: int}>}|null $poll
     */
    private function renderPollFallback(?array $poll): string
    {
        if (empty($poll['options'])) {
            return '';
        }

        $html = "\n<div class=\"poll-fallback\"><p><strong>Poll:</strong></p>\n<ul>\n";
        foreach ($poll['options'] as $opt) {
            $optTitle = htmlspecialchars($opt['title'] ?? 'Option');
            $votes = (int) ($opt['votes'] ?? 0);
            $html .= sprintf('<li>%s (%d votes)</li>%s', $optTitle, $votes, "\n");
        }
        $html .= "</ul></div>\n";

        return $html;
    }
}
