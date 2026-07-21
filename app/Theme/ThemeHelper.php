<?php

declare(strict_types=1);

namespace Indieinabox\Theme;

use Indieinabox\Page;
use Indieinabox\Helper;
use Indieinabox\Site;

/**
 * Class ThemeHelper
 * Provides autonomous methods for themes to retrieve standard markup for pages,
 * abstracting away the low-level logic of metadata, interactions, syndication, etc.
 */
class ThemeHelper
{
    /**
     * Renders the post metadata HTML (date, tags, shortlinks, interactions summary).
     */
    public static function getMetadataHtml(Page $page): string
    {
        // Only show date metadata when the page has a real author-assigned date
        // and it is not a structural kind.
        $showMetadata = !in_array($page->kind, ['page', 'home', 'generic'], true)
            && !empty($page->isodate)
            && $page->localizeddate !== 'Saturday, January 1 of 2001, 00:00 UTC';

        if (!$showMetadata) {
            return '';
        }

        $html = '<div class="post-metadata">';
        
        // Line 1: [TIPO] - Data
        $html .= '<div class="meta-line-1">';
        $html .= Helper::kindLink($page, $page->kind);
        $formattedDate = $page->kind === 'photo' ? date('y/m/dHis', strtotime($page->isodate)) : $page->localizeddate;
        $html .= ' - <a href="' . $page->relpath . ltrim($page->slug, '/') . '" class="u-url"><time class="dt-published" datetime="' . $page->isodate . '">' . $formattedDate . '</time></a>';
        $html .= '</div>';

        // Line 2: Shortlink - Likes / Reposts / Replies
        $html .= '<div class="meta-line-2" style="margin-left: 0.6em;">';
        $interactionsStart = '';
        if (!empty($page->shortlink)) {
            $html .= '<a href="' . htmlspecialchars($page->shortlink) . '" style="color: inherit; text-decoration: none; opacity: 0.8;">🔗</a> - ';
        }

        $likes = Helper::getInteractions($page, 'like');
        $reposts = Helper::getInteractions($page, 'repost');
        $replies = Helper::getInteractions($page, 'reply');

        if (count($likes) > 0) {
            $html .= '<a href="' . $page->relpath . ltrim($page->slug, '/') . '/interactions#likes" style="color: inherit; text-decoration: none;">' . count($likes) . ' ' . Helper::translatePlural('Like', 'Likes', count($likes)) . '</a>';
        } else {
            $html .= '<span style="opacity: 0.8; font-size: 0.9em;">0 ' . Helper::translatePlural('Like', 'Likes', 0) . '</span>';
        }
        $html .= ' / ';

        if (count($reposts) > 0) {
            $html .= '<a href="' . $page->relpath . ltrim($page->slug, '/') . '/interactions#reposts" style="color: inherit; text-decoration: none;">' . count($reposts) . ' ' . Helper::translatePlural('Repost', 'Reposts', count($reposts)) . '</a>';
        } else {
            $html .= '<span style="opacity: 0.8; font-size: 0.9em;">0 ' . Helper::translatePlural('Repost', 'Reposts', 0) . '</span>';
        }
        $html .= ' / ';

        if (count($replies) > 0) {
            $html .= '<a href="' . $page->relpath . ltrim($page->slug, '/') . '#interactions" style="color: inherit; text-decoration: none;">' . count($replies) . ' ' . Helper::translatePlural('Reply', 'Replies', count($replies)) . '</a>';
        } else {
            $html .= '<span style="opacity: 0.8; font-size: 0.9em;">0 ' . Helper::translatePlural('Reply', 'Replies', 0) . '</span>';
        }

        $html .= '</div>';

        // Line 3: Tags
        if (!empty($page->tags)) {
            $html .= '<div class="meta-line-3" style="margin-left: 0.6em;">';
            foreach ($page->tags as $tag) {
                $html .= '<a href="' . $page->relpath . 'tag/' . Helper::slugize($tag) . '/" class="p-category">#' . htmlspecialchars($tag) . '</a>&#32;';
            }
            $html .= '</div>';
        }

        // Custom Garden Fields (if any, append to line 2 or create new block)
        if ($page->kind === 'garden' || $page->kind === 'jardim') {
            $flowerbed = isset($page->metadata->flowerbed) && is_array($page->metadata->flowerbed) ? $page->metadata->flowerbed : ['general'];
            $confidence = $page->metadata->confidence ?? 'possible';
            if (!in_array($confidence, ['certain', 'likely', 'possible', 'unlikely', 'impossible'])) {
                $confidence = 'unknown';
            }
            
            $maturity = $page->metadata->maturity ?? 'sprout';
            if (!in_array($maturity, ['sprout', 'seedling', 'tree', 'wilted', 'stone'])) {
                $maturity = 'unknown';
            }
            
            $importance = $page->metadata->importance ?? 'trivial';
            if (!in_array($importance, ['trivial', 'minor', 'moderate', 'major', 'critical'])) {
                $importance = 'unknown';
            }
            
            $flowerbedLinks = [];
            foreach ($flowerbed as $fb) {
                $flowerbedLinks[] = '<a href="' . $page->relpath . 'flowerbed/' . Helper::slugize($fb) . '/">' . htmlspecialchars(Helper::translate($fb)) . '</a>';
            }
            $html .= '<div class="meta-garden-fields" style="margin-left: 0.6em;">';
            $html .= ' • ' . Helper::translate('Flowerbed') . ': ' . implode(', ', $flowerbedLinks) . '<br>';
            $html .= ' • ' . Helper::translate('Confidence') . ': ' . htmlspecialchars(Helper::translate($confidence)) . '<br>';
            $html .= ' • ' . Helper::translate('Maturity') . ': ' . htmlspecialchars(Helper::translate($maturity)) . '<br>';
            $html .= ' • ' . Helper::translate('Importance') . ': ' . htmlspecialchars(Helper::translate($importance));
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Renders Indieweb properties context (e.g. in-reply-to, like-of, rsvp)
     */
    public static function getIndieWebContext(Page $page): string
    {
        $indiewebProps = [
            'in_reply_to' => ['class' => 'u-in-reply-to', 'label' => 'In reply to'],
            'like_of' => ['class' => 'u-like-of', 'label' => 'Liked'],
            'repost_of' => ['class' => 'u-repost-of', 'label' => 'Reposted'],
            'bookmark_of' => ['class' => 'u-bookmark-of', 'label' => 'Bookmarked'],
            'watch_of' => ['class' => 'u-watch-of', 'label' => 'Watched'],
            'read_of' => ['class' => 'u-read-of', 'label' => 'Read'],
            'listen_of' => ['class' => 'u-listen-of', 'label' => 'Listened to']
        ];

        $html = '<div class="indieweb-context" style="margin-bottom: 1em; font-size: 0.9em; opacity: 0.8;">';
        $hasProps = false;

        foreach ($indiewebProps as $prop => $data) {
            if (!empty($page->metadata->$prop)) {
                $hasProps = true;
                $html .= '<div class="context-item">';
                $html .= '<span class="context-label">' . Helper::translate($data['label']) . ':</span> ';
                $html .= '<a href="' . htmlspecialchars($page->metadata->$prop) . '" class="' . $data['class'] . '">' . htmlspecialchars($page->metadata->$prop) . '</a>';
                $html .= '</div>';
            }
        }

        if (!empty($page->metadata->rsvp)) {
            $hasProps = true;
            $html .= '<div class="context-item">';
            $html .= '<span class="context-label">RSVP:</span> ';
            $html .= '<data class="p-rsvp" value="' . htmlspecialchars($page->metadata->rsvp ?? '') . '">' . htmlspecialchars($page->metadata->rsvp ?? '') . '</data>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $hasProps ? $html : '';
    }

    /**
     * Renders AI Translation Notice if present
     */
    public static function getAITranslationNotice(Page $page): string
    {
        if (isset($page->metadata->translated_by_ia) && $page->metadata->translated_by_ia !== false) {
            $html = '<div class="ai-translation-notice" style="background: rgba(0,0,0,0.05); padding: 1em; border-left: 4px solid var(--accent); margin-bottom: 2em; font-size: 0.9em; font-style: italic;">';
            if ($page->metadata->translated_by_ia === 'revised') {
                $html .= '✓ ' . Helper::translate('This page was automatically translated by AI and revised by a human.');
            } else {
                $html .= '⚠ ' . Helper::translate('This page was automatically translated by AI.');
            }
            $html .= '</div>';
            return $html;
        }

        return '';
    }

    /**
     * Renders Syndication Links (Also on...)
     */
    public static function getSyndicationLinks(Page $page): string
    {
        if (empty($page->metadata->syndication)) {
            return '';
        }

        $html = '<div class="syndication-links" style="margin-top: 1.5em; font-size: 0.9em; opacity: 0.8;">';
        $html .= Helper::translate('Also on') . ':';

        $syndications = is_array($page->metadata->syndication) ? $page->metadata->syndication : [$page->metadata->syndication];
        foreach ($syndications as $synd) {
            $domain = parse_url($synd, PHP_URL_HOST) ?? $synd;
            $html .= ' <a href="' . htmlspecialchars($synd) . '" class="u-syndication" rel="syndication" style="margin-left: 0.5em;">' . htmlspecialchars($domain) . '</a>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Renders Interactions (Likes, Reposts, Replies)
     */
    public static function getInteractionsHtml(Page $page): string
    {
        $likes = Helper::getInteractions($page, 'like');
        $reposts = Helper::getInteractions($page, 'repost');
        $replies = Helper::getInteractions($page, 'reply');

        if (count($likes) === 0 && count($reposts) === 0 && count($replies) === 0) {
            return '';
        }

        $html = '<div id="interactions" class="post-interactions" style="margin-top: 1em; border-top: 1px solid var(--accent); padding-top: 0.5em; font-size: 0.85em; opacity: 0.9;">';

        if (count($likes) > 0 || count($reposts) > 0) {
            $html .= '<div style="margin-bottom: 1em; font-size: 1.1em;">';
            if (count($likes) > 0) {
                $html .= '<a href="' . $page->relpath . ltrim($page->slug, '/') . '/interactions#likes" style="color: inherit; text-decoration: none; margin-right: 1em;">';
                $html .= '<strong>' . count($likes) . '</strong> ' . Helper::translatePlural('Like', 'Likes', count($likes));
                $html .= '</a>';
            }
            if (count($reposts) > 0) {
                $html .= '<a href="' . $page->relpath . ltrim($page->slug, '/') . '/interactions#reposts" style="color: inherit; text-decoration: none;">';
                $html .= '<strong>' . count($reposts) . '</strong> ' . Helper::translatePlural('Repost', 'Reposts', count($reposts));
                $html .= '</a>';
            }
            $html .= '</div>';
        }

        if (count($replies) > 0) {
            $html .= '<div style="margin-top: 1.5em; width: 100%;">';
            $html .= '<h3 style="margin-bottom: 1em; font-size: 1.1em;">' . count($replies) . ' ' . Helper::translatePlural('Reply', 'Replies', count($replies)) . '</h3>';
            $html .= '<div style="margin-left: 0.5em;">';
            foreach ($replies as $reply) {
                $html .= '<div class="p-comment h-cite" id="reply-' . md5($reply['url']) . '" style="margin-bottom: 1.5em; padding-left: 10px; border-left: 2px solid var(--accent);">';
                $html .= '<div style="margin-bottom: 0.3em;">';
                $html .= '<strong><a class="p-author h-card" href="' . htmlspecialchars($reply['url']) . '" rel="nofollow">' . htmlspecialchars($reply['author_name']) . '</a></strong>';
                
                $baseDir = str_ends_with($page->slug, '.html') ? dirname($page->slug) : rtrim($page->slug, '/');
                if ($baseDir === '.' || $baseDir === '\\') $baseDir = '';
                $replyUrl = $page->relpath . ltrim($baseDir ? $baseDir . '/' : '', '/') . 'reply/' . md5($reply['url']) . '/';
                
                $html .= ' <a href="' . $replyUrl . '" style="margin-left: 10px; font-size: 0.85em; opacity: 0.7;">' . Helper::translate('Permalink') . '</a>';
                $html .= '</div>';
                $html .= '<a href="' . $replyUrl . '" style="color: inherit; text-decoration: none; display: block;">';
                $html .= '<div class="p-content" style="font-size: 0.95em; line-height: 1.4; opacity: 0.95;">';
                $html .= nl2br(htmlspecialchars($reply['interaction_content'] ?? ''));
                $html .= '</div></a></div>';
            }
            $html .= '</div></div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Renders a post snippet for list views (like indices and taxonomies).
     *
     * @param Page $contextPage The page where this list is being rendered (for relative paths).
     * @param Page $post The post being rendered.
     * @return string
     */
    public static function renderPostSnippet(Page $contextPage, Page $post): string
    {
        $displayMode = Helper::getKindConfig($post->kind)['display_mode'] ?? 'default';
        $html = '';

        if ($displayMode === 'full_content') {
            $formattedDate = $post->kind === 'photo' ? date('y/m/dHis', strtotime($post->isodate)) : $post->localizeddate;
            $html .= '<div style="margin-bottom: 1em;">';
            $html .= '<div style="font-size:0.85em; opacity:0.75; margin-bottom: 0.5em;">=&gt; <a href="' . $contextPage->relpath . ltrim($post->slug, '/') . '">' . $formattedDate . '</a></div>';
            $html .= '<div style="border-left: 2px solid var(--fg); padding-left: 10px; margin-left: 10px;">';
            $content = preg_replace('/src="([^"]+)\.gif"/', 'src="$1_global.gif"', (string)$post->content);
            $html .= $content;
            $html .= '</div>';
            $html .= self::getInteractionsHtml($post);
            $html .= '</div>';
        } elseif ($displayMode === 'thumbnail_snippet') {
            $html .= '<div style="margin-bottom: 1em; display: flex; align-items: flex-start; gap: 15px;">';
            $thumbSrc = '';
            if (preg_match('/src="([^"]+)\.gif"/', (string)$post->content, $matches)) {
                $thumbSrc = $matches[1] . '_thumb.gif';
            }
            $cleanContent = preg_replace('/<a[^>]*class="[^"]*dithered-image-link[^"]*"[^>]*>.*?<\/a>/is', '', (string)$post->content);
            $snippet = strip_tags($cleanContent);
            $snippet = html_entity_decode($snippet, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $snippet = trim(preg_replace('/\s+/', ' ', $snippet));
            if (mb_strlen($snippet) > 45) {
                $snippet = mb_substr($snippet, 0, 42) . '...';
            }
            
            if ($thumbSrc) {
                $html .= '<a href="' . $contextPage->relpath . ltrim($post->slug, '/') . '">';
                $html .= '<img src="' . htmlspecialchars($thumbSrc) . '" alt="Thumbnail" style="width: 64px; height: 64px; object-fit: cover; border-radius: 4px; margin: 0;">';
                $html .= '</a>';
            } else {
                $html .= '<div style="width: 64px; height: 64px; background: rgba(0,0,0,0.05); border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 0.8em; opacity: 0.5;">img</div>';
            }
            
            $html .= '<div>';
            $formattedDate = $post->kind === 'photo' ? date('y/m/dHis', strtotime($post->isodate)) : $post->localizeddate;
            $html .= '<a href="' . $contextPage->relpath . ltrim($post->slug, '/') . '" style="font-weight: bold; text-decoration: none;">' . $formattedDate . '</a>';
            $html .= '<p style="margin: 0.2em 0 0 0; font-size: 0.9em; opacity: 0.9;">' . htmlspecialchars($snippet) . '</p>';
            $html .= self::getInteractionsHtml($post);
            $html .= '</div></div>';
        } else {
            $hasTitle = Helper::getKindConfig($post->kind)['has_title'] ?? true;
            $formattedDate = $post->kind === 'photo' ? date('y/m/dHis', strtotime($post->isodate)) : $post->localizeddate;
            $html .= '<span style="font-size:0.85em; opacity:0.75; margin-right: 0.5em;">' . $formattedDate . '</span> ';
            
            if (!$hasTitle) {
                $cleanContent = preg_replace('/<a[^>]*class="[^"]*dithered-image-link[^"]*"[^>]*>.*?<\/a>/is', '', (string)$post->content);
                $snippet = strip_tags($cleanContent);
                $snippet = html_entity_decode($snippet, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $snippet = trim(preg_replace('/\s+/', ' ', $snippet));
                if (mb_strlen($snippet) > 45) {
                    $snippet = mb_substr($snippet, 0, 42) . '...';
                }
                $html .= '=&gt; <a href="' . $contextPage->relpath . ltrim($post->slug, '/') . '">' . htmlspecialchars($snippet) . '</a>';
            } else {
                $title = html_entity_decode((string)$post->title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (mb_strlen($title) > 45) {
                    $title = mb_substr($title, 0, 42) . '...';
                }
                $html .= '=&gt; <a href="' . $contextPage->relpath . ltrim($post->slug, '/') . '">' . htmlspecialchars($title) . '</a>';
            }
        }

        return $html;
    }
}
