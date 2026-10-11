<?php

declare(strict_types=1);

namespace Indieinabox\Markdown;

use Indieinabox\Localization\Translator;
use Indieinabox\Media\AttachmentResolver;
use Indieinabox\Media\ImageProcessor;
use Indieinabox\Page\Page;
use Indieinabox\Support\TextParser;
use Indieinabox\Taxonomy\KindHelper;

/**
 * Class HtmlRenderer
 */
class HtmlRenderer implements RendererInterface
{
    /**
     * @var \Indieinabox\Page\Page|null
     */
    private ?\Indieinabox\Page\Page $page = null;

    /**
     * @var \Indieinabox\Site\Site|null
     */
    private ?\Indieinabox\Site\Site $site = null;

    /**
     * @var \Indieinabox\Page\Pages|null
     */
    private ?\Indieinabox\Page\Pages $pages = null;

    public function __construct(
        ?\Indieinabox\Page\Page $page = null,
        ?\Indieinabox\Site\Site $site = null,
        ?\Indieinabox\Page\Pages $pages = null
    ) {
        $this->page = $page;
        $this->site = $site;
        $this->pages = $pages;
    }

    private function getSite(): ?\Indieinabox\Site\Site
    {
        if ($this->site !== null) {
            return $this->site;
        }
        $container = \Indieinabox\Core\Container::getInstance();
        return $container->has(\Indieinabox\Site\Site::class) ? $container->get(\Indieinabox\Site\Site::class) : null;
    }

    private function getPages(): ?\Indieinabox\Page\Pages
    {
        if ($this->pages !== null) {
            return $this->pages;
        }
        $container = \Indieinabox\Core\Container::getInstance();
        return $container->has(\Indieinabox\Page\Pages::class) ? $container->get(\Indieinabox\Page\Pages::class) : null;
    }

    /**
     * Set active page collection context.
     *
     * @param \Indieinabox\Page\Pages $pages
     * @return void
     */
    public function setPages(\Indieinabox\Page\Pages $pages): void
    {
        $this->pages = $pages;
    }

    /**
     * Set active page context.
     *
     * @param \Indieinabox\Page\Page $page
     * @return void
     */
    public function setPage(\Indieinabox\Page\Page $page): void
    {
        $this->page = $page;
    }

    /**
     * Map active layout / kind to appropriate background and foreground colors.
     *
     * @return ((float|int|null|string)[]|null)[]
     *
     * @psalm-return array{bg: list{0?: float|int|null|string, 1?: float|int|null|string, 2?: float|int|null|string,...}|null, fg: list{0?: float|int|null|string, 1?: float|int|null|string, 2?: float|int|null|string,...}|null}
     */
    private function getColors(): array
    {
        $kind = strtolower($this->page ? $this->page->kind : 'generic');
        $kindConfig = KindHelper::getKindConfig($kind);

        if (!empty($kindConfig['palette'])) {
            $bgHex = $kindConfig['palette']['bg'] ?? '#F4F1EA';
            $fgHex = $kindConfig['palette']['fg'] ?? '#2C2E2F';
            return [
                'bg' => sscanf($bgHex, "#%02x%02x%02x"),
                'fg' => sscanf($fgHex, "#%02x%02x%02x"),
            ];
        }

        // Global default
        return [
            'bg' => [244, 241, 234], // #F4F1EA
            'fg' => [44, 46, 47],    // #2C2E2F
        ];
    }

