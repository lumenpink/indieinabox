<?php

declare(strict_types=1);

namespace Indieinabox\Feeds\Generators;

use DateTimeZone;
use Indieinabox\Entry\Entry;
use Indieinabox\Feeds\FeedGeneratorInterface;
use Indieinabox\Site\Site;
use XMLWriter;

/**
 * Generates an Atom 1.0 feed from Entry objects.
 */
class AtomFeedGenerator implements FeedGeneratorInterface
{
    #[\Override]
    public function getFilename(): string
    {
        return 'atom.xml';
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
        $siteAuthor = $site->metadata->author ?? 'Site Owner';

        $feedUrl = $fqdn . ($lang !== ($site->localization->defaultLang ?? 'en') ? '/' . $lang : '') . '/' . $this->getFilename();

        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('feed');
        $xml->writeAttribute('xmlns', 'http://www.w3.org/2005/Atom');

        $xml->writeElement('title', $siteTitle);
        if ($siteDesc !== '') {
            $xml->writeElement('subtitle', $siteDesc);
        }

        $xml->startElement('link');
        $xml->writeAttribute('href', $feedUrl);
        $xml->writeAttribute('rel', 'self');
        $xml->endElement();

        $xml->startElement('link');
        $xml->writeAttribute('href', $fqdn . '/');
        $xml->endElement();

        $xml->writeElement('id', $fqdn . '/');
        $xml->writeElement('updated', gmdate('Y-m-d\TH:i:s\Z'));

        // Feed Author
        $xml->startElement('author');
        $xml->writeElement('name', $siteAuthor);
        $xml->endElement();

        foreach ($feedEntries as $entry) {
            $postUrl = $this->resolveEntryUrl($entry, $fqdn);
            $publishedIso = $entry->getPublishedAt()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
            $updatedIso = $entry->getUpdatedAt()
                ? $entry->getUpdatedAt()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z')
                : $publishedIso;

            $xml->startElement('entry');
            $xml->writeElement('title', $entry->getTitle() ?? substr(strip_tags($entry->getContent()), 0, 60));

            $xml->startElement('link');
            $xml->writeAttribute('href', $postUrl);
            $xml->endElement();

            $xml->writeElement('id', $postUrl);
            $xml->writeElement('published', $publishedIso);
            $xml->writeElement('updated', $updatedIso);

            if ($entry->getSummary() !== null) {
                $xml->writeElement('summary', $entry->getSummary());
            }

            // Author override if entry has custom author
            $author = $entry->getAuthor();
            if (!empty($author['name']) && $author['name'] !== $siteAuthor) {
                $xml->startElement('author');
                $xml->writeElement('name', $author['name']);
                if (!empty($author['url'])) {
                    $xml->writeElement('uri', $author['url']);
                }
                $xml->endElement();
            }

            // Content (HTML + poll fallback)
            $contentHtml = $entry->getContent();
            if ($entry->hasPoll()) {
                /** @psalm-suppress InvalidArgument — getPoll shape is a superset of what renderPollFallback expects */
                $contentHtml .= $this->renderPollFallback($entry->getPoll());
            }

            $xml->startElement('content');
            $xml->writeAttribute('type', 'html');
            $xml->writeCdata($contentHtml);
            $xml->endElement();

            $xml->endElement(); // entry
        }

        $xml->endElement(); // feed

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
            if (!$entry->shouldSyndicateTo('atom')) {
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
