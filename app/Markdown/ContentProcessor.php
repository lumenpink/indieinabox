<?php

declare(strict_types=1);

namespace Indieinabox\Markdown;

use Indieinabox\Yaml;

/**
 * Class ContentProcessor
 */
class ContentProcessor
{
    /**
     * @var ASTParser
     */
    private ASTParser $astParser;

    /**
     * @var HtmlRenderer
     */
    private HtmlRenderer $htmlRenderer;

    /**
     * Method __construct
     */
    public function __construct()
    {
        $this->astParser = new ASTParser();
        $this->htmlRenderer = new HtmlRenderer();
    }

    /**
     * @param string $content
     *
     * @return array<string, mixed>
     */
    public function extractFrontMatter(string &$content): array
    {
        $frontMatter = [];
        if (preg_match('/^---\s*\n((?:[^\n]*+\n)*)---\s*\n/sm', $content, $matches)) {
            $yaml = new Yaml();
            $frontMatter = $yaml->loadString($matches[1]);
        }
        return $frontMatter;
    }

    /**
     * @param string $content
     *
     * @return string
     */
    public function removeYamlFrontMatter(string $content): string
    {
        return preg_replace('/^---\s*\n((?:[^\n]*+\n)*)---\s*\n/sm', '', $content);
    }

    /**
     * Set the date from file modification time if not provided in frontmatter.
     *
     * @param array<string, mixed>  $page
     * @param string $file
     * @return array<string, mixed>
     */
    public function setDate(array $page, string $file): array
    {
        if (!isset($page["date"])) {
            $page["date"] = filemtime($file);
        }
        return $page;
    }

    /**
     * @param array<string, mixed>  $page
     * @param string $content
     * @param string $defaultTitle
     *
     * @return array<string, mixed>
     */
    public function setTitle(array $page, string $content, string $defaultTitle): array
    {
        if (!isset($page["title"])) {
            if (preg_match('/^# (.+)$/m', $content, $matches)) {
                $page["title"] = trim($matches[1]);
            } else {
                $page["title"] = $defaultTitle;
            }
        }
        return $page;
    }

    /**
     * @param array<string, mixed>  $page
     * @param string $content
     *
     * @return array<string, mixed>
     */
    public function processTags(array $page, string &$content): array
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
        $content = (string) preg_replace('/^(?:\s*#\w+\s*?)*$/m', "", $content);

        return $page;
    }

    /**
     * @param string $content
     * @param \Indieinabox\Page|null $page
     *
     * @return string
     */
    public function processContent(string $content, ?\Indieinabox\Page $page = null): string
    {
        $content = $this->addTrailingSlashesToInternalLinks($content);
        $ast = $this->astParser->parse($content);
        if ($page !== null) {
            $this->htmlRenderer->setPage($page);
        }
        return $this->htmlRenderer->render($ast);
    }

    /**
     * @param string $content
     *
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
}
