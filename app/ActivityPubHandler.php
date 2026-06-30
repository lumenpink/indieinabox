<?php

declare(strict_types=1);

namespace Indieinabox;

use PDO;

class ActivityPubHandler
{
    private Site $site;
    private PDO $db;

    public function __construct(Site $site)
    {
        $this->site = $site;
        $this->db = Database::getDb();
        $this->ensureKeys();
    }

    private function ensureKeys(): void
    {
        $stmt = $this->db->query("SELECT * FROM activitypub_keys WHERE key_id = 'main-key'");
        if (!$stmt->fetch()) {
            $config = [
                "digest_alg" => "sha256",
                "private_key_bits" => 2048,
                "private_key_type" => OPENSSL_KEYTYPE_RSA,
            ];
            $res = openssl_pkey_new($config);
            openssl_pkey_export($res, $privKey);
            $pubKey = openssl_pkey_get_details($res)["key"];

            $sql = "INSERT INTO activitypub_keys (key_id, private_key, public_key, created_at) " .
                   "VALUES ('main-key', ?, ?, ?)";
            $insert = $this->db->prepare($sql);
            $insert->execute([$privKey, $pubKey, time()]);
        }
    }

    public function handleWebFinger(): void
    {
        $resource = $_GET['resource'] ?? '';
        $handle = Database::getSetting('activitypub_handle') ?? 'lumen';
        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        $domain = parse_url($fqdn, PHP_URL_HOST);

        $expectedAcct = "acct:{$handle}@{$domain}";

        if ($resource !== $expectedAcct) {
            http_response_code(404);
            echo json_encode(['error' => 'not found']);
            return;
        }

        header('Content-Type: application/jrd+json; charset=utf-8');
        echo json_encode([
            'subject' => $expectedAcct,
            'links' => [
                [
                    'rel' => 'self',
                    'type' => 'application/activity+json',
                    'href' => $fqdn . '/actor'
                ],
                [
                    'rel' => 'http://webfinger.net/rel/profile-page',
                    'type' => 'text/html',
                    'href' => $fqdn . '/'
                ]
            ]
        ], JSON_UNESCAPED_SLASHES);
    }

