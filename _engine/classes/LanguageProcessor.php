<?php

namespace Indieinabox;

class LanguageProcessor
{
    /**
     * @var object
     */
    private $site;

    /**
     * @var array
     */
    private $urltranslations;

    /**
     * @param object $site
     * @param array  $urltranslations
     */
    public function __construct(object $site, array $urltranslations)
    {
        $this->site = $site;
        $this->urltranslations = $urltranslations;
    }

    /**
     * @param  array $page
     * @return array
     */
    public function processLanguage(array $page): array
    {
        $page = $this->setDefaultLanguage($page);
        $page = $this->processOtherLanguages($page);
        $page = $this->processLanguagePaths($page);
        $page = $this->processOriginalContent($page);
        return $page;
    }

    /**
     * @param  array $page
     * @return array
     */
    private function setDefaultLanguage(array $page): array
    {
        $page["lang"] = $this->site->lang;
        if (!isset($page["lang"])) {
            if (!isset($this->site->lang) || empty($this->site->lang)) {
                $page["lang"] = "en";
            } elseif (is_array($this->site->lang)) {
                $page["lang"] = $this->determineLanguageFromSite($page);
            }
        }
        return $page;
    }

    /**
     * @param  array $page
     * @return array
     */
    private function processOtherLanguages(array $page): array
    {
        $page["otherlang"] = [$this->site->lang];
        $page["otherlangpath"] = [""];
        if (is_array($this->site->lang)) {
            $page["otherlang"] = $this->site->lang;
            array_splice($page["otherlang"], array_search($page["lang"], $page["otherlang"], true), 1);

            foreach ($page["otherlang"] as $key => $value) {
                $page["otherlangpath"][$key] = $value . "/";
                $page["otherlangpath"][$key] = $value === $this->site->defaultlang ?: "";
            }
        }
        return $page;
    }

    /**
     * @param  array $page
     * @return string
     */
    private function determineLanguageFromSite(array $page): string
    {
        if (count($this->site->lang) === 1 || $page["slug"] === "/") {
            return $this->site->lang[0];
        }

        $first = explode("/", $page["slug"])[0];
        if (in_array($first, $this->site->lang, true)) {
            return $first;
        }

        return $this->site->lang[0];
    }

    /**
     * @param  array $page
     * @return array
     */
    private function processLanguagePaths(array $page): array
    {
        $page["langpath"] = $page["lang"] === $this->site->defaultlang ? "" : $page["lang"] . "/";

        $page["nick"] = str_replace($page["lang"], '', $page["slug"]);
        $page["nick"] = explode("/", $page["nick"]);
        $page["nick"] = $page["nick"][count($page["nick"]) - 2];

        return $page;
    }

    /**
     * @param  array $page
     * @return array
     */
    private function processOriginalContent(array $page): array
    {
        if (!isset($page["originalcontent"])) {
            $page["originalcontent"] = $this->determineOriginalContent($page);
        }

        if (!isset($page["langslug"])) {
            $page["langslug"] = $this->generateLanguageSlugs($page);
        }

        return $page;
    }

    /**
     * @param  array $page
     * @return string
     */
    private function determineOriginalContent(array $page): string
    {
        if ($page["lang"] === $this->site->defaultlang) {
            return $page["slug"] === "/" ? "index" : $page["slug"];
        }

        if ($page["nick"] === "") {
            return "";
        }

        return $this->getOriginalContent($page["nick"], $page["lang"]);
    }

    /**
     * @param  string $nick
     * @param  string $lang
     * @return string
     */
    private function getOriginalContent(string $nick): string
    {
        // This should be implemented based on your specific content management system
        // Here's a basic implementation assuming URL translations are stored in $this->urltranslations
        if (isset($this->urltranslations[$nick][$this->site->defaultlang])) {
            return $this->urltranslations[$nick][$this->site->defaultlang];
        }
        return $nick;
    }

    /**
     * @param  array $page
     * @return array
     */
    private function generateLanguageSlugs(array $page): array
    {
        $slugs = [];
        foreach ($page["otherlang"] as $lang) {
            $slugs[] = $this->getTranslatedSlug($page["originalcontent"], $lang);
            if ($lang === $this->site->defaultlang) {
                $slugs[] = $page["originalcontent"];
            } elseif ($page["originalcontent"] === "index") {
                $slugs[] = "";
            }
        }
        return $slugs;
    }

    /**
     * @param  string $originalContent
     * @param  string $lang
     * @return string
     */
    private function getTranslatedSlug(string $originalContent, string $lang): string
    {
        if (isset($this->urltranslations[$originalContent][$lang])) {
            return $this->urltranslations[$originalContent][$lang];
        }
        return $originalContent;
    }
}
