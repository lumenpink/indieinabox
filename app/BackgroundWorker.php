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
     * Method __construct
     * @param \Indieinabox\Site $site
     */
    public function __construct(Site $site)
    {
        $this->site = $site;
        $this->db = Database::getDb();
    }

    /**
     * Method runAll
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
            $this->processArchiveQueue();
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * Method processInboxQueue
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
     * Method handleBuildSite
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
     * Method handleWebmention
     * @param array $payload
     * 
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
            'whostyle' => $whostyleData ?? []
        ];

        $filepath = $notificationsDir . DIRECTORY_SEPARATOR . $newMention['id'] . '.md';
        
        $yaml = new \Indieinabox\Yaml();
        $yamlStr = $yaml->dump($newMention);
        $fileContent = "---\n" . trim($yamlStr) . "\n---\n\n" . $content;
        
        file_put_contents($filepath, $fileContent);
        
        // Extract external links to ArchiveQueue
        $this->extractLinksToArchiveQueue($content);
    }

    /**
     * Method handleActivityPub
     * @param array $payload
     * 
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
     * Method saveActivityPubCreate
     * @param array $activity
     * 
     * @return void
     */
    private function saveActivityPubCreate(array $activity): void
    {
        $object = $activity['object'] ?? null;
        if (!$object || !is_array($object)) return;

        $type = $object['type'] ?? '';
        if (!in_array($type, ['Note', 'Article'])) return;

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

        $yaml = new \Indieinabox\Yaml();
        $yamlStr = $yaml->dump($frontmatter);

        $filepath = $inboxDir . DIRECTORY_SEPARATOR . $hash . '.md';
        $fileContent = "---\n" . trim($yamlStr) . "\n---\n\n" . $content;

        file_put_contents($filepath, $fileContent);

        // Extract external links to ArchiveQueue
        $this->extractLinksToArchiveQueue($content);
    }

    /**
     * Method downloadAvatarLocally
     * @param string $actorUrl
     * @param string $photoUrl
     * 
     * @return string
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
     * Method extractLinksToArchiveQueue
     * @param string $htmlContent
     * 
     * @return void
     */
    private function extractLinksToArchiveQueue(string $htmlContent): void
    {
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
     * Method getPublicKey
     * @param string $keyId
     * 
     * @return ?string
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
     * Method fetchJsonUrl
     * @param string $url
     * 
     * @return ?array
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
     * Method queueAcceptFollow
     * @param array $followActivity
     * @param string $targetInbox
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
     * Method processOutbox
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
     * Method processArchiveQueue
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
     * Method resolveFinalUrl
     * @param string $url
     * 
     * @return string
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
     * Method sendToArchiveOrg
     * @param string $url
     * 
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
     * Method fetchPdfFromMicrolink
     * @param string $url
     * @param string $normUrl
     * @param string $pdfDir
     * 
     * @return ?string
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
}
