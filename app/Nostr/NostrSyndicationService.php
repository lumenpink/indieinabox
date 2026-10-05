<?php

declare(strict_types=1);

namespace Indieinabox\Nostr;

use Indieinabox\Nostr\Contracts\NostrRelayClientInterface;
use Indieinabox\Page\Page;
use Indieinabox\Repositories\FileSystemContentRepository;
use Indieinabox\Site\Site;
use RuntimeException;

/**
 * Class NostrSyndicationService
 *
 * Orchestrates cross-posting / POSSE syndication of IndieInABox content to the Nostr protocol.
 * Formats NIP-01 Kind 1 notes, signs with BIP-340 Schnorr signatures, publishes to relays,
 * and maintains frontmatter provenance.
 */
class NostrSyndicationService
{
    private ?Site $site;
    private NostrRelayClientInterface $relayClient;

    public function __construct(
        ?Site $site = null,
        ?NostrRelayClientInterface $relayClient = null
    ) {
        $this->site = $site;
        $this->relayClient = $relayClient ?? new NostrRelayClient();
    }

    public function getRelayClient(): NostrRelayClientInterface
    {
        return $this->relayClient;
    }

    /**
     * Formats note content with title, excerpt/body, permalink, and hashtags for a Kind 1 event.
     */
    public function formatNoteContent(Page $page): string
    {
        $parts = [];
        $title = trim((string) ($page->title ?? ''));
        
        $body = '';
        if (isset($page->content)) {
            if (is_object($page->content)) {
                $body = (string) ($page->content->content ?? (string) $page->content);
            } else {
                $body = (string) $page->content;
            }
        }
        $cleanBody = trim($body);

        if ($title !== '') {
            $parts[] = $title;
        }

        if ($cleanBody !== '') {
            $parts[] = $cleanBody;
        }

        $fqdn = '';
        if ($this->site !== null && !empty($this->site->metadata->fqdn)) {
            $fqdn = rtrim($this->site->metadata->fqdn, '/');
        }
        $slug = trim((string) ($page->slug ?? ''), '/');
        if ($fqdn !== '' && $slug !== '') {
            $parts[] = "{$fqdn}/{$slug}/";
        }

        $tags = $page->tags ?? [];
        if (!empty($tags) && is_array($tags)) {
            $hashList = [];
            foreach ($tags as $tag) {
                $tagStr = trim((string) $tag);
                if ($tagStr !== '') {
                    $hash = str_starts_with($tagStr, '#') ? $tagStr : "#{$tagStr}";
                    if (!str_contains($cleanBody, $hash)) {
                        $hashList[] = $hash;
                    }
                }
            }
            if (!empty($hashList)) {
                $parts[] = implode(' ', $hashList);
            }
        }

        return implode("\n\n", array_filter(array_map('trim', $parts)));
    }

    /**
     * Cross-posts a Page as a Kind 1 note to configured Nostr relays.
     *
     * @return array{id: string, note: string, uri: string, results: array<string, array{success: bool, message: string}>}|null
     */
    public function syndicate(Page $page): ?array
    {
        if (!NostrService::isEnabled($this->site)) {
            return null;
        }

        $privHex = NostrService::getPrivateKey($this->site);
        if ($privHex === null || $privHex === '') {
            return null;
        }

        $pubHex = NostrService::getPublicKey($this->site);
        if ($pubHex === null || $pubHex === '') {
            $pubHex = NostrKey::derivePublicKey($privHex);
        }

        $content = $this->formatNoteContent($page);
        
        $tags = [];
        $rawTags = $page->tags ?? [];
        if (is_array($rawTags)) {
            foreach ($rawTags as $t) {
                $cleanTag = ltrim(trim((string) $t), '#');
                if ($cleanTag !== '') {
                    $tags[] = ['t', strtolower($cleanTag)];
                }
            }
        }

        $fqdn = '';
        if ($this->site !== null && !empty($this->site->metadata->fqdn)) {
            $fqdn = rtrim($this->site->metadata->fqdn, '/');
        }
        $slug = trim((string) ($page->slug ?? ''), '/');
        if ($fqdn !== '' && $slug !== '') {
            $canonicalUrl = "{$fqdn}/{$slug}/";
            $tags[] = ['r', $canonicalUrl];
        }

        $event = new NostrEvent(
            pubkey: $pubHex,
            createdAt: time(),
            kind: 1,
            tags: $tags,
            content: $content
        );

        $event->sign($privHex);
        $eventData = $event->toArray();

        $relays = NostrService::getRelays($this->site);
        $results = $this->relayClient->publish($eventData, $relays);

        $eventId = (string) $event->getId();
        $noteBech32 = NostrKey::hexToNote($eventId);

        return [
            'id' => $eventId,
            'note' => $noteBech32,
            'uri' => "nostr:{$noteBech32}",
            'results' => $results,
        ];
    }

    /**
     * Syndicates a Markdown post file directly to Nostr relays and records provenance.
     */
    public function syndicateFile(string $filePath, bool $force = false): ?string
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("File not found: {$filePath}");
        }

        if (!NostrService::isEnabled($this->site)) {
            return null;
        }

        $contentRepo = new FileSystemContentRepository(null, $this->site);
        $raw = (string) file_get_contents($filePath);
        $parsed = $contentRepo->parseFrontmatterMarkdown($raw);
        $frontmatter = $parsed['frontmatter'];
        $body = $parsed['body'];

        $syndications = (array) ($frontmatter['syndication'] ?? []);
        if (!$force) {
            foreach ($syndications as $s) {
                if (is_string($s) && (str_starts_with($s, 'nostr:') || str_contains($s, 'njump.me/'))) {
                    return $s;
                }
            }
        }

        $slug = basename($filePath, '.md');
        $page = Page::fromArray([
            'title' => (string) ($frontmatter['title'] ?? ''),
            'content' => $body,
            'slug' => $slug,
            'tags' => (array) ($frontmatter['tags'] ?? []),
        ]);

        $res = $this->syndicate($page);
        if ($res === null || empty($res['uri'])) {
            return null;
        }

        $this->recordSyndicationUrl($filePath, $res['uri']);

        return $res['uri'];
    }

    /**
     * Appends the Nostr URI to the Markdown file's frontmatter `syndication` list.
     */
    public function recordSyndicationUrl(string $filePath, string $syndicationUrl): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $contentRepo = new FileSystemContentRepository(null, $this->site);
        $raw = (string) file_get_contents($filePath);
        $parsed = $contentRepo->parseFrontmatterMarkdown($raw);
        $frontmatter = $parsed['frontmatter'];
        $body = $parsed['body'];

        $existing = $frontmatter['syndication'] ?? [];
        if (!is_array($existing)) {
            $existing = [$existing];
        }

        if (!in_array($syndicationUrl, $existing, true)) {
            $existing[] = $syndicationUrl;
            $frontmatter['syndication'] = array_values($existing);

            $newMarkdown = $contentRepo->buildFrontmatterMarkdown($frontmatter, $body);
            return file_put_contents($filePath, $newMarkdown) !== false;
        }

        return true;
    }
}
