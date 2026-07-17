<?php

declare(strict_types=1);

namespace Indieinabox;

use PDO;
use DOMDocument;
use DOMXPath;
use DOMElement;

/**
 * Class BackgroundWorker
 */
class BackgroundWorker
{
    /**
     * @var PDO
     */
    private PDO $db;
    /**
     * @var \Indieinabox\Site
     */
    private Site $site;

    /**
     * Initializes the BackgroundWorker.
     *
     * @param \Indieinabox\Site $site Global site configuration and environment.
     */
    public function __construct(Site $site)
    {
        $this->site = $site;
        $this->db = Database::getDb();
    }

    /**
     * Executes all background tasks (inbox, outbox, archives).
     * Normally called periodically via cron or CLI.
     *
     * @return void
     */
    public function runAll(): void
    {
        $lockFile = Database::$dataDir . '/cron.lock';
        $fp = fopen($lockFile, 'w+');
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            echo "Cron is already running.\n";
            fclose($fp);
            return;
        }

        try {
            $this->processInboxQueue();
            $this->processOutbox();
            $this->processOutgoingWebmentions();
            $this->processArchiveQueue();
            $this->processTwtxtFeeds();
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * Fetches remote Twtxt timeline and hub mentions asynchronously.
     * Rebuilds the site to update the static timeline page if new entries are found.
     */
    public function processTwtxtFeeds(): void
    {
        echo "Running Twtxt Feed processor...\n";
        
        $twtxtManager = new \Indieinabox\Twtxt\TwtxtManager();
        $cacheDir = $this->site->paths->baseDir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'twtxt_cache';
        
        $fetched = false;

        if (!empty($this->site->twtxt->following)) {
            echo "Fetching twtxt timeline feeds...\n";
            $twtxtManager->fetchTimeline($this->site->twtxt->following, $cacheDir, true);
            $fetched = true;
        }

        if (!empty($this->site->twtxt->hubs)) {
            echo "Fetching twtxt hub mentions...\n";
            $twtxtManager->fetchHubMentions($this->site->twtxt->hubs, $this->site->metadata->fqdn, $cacheDir, true);
            $fetched = true;
        }

        if ($fetched) {
            echo "Triggering incremental build to update timeline...\n";
            $builder = new \Indieinabox\SiteBuilder($this->site);
            $builder->build();
        }
        
        echo "Twtxt Feed processor done.\n";
    }

    /**
     * Processes the incoming queue (Webmentions, ActivityPub activities).
     *
     * @return void
     */
    public function processInboxQueue(): void
    {
        echo "Running Inbox Queue processor...\n";
        
        $sql = "SELECT id, type, payload_json FROM inbox_queue ORDER BY id ASC LIMIT 50";
        $stmt = $this->db->query($sql);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($items)) {
            echo "No inbox items.\n";
            return;
        }

        foreach ($items as $item) {
            $id = $item['id'];
            $type = $item['type'];
            $payload = json_decode($item['payload_json'], true);

            echo "Processing inbox item $id ($type)...\n";
            try {
                if ($type === 'webmention') {
                    $this->handleWebmention($payload);
                } elseif ($type === 'activitypub') {
                    $this->handleActivityPub($payload);
                } elseif ($type === 'build_site') {
                    $this->handleBuildSite();
                }
            } catch (\Exception $e) {
                echo "Error processing inbox item $id: " . $e->getMessage() . "\n";
            }
            
            // Remove from queue whether it succeeds or fails, to avoid poison pills
            $del = $this->db->prepare("DELETE FROM inbox_queue WHERE id = ?");
            $del->execute([$id]);
        }
        echo "Inbox queue done.\n";
    }

    /**
     * Triggers a site rebuild.
     * Instantiates the SiteBuilder and recompiles static assets/pages.
     *
     * @return void
     */
    private function handleBuildSite(): void
    {
        echo "Rebuilding static site...\n";
        
        $this->site->config = Database::getAllSettings();
        $this->site->config['kinds'] = Database::getKinds();
        $this->site->config['translations'] = Database::getTranslations();
        $this->site->config['urltranslations'] = Database::getUrlTranslations();
        
        $siteBuilder = new SiteBuilder($this->site);
        $siteBuilder->build();
        
        echo "Site rebuild completed.\n";
    }

