<?php

declare(strict_types=1);

namespace Indieinabox\Http\Controllers;

use Indieinabox\ActivityPub\InteractionHandler;
use Indieinabox\ActivityPub\KeyManager;
use Indieinabox\Core\Database;
use Indieinabox\Federation\ActivityPubC2SService;
use Indieinabox\Federation\Contracts\ActivityPubC2SInterface;
use Indieinabox\Federation\Contracts\FlowerbedGroupInterface;
use Indieinabox\Federation\FlowerbedGroupService;
use Indieinabox\Repositories\SqliteActivityPubRepository;
use Indieinabox\Site\Site;
use PDO;

/**
 * Controller managing HTTP endpoints for ActivityPub federation, actor discovery, and inbox/outbox.
 */
class ActivityPubController extends AbstractController
{
    private PDO $db;
    private KeyManager $keyManager;
    private InteractionHandler $interactionHandler;
    private ActivityPubC2SInterface $c2s;
    private FlowerbedGroupInterface $flowerbedGroup;

    public function __construct(
        Site $site,
        ?PDO $db = null,
        ?KeyManager $keyManager = null,
        ?InteractionHandler $interactionHandler = null,
        ?ActivityPubC2SInterface $c2s = null,
        ?FlowerbedGroupInterface $flowerbedGroup = null
    ) {
        parent::__construct($site);
        $this->db = $db ?? Database::getDb();
        $this->keyManager = $keyManager ?? new KeyManager($this->db);
        $this->interactionHandler = $interactionHandler ?? new InteractionHandler($this->site, $this->db);
        $repo = new SqliteActivityPubRepository($this->db);
        $this->c2s = $c2s ?? new ActivityPubC2SService($this->site, $repo);
        $this->flowerbedGroup = $flowerbedGroup ?? new FlowerbedGroupService($this->site, $repo, $this->keyManager);
        $this->keyManager->ensureKeys();
    }

    public function getKeyManager(): KeyManager
    {
        return $this->keyManager;
    }

    public function getInteractionHandler(): InteractionHandler
    {
        return $this->interactionHandler;
    }

    /**
     * Handles /interact route.
     */
    public function interact(): void
    {
        $this->interactionHandler->handleInteract();
    }

    /**
     * Handles /authorize_interaction route.
     */
    public function authorizeInteraction(): void
    {
        $this->interactionHandler->handleAuthorizeInteraction();
    }

    /**
     * Handles WebFinger (.well-known/webfinger) requests for actor discovery.
     */
    public function webfinger(): void
    {
        $resource = $_GET['resource'] ?? '';
        $handle = Database::getSetting('activitypub_handle') ?? 'schwartz';
        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        $domain = parse_url($fqdn, PHP_URL_HOST);

        $expectedAcct = "acct:{$handle}@{$domain}";

        // WebFinger discovery for Flowerbed Group Actors (e.g. acct:flowerbed-tech@domain or existing flowerbed slug)
        if (preg_match('/^acct:(?:flowerbed-([a-zA-Z0-9_\-]+)|([a-zA-Z0-9_\-]+))@' . preg_quote((string) $domain, '/') . '$/i', $resource, $fbMatches)) {
            $isExplicitFlowerbed = !empty($fbMatches[1]);
            $flowerbedSlug = strtolower(!empty($fbMatches[1]) ? $fbMatches[1] : $fbMatches[2]);

            if ($flowerbedSlug !== strtolower($handle)) {
                $isKnown = $isExplicitFlowerbed;
                if (!$isKnown) {
                    try {
                        $stmtFb = $this->db->prepare('SELECT COUNT(*) FROM flowerbed_followers WHERE flowerbed_slug = ?');
                        $stmtFb->execute([$flowerbedSlug]);
                        $isKnown = ((int) $stmtFb->fetchColumn()) > 0;
                    } catch (\Throwable) {
                        $isKnown = false;
                    }
                }

                if ($isKnown) {
                    $groupActorUri = $fqdn . '/@flowerbed/' . $flowerbedSlug;
                    header('Content-Type: application/jrd+json; charset=utf-8');
                    echo json_encode([
                        'subject' => $resource,
                        'aliases' => [
                            $groupActorUri,
                            $fqdn . '/flowerbed/' . $flowerbedSlug . '/',
                        ],
                        'links' => [
                            [
                                'rel' => 'self',
                                'type' => 'application/activity+json',
                                'href' => $groupActorUri,
                            ],
                            [
                                'rel' => 'http://webfinger.net/rel/profile-page',
                                'type' => 'text/html',
                                'href' => $fqdn . '/flowerbed/' . $flowerbedSlug . '/',
                            ],
                        ],
                    ], JSON_UNESCAPED_SLASHES);
                    return;
                }
            }
        }

        if ($resource !== $expectedAcct) {
            $this->jsonResponse(['error' => 'not found'], 404);
            return;
        }

        header('Content-Type: application/jrd+json; charset=utf-8');
        echo json_encode([
            'subject' => $expectedAcct,
            'links' => [
                [
                    'rel' => 'self',
                    'type' => 'application/activity+json',
                    'href' => $fqdn . '/actor',
                ],
                [
                    'rel' => 'http://webfinger.net/rel/profile-page',
                    'type' => 'text/html',
                    'href' => $fqdn . '/',
                ],
                [
                    'rel' => 'http://ostatus.org/schema/1.0/subscribe',
                    'template' => $fqdn . '/authorize_interaction?uri={uri}',
                ],
            ],
        ], JSON_UNESCAPED_SLASHES);
    }

