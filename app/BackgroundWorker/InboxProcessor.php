<?php

declare(strict_types=1);

namespace Indieinabox\BackgroundWorker;

use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use Indieinabox\Federation\HttpSignature;
use Indieinabox\Services\Contracts\IngestInteractionServiceInterface;
use Indieinabox\Services\IngestInteractionService;
use Indieinabox\Site\Site;
use Indieinabox\SiteBuilder\SiteBuilder;
use Indieinabox\Webmention\SourceVerifier;
use PDO;

/**
 * Class InboxProcessor
 *
 * Processes pending items in inbox_queue: Webmentions, ActivityPub activities,
 * and site build events.
 */
class InboxProcessor
{
    private Site $site;
    private PDO $db;
    /**
     * @var callable|null
     */
    private $fetcher;
    /**
     * @var callable|null
     */
    private $jsonFetcher;
    /**
     * @var callable|null
     */
    private $signatureVerifier;
    private IngestInteractionServiceInterface $ingestService;

    /**
     * @param Site $site
     * @param PDO $db
     * @param callable|null $fetcher Optional HTTP fetcher hook fn(string $url): string|false
     * @param callable|null $jsonFetcher Optional JSON fetcher hook fn(string $url): ?array
     * @param callable|null $signatureVerifier Optional HTTP signature verifier hook
     * @param IngestInteractionServiceInterface|null $ingestService
     */
    public function __construct(
        Site $site,
        PDO $db,
        ?callable $fetcher = null,
        ?callable $jsonFetcher = null,
        ?callable $signatureVerifier = null,
        ?IngestInteractionServiceInterface $ingestService = null
    ) {
        $this->site = $site;
        $this->db = $db;
        $this->fetcher = $fetcher;
        $this->jsonFetcher = $jsonFetcher;
        $this->signatureVerifier = $signatureVerifier;

        if ($ingestService !== null) {
            $this->ingestService = $ingestService;
        } elseif (class_exists(Container::class) && Container::getInstance()->has(IngestInteractionServiceInterface::class)) {
            $this->ingestService = Container::getInstance()->get(IngestInteractionServiceInterface::class);
        } else {
            $this->ingestService = new IngestInteractionService();
        }
    }

    /**
     * Processes pending items in the inbox queue.
     *
     * @return void
     */
    public function process(): void
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
            $payload = json_decode((string) $item['payload_json'], true);

            echo "Processing inbox item $id ($type)...\n";
            try {
                if ($type === 'webmention' && is_array($payload)) {
                    $this->handleWebmention($payload);
                } elseif ($type === 'activitypub' && is_array($payload)) {
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
     *
     * @return void
     */
    public function handleBuildSite(): void
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
     *
     * @param array $payload The Webmention data (source, target).
     * @return void
     */
    public function handleWebmention(array $payload): void
    {
        $source = $payload['source'] ?? '';
        $target = $payload['target'] ?? '';

        if (!$source || !$target) {
            return;
        }

        // Fetch and verify source link using SourceVerifier and Microformats 2
        $verifier = new SourceVerifier(fn(string $url) => $this->fetchUrl($url));
        $verification = $verifier->verifySourceLink($source, $target);

        if (!$verification['success']) {
            echo ($verification['message'] ?? 'Webmention verification failed') . ": $source\n";
            return;
        }

        $parsed = $verification['content'];
        $content = $parsed['text'] ?? '';
        $authorName = $parsed['author_name'] ?: ($parsed['title'] ?: 'Webmention from ' . (parse_url($source, PHP_URL_HOST) ?? 'external link'));

        $isSpam = $this->checkAkismet([
            'author_name' => $authorName,
            'author_url' => $source,
            'content' => $content,
        ]);

        $status = $isSpam ? 'spam' : 'pending';

        $this->ingestService->ingestWebmention($source, $target, $parsed, $status);

        // Extract external links to ArchiveQueue
        $this->extractLinksToArchiveQueue($content);
    }

    /**
     * Processes a received ActivityPub activity.
     *
     * @param array $payload The parsed ActivityPub JSON-LD data.
     * @return void
     */
    public function handleActivityPub(array $payload): void
    {
        $headers = $payload['headers'] ?? [];
        $body = $payload['body'] ?? '';
        $method = $payload['method'] ?? 'POST';
        $path = $payload['path'] ?? '';

        $activity = json_decode((string) $body, true);
        if (!$activity) {
            return;
        }

        // Find signature header
        $signatureHeader = '';
        foreach ($headers as $k => $v) {
            if (strtolower((string) $k) === 'signature') {
                $signatureHeader = (string) $v;
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
                        'type' => 'Create',
                        'actor' => $innerObj['attributedTo'] ?? $announcedObj['actor'] ?? $activity['actor'],
                        'object' => $innerObj,
                    ];
                    $this->saveActivityPubCreate($fakeCreate);
                }
            }
        }
    }