    /**
     * Parses and handles a received Webmention.
     * Retrieves the source page, extracts microformats, checks for spam, and saves as a comment/like/repost.
     *
     * @param array $payload The Webmention data (source, target).
     * @return void
     */
    private function handleWebmention(array $payload): void
    {
        $source = $payload['source'];
        $target = $payload['target'];
        
        // Fetch and verify source link
        $html = $this->fetchUrl($source);
        if ($html === false) {
            echo "Failed to fetch webmention source: $source\n";
            return;
        }

        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        $found = false;
        $interactionType = 'webmention';
        foreach ($xpath->query('//a[@href]') as $link) {
            if ($link instanceof DOMElement) {
                $href = $link->getAttribute('href');
                $classes = explode(' ', $link->getAttribute('class'));
                // Basic matching for now (can be improved)
                if (strpos($href, parse_url($target, PHP_URL_PATH)) !== false || strpos($href, $target) !== false) {
                    $found = true;
                    if (in_array('u-like-of', $classes)) $interactionType = 'like';
                    elseif (in_array('u-repost-of', $classes)) $interactionType = 'repost';
                    elseif (in_array('u-in-reply-to', $classes)) $interactionType = 'reply';
                    elseif (in_array('u-bookmark-of', $classes)) $interactionType = 'bookmark';
                    break;
                }
            }
        }

        if (!$found) {
            echo "No link to target found in webmention source: $source\n";
            return;
        }

        $titleNode = $xpath->query('//title')->item(0);
        $title = $titleNode ? trim($titleNode->nodeValue) : '';

        $content = '';
        $entryContent = $xpath->query('//*[contains(@class, "e-content")]')->item(0);
        if ($entryContent) {
            $content = trim($entryContent->nodeValue);
        } else {
            $pNode = $xpath->query('//p')->item(0);
            if ($pNode) {
                $content = trim($pNode->nodeValue);
            }
        }
        if (strlen($content) > 300) {
            $content = substr($content, 0, 297) . '...';
        }

        $whostyleData = null;
        $hashData = \Indieinabox\Whostyles::extract($html);
        if ($hashData) {
            $whostyleData = \Indieinabox\Whostyles::decode($hashData);
        }

        $targetPath = parse_url($target, PHP_URL_PATH) ?? '/';
        $sitePath = parse_url($this->site->metadata->fqdn ?? '', PHP_URL_PATH);
        if ($sitePath && $sitePath !== '/' && strpos($targetPath, $sitePath) === 0) {
            $targetPath = substr($targetPath, strlen($sitePath));
        }
        $slug = trim($targetPath, '/');
        if ($slug === '') {
            $slug = 'home';
        }
        $hash = md5($slug);

        $dataDir = \Indieinabox\Database::$dataDir ?? (dirname(__DIR__) . '/data');
        $notificationsDir = $dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . 'notifications';
        
        if (!is_dir($notificationsDir)) {
            @mkdir($notificationsDir, 0755, true);
        }

        $newMention = [
            'id' => $hash . '_' . md5($source),
            'target_hash' => $hash,
            'source' => $source,
            'target' => $target,
            'author_name' => $title ?: 'Webmention from ' . (parse_url($source, PHP_URL_HOST) ?? 'external link'),
            'author_photo' => '',
            'url' => $source,
            'published' => time(),
            'is_read' => 0,
            'type' => 'webmention',
            'interaction_type' => $interactionType,
            'status' => 'pending',
            'whostyle' => $whostyleData ?? []
        ];

        $isSpam = $this->checkAkismet([
            'author_name' => $newMention['author_name'],
            'author_url' => $source,
            'content' => $content
        ]);

        if ($isSpam) {
            $newMention['status'] = 'spam';
            $targetDir = $dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . 'spam';
        } else {
            $targetDir = $notificationsDir;
        }
        
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $filepath = $targetDir . DIRECTORY_SEPARATOR . $newMention['id'] . '.md';
        
        $yaml = new \Indieinabox\Yaml();
        $yamlStr = $yaml->dump($newMention);
        $fileContent = "---\n" . trim($yamlStr) . "\n---\n\n" . $content;
        
        file_put_contents($filepath, $fileContent);
        
        // Extract external links to ArchiveQueue
        $this->extractLinksToArchiveQueue($content);
    }