    public function handleActor(): void
    {
        $handle = Database::getSetting('activitypub_handle') ?? 'lumen';
        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');

        $stmt = $this->db->query("SELECT public_key FROM activitypub_keys WHERE key_id = 'main-key'");
        $keyRow = $stmt->fetch();
        $pubKey = $keyRow ? $keyRow['public_key'] : '';

        header('Content-Type: application/activity+json; charset=utf-8');
        echo json_encode([
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                'https://w3id.org/security/v1'
            ],
            'id' => $fqdn . '/actor',
            'type' => 'Person',
            'preferredUsername' => $handle,
            'name' => $this->site->metadata->title ?? $handle,
            'summary' => $this->site->metadata->sitename ?? '',
            'inbox' => $fqdn . '/inbox',
            'outbox' => $fqdn . '/outbox',
            'followers' => $fqdn . '/followers',
            'following' => $fqdn . '/following',
            'url' => $fqdn . '/',
            'publicKey' => [
                'id' => $fqdn . '/actor#main-key',
                'owner' => $fqdn . '/actor',
                'publicKeyPem' => $pubKey
            ]
        ], JSON_UNESCAPED_SLASHES);
    }

    public function handleInbox(): void
    {
        $body = file_get_contents('php://input');
        $activity = json_decode($body, true);

        if (!$activity) {
            http_response_code(400);
            return;
        }

        $headers = getallheaders();
        $signatureHeader = '';
        foreach ($headers as $k => $v) {
            if (strtolower($k) === 'signature') {
                $signatureHeader = $v;
                break;
            }
        }

        if (!$signatureHeader) {
            http_response_code(401);
            echo "Missing signature";
            return;
        }

        preg_match('/keyId="([^"]+)"/', $signatureHeader, $matches);
        $keyId = $matches[1] ?? '';
        if (!$keyId) {
            http_response_code(401);
            echo "Missing keyId in signature";
            return;
        }

        $pubKey = $this->getPublicKey($keyId);
        if (!$pubKey) {
            http_response_code(401);
            echo "Failed to fetch public key";
            return;
        }

        $path = $_SERVER['REQUEST_URI'] ?? '/inbox';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'POST';

        if (!HttpSignature::verify($headers, $method, $path, $pubKey)) {
            http_response_code(401);
            echo "Invalid signature";
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
            $this->handleCreateActivity($activity);
        }

        http_response_code(202);
    }

    private function getPublicKey(string $keyId): ?string
    {
        $stmt = $this->db->prepare("SELECT public_key FROM activitypub_actors WHERE actor_url = ?");
        $stmt->execute([$keyId]);
        if ($row = $stmt->fetch()) {
            return $row['public_key'];
        }

        $actorUrl = preg_replace('/#.*$/', '', $keyId);
        $ch = curl_init($actorUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/activity+json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300 && $res) {
            $data = json_decode($res, true);
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

    private function handleCreateActivity(array $activity): void
    {
        $object = $activity['object'] ?? null;
        if (!$object || !is_array($object)) {
            return;
        }

        $type = $object['type'] ?? '';
        if (!in_array($type, ['Note', 'Article'])) {
            return;
        }

        $id = $object['id'] ?? '';
        if (!$id) return;

        $hash = md5($id);
        $dataDir = \Indieinabox\Database::$dataDir ?? (dirname(__DIR__) . '/data');
        $inboxDir = $dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . 'inbox';
        
        if (!is_dir($inboxDir)) {
            @mkdir($inboxDir, 0755, true);
        }

        $actor = $activity['actor'] ?? '';
        $authorName = $actor;
        $authorPhoto = '';
        
        // Optionally fetch actor details for name and photo, but for now we use actor url
        
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

    public function handleOutbox(): void
    {
        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        header('Content-Type: application/activity+json; charset=utf-8');
        echo json_encode([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $fqdn . '/outbox',
            'type' => 'OrderedCollection',
            'totalItems' => 0,
            'orderedItems' => []
        ]);
    }

    public function queueCreateActivity(string $postUrl, string $content, ?string $name): void
    {
        $handle = Database::getSetting('activitypub_handle') ?? 'lumen';
        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        
        $actorId = $fqdn . '/actor';
        $objectId = $postUrl;
        
        $object = [
            'id' => $objectId,
            'type' => 'Note', // Default to Note for microblogging
            'published' => gmdate('Y-m-d\TH:i:s\Z'),
            'url' => $postUrl,
            'attributedTo' => $actorId,
            'content' => $content,
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            'cc' => [$fqdn . '/followers']
        ];
        
        if ($name) {
            $object['type'] = 'Article';
            $object['name'] = $name;
        }

        $activityId = $postUrl . '#activity';
        $createActivity = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $activityId,
            'type' => 'Create',
            'actor' => $actorId,
            'published' => gmdate('Y-m-d\TH:i:s\Z'),
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            'cc' => [$fqdn . '/followers'],
            'object' => $object
        ];

        $payload = json_encode($createActivity, JSON_UNESCAPED_SLASHES);

        // Fetch all followers
        $stmt = $this->db->query("SELECT inbox_url, shared_inbox_url FROM activitypub_followers");
        $followers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $inboxes = [];
        foreach ($followers as $f) {
            $inbox = !empty($f['shared_inbox_url']) ? $f['shared_inbox_url'] : $f['inbox_url'];
            if (!in_array($inbox, $inboxes)) {
                $inboxes[] = $inbox;
            }
        }

        $sql = "INSERT INTO activitypub_outbox (payload_json, target_inbox, status, created_at) " .
               "VALUES (?, ?, 'pending', ?)";
        $insertStmt = $this->db->prepare($sql);
        $now = time();
        foreach ($inboxes as $target) {
            $insertStmt->execute([$payload, $target, $now]);
        }
    }

    public static function processOutbox(): void
    {
        echo "Running ActivityPub outbox processor...\n";

        $db = Database::getDb();

        // Prune old outbox entries (7 days) and actors (30 days)
        $sevenDaysAgo = time() - (7 * 86400);
        $thirtyDaysAgo = time() - (30 * 86400);
        
        $db->exec("DELETE FROM activitypub_outbox WHERE status IN ('sent', 'failed') AND created_at < $sevenDaysAgo");
        $db->exec("DELETE FROM activitypub_actors WHERE updated_at < $thirtyDaysAgo");

        $stmt = $db->query("SELECT private_key FROM activitypub_keys WHERE key_id = 'main-key'");
        $keyRow = $stmt->fetch();
        if (!$keyRow) {
            echo "No RSA key found. Exiting.\n";
            exit(1);
        }
        $privateKey = $keyRow['private_key'];

        $sql = "SELECT id, payload_json, target_inbox FROM activitypub_outbox " .
               "WHERE status = 'pending' LIMIT 50";
        $stmt = $db->query($sql);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($messages)) {
            echo "No pending messages.\n";
            exit(0);
        }

        $fqdn = Database::getSetting('fqdn');
        if (!$fqdn) {
            echo "FQDN not configured in database settings.\n";
            exit(1);
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
            
            // For local dev SSL
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                echo "Success ($httpCode).\n";
                $update = $db->prepare("UPDATE activitypub_outbox SET status = 'sent' WHERE id = ?");
                $update->execute([$id]);
            } else {
                echo "Failed ($httpCode): $error\n";
                $update = $db->prepare("UPDATE activitypub_outbox SET status = 'failed' WHERE id = ?");
                $update->execute([$id]);
            }
        }

        echo "Done.\n";
    }
}
