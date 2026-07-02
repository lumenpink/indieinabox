<?php

declare(strict_types=1);

namespace Indieinabox\Feeds;

use DateTimeZone;
use Indieinabox\Page;
use Indieinabox\Site\Metadata;
use Indieinabox\Helper;

/**
 * Class FeedManager
 */
class FeedManager
{
    /**
     * Filter and prepare pages for feed generation.
     *
     * @param Page[] $pages
     * @param int $limit
     * @return Page[]
     */
    private function preparePages(array $pages, int $limit): array
    {
        // Filter pages
        $filteredPages = array_filter($pages, function (Page $page) {
            // Exclude drafts
            if (in_array("draft", $page->metadata->tags)) {
                return false;
            }
            
            // Exclude structural pages not shown on home
            if (!Helper::removegeneric($page)) {
                return false;
            }
            
            // Exclude pages explicitly hidden from RSS
            $hideOnRss = $page->metadata->hide_on_rss ?? false;
            if ($hideOnRss === true || strtolower((string)$hideOnRss) === 'yes') {
                return false;
            }
            
            return true;
        });

        // Sort chronologically descending (newest first)
        usort($filteredPages, function (Page $a, Page $b) {
            return $b->date <=> $a->date;
        });

        // Apply limit if > 0
        if ($limit > 0) {
            $filteredPages = array_slice($filteredPages, 0, $limit);
        }

        return $filteredPages;
    }

    /**
     * Generates an RSS 2.0 feed.
     *
     * @param Page[] $pages
     * @param string $outputFile
     * @param string $fqdn
     * @param Metadata $metadata
     * @param int $limit
     */
    public function generateRss(array $pages, string $outputFile, string $fqdn, Metadata $metadata, int $limit = 20): void
    {
        $feedPages = $this->preparePages($pages, $limit);

        $fqdn = rtrim($fqdn, '/');
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        
        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $xml->writeAttribute('xmlns:content', 'http://purl.org/rss/1.0/modules/content/');
        
        $xml->startElement('channel');
        
        $xml->writeElement('title', $metadata->sitename);
        $xml->writeElement('description', $metadata->description);
        $xml->writeElement('link', $fqdn . '/');
        $xml->writeElement('generator', 'Indieinabox');
        $xml->writeElement('lastBuildDate', gmdate('r'));
        
        // Atom self link
        $xml->startElement('atom:link');
        $xml->writeAttribute('href', $fqdn . '/rss.xml');
        $xml->writeAttribute('rel', 'self');
        $xml->writeAttribute('type', 'application/rss+xml');
        $xml->endElement();

        foreach ($feedPages as $page) {
            $postUrl = $fqdn . '/' . ltrim($page->slug, '/');
            $date = clone $page->date;
            $date->setTimezone(new DateTimeZone('UTC'));
            
            $xml->startElement('item');
            
            $xml->writeElement('title', $page->title);
            $xml->writeElement('link', $postUrl);
            $xml->writeElement('guid', $postUrl);
            $xml->writeElement('pubDate', $date->format('r'));

            // Description / content
            $content = $page->content ?? '';
            // Basic HTML content (can include images etc)
            $xml->startElement('description');
            $xml->writeCdata((string)$content);
            $xml->endElement();
            
            $xml->endElement(); // item
        }

        $xml->endElement(); // channel
        $xml->endElement(); // rss
        
        file_put_contents($outputFile, $xml->outputMemory());
    }

    /**
     * Generates an Atom 1.0 feed.
     *
     * @param Page[] $pages
     * @param string $outputFile
     * @param string $fqdn
     * @param Metadata $metadata
     * @param int $limit
     */
    public function generateAtom(array $pages, string $outputFile, string $fqdn, Metadata $metadata, int $limit = 20): void
    {
        $feedPages = $this->preparePages($pages, $limit);

        $fqdn = rtrim($fqdn, '/');
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        
        $xml->startElement('feed');
        $xml->writeAttribute('xmlns', 'http://www.w3.org/2005/Atom');
        
        $xml->writeElement('title', $metadata->sitename);
        $xml->writeElement('subtitle', $metadata->description);
        $xml->startElement('link');
        $xml->writeAttribute('href', $fqdn . '/atom.xml');
        $xml->writeAttribute('rel', 'self');
        $xml->endElement();
        
        $xml->startElement('link');
        $xml->writeAttribute('href', $fqdn . '/');
        $xml->endElement();
        
        $xml->writeElement('id', $fqdn . '/');
        $xml->writeElement('updated', gmdate('Y-m-d\TH:i:s\Z'));
        
        // Author
        $xml->startElement('author');
        $xml->writeElement('name', $metadata->author ?? 'Unknown');
        $xml->endElement();

        foreach ($feedPages as $page) {
            $postUrl = $fqdn . '/' . ltrim($page->slug, '/');
            $date = clone $page->date;
            $date->setTimezone(new DateTimeZone('UTC'));
            
            $xml->startElement('entry');
            $xml->writeElement('title', $page->title);
            
            $xml->startElement('link');
            $xml->writeAttribute('href', $postUrl);
            $xml->endElement();
            
            $xml->writeElement('id', $postUrl);
            $xml->writeElement('updated', $date->format('Y-m-d\TH:i:s\Z'));
            
            $content = $page->content ?? '';
            $xml->startElement('content');
            $xml->writeAttribute('type', 'html');
            $xml->writeCdata((string)$content);
            $xml->endElement();
            
            $xml->endElement(); // entry
        }

        $xml->endElement(); // feed
        
        file_put_contents($outputFile, $xml->outputMemory());
    }
}
