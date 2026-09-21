<?php

declare(strict_types=1);

namespace Indieinabox\Feeds\Generators;

use DateTimeZone;
use Indieinabox\Entry\Entry;
use Indieinabox\Feeds\FeedGeneratorInterface;
use Indieinabox\Taxonomy\KindHelper;
use Indieinabox\Site\Site;
use Indieinabox\Twtxt\TwtxtManager;

/**
 * Generates a twtxt.txt feed from Entry objects.
 */
class TwtxtFeedGenerator implements FeedGeneratorInterface
{
    #[\Override]
    public function getFilename(): string
    {
        return 'twtxt.txt';
    }

    /**
     * @param Entry[] $entries
     */
    #[\Override]
    public function generate(array $entries, string $outputPath, Site $site, string $lang = 'en'): void
    {
        $feedEntries = $this->prepareEntries($entries);
        $fqdn = rtrim($site->metadata->fqdn ?? 'http://localhost', '/');
        $twtxtConfig = $site->twtxt;

        $content = '';

        // Add standard and Yarns metadata comments
        $siteTitle = $site->metadata->title ?? ($site->metadata->defaultTitle ?? null);
        if (!empty($siteTitle)) {
            $content .= "# title = {$siteTitle}\n";
        }
        $content .= "# link = {$fqdn}/twtxt.txt\n";

        if (!empty($twtxtConfig->nick)) {
            $content .= "# nick = {$twtxtConfig->nick}\n";
        }
        $author = $twtxtConfig->nick ?: ($site->metadata->author ?? null);
        if (!empty($author)) {
            $content .= "# author = {$author}\n";
        }
        $description = $twtxtConfig->description ?: ($site->metadata->description ?? null);
        if (!empty($description)) {
            $content .= "# description = {$description}\n";
        }
        if (!empty($twtxtConfig->avatar)) {
            $content .= "# avatar = {$twtxtConfig->avatar}\n";
        }
        foreach ((array) ($twtxtConfig->following ?? []) as $follow) {
            if (!empty($follow['nick']) && !empty($follow['url'])) {
                $content .= "# follow = {$follow['nick']} {$follow['url']}\n";
            }
        }
        if ($content !== '') {
            $content .= "\n";
        }

        foreach ($feedEntries as $entry) {
            $timestamp = $entry->getPublishedAt()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
            $message = $this->formatEntryToTwtxt($entry, $fqdn, $site);

            if ($message !== '') {
                $content .= "{$timestamp}\t{$message}\n";
            }
        }

        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($outputPath, $content);
    }

    /**
     * @param Entry[] $entries
     * @return Entry[]
     */
    private function prepareEntries(array $entries): array
    {
        $filtered = array_filter($entries, function (Entry $entry) {
            if (!$entry->shouldSyndicateTo('twtxt')) {
                return false;
            }

            // Exclude structural pages not meant for feed
            if ($entry->isPage()) {
                return false;
            }

            return true;
        });

        // Twtxt specification: chronological ascending (oldest first)
        usort($filtered, function (Entry $a, Entry $b) {
            return $a->getPublishedAt() <=> $b->getPublishedAt();
        });

        return $filtered;
    }

    private function formatEntryToTwtxt(Entry $entry, string $fqdn, Site $site): string
    {
        $postUrl = $this->resolveEntryUrl($entry, $fqdn);
        $kind = $entry->getKind();
        $displayMode = $site->config['kinds'][$kind]['display_mode'] ?? (KindHelper::getKindConfig($kind)['display_mode'] ?? 'default');

        $subjectTag = '';
        $metaSubject = $entry->getMetadata()['subject'] ?? null;
        if (!empty($metaSubject)) {
            $subjectTag = '(#' . ltrim($metaSubject, '#') . ')';
        }

        if ($displayMode === 'full_content' || $entry->isNote()) {
            $text = TwtxtManager::cleanMessage($entry->getRawContent() ?: strip_tags($entry->getContent()));
            $text = preg_replace('/^\[[A-Z]{2,}\]\s+/', '', $text) ?? '';
            if ($subjectTag !== '' && !str_contains($text, $subjectTag)) {
                $text = $subjectTag . ' ' . $text;
            }
            return $text;
        }

        if ($displayMode === 'thumbnail_snippet') {
            $caption = TwtxtManager::cleanMessage($entry->getRawContent() ?: strip_tags($entry->getContent()));
            if ($caption === '') {
                $caption = $entry->getTitle() ?? '';
            }

            $caption = preg_replace('/^\[[A-Z]{2,}\]\s+/', '', $caption) ?? '';
            if (mb_strlen($caption) > 140) {
                $caption = mb_substr($caption, 0, 137) . '...';
            }

            $imageUrl = '';
            $attachments = $entry->getAttachments();
            if (!empty($attachments)) {
                $img = $attachments[0]['url'];
                if (preg_match('/^https?:\/\//i', $img)) {
                    $imageUrl = $img;
                } else {
                    $imageUrl = rtrim($fqdn, '/') . '/' . ltrim($img, '/');
                }
            }

            $subjectPrefix = $subjectTag !== '' ? $subjectTag . ' ' : '';
            if ($imageUrl !== '') {
                return "{$subjectPrefix}{$caption} {$imageUrl} - {$postUrl}";
            }
            return "{$subjectPrefix}{$caption} - {$postUrl}";
        }

        // Articles / Generic Pages
        $title = $entry->getTitle() ?? '';
        $title = preg_replace('/^\[[A-Z]{2,}\]\s+/', '', $title) ?? '';

        $snippet = TwtxtManager::cleanMessage($entry->getRawContent() ?: strip_tags($entry->getContent()));
        $snippet = preg_replace('/^\[[A-Z]{2,}\]\s+/', '', $snippet) ?? '';

        if (mb_strlen($snippet) > 100) {
            $snippet = mb_substr($snippet, 0, 97) . '...';
        }

        $subjectPrefix = $subjectTag !== '' ? $subjectTag . ' ' : '';

        if ($title !== '' && $snippet !== '') {
            return "{$subjectPrefix}{$title}: {$snippet} - {$postUrl}";
        }
        if ($title !== '') {
            return "{$subjectPrefix}{$title} - {$postUrl}";
        }
        return $snippet !== '' ? "{$subjectPrefix}{$snippet} - {$postUrl}" : '';
    }

    private function resolveEntryUrl(Entry $entry, string $fqdn): string
    {
        if ($entry->isFederated() && filter_var($entry->getSourceUrl(), FILTER_VALIDATE_URL)) {
            return $entry->getSourceUrl();
        }

        return $fqdn . '/' . ltrim($entry->getSlug(), '/');
    }
}
