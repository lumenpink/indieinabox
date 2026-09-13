<?php

declare(strict_types=1);

namespace Indieinabox\SiteBuilder;

use Indieinabox\Entry\Entry;
use Indieinabox\Feeds\FeedGeneratorInterface;
use Indieinabox\Feeds\Generators\AtomFeedGenerator;
use Indieinabox\Feeds\Generators\RssFeedGenerator;
use Indieinabox\Feeds\Generators\TwtxtFeedGenerator;
use Indieinabox\Page;
use Indieinabox\Pages;
use Indieinabox\Site;

/**
 * Orchestrates generation and publishing of feeds across all languages and protocols.
 */
class FeedPublisher
{
    private Site $site;
    /** @var FeedGeneratorInterface[] */
    private array $generators;

    /**
     * @param Site $site
     * @param FeedGeneratorInterface[]|null $generators
     */
    public function __construct(Site $site, ?array $generators = null)
    {
        $this->site = $site;
        $this->generators = $generators ?? [
            new RssFeedGenerator(),
            new AtomFeedGenerator(),
            new TwtxtFeedGenerator(),
        ];
    }

    /**
     * Publishes feeds for all configured languages from the Pages collection.
     */
    public function publishFeeds(Pages $pages): void
    {
        $base = $this->site->paths->baseDir;
        $outDirHtml = $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDirHtml;
        $outDirGemini = $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDirGemini;
        $outDirGopher = $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDirGopher;

        if (!is_dir($outDirHtml)) {
            mkdir($outDirHtml, 0777, true);
        }
        if (!is_dir($outDirGemini)) {
            mkdir($outDirGemini, 0777, true);
        }
        if (!is_dir($outDirGopher)) {
            mkdir($outDirGopher, 0777, true);
        }

        $defaultLang = $this->site->localization->defaultLang ?? 'en';

        $entriesByLang = [];
        /** @var Page $page */
        foreach ($pages as $page) {
            $entry = $page->toEntry();
            $lang = $entry->getLang() ?: $defaultLang;
            $entriesByLang[$lang][] = $entry;
        }

        echo "Generating feeds...\n";
        foreach ($entriesByLang as $lang => $entries) {
            $langDirHtml = $outDirHtml;
            $langDirGemini = $outDirGemini;
            $langDirGopher = $outDirGopher;

            if ($lang !== $defaultLang) {
                $langDirHtml .= DIRECTORY_SEPARATOR . $lang;
                $langDirGemini .= DIRECTORY_SEPARATOR . $lang;
                $langDirGopher .= DIRECTORY_SEPARATOR . $lang;
                if (!is_dir($langDirHtml)) {
                    mkdir($langDirHtml, 0777, true);
                }
                if (!is_dir($langDirGemini)) {
                    mkdir($langDirGemini, 0777, true);
                }
                if (!is_dir($langDirGopher)) {
                    mkdir($langDirGopher, 0777, true);
                }
            }

            foreach ($this->generators as $generator) {
                $outFile = $langDirHtml . DIRECTORY_SEPARATOR . $generator->getFilename();
                $generator->generate($entries, $outFile, $this->site, $lang);
                \Indieinabox\SiteBuilder::addManifest($outFile);

                // Twtxt replicate to Gemini and Gopher
                if ($generator instanceof TwtxtFeedGenerator && file_exists($outFile)) {
                    $geminiTwtxt = $langDirGemini . DIRECTORY_SEPARATOR . $generator->getFilename();
                    $gopherTwtxt = $langDirGopher . DIRECTORY_SEPARATOR . $generator->getFilename();
                    copy($outFile, $geminiTwtxt);
                    copy($outFile, $gopherTwtxt);
                    \Indieinabox\SiteBuilder::addManifest($geminiTwtxt);
                    \Indieinabox\SiteBuilder::addManifest($gopherTwtxt);
                }
            }
        }
    }
}
