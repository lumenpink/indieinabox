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
     * @var \Indieinabox\Site
     */
    private Site $site;
    /**
     * @var PDO
     */
    private PDO $db;

    /**
     * Initializes the ActivityPubHandler and ensures cryptographic keys are generated.
     *
     * @param \Indieinabox\Site $site Global site configuration and environment.
     */
    public function __construct(Site $site)
    {
        $this->site = $site;
        $this->db = Database::getDb();
        $this->ensureKeys();
    }

    /**
     * Ensures an RSA key pair exists for signing ActivityPub payloads.
     * If keys are missing, generates a new 2048-bit RSA pair and saves them to the data directory.
     *
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
     * Handles WebFinger (.well-known/webfinger) requests for actor discovery.
     * Returns a JSON JRD (JSON Resource Descriptor) mapping the requested alias to the actor profile.
     *
     * @return void
     */
    public function handleWebFinger(): void
    {
        $resource = $_GET['resource'] ?? '';
        $handle = Database::getSetting('activitypub_handle') ?? 'schwartz';
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
     * Outputs the ActivityPub Actor profile (Person) in JSON-LD format.
     * Defines inbox, outbox, public keys, and other identifying metadata.
     *
     * @return void
     */
    public function handleActor(): void
    {
        $handle = Database::getSetting('activitypub_handle') ?? 'schwartz';
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
     * Handles incoming activities (POST to /inbox).
     * Processes Follow requests by enqueuing an Accept response to a background worker.
     * Logs or ignores other types of incoming activities.
     *
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
     * Queues an Accept activity in response to a received Follow activity.
     * Stores the intent in the outbox queue to be processed asynchronously.
     *
     * @param array $followActivity The received Follow activity payload.
     * @param string $targetInbox The inbox URL of the actor who sent the Follow request.
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
     * Handles GET requests to the outbox (/outbox).
     * Returns an empty OrderedCollection by default as full outbox pagination 
     * is often not exposed or needed for static-site implementations.
     *
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

    public static function buildObjectForPageArray(string $objectId, string $actorId, string $fqdn, string $content, ?string $name, array $metadata = []): array
    {
        $object = [
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                'https://w3id.org/security/v1'
            ],
            'id' => $objectId,
            'type' => 'Note', // Default to Note for microblogging
            'published' => gmdate('Y-m-d\TH:i:s\Z'),
            'url' => $objectId,
            'attributedTo' => $actorId,
            'content' => $content,
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            'cc' => [$fqdn . '/followers']
        ];
        
        if ($name) {
            $object['type'] = 'Article';
            $object['name'] = $name;
        }

        if (isset($metadata['read_of'])) {
            $object['type'] = 'Article'; // BookWyrm uses Article for reading activities/reviews
            $object['inReplyToBook'] = $metadata['read_of'];
            
            if (isset($metadata['rating'])) {
                $object['rating'] = (int) $metadata['rating'];
            } elseif (isset($metadata['p_rating'])) {
                $object['rating'] = (int) $metadata['p_rating'];
            }
            
            if (isset($metadata['read_status'])) {
                $object['readingStatus'] = $metadata['read_status'];
            }
        }

        if (isset($metadata['reply'])) {
            $object['inReplyTo'] = $metadata['reply'];
        }

        $to = ['https://www.w3.org/ns/activitystreams#Public'];
        $cc = [$fqdn . '/followers'];

        $syn = $metadata['syndicate_to'] ?? $metadata['mp_syndicate_to'] ?? null;
        if ($syn) {
            $syndicates = is_array($syn) ? $syn : [$syn];
            foreach ($syndicates as $target) {
                // If it's a URL, we treat it as an ActivityPub actor to CC or TO. 
                // Lemmy / Kbin groups receive posts this way.
                if (filter_var($target, FILTER_VALIDATE_URL)) {
                    $to[] = $target;
                }
            }
        }
        
        $object['to'] = $to;
        $object['cc'] = $cc;
        
        return $object;
    }

    /**
     * Queues a Create activity for a new post.
     *
     * @param string $postUrl The public URL of the new post.
     * @param string $content The HTML content of the post.
     * @param ?string $name The title of the post (if applicable).
     * @return void
     */
    public function queueCreateActivity(string $postUrl, string $content, ?string $name, array $metadata = []): void
    {
        $handle = Database::getSetting('activitypub_handle') ?? 'schwartz';
        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');

        $actorId = $fqdn . '/actor';
        $objectId = $postUrl;
        
        $object = self::buildObjectForPageArray($objectId, $actorId, $fqdn, $content, $name, $metadata);
        
        $activityId = $postUrl . '#activity';
        $createActivity = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $activityId,
            'type' => 'Create',
            'actor' => $actorId,
            'published' => gmdate('Y-m-d\TH:i:s\Z'),
            'to' => $object['to'] ?? [],
            'cc' => $object['cc'] ?? [],
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
