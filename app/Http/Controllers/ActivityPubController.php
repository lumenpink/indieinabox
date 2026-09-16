<?php

declare(strict_types=1);

namespace Indieinabox\Http\Controllers;

use Indieinabox\ActivityPub\InteractionHandler;
use Indieinabox\ActivityPub\KeyManager;
use Indieinabox\Core\Database;
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

    public function __construct(
        Site $site,
        ?PDO $db = null,
        ?KeyManager $keyManager = null,
        ?InteractionHandler $interactionHandler = null
    ) {
        parent::__construct($site);
        $this->db = $db ?? Database::getDb();
        $this->keyManager = $keyManager ?? new KeyManager($this->db);
        $this->interactionHandler = $interactionHandler ?? new InteractionHandler($this->site, $this->db);
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
     * Handles GET requests to the outbox (/outbox).
     */
    public function outbox(): void
    {
        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        header('Content-Type: application/activity+json; charset=utf-8');
        echo json_encode([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $fqdn . '/outbox',
            'type' => 'OrderedCollection',
            'totalItems' => 0,
            'orderedItems' => [],
        ]);
    }
}