    /**
     * Outputs the ActivityPub Actor profile (Person) in JSON-LD format.
     */
    public function actor(): void
    {
        $handle = Database::getSetting('activitypub_handle') ?? 'schwartz';
        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        $pubKey = $this->keyManager->getPublicKey('main-key') ?? '';

        $avatarUrl = Database::getSetting('activitypub_avatar');
        $backgroundUrl = Database::getSetting('activitypub_background');

        $actor = [
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                'https://w3id.org/security/v1',
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
                'publicKeyPem' => $pubKey,
            ],
        ];

        if ($avatarUrl) {
            $actor['icon'] = [
                'type' => 'Image',
                'mediaType' => 'image/png',
                'url' => $fqdn . $avatarUrl,
            ];
        }

        if ($backgroundUrl) {
            $actor['image'] = [
                'type' => 'Image',
                'mediaType' => 'image/png',
                'url' => $fqdn . $backgroundUrl,
            ];
        }

        header('Content-Type: application/activity+json; charset=utf-8');
        echo json_encode($actor, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handles incoming activities (POST to /inbox).
     */
    public function inbox(): void
    {
        $body = file_get_contents('php://input') ?: '';

        // Defederation & Moderation check
        $parsedBody = json_decode($body, true);
        if (is_array($parsedBody)) {
            $senderActor = is_string($parsedBody['actor'] ?? null)
                ? $parsedBody['actor']
                : ($parsedBody['actor']['id'] ?? null);
            if ($senderActor !== null) {
                $moderationService = new \Indieinabox\Moderation\ModerationService($this->db);
                if ($moderationService->isBlocked(null, (string) $senderActor)) {
                    http_response_code(403);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['error' => 'forbidden', 'message' => 'Originating domain or actor is blocked by moderation policy']);
                    return;
                }
            }
        }

        $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
        if (empty($headers)) {
            foreach ($_SERVER as $name => $value) {
                if (str_starts_with($name, 'HTTP_')) {
                    $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                    $headers[$headerName] = (string) $value;
                }
            }
        }

        $path = $_SERVER['REQUEST_URI'] ?? '/inbox';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'POST';

        $payload = [
            'method' => $method,
            'path' => $path,
            'headers' => $headers,
            'body' => $body,
        ];

        $sql = "INSERT INTO inbox_queue (type, payload_json, created_at) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['activitypub', json_encode($payload), time()]);

        http_response_code(202);
    }

    /**
     * Handles requests to the outbox (/outbox).
     * Supports W3C ActivityPub Client-to-Server (POST) and OrderedCollection retrieval (GET).
     */
    public function outbox(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'POST') {
            $tokenManager = new \Indieinabox\IndieAuth\TokenManager($this->db);
            $token = null;
            $auth = $tokenManager->validateBearerToken($token);

            if ($auth === null) {
                http_response_code(401);
                header('WWW-Authenticate: Bearer error="invalid_token"');
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'unauthorized', 'error_description' => 'Valid Bearer token required for C2S submission']);
                return;
            }

            $raw = file_get_contents('php://input') ?: '';
            $payload = json_decode($raw, true);
            if (!is_array($payload)) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'invalid_json', 'error_description' => 'Malformed JSON payload']);
                return;
            }

            $result = $this->c2s->handleSubmission($payload, $auth);
            http_response_code($result['status']);
            foreach ($result['headers'] as $hName => $hVal) {
                header("{$hName}: {$hVal}");
            }
            echo json_encode($result['body'], JSON_UNESCAPED_SLASHES);
            return;
        }

        // GET /outbox
        header('Content-Type: application/activity+json; charset=utf-8');
        echo json_encode($this->c2s->getOutboxCollection(), JSON_UNESCAPED_SLASHES);
    }

    public function flowerbedActor(string $slug): void
    {
        header('Content-Type: application/activity+json; charset=utf-8');
        echo json_encode($this->flowerbedGroup->getActor($slug), JSON_UNESCAPED_SLASHES);
    }

    public function flowerbedInbox(string $slug): void
    {
        $raw = file_get_contents('php://input') ?: '';
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $result = $this->flowerbedGroup->handleInbox($slug, $payload);
        http_response_code($result['status']);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result['body'], JSON_UNESCAPED_SLASHES);
    }

    public function flowerbedOutbox(string $slug): void
    {
        header('Content-Type: application/activity+json; charset=utf-8');
        echo json_encode($this->flowerbedGroup->getOutbox($slug), JSON_UNESCAPED_SLASHES);
    }

    public function flowerbedFollowers(string $slug): void
    {
        header('Content-Type: application/activity+json; charset=utf-8');
        echo json_encode($this->flowerbedGroup->getFollowers($slug), JSON_UNESCAPED_SLASHES);
    }
}
