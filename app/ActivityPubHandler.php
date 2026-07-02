<?php

declare(strict_types=1);

namespace Indieinabox;

use PDO;

/**
 * Class ActivityPubHandler
 */
class ActivityPubHandler
{
    /**
     * @var Indieinabox\Site
     */
    private Site $site;
    /**
     * @var PDO
     */
    private PDO $db;

    /**
     * Method __construct
     * @param Indieinabox\Site $site
     */
    public function __construct(Site $site)
    {
        $this->site = $site;
        $this->db = Database::getDb();
        $this->ensureKeys();
    }

    /**
     * Method ensureKeys
     * @return void
     */
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

    /**
     * Method handleWebFinger
     * @return void
     */
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

    /**
     * Method handleActor
     * @return void
     */
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

    /**
     * Method handleInbox
     * @return void
     */
    public function handleInbox(): void
    {
        $body = file_get_contents('php://input');

        $headers = getallheaders();
        $path = $_SERVER['REQUEST_URI'] ?? '/inbox';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'POST';

        $payload = [
            'method' => $method,
            'path' => $path,
            'headers' => $headers,
            'body' => $body
        ];

        $sql = "INSERT INTO inbox_queue (type, payload_json, created_at) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['activitypub', json_encode($payload), time()]);

        http_response_code(202);
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
     * Method handleOutbox
     * @return void
     */
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

    /**
     * Method queueCreateActivity
     * @param string $postUrl
     * @param string $content
     * @param ?string $name
     * 
     * @return void
     */
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

}
