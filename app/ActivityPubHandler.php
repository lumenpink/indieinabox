<?php

declare(strict_types=1);

namespace Indieinabox;

use PDO;
use Indieinabox\ActivityPub\ActivityBuilder;
use Indieinabox\ActivityPub\InteractionHandler;
use Indieinabox\ActivityPub\KeyManager;

/**
 * Class ActivityPubHandler
 *
 * Orchestrates ActivityPub server endpoints (WebFinger, Actor, Inbox, Outbox)
 * and delegates domain-specific logic to KeyManager, ActivityBuilder, and InteractionHandler.
 */
class ActivityPubHandler
{
    /**
     * @var Site Global site configuration and environment.
     */
    private Site $site;

    /**
     * @var PDO Database connection.
     */
    private PDO $db;

    /**
     * @var KeyManager RSA key manager.
     */
    private KeyManager $keyManager;

    /**
     * @var InteractionHandler Federated interaction handler.
     */
    private InteractionHandler $interactionHandler;

    /**
     * Initializes the ActivityPubHandler, binds dependencies, and ensures cryptographic keys exist.
     *
     * @param Site $site Global site configuration.
     * @param ?PDO $db Database connection.
     * @param ?KeyManager $keyManager Key manager service.
     * @param ?InteractionHandler $interactionHandler Interaction handler service.
     */
    public function __construct(
        Site $site,
        ?PDO $db = null,
        ?KeyManager $keyManager = null,
        ?InteractionHandler $interactionHandler = null
    ) {
        $this->site = $site;
        $this->db = $db ?? Database::getDb();
        $this->keyManager = $keyManager ?? new KeyManager($this->db);
        $this->interactionHandler = $interactionHandler ?? new InteractionHandler($this->site, $this->db);
        $this->ensureKeys();
    }

    /**
     * Ensures an RSA key pair exists for signing ActivityPub payloads.
     *
     * @return void
     */
    public function ensureKeys(): void
    {
        $this->keyManager->ensureKeys();
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
                ],
                [
                    'rel' => 'http://ostatus.org/schema/1.0/subscribe',
                    'template' => $fqdn . '/authorize_interaction?uri={uri}'
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
        $pubKey = $this->keyManager->getPublicKey('main-key') ?? '';

        $avatarUrl = Database::getSetting('activitypub_avatar');
        $backgroundUrl = Database::getSetting('activitypub_background');

        $actor = [
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                'https://w3id.org/security/v1'
            ],
            'id' => $fqdn . '/actor',
            'type' => 'Person',
            'preferredUsername' => $handle,
            'name' => $this->site->metadata->title ?? $handle,
            'summary' => Database::getSetting('activitypub_bio') ?? '',
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
        ];

        if ($avatarUrl) {
            $actor['icon'] = [
                'type' => 'Image',
                'mediaType' => 'image/png',
                'url' => $fqdn . $avatarUrl
            ];
        }

        if ($backgroundUrl) {
            $actor['image'] = [
                'type' => 'Image',
                'mediaType' => 'image/png',
                'url' => $fqdn . $backgroundUrl
            ];
        }

        header('Content-Type: application/activity+json; charset=utf-8');
        echo json_encode($actor, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handles incoming activities (POST to /inbox).
     * Enqueues incoming activities into inbox_queue for asynchronous background processing.
     *
     * @return void
     */
    public function handleInbox(): void
    {
        $body = file_get_contents('php://input') ?: '';

        $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
        if (empty($headers)) {
            foreach ($_SERVER as $name => $value) {
                if (str_starts_with($name, 'HTTP_')) {
                    $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                    $headers[$headerName] = (string)$value;
                }
            }
        }

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
     * @param array<string, mixed> $followActivity The received Follow activity payload.
     * @param string $targetInbox The inbox URL of the actor who sent the Follow request.
     * @return void
     */
    public function queueAcceptFollow(array $followActivity, string $targetInbox): void
    {
        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        $acceptId = $fqdn . '/activity/' . uniqid();
        $accept = ActivityBuilder::buildAcceptActivity($acceptId, $fqdn . '/actor', $followActivity);

        $sql = "INSERT INTO activitypub_outbox (payload_json, target_inbox, status, created_at) " .
               "VALUES (?, ?, 'pending', ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([json_encode($accept, JSON_UNESCAPED_SLASHES), $targetInbox, time()]);
    }

    /**
     * Handles GET requests to the outbox (/outbox).
     * Returns an empty OrderedCollection by default.
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

    /**
     * Builds an ActivityStreams Note or Article object array for a page or post.
     *
     * @param string $objectId Unique object IRI.
     * @param string $actorId Local actor IRI.
     * @param string $fqdn Fully qualified domain name.
     * @param string $content Post content.
     * @param ?string $name Post title.
     * @param array<string, mixed> $metadata Post frontmatter metadata.
     * @return array<string, mixed>
     */
    public static function buildObjectForPageArray(
        string $objectId,
        string $actorId,
        string $fqdn,
        string $content,
        ?string $name,
        array $metadata = []
    ): array {
        return ActivityBuilder::buildObjectForPageArray($objectId, $actorId, $fqdn, $content, $name, $metadata);
    }

    /**
     * Queues a Create activity for a new post and broadcasts it to all followers.
     *
     * @param string $postUrl The public URL of the new post.
     * @param string $content The HTML content of the post.
     * @param ?string $name The title of the post (if applicable).
     * @param array<string, mixed> $metadata Metadata array.
     * @return void
     */
    public function queueCreateActivity(string $postUrl, string $content, ?string $name, array $metadata = []): void
    {
        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        $actorId = $fqdn . '/actor';
        $objectId = $postUrl;

        $object = ActivityBuilder::buildObjectForPageArray($objectId, $actorId, $fqdn, $content, $name, $metadata);
        $activityId = $postUrl . '#activity';
        $createActivity = ActivityBuilder::buildCreateActivity($activityId, $actorId, $object);

        $payload = json_encode($createActivity, JSON_UNESCAPED_SLASHES);

        // Fetch all followers
        $stmt = $this->db->query("SELECT inbox_url, shared_inbox_url FROM activitypub_followers");
        $followers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $inboxes = [];
        foreach ($followers as $f) {
            $inbox = !empty($f['shared_inbox_url']) ? $f['shared_inbox_url'] : $f['inbox_url'];
            if (!in_array($inbox, $inboxes, true)) {
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

    /**
     * Handles /interact route.
     *
     * @return void
     */
    public function handleInteract(): void
    {
        $this->interactionHandler->handleInteract();
    }

    /**
     * Handles /authorize_interaction route.
     *
     * @return void
     */
    public function handleAuthorizeInteraction(): void
    {
        $this->interactionHandler->handleAuthorizeInteraction();
    }
}
