<?php

declare(strict_types=1);

namespace Indieinabox\Markdown;

use Indieinabox\Page;
use Indieinabox\Site;
use Indieinabox\Translations\UrlTranslations;

/**
 * Class LanguageProcessor
 * Processes language-related data for a given page.
 */
class LanguageProcessor
{
    /**
     * @var Site
     */
    private $site;

    /**
     * @var UrlTranslations
     */
    private $urlTranslations;

    /**
     * LanguageProcessor constructor.
     *
     * @param Site $site
     * @param UrlTranslations $urlTranslations
     */
    public function __construct(Site $site, UrlTranslations $urlTranslations)
    {
        $this->site = $site;
        $this->urlTranslations = $urlTranslations;
    }

    /**
     * Process the language-related data for a given page.
     *
     * @param Page $page
     * @return Page
     */
    public function processLanguage(Page $page): Page
    {
        $page = $this->setDefaultLanguage($page);
        $page = $this->processOtherLanguages($page);
        $page = $this->processLanguagePaths($page);
        $page = $this->processOriginalContent($page);
        return $page;
    }

    /**
     * Set the default language for the page.
     *
     * @param Page $page
     * @return Page
     */
    private function setDefaultLanguage(Page $page): Page
    {
        if (empty($page->localization->lang)) {
            $page->localization->lang = $this->site->localization->defaultLang;
        }
        return $page;
    }

    /**
     * Process other languages for the page.
     *
     * @param Page $page
     * @return Page
     */
    private function processOtherLanguages(Page $page): Page
    {
        $page->localization->otherlang = [$this->site->localization->lang];
        $page->localization->otherlangpath = [""];
        if (is_array($this->site->localization->lang)) {
            $page->localization->otherlang = $this->site->localization->lang;
            array_splice($page->localization->otherlang, array_search($page->localization->lang, $page->localization->otherlang, true), 1);

            foreach ($page->localization->otherlang as $key => $value) {
                $page->localization->otherlangpath[$key] = $value === $this->site->localization->defaultLang ? "" : $value . "/";
            }
        }
        return $page;
    }

    /**
     * Determine the language from the site configuration.
     *
     * @param Page $page
     * @return string
     */
    private function determineLanguageFromSite(Page $page): string
    {
        if (count($this->site->localization->lang) === 1 || $page->slug === "/") {
            return $this->site->localization->lang[0];
        }

        $first = explode("/", $page->slug)[0];
        if (in_array($first, $this->site->localization->lang, true)) {
            return $first;
        }

        return $this->site->localization->lang[0];
    }

    /**
     * Process language paths for the page.
     *
     * @param Page $page
     * @return Page
     */
    private function processLanguagePaths(Page $page): Page
    {
        $page->localization->langpath = $page->localization->lang === $this->site->localization->defaultLang ? "" : $page->localization->lang . "/";

        $nick = str_replace($page->localization->lang, '', $page->slug);
        $nick = explode("/", $nick);
        $nick = $nick[count($nick) - 2];
        $page->metadata->nick = $nick;

        return $page;
    }

    /**
     * Process original content for the page.
     *
     * @param Page $page
     * @return Page
     */
    private function processOriginalContent(Page $page): Page
    {
        if (!isset($page->content->originalcontent)) {
            $page->content->originalcontent = $this->determineOriginalContent($page);
        }

        if (!isset($page->localization->langslug)) {
            $page->localization->langslug = $this->generateLanguageSlugs($page);
        }

        return $page;
    }

    /**
     * Determine the original content for the page.
     *
     * @param Page $page
     * @return string
     */
    private function determineOriginalContent(Page $page): string
    {
        if ($page->localization->lang === $this->site->localization->defaultLang) {
            return $page->slug === "/" ? "index" : $page->slug;
        }

        if ($page->metadata->nick === "") {
            return "";
        }

        return $this->urlTranslations->getOriginalContent($page->metadata->nick, $page->localization->lang);
    }

    /**
     * Generate language slugs for the page.
     *
     * @param Page $page
     * @return array
     */
    private function generateLanguageSlugs(Page $page): array
    {
        $slugs = [];
        foreach ($page->localization->otherlang as $lang) {
            if ($lang === $this->site->localization->defaultLang) {
                $slugs[] = $page->content->originalcontent;
            } elseif ($page->content->originalcontent === "index") {
                $slugs[] = "";
            } else {
                $slugs[] = $this->urlTranslations->getTranslatedSlug($page->content->originalcontent, $lang);
            }
        }
        return $slugs;
    }
}