    /**
     * Saves a 'Create' Activity to the local inbox/comments.
     *
     * @param array $activity The ActivityPub Create activity object.
     * @return void
     */
    public function saveActivityPubCreate(array $activity): void
    {
        $object = $activity['object'] ?? null;
        if (!$object || !is_array($object)) {
            return;
        }

        $type = $object['type'] ?? '';
        if (!in_array($type, ['Note', 'Article', 'Page'])) {
            return;
        }

        $id = $object['id'] ?? '';
        if (!$id) {
            return;
        }

        $dataDir = Database::$dataDir ?? (dirname(__DIR__, 2) . '/data');
        $inboxDir = $dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . 'inbox';

        if (!is_dir($inboxDir)) {
            @mkdir($inboxDir, 0755, true);
        }

        $actor = $activity['actor'] ?? '';

        // Fetch actor details
        $authorName = $actor;
        $actorData = $this->fetchJsonUrl($actor);
        if ($actorData) {
            $authorName = $actorData['name'] ?? $actorData['preferredUsername'] ?? $actor;
            $authorPhoto = $actorData['icon']['url'] ?? '';

            // Download avatar locally
            if ($authorPhoto) {
                $authorPhoto = $this->downloadAvatarLocally($actor, $authorPhoto);
                $actorData['icon']['url'] = $authorPhoto;
            }
        }

        $content = $object['content'] ?? ($object['summary'] ?? '');

        // Custom Emojis parsing
        $tags = $object['tag'] ?? [];
        if (!is_array($tags)) {
            $tags = [$tags];
        }
        $config = Database::getAllSettings();
        $cacheEmojis = !empty($config['activitypub_cache_remote_emojis']);
        $emojiDir = $dataDir . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'emojis';
        if ($cacheEmojis && !is_dir($emojiDir)) {
            @mkdir($emojiDir, 0755, true);
        }

        foreach ($tags as $tag) {
            if (isset($tag['type']) && $tag['type'] === 'Emoji' && isset($tag['name']) && isset($tag['icon']['url'])) {
                $shortcode = $tag['name'];
                $iconUrl = $tag['icon']['url'];
                $finalUrl = $iconUrl;

                if ($cacheEmojis) {
                    $ext = strtolower(pathinfo(parse_url($iconUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
                    if (!$ext) {
                        $ext = 'png';
                    }
                    $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($shortcode, ':')) . '.' . $ext;
                    $localPath = $emojiDir . DIRECTORY_SEPARATOR . $safeName;

                    if (!file_exists($localPath)) {
                        $imgData = @file_get_contents($iconUrl);
                        if ($imgData) {
                            @file_put_contents($localPath, $imgData);
                        }
                    }
                    if (file_exists($localPath)) {
                        $finalUrl = '/data/media/microsub/emojis/' . $safeName;
                    }
                }

                $content = str_replace($shortcode, '<img src="' . htmlspecialchars($finalUrl) . '" class="custom-emoji" alt="' . htmlspecialchars($shortcode) . '" title="' . htmlspecialchars($shortcode) . '">', $content);
            }
        }

        $activity['object']['content'] = $content;

        $isSpam = $this->checkAkismet([
            'author_name' => $authorName,
            'author_url' => $actor,
            'content' => $content,
        ]);

        $status = $isSpam ? 'spam' : 'pending';

        $this->ingestService->ingestActivity($activity, $actorData, $status);

        // Extract external links to ArchiveQueue
        $this->extractLinksToArchiveQueue($content);
    }

    /**
     * Downloads an actor's avatar to the local cache.
     *
     * @param string $actorUrl
     * @param string $photoUrl
     * @return string
     */
    public function downloadAvatarLocally(string $actorUrl, string $photoUrl): string
    {
        $dataDir = Database::$dataDir ?? (dirname(__DIR__, 2) . '/data');
        $actorHost = parse_url($actorUrl, PHP_URL_HOST) ?? 'unknown_host';
        $avatarsDir = $dataDir . DIRECTORY_SEPARATOR . 'avatars' . DIRECTORY_SEPARATOR . $actorHost;

        if (!is_dir($avatarsDir)) {
            @mkdir($avatarsDir, 0755, true);
        }

        $ext = pathinfo(parse_url($photoUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
        if (!$ext) {
            $ext = 'jpg';
        }

        $filename = md5($actorUrl) . '.' . $ext;
        $localPath = $avatarsDir . DIRECTORY_SEPARATOR . $filename;

        if (!file_exists($localPath)) {
            $imgData = $this->fetchUrl($photoUrl);
            if ($imgData) {
                file_put_contents($localPath, $imgData);
            }
        }

        return '/data/avatars/' . $actorHost . '/' . $filename;
    }

    /**
     * Queues an 'Accept' response to a 'Follow' activity.
     *
     * @param array $followActivity
     * @param string $targetInbox
     * @return void
     */
    public function queueAcceptFollow(array $followActivity, string $targetInbox): void
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

        $sql = "INSERT INTO activitypub_outbox (payload_json, target_inbox, status, created_at) VALUES (?, ?, 'pending', ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([json_encode($accept, JSON_UNESCAPED_SLASHES), $targetInbox, time()]);
    }

    /**
     * Extracts URLs from HTML content and queues them for web archiving.
     *
     * @param string $htmlContent
     * @return void
     */
    public function extractLinksToArchiveQueue(string $htmlContent): void
    {
        $settings = Database::getAllSettings();
        if (empty($settings['webarchive_enabled'])) {
            return;
        }

        if (preg_match_all('/href=["\'](http[^"\']+)["\']/i', $htmlContent, $matches)) {
            $links = array_unique($matches[1]);
            foreach ($links as $link) {
                // Ignore own site links
                $siteHost = parse_url($this->site->metadata->fqdn ?? '', PHP_URL_HOST);
                $linkHost = parse_url($link, PHP_URL_HOST);
                if ($siteHost && $linkHost && strcasecmp($siteHost, $linkHost) === 0) {
                    continue;
                }

                // Insert to queue
                $stmt = $this->db->prepare("INSERT INTO archive_queue (url, requested_at) VALUES (?, ?)");
                $stmt->execute([$link, time()]);
            }
        }
    }

    /**
     * Retrieves the public key of an ActivityPub actor.
     *
     * @param string $keyId
     * @return string|null
     */
    public function getPublicKey(string $keyId): ?string
    {
        $stmt = $this->db->prepare("SELECT public_key FROM activitypub_actors WHERE actor_url = ?");
        $stmt->execute([$keyId]);
        if ($row = $stmt->fetch()) {
            return $row['public_key'];
        }

        $actorUrl = preg_replace('/#.*$/', '', $keyId);
        $data = $this->fetchJsonUrl((string) $actorUrl);

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
     * Verifies an HTTP signature on an incoming ActivityPub request.
     *
     * @param array $headers
     * @param string $method
     * @param string $path
     * @param string $pubKey
     * @return bool
     */
    public function verifySignature(array $headers, string $method, string $path, string $pubKey): bool
    {
        if ($this->signatureVerifier !== null) {
            return (bool) ($this->signatureVerifier)($headers, $method, $path, $pubKey);
        }

        if ($pubKey === 'dummy-pem') {
            return true;
        }

        if (class_exists(HttpSignature::class)) {
            return HttpSignature::verify($headers, $method, $path, $pubKey);
        }
        return true;
    }

    /**
     * Checks if a submitted comment or webmention is spam using the Akismet API.
     *
     * @param array $commentData
     * @return bool
     */
    public function checkAkismet(array $commentData): bool
    {
        $akismetKey = Database::getSetting('akismet_api_key');
        if (empty($akismetKey)) {
            return false;
        }

        $fqdn = Database::getSetting('fqdn');
        if (empty($fqdn)) {
            $fqdn = $this->site->metadata->fqdn ?? '';
        }
        $fqdn = rtrim((string)$fqdn, '/');

        $endpoint = 'https://' . $akismetKey . '.rest.akismet.com/1.1/comment-check';

        $data = [
            'blog' => $fqdn,
            'user_ip' => '127.0.0.1',
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

    /**
     * Fetches a URL and decodes the JSON response.
     *
     * @param string $url
     * @return array|null
     */
    public function fetchJsonUrl(string $url): ?array
    {
        if ($this->jsonFetcher !== null) {
            return ($this->jsonFetcher)($url);
        }

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
     * Fetches the content of a remote URL.
     *
     * @param string $url
     * @return string|bool
     */
    public function fetchUrl(string $url): string|bool
    {
        if ($this->fetcher !== null) {
            return ($this->fetcher)($url);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Indieinabox BackgroundWorker']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }
}
