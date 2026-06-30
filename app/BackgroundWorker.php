<?php

declare(strict_types=1);

namespace Indieinabox;

use PDO;
use DOMDocument;
use DOMXPath;
use DOMElement;

class BackgroundWorker
{
    private PDO $db;
    private Site $site;

    public function __construct(Site $site)
    {
        $this->site = $site;
        $this->db = Database::getDb();
    }

    public function runAll(): void
    {
        $this->processInboxQueue();
        $this->processOutbox();
        $this->processArchiveQueue();
    }

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
        foreach ($xpath->query('//a[@href]') as $link) {
            if ($link instanceof DOMElement) {
                $href = $link->getAttribute('href');
                // Basic matching for now (can be improved)
                if (strpos($href, parse_url($target, PHP_URL_PATH)) !== false || strpos($href, $target) !== false) {
                    $found = true;
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

        // We skip verification for now if the library throws. In real env it would be:
        if (!HttpSignature::verify($headers, $method, $path, $pubKey)) {
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

    public function processArchiveQueue(): void
    {
        echo "Running Archive Queue processor...\n";
        
        $sql = "SELECT id, url, requested_at FROM archive_queue WHERE status = 'pending' ORDER BY id ASC LIMIT 10";
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
            $requestedAt = $item['requested_at'];
            
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
            
            // Check if we already have a recent snapshot (within 24 hours)
            $check = $this->db->prepare("SELECT id FROM archived_links WHERE url = ? AND timestamp > ?");
            $check->execute([$normUrl, $requestedAt - 86400]);
            if ($check->fetch()) {
                echo "Recent snapshot exists, skipping.\n";
                $this->db->prepare("DELETE FROM archive_queue WHERE id = ?")->execute([$id]);
                continue;
            }

            // 1. Send to Archive.org (fire and forget)
            $archiveOrgUrl = "https://web.archive.org/save/" . $url;
            $ch = curl_init($archiveOrgUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_USERAGENT, "Indieinabox WebArchiver");
            curl_exec($ch);
            curl_close($ch);

            // 2. Generate PDF via Microlink API
            $pdfApiUrl = "https://api.microlink.io/?url=" . urlencode($url) . "&pdf=true&meta=false";
            $pdfData = $this->fetchJsonUrl($pdfApiUrl);
            
            $localPdfPath = null;
            if ($pdfData && isset($pdfData['data']['pdf']['url'])) {
                $pdfDownloadUrl = $pdfData['data']['pdf']['url'];
                $pdfBytes = $this->fetchUrl($pdfDownloadUrl);
                if ($pdfBytes) {
                    $filename = md5($normUrl . time()) . '.pdf';
                    $filepath = $pdfDir . DIRECTORY_SEPARATOR . $filename;
                    file_put_contents($filepath, $pdfBytes);
                    $localPdfPath = '/data/archives/' . $filename;
                }
            }

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
}
