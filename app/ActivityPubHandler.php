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

        // Add Image Attachments for Pixelfed/Mastodon
        $attachments = [];
        $photos = $metadata['photo'] ?? [];
        if (!is_array($photos)) {
            $photos = [$photos];
        }
        foreach ($photos as $photo) {
            $photoUrl = filter_var($photo, FILTER_VALIDATE_URL) ? $photo : rtrim($fqdn, '/') . '/' . ltrim($photo, '/');
            $ext = strtolower(pathinfo(parse_url($photoUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
            $mediaType = 'image/jpeg';
            if ($ext === 'png') $mediaType = 'image/png';
            elseif ($ext === 'gif') $mediaType = 'image/gif';
            elseif ($ext === 'webp') $mediaType = 'image/webp';
            
            $attachments[] = [
                'type' => 'Document',
                'mediaType' => $mediaType,
                'url' => $photoUrl,
                'name' => 'Image'
            ];
        }
        if (!empty($attachments)) {
            $object['attachment'] = $attachments;
        }

        // Add Custom Emojis tags
        $tags = [];
        if (preg_match_all('/:([a-zA-Z0-9_]+):/', $content, $matches)) {
            $uniqueEmojis = array_unique($matches[1]);
            $config = \Indieinabox\Database::getAllSettings();
            $contentDirName = $config['contentdir'] ?? 'content';
            $emojiDir = dirname(__DIR__) . '/' . $contentDirName . '/media/emojis';
            
            if (is_dir($emojiDir)) {
                foreach ($uniqueEmojis as $shortcode) {
                    foreach (['png', 'gif', 'jpg', 'webp'] as $ext) {
                        if (file_exists($emojiDir . '/' . $shortcode . '.' . $ext)) {
                            $tags[] = [
                                'type' => 'Emoji',
                                'id' => rtrim($fqdn, '/') . '/media/emojis/' . $shortcode . '.' . $ext,
                                'name' => ':' . $shortcode . ':',
                                'icon' => [
                                    'type' => 'Image',
                                    'mediaType' => 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext),
                                    'url' => rtrim($fqdn, '/') . '/media/emojis/' . $shortcode . '.' . $ext
                                ]
                            ];
                            break;
                        }
                    }
                }
            }
        }
        if (!empty($tags)) {
            $object['tag'] = $tags;
        }

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

    /**
     * Handles /interact route.
     * Displays a form for the user to enter their instance domain or handle,
     * and redirects them to the authorize_interaction endpoint on their instance.
     *
     * @return void
     */
    public function handleInteract(): void
    {
        $uri = $_GET['uri'] ?? '';

        if (empty($uri)) {
            http_response_code(400);
            echo "Missing URI parameter.";
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $handleOrDomain = trim($_POST['instance'] ?? '');
            if (empty($handleOrDomain)) {
                echo "Please enter a valid domain or handle.";
                return;
            }

            // Extract domain
            $domain = $handleOrDomain;
            if (strpos($handleOrDomain, '@') !== false) {
                $parts = explode('@', ltrim($handleOrDomain, '@'));
                if (count($parts) >= 2) {
                    $domain = end($parts);
                } else {
                    $domain = $parts[0];
                }
            }
            
            // Remove protocol if present
            $domain = preg_replace('#^https?://#', '', $domain);
            $domain = rtrim($domain, '/');

            $redirectUrl = 'https://' . $domain . '/authorize_interaction?uri=' . urlencode($uri);
            header('Location: ' . $redirectUrl);
            exit;
        }

        // Output a simple HTML form
        header('Content-Type: text/html; charset=utf-8');
        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>' . Helper::translate('Interact via Fediverse') . '</title>';
        $html .= '<style>
            body { font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 2em auto; padding: 1em; background: #fdfdfd; color: #333; line-height: 1.5; }
            @media (prefers-color-scheme: dark) {
                body { background: #121212; color: #fdfdfd; }
                input[type="text"] { background: #222; color: #fff; border: 1px solid #444; }
            }
            h1 { font-size: 1.5em; margin-bottom: 0.5em; }
            form { margin-top: 1.5em; display: flex; flex-direction: column; gap: 1em; }
            input[type="text"] { padding: 0.75em; font-size: 1em; border: 1px solid #ccc; border-radius: 4px; }
            button { padding: 0.75em 1em; font-size: 1em; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
            button:hover { background: #0056b3; }
        </style>';
        $html .= '</head><body>';
        $html .= '<h1>' . Helper::translate('Interact via Fediverse') . '</h1>';
        $html .= '<p>' . Helper::translate('Enter your Mastodon or compatible instance domain (e.g. <code>mastodon.social</code>) or your full handle (e.g. <code>@user@mastodon.social</code>) to proceed.') . '</p>';
        $html .= '<form method="post">';
        $html .= '<label for="instance">' . Helper::translate('Instance domain or handle:') . '</label>';
        $html .= '<input type="text" id="instance" name="instance" placeholder="@user@instance.social" required autofocus>';
        $html .= '<button type="submit">' . Helper::translate('Continue') . '</button>';
        $html .= '</form>';
        $html .= '</body></html>';

        echo $html;
    }

    /**
     * Handles /authorize_interaction route.
     * This acts as the local Indieinabox client for remote interaction.
     * 
     * @return void
     */
    public function handleAuthorizeInteraction(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(43200);
            session_start();
        }

        $uri = $_GET['uri'] ?? '';

        if (empty($uri)) {
            http_response_code(400);
            echo "Missing URI parameter.";
            return;
        }

        if (empty($_SESSION['admin_authenticated'])) {
            $_SESSION['redirect_after_login'] = '/authorize_interaction?uri=' . urlencode($uri);
            header('Location: /admin/config');
            exit;
        }

        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        $actorId = $fqdn . '/actor';

        // Process POST (User confirms action)
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $action = $_POST['action'] ?? '';
            $createLocal = isset($_POST['create_local']) && $_POST['create_local'] === 'on';

            if (!in_array($action, ['Like', 'Announce', 'Create'])) {
                echo "Invalid action.";
                return;
            }

            // Fetch remote object to find inbox
            $ctx = stream_context_create(['http' => ['header' => "Accept: application/activity+json\r\nUser-Agent: Indieinabox\r\n"]]);
            $remoteObjJson = @file_get_contents($uri, false, $ctx);
            
            if (!$remoteObjJson) {
                echo "Failed to fetch remote object.";
                return;
            }
            $remoteObj = json_decode($remoteObjJson, true);
            if (!$remoteObj || empty($remoteObj['attributedTo'])) {
                echo "Invalid remote object (no attributedTo found).";
                return;
            }

            $remoteActorUrl = $remoteObj['attributedTo'];
            $remoteActorJson = @file_get_contents($remoteActorUrl, false, $ctx);
            $remoteActor = json_decode((string)$remoteActorJson, true);
            $inbox = $remoteActor['inbox'] ?? null;

            if (!$inbox) {
                echo "Remote actor has no inbox.";
                return;
            }

            // Generate Payload
            $payload = [];
            $activityId = $fqdn . '/activity/' . uniqid();

            if ($action === 'Like' || $action === 'Announce') {
                $payload = [
                    '@context' => 'https://www.w3.org/ns/activitystreams',
                    'id' => $activityId,
                    'type' => $action,
                    'actor' => $actorId,
                    'object' => $uri
                ];
                
                // Create local post if requested
                if ($createLocal) {
                    $folder = ($action === 'Like') ? 'likes' : 'reposts';
                    $prop = ($action === 'Like') ? 'like_of' : 'repost_of';
                    $dir = Database::$dataDir . '/content/' . $folder;
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                    $filename = date('YmdHis') . '.md';
                    $content = "---\ndate: " . date('Y-m-d H:i:s') . "\n{$prop}: \"{$uri}\"\n---\n\n";
                    file_put_contents($dir . '/' . $filename, $content);
                    
                    if (class_exists('\Indieinabox\BackgroundWorker')) {
                        $worker = new \Indieinabox\BackgroundWorker($this->site);
                        $worker->runBuild();
                    }
                }
            } elseif ($action === 'Create') {
                $replyContent = $_POST['reply_content'] ?? '';
                $objId = $fqdn . '/reply/' . uniqid();
                $payload = [
                    '@context' => 'https://www.w3.org/ns/activitystreams',
                    'id' => $activityId,
                    'type' => 'Create',
                    'actor' => $actorId,
                    'published' => gmdate('Y-m-d\TH:i:s\Z'),
                    'to' => ['https://www.w3.org/ns/activitystreams#Public'],
                    'cc' => [$remoteActorUrl],
                    'object' => [
                        'id' => $objId,
                        'type' => 'Note',
                        'published' => gmdate('Y-m-d\TH:i:s\Z'),
                        'attributedTo' => $actorId,
                        'inReplyTo' => $uri,
                        'content' => nl2br(htmlspecialchars($replyContent)),
                        'to' => ['https://www.w3.org/ns/activitystreams#Public'],
                        'cc' => [$remoteActorUrl]
                    ]
                ];
                
                if ($createLocal) {
                    $dir = Database::$dataDir . '/content/replies';
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                    $filename = date('YmdHis') . '.md';
                    $content = "---\ndate: " . date('Y-m-d H:i:s') . "\nin_reply_to: \"{$uri}\"\n---\n\n" . $replyContent;
                    file_put_contents($dir . '/' . $filename, $content);
                    
                    if (class_exists('\Indieinabox\BackgroundWorker')) {
                        $worker = new \Indieinabox\BackgroundWorker($this->site);
                        $worker->runBuild();
                    }
                }
            }

            // Enqueue activity to be sent
            $sql = "INSERT INTO activitypub_outbox (payload_json, target_inbox, status, created_at) VALUES (?, ?, 'pending', ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([json_encode($payload, JSON_UNESCAPED_SLASHES), $inbox, time()]);

            echo "<p>Interaction successfully sent to the remote instance!</p>";
            echo "<script>setTimeout(() => window.close(), 3000);</script>";
            return;
        }

        // Output Form
        header('Content-Type: text/html; charset=utf-8');
        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>' . Helper::translate('Authorize Interaction') . '</title>';
        $html .= '<style>
            body { font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 2em auto; padding: 1em; background: #fdfdfd; color: #333; line-height: 1.5; }
            @media (prefers-color-scheme: dark) { body { background: #121212; color: #fdfdfd; } textarea, select { background: #222; color: #fff; border: 1px solid #444; } }
            h1 { font-size: 1.5em; margin-bottom: 0.5em; }
            form { margin-top: 1.5em; display: flex; flex-direction: column; gap: 1em; }
            textarea { padding: 0.75em; font-size: 1em; border: 1px solid #ccc; border-radius: 4px; resize: vertical; min-height: 100px; }
            button { padding: 0.75em 1em; font-size: 1em; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
            button:hover { background: #0056b3; }
            .checkbox-group { display: flex; align-items: center; gap: 0.5em; font-size: 0.9em; }
        </style>';
        $html .= '<script>
            function toggleReply() {
                var action = document.getElementById("action").value;
                document.getElementById("reply-container").style.display = (action === "Create") ? "block" : "none";
            }
        </script>';
        $html .= '</head><body>';
        $html .= '<h1>' . Helper::translate('Interact with Remote Post') . '</h1>';
        $html .= '<p>' . Helper::translate('You are about to interact with:') . '<br><a href="' . htmlspecialchars($uri) . '" target="_blank">' . htmlspecialchars($uri) . '</a></p>';
        $html .= '<form method="post">';
        $html .= '<label for="action">' . Helper::translate('Action:') . '</label>';
        $html .= '<select id="action" name="action" onchange="toggleReply()" style="padding: 0.5em; font-size: 1em;">
            <option value="Like">' . Helper::translate('Like') . '</option>
            <option value="Announce">' . Helper::translate('Repost') . '</option>
            <option value="Create">' . Helper::translate('Reply') . '</option>
        </select>';
        $html .= '<div id="reply-container" style="display:none; flex-direction: column; gap: 0.5em;">';
        $html .= '<label for="reply_content">' . Helper::translate('Your Reply:') . '</label>';
        $html .= '<textarea id="reply_content" name="reply_content" placeholder="..."></textarea>';
        $html .= '</div>';
        $html .= '<div class="checkbox-group">';
        $html .= '<input type="checkbox" id="create_local" name="create_local" checked>';
        $html .= '<label for="create_local">' . Helper::translate('Save this interaction publicly on my site') . '</label>';
        $html .= '</div>';
        $html .= '<button type="submit">' . Helper::translate('Send Interaction') . '</button>';
        $html .= '</form>';
        $html .= '</body></html>';

        echo $html;
    }

}