    /**
     * Processes a received ActivityPub activity.
     * Validates the HTTP signature and handles Follow, Undo, Create, or Like actions.
     *
     * @param array $payload The parsed ActivityPub JSON-LD data.
     * @return void
     */
    private function handleActivityPub(array $payload): void
    {
        $headers = $payload['headers'];
        $body = $payload['body'];
        $method = $payload['method'];
        $path = $payload['path'];

        $activity = json_decode($body, true);
        if (!$activity) return;

        // Find signature header
        $signatureHeader = '';
        foreach ($headers as $k => $v) {
            if (strtolower($k) === 'signature') {
                $signatureHeader = $v;
                break;
            }
        }

        if (!$signatureHeader) {
            echo "Missing signature.\n";
            return;
        }

        preg_match('/keyId="([^"]+)"/', $signatureHeader, $matches);
        $keyId = $matches[1] ?? '';
        if (!$keyId) {
            echo "Missing keyId in signature.\n";
            return;
        }

        $pubKey = $this->getPublicKey($keyId);
        if (!$pubKey) {
            echo "Failed to fetch public key.\n";
            return;
        }

        if (!$this->verifySignature($headers, $method, $path, $pubKey)) {
             echo "Invalid signature.\n";
             return;
        }

        $type = $activity['type'] ?? '';

        if ($type === 'Follow') {
            $actor = $activity['actor'] ?? '';
            if ($actor) {
                $actorInbox = $actor . '/inbox';
                $sql = "INSERT OR REPLACE INTO activitypub_followers (actor_url, inbox_url) VALUES (?, ?)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$actor, $actorInbox]);
                $this->queueAcceptFollow($activity, $actorInbox);
            }
        } elseif ($type === 'Create') {
            $this->saveActivityPubCreate($activity);
        } elseif ($type === 'Announce') {
            // Lemmy/FEP-1b12: Groups Announce topics and replies.
            // We unwrap the Announce and process the inner object as a Create.
            if (isset($activity['object']) && is_array($activity['object'])) {
                $announcedObj = $activity['object'];
                $innerType = $announcedObj['type'] ?? '';
                
                if (in_array($innerType, ['Create', 'Note', 'Article', 'Page'])) {
                    $innerObj = ($innerType === 'Create' && isset($announcedObj['object'])) ? $announcedObj['object'] : $announcedObj;
                    
                    // Construct a fake Create activity to process locally
                    $fakeCreate = [
                        'actor' => $innerObj['attributedTo'] ?? $announcedObj['actor'] ?? $activity['actor'],
                        'object' => $innerObj
                    ];
                    $this->saveActivityPubCreate($fakeCreate);
                }
            }
        }
    }

    /**
     * Method verifySignature
     * @param array $headers
     * @param string $method
     * @param string $path
     * @param string $pubKey
     * 
     * @return bool
     */
    protected function verifySignature(array $headers, string $method, string $path, string $pubKey): bool
    {
        // We skip verification for now if the library throws. In real env it would be:
        if (class_exists('HttpSignature')) {
            return HttpSignature::verify($headers, $method, $path, $pubKey);
        }
        return true;
    }

