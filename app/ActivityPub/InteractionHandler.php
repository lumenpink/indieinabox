<?php

declare(strict_types=1);

namespace Indieinabox\ActivityPub;

use PDO;
use Indieinabox\Site;
use Indieinabox\Database;
use Indieinabox\Helper;

/**
 * Class InteractionHandler
 *
 * Handles client-side Fediverse interactions (/interact and /authorize_interaction).
 */
class InteractionHandler
{
    /**
     * @var Site Global site instance.
     */
    private Site $site;

    /**
     * @var PDO Database connection.
     */
    private PDO $db;

    /**
     * InteractionHandler constructor.
     *
     * @param Site $site Site instance.
     * @param ?PDO $db Optional PDO connection.
     */
    public function __construct(Site $site, ?PDO $db = null)
    {
        $this->site = $site;
        $this->db = $db ?? Database::getDb();
    }

    /**
     * Handles /interact route.
     * Displays a form for entering remote instance/handle and redirects to authorize_interaction.
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

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $handleOrDomain = trim($_POST['instance'] ?? '');
            if (empty($handleOrDomain)) {
                echo "Please enter a valid domain or handle.";
                return;
            }

            $domain = $this->extractDomain($handleOrDomain);
            $redirectUrl = 'https://' . $domain . '/authorize_interaction?uri=' . urlencode($uri);
            header('Location: ' . $redirectUrl);
            exit;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderInteractHtml($uri);
    }

    /**
     * Extracts domain name from a handle (@user@domain) or host string.
     *
     * @param string $handleOrDomain
     * @return string
     */
    public function extractDomain(string $handleOrDomain): string
    {
        $domain = $handleOrDomain;
        if (str_contains($handleOrDomain, '@')) {
            $parts = explode('@', ltrim($handleOrDomain, '@'));
            if (count($parts) >= 2) {
                $domain = end($parts);
            } else {
                $domain = $parts[0];
            }
        }

        $domain = (string)preg_replace('#^https?://#', '', $domain);
        return rtrim($domain, '/');
    }

    /**
     * Renders the HTML form for /interact.
     *
     * @param string $uri
     * @return string
     */
    public function renderInteractHtml(string $uri): string
    {
        $title = Helper::translate('Interact via Fediverse');
        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>' . htmlspecialchars($title) . '</title>';
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
        $html .= '<h1>' . htmlspecialchars($title) . '</h1>';
        $html .= '<p>' . Helper::translate('Enter your Mastodon or compatible instance domain (e.g. <code>mastodon.social</code>) or your full handle (e.g. <code>@user@mastodon.social</code>) to proceed.') . '</p>';
        $html .= '<form method="post">';
        $html .= '<label for="instance">' . Helper::translate('Instance domain or handle:') . '</label>';
        $html .= '<input type="text" id="instance" name="instance" placeholder="@user@instance.social" required autofocus>';
        $html .= '<button type="submit">' . Helper::translate('Continue') . '</button>';
        $html .= '</form>';
        $html .= '</body></html>';

        return $html;
    }

    /**
     * Handles /authorize_interaction route.
     * Acts as the local Indieinabox client for remote interaction.
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

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $action = $_POST['action'] ?? '';
            $createLocal = isset($_POST['create_local']) && $_POST['create_local'] === 'on';

            if (!in_array($action, ['Like', 'Announce', 'Create'], true)) {
                echo "Invalid action.";
                return;
            }

            $remoteObj = $this->fetchRemoteJson($uri);
            if (!$remoteObj || empty($remoteObj['attributedTo'])) {
                echo "Invalid remote object (no attributedTo found).";
                return;
            }

            $remoteActorUrl = (string)$remoteObj['attributedTo'];
            $remoteActor = $this->fetchRemoteJson($remoteActorUrl);
            $inbox = $remoteActor['inbox'] ?? null;

            if (!$inbox) {
                echo "Remote actor has no inbox.";
                return;
            }

            $this->processInteraction($action, $uri, $actorId, $remoteActorUrl, $inbox, $createLocal);

            echo "<p>Interaction successfully sent to the remote instance!</p>";
            echo "<script>setTimeout(() => window.close(), 3000);</script>";
            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderAuthorizeHtml($uri);
    }

    /**
     * Processes confirmed interaction by building payload, saving local post if desired, and queueing outbox.
     *
     * @param string $action 'Like', 'Announce', or 'Create'
     * @param string $uri Remote object URI
     * @param string $actorId Local actor URI
     * @param string $remoteActorUrl Remote actor URI
     * @param string $inbox Remote actor inbox
     * @param bool $createLocal Whether to save a local markdown file
     * @return void
     */
    public function processInteraction(
        string $action,
        string $uri,
        string $actorId,
        string $remoteActorUrl,
        string $inbox,
        bool $createLocal
    ): void {
        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        $activityId = $fqdn . '/activity/' . uniqid();

        if ($action === 'Like' || $action === 'Announce') {
            $payload = ActivityBuilder::buildInteractionActivity($activityId, $action, $actorId, $uri);

            if ($createLocal) {
                $folder = ($action === 'Like') ? 'likes' : 'reposts';
                $prop = ($action === 'Like') ? 'like_of' : 'repost_of';
                $baseDir = Database::$dataDir !== '' ? Database::$dataDir : dirname(__DIR__, 2);
                $dir = $baseDir . '/content/' . $folder;
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                $filename = date('YmdHis') . '.md';
                $content = "---\ndate: " . date('Y-m-d H:i:s') . "\n{$prop}: \"{$uri}\"\n---\n\n";
                file_put_contents($dir . '/' . $filename, $content);

                $this->triggerSiteBuild();
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
                $baseDir = Database::$dataDir !== '' ? Database::$dataDir : dirname(__DIR__, 2);
                $dir = $baseDir . '/content/replies';
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                $filename = date('YmdHis') . '.md';
                $content = "---\ndate: " . date('Y-m-d H:i:s') . "\nin_reply_to: \"{$uri}\"\n---\n\n" . $replyContent;
                file_put_contents($dir . '/' . $filename, $content);

                $this->triggerSiteBuild();
            }
        } else {
            return;
        }

        $sql = "INSERT INTO activitypub_outbox (payload_json, target_inbox, status, created_at) VALUES (?, ?, 'pending', ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([json_encode($payload, JSON_UNESCAPED_SLASHES), $inbox, time()]);
    }

    /**
     * Triggers static site rebuild if SiteBuilder exists.
     *
     * @return void
     */
    protected function triggerSiteBuild(): void
    {
        if (class_exists('\Indieinabox\SiteBuilder')) {
            $builder = new \Indieinabox\SiteBuilder($this->site);
            $builder->build();
        }
    }

    /**
     * Fetches remote JSON-LD/ActivityStreams object or actor via HTTP.
     *
     * @param string $url
     * @return ?array<string, mixed>
     */
    public function fetchRemoteJson(string $url): ?array
    {
        $ctx = stream_context_create([
            'http' => [
                'header' => "Accept: application/activity+json, application/ld+json\r\nUser-Agent: Indieinabox\r\n",
                'timeout' => 5
            ]
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if (!$raw) {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Renders HTML form for /authorize_interaction.
     *
     * @param string $uri
     * @return string
     */
    public function renderAuthorizeHtml(string $uri): string
    {
        $title = Helper::translate('Authorize Interaction');
        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>' . htmlspecialchars($title) . '</title>';
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
        $html .= '<h1>' . htmlspecialchars($title) . '</h1>';
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

        return $html;
    }
}
