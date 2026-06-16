<?php

class MarkdownParser
{
    /**
     * @var \Indieinabox\Parsedown
     */
    private $parsedown;

    /**
     * @var object
     */
    private $site;

    /**
     * @var array
     */
    private $urltranslations;

    /**
     * @var string
     */
    private $base;

    /**
     * @param array $dependencies
     */
    public function __construct(array $dependencies)
    {
        foreach ($dependencies as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * @param  string $file
     * @return array|false|null
     */
    public function parse(string $file)
    {
        if (!$this->isValidFile($file)) {
            return false;
        }

        $fileInfo = $this->getFileInfo($file);
        $content = file_get_contents($file);

        $page = $this->extractFrontMatter($content);
        $content = $this->removeYamlFrontMatter($content);

        $page = $this->setTitle($page, $content);
        $page = $this->setDate($page, $file);
        $page = $this->processTags($page, $content);

        $content = $this->processContent($content);
        $page['content'] = trim($content, " \n\r\t");

        if (!$this->site->buildall && empty($page)) {
            return null;
        }

        $page = $this->processSlug($page, $file, $fileInfo);
        $page = $this->processLanguage($page);
        $page = $this->setLayout($page, $fileInfo);
        $page = $this->setMetadata($page);

        return $page;
    }

    /**
     * @param  string $file
     * @return bool
     */
    private function isValidFile(string $file): bool
    {
        if (!file_exists($file) || !is_readable($file)) {
            return false;
        }

        $ext = pathinfo($file, PATHINFO_EXTENSION);
        return in_array($ext, $this->site->support->support, true);
    }

    /**
     * @param  string $file
     * @return array
     */
    private function getFileInfo(string $file): array
    {
        return [
            'ext' => pathinfo($file, PATHINFO_EXTENSION),
            'filename' => pathinfo($file, PATHINFO_FILENAME)
        ];
    }

    /**
     * @param  string $content
     * @return array
     */
    private function extractFrontMatter(string &$content): array
    {
        $frontMatter = [];
        if (preg_match('/^---\s*\n((?:[^\n]*+\n)*)---\s*\n/sm', $content, $matches)) {
            $yaml = new \Indieinabox\Yaml();
            $frontMatter = $yaml->loadString($matches[1]);
        }
        return $frontMatter;
    }

    /**
     * @param  string $content
     * @return string
     */
    private function removeYamlFrontMatter(string $content): string
    {
        return preg_replace('/^---\s*\n((?:[^\n]*+\n)*)---\s*\n/sm', '', $content);
    }

    /**
     * @param  array  $page
     * @param  string $content
     * @return array
     */
    private function setTitle(array $page, string $content): array
    {
        if (!isset($page["title"])) {
            if (preg_match('/^# (.+)$/m', $content, $matches)) {
                $page["title"] = trim($matches[1]);
            } else {
                $page["title"] = $this->site->defaulttitle;
            }
        }
        return $page;
    }

    /**
     * @param  array  $page
     * @param  string $file
     * @return array
     */
    private function setDate(array $page, string $file): array
    {
        if (!isset($page["date"])) {
            $page["date"] = filemtime($file);
        }
        return $page;
    }

    /**
     * @param  array  $page
     * @param  string $content
     * @return array
     */
    private function processTags(array $page, string &$content): array
    {
        preg_match_all("/(?<!\\\\)\s#\w+/", $content, $tagmatches);

        $tags = array_map(
            function (string $tag): string {
                return strtolower(ltrim(trim($tag), "#"));
            },
            $tagmatches[0]
        );

        if (!isset($page["tags"])) {
            $page["tags"] = [];
        } elseif (!is_array($page["tags"])) {
            $page["tags"] = (array) $page["tags"];
        }

        $page["tags"] = array_unique(array_merge($page["tags"], $tags));
        $content = preg_replace('/^(?:\s*#\w+\s*?)*$/m', "", $content);

        return $page;
    }

    /**
     * @param  string $content
     * @return string
     */
    private function processContent(string $content): string
    {
        $content = $this->addTrailingSlashesToInternalLinks($content);
        return $this->parsedown->text($content);
    }

    /**
     * @param  string $content
     * @return string
     */
    private function addTrailingSlashesToInternalLinks(string $content): string
    {
        return preg_replace_callback(
            "/\[(.*?)\]\((.*?)\)/",
            function (array $matches): string {
                $link = $matches[2];
                $path_info = pathinfo($link);
                if (!isset($path_info["extension"])) {
                    $link = rtrim($link, "/") . "/";
                }
                return "[" . $matches[1] . "](" . $link . ")";
            },
            $content
        );
    }

    /**
     * @param  array  $page
     * @param  string $file
     * @param  array  $fileInfo
     * @return array
     */
    private function processSlug(array $page, string $file, array $fileInfo): array
    {
        $slug = $this->generateBaseSlug($file);
        $slug = $this->normalizeSlug($slug, $page, $fileInfo);
        $page["slug"] = $slug;
        $page["relpath"] = $this->calculateRelativePath($slug);
        return $page;
    }

    /**
     * @param  string $file
     * @return string
     */
    private function generateBaseSlug(string $file): string
    {
        $slug = str_replace($this->base . DS . $this->site->contentdir, "", $file);
        $slug = ltrim($slug, DS);
        return preg_replace("/^" . $this->site->contentdir . "/", "", $slug);
    }

    /**
     * @param  string $slug
     * @param  array  $page
     * @param  array  $fileInfo
     * @return string
     */
    private function normalizeSlug(string $slug, array $page, array $fileInfo): string
    {
        if ($fileInfo['filename'] === "index") {
            $slug = str_replace($fileInfo['filename'] . "." . $fileInfo['ext'], "", $slug);
        } else {
            $slug = str_replace("." . $fileInfo['ext'], "", $slug);
        }

        if (isset($page["slug"])) {
            $slug = str_replace($fileInfo['filename'], $page["slug"], $slug);
        }

        $slug = trim($slug, DS);
        $slug = str_replace(DS, "/", $slug);
        $slug = strtolower($slug);
        return rtrim($slug, "/") . "/";
    }

    /**
     * @param  string $slug
     * @return string
     */
    private function calculateRelativePath(string $slug): string
    {
        $parts = explode('/', rtrim($slug, '/'));

        if (count($parts) === 0 || $slug === '/') {
            return './';
        }

        return str_repeat('../', count($parts));
    }

    /**
     * @param  array $page
     * @return array
     */
    private function processLanguage(array $page): array
    {
        $page = $this->setDefaultLanguage($page);
        $page = $this->processOtherLanguages($page);
        $page = $this->processLanguagePaths($page);
        $page = $this->processOriginalContent($page);
        return $page;
    }

    /**
     * @param  array $page
     * @param  array $fileInfo
     * @return array
     */
    private function setLayout(array $page, array $fileInfo): array
    {
        $layout = $this->determineLayout($page);
        $page["layout"] = $layout;
        return $page;
    }

    /**
     * @param  array $page
     * @return array
     */
    private function setMetadata(array $page): array
    {
        if (!isset($page["default-category"])) {
            $page["default-category"] = "General";
        }

        if (!isset($page["category"])) {
            $page["category"] = $page["default-category"];
        }

        $kindResult = \Indieinabox\Helper::kind($page);
        $page["localizedkind"] = $kindResult["localized"];
        $page["kind"] = $kindResult["kind"];

        $dateResult = \Indieinabox\Helper::localizeddate($page);
        $page["localizeddate"] = $dateResult["long"];
        $page["isodate"] = $dateResult["iso"];

        return $page;
    }

    /**
     * @param  array $page
     * @return array
     */
    private function setDefaultLanguage(array $page): array
    {
        if (!isset($page["lang"])) {
            if (!isset($this->site->lang) || empty($this->site->lang)) {
                $page["lang"] = "en";
            } elseif (is_array($this->site->lang)) {
                $page["lang"] = $this->determineLanguageFromSite($page);
            } else {
                $page["lang"] = $this->site->lang;
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
    private function processOtherLanguages(array $page): array
    {
        if (is_array($this->site->lang)) {
            $page["otherlang"] = $this->site->lang;
            array_splice($page["otherlang"], array_search($page["lang"], $page["otherlang"], true), 1);

            foreach ($page["otherlang"] as $key => $value) {
                $page["otherlangpath"][$key] = $value === $this->site->defaultlang ? "" : $value . "/";
            }
        } else {
            $page["otherlang"] = [$this->site->lang];
            $page["otherlangpath"] = [""];
        }
        return $page;
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
    private function getOriginalContent(string $nick, string $lang = ""): string
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
            if ($lang === $this->site->defaultlang) {
                $slugs[] = $page["originalcontent"];
            } elseif ($page["originalcontent"] === "index") {
                $slugs[] = "";
            } else {
                $slugs[] = $this->getTranslatedSlug($page["originalcontent"], $lang);
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

    /**
     * @param  array $page
     * @return string
     */
    private function determineLayout(array $page): string
    {
        // Default layout
        $layout = "page";

        // Check if layout is specified in page metadata
        if (isset($page["layout"])) {
            $layoutFile = $this->base . DS . "resources" . DS . "views" . DS . $page["layout"] . ".php";
            if (file_exists($layoutFile) && is_readable($layoutFile)) {
                return $page["layout"];
            }
        }

        // Try to determine layout from folder structure
        $slugParts = explode("/", trim($page["slug"], "/"));
        if (count($slugParts) > 1) {
            $folderName = trim($slugParts[count($slugParts) - 2]);
            $layoutFile = $this->base . DS . "resources" . DS . "views" . DS . $folderName . ".php";
            if (file_exists($layoutFile) && is_readable($layoutFile)) {
                return $folderName;
            }
        }

        return $layout;
    }
}

/**
 * Global bridge function to parse markdown files using the legacy parser
 *
 * @param string $file Path to markdown file
 * @return \Indieinabox\Page|false|null
 */
function parse(string $file)
{
    global $site, $base, $parsedown, $urltranslations;
    static $parser = null;
    if ($parser === null) {
        $parser = new MarkdownParser([
            'site' => $site,
            'base' => $base,
            'parsedown' => $parsedown,
            'urltranslations' => $urltranslations ?? []
        ]);
    }
    $pageData = $parser->parse($file);
    if ($pageData === false || $pageData === null) {
        return $pageData;
    }
    return \Indieinabox\Page::fromArray($pageData);
}