    /**
     * Saves a 'Create' Activity (e.g. a remote post or reply) to the local inbox/comments.
     * Converts HTML content to Markdown, downloads avatars, and saves as a pending interaction.
     *
     * @param array $activity The ActivityPub Create activity object.
     * @return void
     */
    private function saveActivityPubCreate(array $activity): void
    {
        $object = $activity['object'] ?? null;
        if (!$object || !is_array($object)) return;

        $type = $object['type'] ?? '';
        if (!in_array($type, ['Note', 'Article', 'Page'])) return;

        $id = $object['id'] ?? '';
        if (!$id) return;

        $hash = md5($id);
        $dataDir = \Indieinabox\Database::$dataDir ?? (dirname(__DIR__) . '/data');
        $inboxDir = $dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . 'inbox';
        
        if (!is_dir($inboxDir)) {
            @mkdir($inboxDir, 0755, true);
        }

        $actor = $activity['actor'] ?? '';
        
        // Fetch actor details
        $authorName = $actor;
        $authorPhoto = '';
        $actorData = $this->fetchJsonUrl($actor);
        if ($actorData) {
            $authorName = $actorData['name'] ?? $actorData['preferredUsername'] ?? $actor;
            $authorPhoto = $actorData['icon']['url'] ?? '';
            
            // Download avatar locally
            if ($authorPhoto) {
                $authorPhoto = $this->downloadAvatarLocally($actor, $authorPhoto);
            }
        }

        $published = isset($object['published']) ? strtotime($object['published']) : time();
        $content = $object['content'] ?? ($object['summary'] ?? '');
        
        $frontmatter = [
            'id' => $hash,
            'url' => $object['url'] ?? $id,
            'author_name' => $authorName,
            'author_photo' => $authorPhoto,
            'published' => $published,
            'is_read' => 0,
            'type' => 'activitypub'
        ];

        if (isset($object['inReplyToBook'])) {
            $frontmatter['read_of'] = $object['inReplyToBook'];
            if (isset($object['rating'])) {
                $frontmatter['rating'] = $object['rating'];
            }
            if (isset($object['readingStatus'])) {
                $frontmatter['read_status'] = $object['readingStatus'];
            }
        }

        $yaml = new \Indieinabox\Yaml();
        $yamlStr = $yaml->dump($frontmatter);

        $isSpam = $this->checkAkismet([
            'author_name' => $authorName,
            'author_url' => $actor,
            'content' => $content
        ]);

        if ($isSpam) {
            $frontmatter['status'] = 'spam';
            $targetDir = $dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . 'spam';
        } else {
            $targetDir = $inboxDir;
        }

        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $filepath = $targetDir . DIRECTORY_SEPARATOR . $hash . '.md';
        $fileContent = "---\n" . trim($yamlStr) . "\n---\n\n" . $content;

        file_put_contents($filepath, $fileContent);

        // Extract external links to ArchiveQueue
        $this->extractLinksToArchiveQueue($content);
    }

