<?php

declare(strict_types=1);

namespace Indieinabox\Microsub;

/**
 * Universal Post Object (Extended JF2 Entry)
 *
 * This class represents a unified structure for all posts retrieved
 * by the Microsub server, regardless of their origin (ActivityPub, Twtxt, RSS).
 * It safely encapsulates federated extensions into the `_indieinabox` namespace.
 */
class ExtendedEntry
{
    // Standard JF2 Core Properties
    public string $type = 'entry';
    public string $uid = '';
    public string $url = '';
    public string $published = '';
    public ?array $author = null; // ['type' => 'card', 'name' => '...', 'photo' => '...']
    public array $content = ['html' => '', 'text' => ''];

    // Extended Architecture Properties (_indieinabox)
    public string $network = 'unknown'; // 'activitypub', 'twtxt', 'rss', etc.
    public string $originServer = '';
    public array $capabilities = []; // e.g. ['reply', 'like', 'repost']
    public ?string $contentWarning = null;
    public ?array $poll = null;
    public ?array $reels = null;
    
    // Internal state
    public bool $isRead = false;

    /**
     * Serializes the object into a fully compliant JF2 array,
     * including the graceful degradation fallbacks.
     *
     * @return array
     */
    public function toJF2Array(): array
    {
        $payload = [
            'type' => $this->type,
            'uid' => $this->uid,
            'url' => $this->url,
            'published' => $this->published,
            '_is_read' => $this->isRead
        ];

        if (!empty($this->author)) {
            $payload['author'] = $this->author;
        }

        // --- Graceful Degradation / Fallback rendering ---
        $htmlOut = $this->content['html'] ?? '';
        $textOut = $this->content['text'] ?? '';

        // Fallback for Content Warning (collapsible <details>)
        if (!empty($this->contentWarning)) {
            $htmlOut = sprintf('<details><summary>CW: %s</summary>%s</details>', 
                htmlspecialchars($this->contentWarning), 
                $htmlOut
            );
            $textOut = sprintf('CW: %s. %s', $this->contentWarning, $textOut);
        }

        // Fallback for Polls (render as list)
        if (!empty($this->poll) && isset($this->poll['options'])) {
            $htmlOut .= "\n<p><strong>Poll:</strong></p>\n<ul>\n";
            $textOut .= "\n\nPoll:\n";
            foreach ($this->poll['options'] as $opt) {
                $title = htmlspecialchars($opt['title'] ?? 'Option');
                $votes = (int)($opt['votes'] ?? 0);
                $htmlOut .= sprintf('<li>%s (%d votes)</li>%s', $title, $votes, "\n");
                $textOut .= sprintf('- %s (%d votes)' . "\n", $title, $votes);
            }
            $htmlOut .= "</ul>\n";
        }

        $payload['content'] = [
            'html' => $htmlOut,
            'text' => $textOut
        ];

        // --- Native Rich Metadata for Custom Clients ---
        $payload['_indieinabox'] = [
            'network' => $this->network,
            'origin_server' => $this->originServer,
            'capabilities' => $this->capabilities,
            'content_warning' => $this->contentWarning,
            'poll' => $this->poll,
            'reels' => $this->reels
        ];

        return array_filter($payload, function($val) {
            return $val !== null;
        });
    }
}
