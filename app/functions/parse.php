<?php

use Indieinabox\Markdown\ContentProcessor;
use Indieinabox\Markdown\FileProcessor;
use Indieinabox\Markdown\LanguageProcessor;
use Indieinabox\Translations\UrlTranslations;

/**
 * Global bridge function — parses a Markdown file and returns a Page object.
 *
 * Instantiates the namespaced Indieinabox\MarkdownParser once per request and
 * caches it in a static variable to avoid re-creating sub-processors on every call.
 *
 * @param  string $file Absolute path to the Markdown file.
 * @return \Indieinabox\Page|false|null
 */
function parse(string $file)
{
    global $site, $base, $parsedown, $urltranslations;

    static $parser = null;

    if ($parser === null) {
        $fileProcessor     = new FileProcessor($site, $base);
        $contentProcessor  = new ContentProcessor($parsedown);
        $urlTranslations   = new UrlTranslations($urltranslations ?? []);
        $languageProcessor = new LanguageProcessor($site, $urlTranslations);

        $parser = new \Indieinabox\MarkdownParser(
            $fileProcessor,
            $contentProcessor,
            $languageProcessor,
            $site
        );
    }

    return $parser->parse($file);
}