    /**
     * Recursively walks the AST and returns the generated HTML.
     *
     * @param Node $node
     * @return string
     */
    #[\Override]
    public function render(Node $node): string
    {
        if ($node instanceof RootNode) {
            $html = '';
            
            // Inject BookWyrm / IndieWeb read microformats
            if ($this->page && isset($this->page->metadata->read_of)) {
                $html .= '<div class="h-review reading-status-block" style="margin-bottom: 1em; padding: 1em; border-left: 4px solid #ccc;">';
                $html .= '<strong>Lendo:</strong> <a href="' . htmlspecialchars((string)$this->page->metadata->read_of) . '" class="u-read-of">' . htmlspecialchars((string)$this->page->metadata->read_of) . '</a>';
                
                if (isset($this->page->metadata->read_status)) {
                    $html .= ' <span class="p-read-status">(' . htmlspecialchars((string)$this->page->metadata->read_status) . ')</span>';
                }
                
                if (isset($this->page->metadata->rating)) {
                    $html .= '<br><strong>Avaliação:</strong> <data class="p-rating" value="' . htmlspecialchars((string)$this->page->metadata->rating) . '">' . htmlspecialchars((string)$this->page->metadata->rating) . '/5</data>';
                } elseif (isset($this->page->metadata->p_rating)) {
                    $html .= '<br><strong>Avaliação:</strong> <data class="p-rating" value="' . htmlspecialchars((string)$this->page->metadata->p_rating) . '">' . htmlspecialchars((string)$this->page->metadata->p_rating) . '/5</data>';
                }
                
                $html .= '</div>' . "\n";
            }
            
            foreach ($node->children as $child) {
                $html .= $this->render($child);
            }
            return $html;
        }

        if ($node instanceof HeadingNode) {
            $inner = '';
            foreach ($node->children as $child) {
                $inner .= $this->render($child);
            }
            if ($node->level === 1) {
                return "<h1 class=\"p-name\">{$inner}</h1>\n";
            }
            return "<h{$node->level}>{$inner}</h{$node->level}>\n";
        }

        if ($node instanceof ParagraphNode) {
            $inner = '';
            foreach ($node->children as $child) {
                $inner .= $this->render($child);
            }
            return "<p>{$inner}</p>\n";
        }

        if ($node instanceof ListNode) {
            $inner = '';
            foreach ($node->children as $child) {
                $inner .= $this->render($child);
            }
            return "<ul>\n{$inner}</ul>\n";
        }

        if ($node instanceof ListItemNode) {
            $inner = '';
            foreach ($node->children as $child) {
                $inner .= $this->render($child);
            }
            return "  <li>{$inner}</li>\n";
        }

        if ($node instanceof TextNode) {
            return nl2br(htmlspecialchars($node->text, ENT_QUOTES | ENT_HTML5));
        }

        if ($node instanceof RawHtmlBlockNode) {
            return $node->html . "\n";
        }

        if ($node instanceof RawHtmlInlineNode) {
            return $node->html;
        }

        if ($node instanceof StrongNode) {
            $inner = '';
            foreach ($node->children as $child) {
                $inner .= $this->render($child);
            }
            return "<strong>{$inner}</strong>";
        }

        if ($node instanceof EmphasisNode) {
            $inner = '';
            foreach ($node->children as $child) {
                $inner .= $this->render($child);
            }
            return "<em>{$inner}</em>";
        }

        if ($node instanceof CodeInlineNode) {
            return "<code>" . htmlspecialchars($node->code, ENT_QUOTES | ENT_HTML5) . "</code>";
        }

        if ($node instanceof WikilinkNode) {
            $target = trim($node->target);
            
            $site = $this->getSite();
            $pages = $this->getPages();
            $foundPage = null;
            $currentPageLang = $this->page ? ($this->page->lang ?? 'en') : 'en';
            
            $relpath = $this->page ? $this->page->relpath : './';
            $defaultLang = $site?->localization->defaultLang ?? 'en';
            $langPrefix = ($currentPageLang !== $defaultLang) ? $currentPageLang . '/' : '';
            
            $isMeta = false;

            // Check if it is explicitly marked as a metapage link using '%'
            if (str_starts_with($target, '%')) {
                $isMeta = true;
                $metaKey = TextParser::slugize(substr($target, 1)); // e.g. 'garden' or 'index'
                
                if ($metaKey === 'index' || $metaKey === 'home') {
                    $url = $relpath . $langPrefix;
                } else {
                    $matchedKind = null;
                    if ($site !== null && !empty($site->config['kinds'])) {
                        foreach ($site->config['kinds'] as $k => $kindData) {
                            $kStr = (string) $k;
                            if ($metaKey === TextParser::slugize($kStr)) {
                                $matchedKind = $kStr;
                                break;
                            }
                        }
                    }
                    if ($matchedKind !== null) {
                        $folder = KindHelper::getKindFolder($matchedKind, $currentPageLang);
                        $url = $relpath . $langPrefix . ltrim($folder . '/', '/');
                    } else {
                        // Invalid metapage key
                        $url = $relpath . $langPrefix . $metaKey . '/';
                        $isMeta = false; // Mark as missing
                    }
                }
            } else {
                // Normal page link
                $slugTargetParts = array_map([TextParser::class, 'slugize'], explode('/', $target));
                $slugTarget = end($slugTargetParts);
                $slugTargetFull = implode('/', $slugTargetParts);
                
                if (isset($pages) && ($pages instanceof \Indieinabox\Page\Pages || is_iterable($pages))) {
                    $matches = [];
                    // 1. Try to find in the same language first
                    foreach ($pages as $p) {
                        $pLang = $p->lang ?? 'en';
                        if ($pLang !== $currentPageLang) {
                            continue;
                        }

                        $pTitle = TextParser::slugize($p->title ?? '');
                        $pSlug = TextParser::slugize(basename($p->slug ?? ''));
                        $pNick = TextParser::slugize($p->nick ?? '');

                        if ($slugTarget === $pTitle || $slugTarget === $pSlug || $slugTarget === $pNick) {
                            $matches[] = $p;
                        }
                    }

                    // 2. If not found in current language, check url_translations table/config
                    if (empty($matches)) {
                        $rawTranslations = $site?->config['urltranslations']
                            ?? \Indieinabox\Core\Database::getUrlTranslations();
                        $urlTranslationsArr = (array) $rawTranslations;
                        $translatedTargetNick = null;

                        // Check direct mapping (slug_key -> $currentPageLang)
                        if (isset($urlTranslationsArr[$slugTarget][$currentPageLang])) {
                            $translatedTargetNick = TextParser::slugize(
                                $urlTranslationsArr[$slugTarget][$currentPageLang]
                            );
                        } else {
                            // Check reverse mapping (slug_value -> base key -> $currentPageLang)
                            foreach ($urlTranslationsArr as $baseKey => $trans) {
                                foreach ($trans as $l => $v) {
                                    $vSlug = TextParser::slugize($v);
                                    $baseSlug = TextParser::slugize($baseKey);
                                    if ($vSlug === $slugTarget || $baseSlug === $slugTarget) {
                                        if (isset($trans[$currentPageLang])) {
                                            $translatedTargetNick = TextParser::slugize($trans[$currentPageLang]);
                                        } elseif ($currentPageLang === $defaultLang) {
                                            $translatedTargetNick = TextParser::slugize($baseKey);
                                        }
                                        break 2;
                                    }
                                }
                            }
                        }

                        if ($translatedTargetNick !== null) {
                            foreach ($pages as $p) {
                                $pLang = $p->lang ?? 'en';
                                if ($pLang !== $currentPageLang) {
                                    continue;
                                }

                                $pTitle = TextParser::slugize($p->title ?? '');
                                $pSlug = TextParser::slugize(basename($p->slug ?? ''));
                                $pNick = TextParser::slugize($p->nick ?? '');

                                if (
                                    $translatedTargetNick === $pTitle
                                    || $translatedTargetNick === $pSlug
                                    || $translatedTargetNick === $pNick
                                ) {
                                    $matches[] = $p;
                                }
                            }
                        }
                    }

                    // 3. If still not found, search in other languages and check for clones in $currentPageLang
                    if (empty($matches)) {
                        $sourceMatches = [];
                        foreach ($pages as $p) {
                            $pTitle = TextParser::slugize($p->title ?? '');
                            $pSlug = TextParser::slugize(basename($p->slug ?? ''));
                            $pNick = TextParser::slugize($p->nick ?? '');

                            if ($slugTarget === $pTitle || $slugTarget === $pSlug || $slugTarget === $pNick) {
                                $sourceMatches[] = $p;
                            }
                        }

                        if (!empty($sourceMatches)) {
                            // Check if any sourceMatch has a clone/translation in $currentPageLang
                            foreach ($sourceMatches as $srcPage) {
                                $srcSlug = trim($srcPage->slug, '/');
                                $srcNick = TextParser::slugize($srcPage->nick ?? '');

                                foreach ($pages as $candidate) {
                                    if (($candidate->lang ?? 'en') !== $currentPageLang) {
                                        continue;
                                    }

                                    $transSource = trim($candidate->metadata->translation_source ?? '', '/');

                                    if (
                                        $transSource !== ''
                                        && (
                                            $transSource === $srcSlug
                                            || str_ends_with($transSource, '/' . $srcSlug)
                                            || $transSource === $srcNick
                                        )
                                    ) {
                                        $matches[] = $candidate;
                                        break;
                                    }
                                }
                                if (!empty($matches)) {
                                    break;
                                }
                            }

                            // If no clone found in $currentPageLang, fallback to sourceMatches
                            if (empty($matches)) {
                                $matches = $sourceMatches;
                            }
                        }
                    }

                    if (!empty($matches)) {
                        // 1. Exact path match precedence (if target contains path)
                        if (str_contains($target, '/')) {
                            foreach ($matches as $m) {
                                $mSlugClean = preg_replace('/\.html$/', '', $m->slug ?? '');
                                $mSlugParts = array_map([TextParser::class, 'slugize'], explode('/', $mSlugClean));
                                $mSlugFull = implode('/', $mSlugParts);
                                if ($mSlugFull === $slugTargetFull || str_ends_with($mSlugFull, '/' . $slugTargetFull)) {
                                    $foundPage = $m;
                                    break;
                                }
                            }
                        }
                        
                        // 2. 'jardim' / 'gardens' folder precedence
                        if (!$foundPage) {
                            foreach ($matches as $m) {
                                $mSlugClean = preg_replace('/\.html$/', '', $m->slug ?? '');
                                if (str_starts_with($mSlugClean, 'jardim/') || dirname($mSlugClean) === 'jardim' || str_contains($mSlugClean, '/gardens/')) {
                                    $foundPage = $m;
                                    break;
                                }
                            }
                        }
                        
                        // 3. First match fallback
                        if (!$foundPage) {
                            $foundPage = $matches[0];
                        }
                    }
                }
                
                if ($foundPage) {
                    $url = $relpath . ltrim($foundPage->slug, '/');
                } else {
                    $url = $relpath . $langPrefix . $slugTargetFull . '/';
                }
            }

            $urlEsc = htmlspecialchars($url, ENT_QUOTES | ENT_HTML5);
            $labelEsc = htmlspecialchars($node->label, ENT_QUOTES | ENT_HTML5);
            $cssClass = ($foundPage || $isMeta) ? "wikilink" : "wikilink missing";
            return "<a href=\"{$urlEsc}\" class=\"{$cssClass}\">{$labelEsc}</a>";
        }

        if ($node instanceof LinkNode) {
            $target = $node->target;
            if (preg_match('#^https?://#i', $target)) {
                $site = $this->getSite();
                $fqdn = $site?->metadata?->fqdn ?? '';
                if ($fqdn === '' || strpos($target, $fqdn) !== 0) {
                    $ts = $this->page && is_array($this->page->frontmatter) && isset($this->page->frontmatter['published']) 
                          ? (strtotime((string)$this->page->frontmatter['published']) ?: time())
                          : time();
                    
                    // Fallback to page date if published not found but Date object exists
                    if ($this->page && $this->page->published && $ts === time()) {
                        $ts = $this->page->published->getTimestamp();
                    }
                    $target = '/archive?url=' . urlencode($target) . '&ts=' . $ts;
                }
            }
            $targetEsc = htmlspecialchars($target, ENT_QUOTES | ENT_HTML5);
            $labelEsc = htmlspecialchars($node->label, ENT_QUOTES | ENT_HTML5);
            return "<a href=\"{$targetEsc}\">{$labelEsc}</a>";
        }

        if ($node instanceof ImageNode) {
            $target = $node->target;
            $alt = $node->label;
            $site = $this->getSite();

            $width = !empty($node->width) ? (int)$node->width : null;
            if ($site && !preg_match('#^(https?:)?//#i', $target)) {
                $referencingFile = ($this->page && $this->page->filepath) ? $this->page->filepath : '';
                $caminhoOriginal = AttachmentResolver::resolve($target, $referencingFile, $site);

                if ($caminhoOriginal && file_exists($caminhoOriginal)) {
                    $mediaInfo = AttachmentResolver::publishImage($caminhoOriginal, $site, $width);
                    $target = $mediaInfo['webPath'];
                    $originalTarget = $mediaInfo['originalWebPath'];

                    if ($this->page && !empty($this->page->relpath)) {
                        $rel = rtrim($this->page->relpath, '/') . '/';
                        $target = $rel . ltrim($target, '/');
                        $originalTarget = $rel . ltrim($originalTarget, '/');
                    }
                }
            }

            $targetEsc = htmlspecialchars($target, ENT_QUOTES | ENT_HTML5);
            $altEsc = htmlspecialchars($alt, ENT_QUOTES | ENT_HTML5);
            $dimAttrs = '';
            if (!empty($node->width)) {
                $dimAttrs .= ' width="' . htmlspecialchars($node->width, ENT_QUOTES | ENT_HTML5) . '"';
            }
            if (!empty($node->height)) {
                $dimAttrs .= ' height="' . htmlspecialchars($node->height, ENT_QUOTES | ENT_HTML5) . '"';
            }
            $imgTag = "<img src=\"{$targetEsc}\" alt=\"{$altEsc}\"{$dimAttrs} loading=\"lazy\" style=\"max-height: 75vh; max-width: 100%; width: auto; height: auto; object-fit: contain; display: block; margin: 0 auto;\">";
            
            if (isset($originalTarget)) {
                $origEsc = htmlspecialchars($originalTarget, ENT_QUOTES | ENT_HTML5);
                $linkText = Translator::translate('Original image');
                $linkedImgTag = "<a href=\"{$origEsc}\" class=\"dithered-image-link\" target=\"_self\" style=\"display: inline-block; cursor: pointer;\">{$imgTag}</a>";
                return "<figure class=\"content-media\" style=\"text-align: center; margin: 1.5rem auto; display: block; max-width: 100%;\">\n{$linkedImgTag}\n<figcaption style=\"text-align: center;\"><a href=\"{$origEsc}\" class=\"dithered-image-link\" target=\"_self\" style=\"font-size: 0.85em; opacity: 0.8;\">[ {$linkText} ]</a></figcaption>\n</figure>\n";
            }
            return "<figure class=\"content-media\" style=\"text-align: center; margin: 1.5rem auto; display: block; max-width: 100%;\">\n{$imgTag}\n</figure>\n";
        }
        return '';
    }
}