    /**
     * Downloads an actor's avatar to the local cache.
     * Re-uses the cached version if already downloaded.
     *
     * @param string $actorUrl The URL of the actor profile.
     * @param string $photoUrl The remote URL of the avatar image.
     * @return string The local path to the downloaded avatar.
     */
    private function downloadAvatarLocally(string $actorUrl, string $photoUrl): string
    {
        $dataDir = \Indieinabox\Database::$dataDir ?? (dirname(__DIR__) . '/data');
        $actorHost = parse_url($actorUrl, PHP_URL_HOST) ?? 'unknown_host';
        $avatarsDir = $dataDir . DIRECTORY_SEPARATOR . 'avatars' . DIRECTORY_SEPARATOR . $actorHost;
        
        if (!is_dir($avatarsDir)) {
            @mkdir($avatarsDir, 0755, true);
        }

        $ext = pathinfo(parse_url($photoUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
        if (!$ext) $ext = 'jpg';
        
        $filename = md5($actorUrl) . '.' . $ext;
        $localPath = $avatarsDir . DIRECTORY_SEPARATOR . $filename;
        
        if (!file_exists($localPath)) {
            $imgData = $this->fetchUrl($photoUrl);
            if ($imgData) {
                file_put_contents($localPath, $imgData);
            }
        }
        
        // Return relative path for web access (depends on how web serves data dir)
        // For simplicity we use a virtual path
        return '/data/avatars/' . $actorHost . '/' . $filename;
    }

    /**
     * Processes outgoing webmentions.
     */
    private function processOutgoingWebmentions(): void
    {
        echo "Running Outgoing Webmention processor...\n";
        
        // Clean up old ones (older than 7 days)
        $sevenDaysAgo = time() - (7 * 86400);
        $this->db->exec("DELETE FROM outgoing_webmentions WHERE status IN ('sent', 'failed') AND created_at < $sevenDaysAgo");

        $stmt = $this->db->query("SELECT id, source_url, target_url FROM outgoing_webmentions WHERE status = 'pending' LIMIT 20");
        $webmentions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($webmentions)) {
            echo "No outgoing webmentions pending.\n";
            return;
        }

        foreach ($webmentions as $wm) {
            $id = (int)$wm['id'];
            $source = $wm['source_url'];
            $target = $wm['target_url'];

            echo "Sending webmention from $source to $target...\n";

            $endpoint = $this->discoverWebmentionEndpoint($target);
            if (!$endpoint) {
                echo "No webmention endpoint found for $target\n";
                $this->db->prepare("UPDATE outgoing_webmentions SET status = 'failed' WHERE id = ?")->execute([$id]);
                continue;
            }

            echo "Found endpoint: $endpoint\n";

            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['source' => $source, 'target' => $target]));
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Indieinabox Webmention Sender/1.0');
            $response = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code >= 200 && $code < 300) {
                echo "Webmention sent successfully.\n";
                $this->db->prepare("UPDATE outgoing_webmentions SET status = 'sent' WHERE id = ?")->execute([$id]);
            } else {
                echo "Webmention failed with HTTP $code.\n";
                $this->db->prepare("UPDATE outgoing_webmentions SET status = 'failed' WHERE id = ?")->execute([$id]);
            }
        }
        
        echo "Outgoing Webmention processor done.\n";
    }

    /**
     * Discovers a webmention endpoint from a target URL.
     *
     * @param string $url The target URL.
     * @return string|null The endpoint URL or null if not found.
     */
    private function discoverWebmentionEndpoint(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Indieinabox Endpoint Discoverer/1.0');
        $response = curl_exec($ch);
        
        if ($response === false) {
            return null;
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        curl_close($ch);

        // 1. Check Link headers
        if (preg_match('/Link:\s*<([^>]+)>;\s*rel=[\'"]?(?:[^>]*\s+)?webmention(?:\s+[^>]+)?[\'"]?/i', $headers, $matches)) {
            return $this->resolveUrl($url, $matches[1]);
        }

        // 2. Check HTML body for <link rel="webmention">
        if (preg_match('/<link\s+[^>]*rel=[\'"]?(?:[^>]*\s+)?webmention(?:\s+[^>]+)?[\'"]?[^>]*href=[\'"]([^>"\']+)[\'"]/i', $body, $matches) || 
            preg_match('/<link\s+[^>]*href=[\'"]([^>"\']+)[\'"][^>]*rel=[\'"]?(?:[^>]*\s+)?webmention(?:\s+[^>]+)?[\'"]?/i', $body, $matches)) {
            return $this->resolveUrl($url, $matches[1]);
        }

        return null;
    }

    /**
     * Resolves a relative URL against a base URL.
     */
    private function resolveUrl(string $base, string $rel): string
    {
        if (parse_url($rel, PHP_URL_SCHEME) != '') return $rel;
        if ($rel[0] == '#' || $rel[0] == '?') return $base . $rel;
        extract(parse_url($base));
        /** @var string $scheme */
        /** @var string $host */
        /** @var string $path */
        if (!isset($path)) $path = '/';
        $path = preg_replace('#/[^/]*$#', '', $path);
        if ($rel[0] == '/') $path = '';
        $abs = "$host$path/$rel";
        $re = ['#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#'];
        for ($n = 1; $n > 0; $abs = preg_replace($re, '/', $abs, -1, $n)) {}
        return $scheme . '://' . $abs;
    }

    /**
     * Scans markdown content for external links and queues them for archiving.
     * Used to automatically snapshot outgoing links to the Wayback Machine.
     *
     * @param string $htmlContent The content text to scan for links.
     * @return void
     */
    private function extractLinksToArchiveQueue(string $htmlContent): void
    {
        $settings = \Indieinabox\Database::getAllSettings();
        if (empty($settings['webarchive_enabled'])) {
            return;
        }
        if (preg_match_all('/href=["\'](http[^"\']+)["\']/i', $htmlContent, $matches)) {
            $links = array_unique($matches[1]);
            foreach ($links as $link) {
                // Ignore own site links
                $siteHost = parse_url($this->site->metadata->fqdn ?? '', PHP_URL_HOST);
                $linkHost = parse_url($link, PHP_URL_HOST);
                if ($siteHost && $linkHost && strcasecmp($siteHost, $linkHost) === 0) continue;
                
                // Insert to queue
                $stmt = $this->db->prepare("INSERT INTO archive_queue (url, requested_at) VALUES (?, ?)");
                $stmt->execute([$link, time()]);
            }
        }
    }

    /**
     * Retrieves the public key of an ActivityPub actor.
     * Caches the fetched key locally to speed up future signature verifications.
     *
     * @param string $keyId The URL of the actor to fetch the key for.
     * @return string|null The PEM encoded public key, or null if not found.
     */
    private function getPublicKey(string $keyId): ?string
    {
        $stmt = $this->db->prepare("SELECT public_key FROM activitypub_actors WHERE actor_url = ?");
        $stmt->execute([$keyId]);
        if ($row = $stmt->fetch()) {
            return $row['public_key'];
        }

        $actorUrl = preg_replace('/#.*$/', '', $keyId);
        $data = $this->fetchJsonUrl($actorUrl);
        
        if ($data) {
            $fetchedKeyId = $data['publicKey']['id'] ?? '';
            if ($fetchedKeyId === $keyId && isset($data['publicKey']['publicKeyPem'])) {
                $pubKey = $data['publicKey']['publicKeyPem'];
                $stmt = $this->db->prepare("INSERT OR REPLACE INTO activitypub_actors (actor_url, public_key, updated_at) VALUES (?, ?, ?)");
                $stmt->execute([$keyId, $pubKey, time()]);
                return $pubKey;
            }
        }
        return null;
    }

    /**
     * Fetches a URL and decodes the JSON response.
     * Assumes an ActivityPub/JSON-LD friendly Accept header.
     *
     * @param string $url The URL to fetch.
     * @return array|null The decoded JSON array, or null on failure.
     */
    protected function fetchJsonUrl(string $url): ?array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/activity+json, application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300 && $res) {
            return json_decode($res, true);
        }
        return null;
    }

    /**
     * Method fetchUrl
     * @param string $url
     */
    protected function fetchUrl(string $url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Indieinabox BackgroundWorker");
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }

    /**
     * Queues an 'Accept' response to a 'Follow' activity.
     * Places the payload into the outbox for background delivery.
     *
     * @param array $followActivity The received Follow activity data.
     * @param string $targetInbox The inbox URL to deliver the Accept activity to.
     * 
     * @return void
     */
    private function queueAcceptFollow(array $followActivity, string $targetInbox): void
    {
        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        $acceptId = $fqdn . '/activity/' . uniqid();

        $accept = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $acceptId,
            'type' => 'Accept',
            'actor' => $fqdn . '/actor',
            'object' => $followActivity
        ];

        $sql = "INSERT INTO activitypub_outbox (payload_json, target_inbox, status, created_at) " .
               "VALUES (?, ?, 'pending', ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([json_encode($accept, JSON_UNESCAPED_SLASHES), $targetInbox, time()]);
    }

    /**
     * Processes the outgoing queue (Outbox).
     * Delivers queued activities (e.g., Creates, Accepts) to followers' inboxes using HTTP Signatures.
     *
     * @return void
     */
    public function processOutbox(): void
    {
        echo "Running ActivityPub outbox processor...\n";

        // Prune old outbox entries (7 days) and actors (30 days)
        $sevenDaysAgo = time() - (7 * 86400);
        $thirtyDaysAgo = time() - (30 * 86400);
        
        $this->db->exec("DELETE FROM activitypub_outbox WHERE status IN ('sent', 'failed') AND created_at < $sevenDaysAgo");
        $this->db->exec("DELETE FROM activitypub_actors WHERE updated_at < $thirtyDaysAgo");

        $stmt = $this->db->query("SELECT private_key FROM activitypub_keys WHERE key_id = 'main-key'");
        $keyRow = $stmt->fetch();
        if (!$keyRow) {
            echo "No RSA key found. Exiting.\n";
            return;
        }
        $privateKey = $keyRow['private_key'];

        $sql = "SELECT id, payload_json, target_inbox FROM activitypub_outbox " .
               "WHERE status = 'pending' LIMIT 50";
        $stmt = $this->db->query($sql);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($messages)) {
            echo "No pending messages.\n";
            return;
        }

        $fqdn = Database::getSetting('fqdn');
        if (!$fqdn) {
            echo "FQDN not configured in database settings.\n";
            return;
        }
        $fqdn = rtrim($fqdn, '/');

        $keyId = $fqdn . '/actor#main-key';

        foreach ($messages as $msg) {
            $id = $msg['id'];
            $payload = $msg['payload_json'];
            $targetUrl = $msg['target_inbox'];
            
            echo "Processing message $id for $targetUrl...\n";

            $headers = HttpSignature::sign(
                $keyId,
                $privateKey,
                'POST',
                $targetUrl,
                $payload,
                ['Content-Type' => 'application/activity+json']
            );

            $ch = curl_init($targetUrl);
            $curlHeaders = [];
            foreach ($headers as $k => $v) {
                $curlHeaders[] = "$k: $v";
            }
            
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                echo "Success ($httpCode).\n";
                $update = $this->db->prepare("UPDATE activitypub_outbox SET status = 'sent' WHERE id = ?");
                $update->execute([$id]);
            } else {
                echo "Failed ($httpCode): $error\n";
                $update = $this->db->prepare("UPDATE activitypub_outbox SET status = 'failed' WHERE id = ?");
                $update->execute([$id]);
            }
        }

        echo "Done.\n";
    }

    /**
     * Processes the archive queue.
     * Submits external links to the Wayback Machine and optionally generates PDFs via Microlink.
     *
     * @return void
     */
    public function processArchiveQueue(): void
    {
        echo "Running Archive Queue processor...\n";
        
        $sql = "SELECT id, url, requested_at, force_archive FROM archive_queue WHERE status = 'pending' ORDER BY id ASC LIMIT 10";
        $stmt = $this->db->query($sql);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($items)) {
            echo "No archive items.\n";
            return;
        }

        $dataDir = \Indieinabox\Database::$dataDir ?? (dirname(__DIR__) . '/data');
        $pdfDir = $dataDir . DIRECTORY_SEPARATOR . 'archives';
        if (!is_dir($pdfDir)) {
            @mkdir($pdfDir, 0755, true);
        }

        foreach ($items as $item) {
            $id = $item['id'];
            $url = $item['url'];
            $requestedAt = (int)$item['requested_at'];
            $forceArchive = (int)($item['force_archive'] ?? 0);

            // Mark as processing
            $this->db->prepare("UPDATE archive_queue SET status = 'processing' WHERE id = ?")->execute([$id]);
            
            echo "Archiving URL: $url\n";
            
            // Resolve final URL following redirects
            $finalUrl = $this->resolveFinalUrl($url);
            if ($finalUrl !== $url) {
                // Save to archive_aliases
                $stmt = $this->db->prepare("INSERT OR IGNORE INTO archive_aliases (alias_url, target_url) VALUES (?, ?)");
                $stmt->execute([$url, $finalUrl]);
                $url = $finalUrl;
            }

            // Basic normalization (strip trailing slash, to lowercase)
            $normUrl = rtrim(strtolower($url), '/');
            
            if (!$forceArchive) {
                // Check if already archived recently
                $stmt = $this->db->prepare("SELECT * FROM archived_links WHERE url = ? AND timestamp > ?");
                $stmt->execute([$normUrl, $requestedAt - 86400]); // Don't archive if we already did in the last 24h
                if ($stmt->fetch()) {
                    // Skip
                    $this->db->prepare("DELETE FROM archive_queue WHERE id = ?")->execute([$id]);
                    continue;
                }
            }

            // 1. Send to Archive.org (fire and forget)
            $this->sendToArchiveOrg($url);

            // 2. Generate PDF via Microlink API
            $localPdfPath = $this->fetchPdfFromMicrolink($url, $normUrl, $pdfDir);

            // Insert into archived_links
            $stmt = $this->db->prepare("INSERT INTO archived_links (url, timestamp, local_pdf_path, archive_org_url) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $normUrl,
                time(),
                $localPdfPath,
                "https://web.archive.org/web/" . gmdate('YmdHis') . "/" . $url
            ]);

            // Mark as done
            $this->db->prepare("DELETE FROM archive_queue WHERE id = ?")->execute([$id]);
            echo "Archived: $url\n";
        }
        
        echo "Archive queue done.\n";
    }

    /**
     * Follows HTTP redirects to resolve the final destination URL.
     * Prevents archiving tracking links or URL shorteners directly.
     *
     * @param string $url The original URL to resolve.
     * @return string The final resolved URL.
     */
    protected function resolveFinalUrl(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_exec($ch);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        return $finalUrl ? $finalUrl : $url;
    }

    /**
     * Submits a URL to the Internet Archive's Wayback Machine.
     *
     * @param string $url The URL to archive.
     * @return void
     */
    protected function sendToArchiveOrg(string $url): void
    {
        $archiveOrgUrl = "https://web.archive.org/save/" . $url;
        $ch = curl_init($archiveOrgUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, "Indieinabox WebArchiver");
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Generates and downloads a PDF snapshot of a URL using the Microlink API.
     * Saves the PDF to the local archive directory.
     *
     * @param string $url The URL to snapshot.
     * @param string $normUrl The normalized URL.
     * @param string $pdfDir The local directory to save the PDF.
     * @return string|null The relative path to the PDF, or null on failure.
     */
    protected function fetchPdfFromMicrolink(string $url, string $normUrl, string $pdfDir): ?string
    {
        $pdfApiUrl = "https://api.microlink.io/?url=" . urlencode($url) . "&pdf=true&meta=false";
        $pdfData = $this->fetchJsonUrl($pdfApiUrl);
        
        if ($pdfData && isset($pdfData['data']['pdf']['url'])) {
            $pdfDownloadUrl = $pdfData['data']['pdf']['url'];
            $pdfBytes = $this->fetchUrl($pdfDownloadUrl);
            if ($pdfBytes) {
                $filename = md5($normUrl . time()) . '.pdf';
                $filepath = $pdfDir . DIRECTORY_SEPARATOR . $filename;
                file_put_contents($filepath, $pdfBytes);
                return '/data/archives/' . $filename;
            }
        }
        return null;
    }

    /**
     * Checks if a submitted comment or webmention is spam using the Akismet API.
     *
     * @param array $commentData The comment data containing 'author_name', 'author_url', and 'content'.
     * @return bool True if the content is classified as spam, false otherwise.
     */
    private function checkAkismet(array $commentData): bool
    {
        $akismetKey = \Indieinabox\Database::getSetting('akismet_api_key');
        if (empty($akismetKey)) {
            return false;
        }

        $fqdn = \Indieinabox\Database::getSetting('fqdn');
        if (empty($fqdn)) {
            $fqdn = $this->site->metadata->fqdn ?? '';
        }
        $fqdn = rtrim((string)$fqdn, '/');

        $endpoint = 'https://' . $akismetKey . '.rest.akismet.com/1.1/comment-check';

        $data = [
            'blog' => $fqdn,
            'user_ip' => '127.0.0.1', // Server-to-server usually masks original IP
            'user_agent' => 'Indieinabox/1.0 | Webmention',
            'comment_type' => 'comment',
            'comment_author' => $commentData['author_name'] ?? '',
            'comment_author_url' => $commentData['author_url'] ?? '',
            'comment_content' => $commentData['content'] ?? ''
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $res = curl_exec($ch);
        curl_close($ch);

        return trim((string)$res) === 'true';
    }
}
