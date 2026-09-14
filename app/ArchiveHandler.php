<?php

declare(strict_types=1);

namespace Indieinabox;

use PDO;

/**
 * Class ArchiveHandler
 *
 * Handles viewing of local archived link snapshots (HTML/PDF/Wayback)
 * and queuing forced snapshot updates.
 */
class ArchiveHandler
{
    /**
     * @var Site
     */
    private Site $site;

    /**
     * @param Site $site
     */
    public function __construct(Site $site)
    {
        $this->site = $site;
    }

    /**
     * Handles /archive requests to display local link snapshots or external archive fallbacks.
     *
     * @return void
     */
    public function handle(): void
    {
        $url = $_GET['url'] ?? '';
        $ts = (int) ($_GET['ts'] ?? time());

        if (!$url) {
            header('HTTP/1.1 400 Bad Request');
            echo "URL is required";
            return;
        }

        $db = Database::getDb();

        // Follow alias if exists
        $stmt = $db->prepare("SELECT target_url FROM archive_aliases WHERE alias_url = ?");
        $stmt->execute([$url]);
        if ($row = $stmt->fetch()) {
            $url = $row['target_url'];
        }

        $normUrl = rtrim(strtolower($url), '/');

        // Find closest snapshot by timestamp difference
        $stmt = $db->prepare("SELECT * FROM archived_links WHERE url = ? ORDER BY ABS(timestamp - ?) ASC LIMIT 1");
        $stmt->execute([$normUrl, $ts]);
        $snapshot = $stmt->fetch(PDO::FETCH_ASSOC);

        echo $this->renderArchiveView($url, $snapshot);
    }

    /**
     * Handles /archive/force POST requests to queue a fresh snapshot.
     *
     * @return void
     */
    public function handleForce(): void
    {
        $url = $_POST['url'] ?? '';
        if (!$url) {
            header('HTTP/1.1 400 Bad Request');
            echo "URL is required";
            return;
        }

        $db = Database::getDb();

        // Follow alias if exists
        $stmt = $db->prepare("SELECT target_url FROM archive_aliases WHERE alias_url = ?");
        $stmt->execute([$url]);
        if ($row = $stmt->fetch()) {
            $url = $row['target_url'];
        }

        $normUrl = rtrim(strtolower($url), '/');

        // Insert directly into archive_queue
        $stmt = $db->prepare("INSERT INTO archive_queue (url, force_archive) VALUES (?, 1)");
        $stmt->execute([$normUrl]);

        // Redirect back to archive view
        $redirectUrl = '/archive?url=' . urlencode($url) . '&ts=' . time();
        header('Location: ' . $redirectUrl);
        exit;
    }

    /**
     * Renders the archive iframe toolbar and viewer HTML markup.
     *
     * @param string $url
     * @param array<string, mixed>|false $snapshot
     * @return string
     */
    public function renderArchiveView(string $url, $snapshot): string
    {
        $html = '<!DOCTYPE html><html><head><title>Archive View</title>';
        $html .= '<style>
            body, html { margin: 0; padding: 0; height: 100%; overflow: hidden; font-family: system-ui, sans-serif; }
            .archive-bar { background: #1a1a1a; color: #f0f0f0; padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #333; font-size: 14px; }
            .archive-bar .meta { display: flex; align-items: center; gap: 15px; }
            .archive-bar .actions { display: flex; align-items: center; gap: 15px; }
            .archive-bar a { color: #66b3ff; text-decoration: none; font-weight: 500; }
            .archive-bar a:hover { text-decoration: underline; color: #99ccff; }
            .archive-bar form { margin: 0; padding: 0; }
            .archive-bar button { background: #333; color: white; border: 1px solid #555; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 12px; }
            .archive-bar button:hover { background: #444; }
            .archive-frame { width: 100%; height: calc(100% - 46px); border: none; background: #fff; }
        </style>';
        $html .= '</head><body>';

        $html .= '<div class="archive-bar">';
        if ($snapshot) {
            $tsSnapshot = (int) $snapshot['timestamp'];
            $date = date('Y-m-d H:i', $tsSnapshot);
            $diffStr = Helper::timeAgo($tsSnapshot);

            $html .= "<div class=\"meta\">";
            $html .= "<strong>Local Snapshot</strong> <span>{$date} ({$diffStr})</span>";
            $html .= "</div>";

            $html .= '<div class="actions">';
            if (!empty($snapshot['local_pdf_path'])) {
                $html .= '<a href="' . htmlspecialchars((string) $snapshot['local_pdf_path']) . '" target="_blank">View PDF</a>';
            }
            if (!empty($snapshot['archive_org_url'])) {
                $html .= '<a href="' . htmlspecialchars((string) $snapshot['archive_org_url']) . '" target="_blank">Archive.org</a>';
            }
            $html .= '<a href="' . htmlspecialchars($url) . '" target="_blank" style="color: #ff9999;">Original Site</a>';
            $html .= '<form method="POST" action="/archive/force">';
            $html .= '<input type="hidden" name="url" value="' . htmlspecialchars($url) . '">';
            $html .= '<button type="submit" title="Request a fresh snapshot">Force Update</button>';
            $html .= '</form>';
            $html .= '</div>';
        } else {
            $html .= "<div class=\"meta\">Snapshot processing or not available locally.</div>";
            $html .= '<div class="actions">';
            $html .= '<a href="' . htmlspecialchars($url) . '" target="_blank" style="color: #ff9999;">Original Site</a>';
            $html .= '<form method="POST" action="/archive/force">';
            $html .= '<input type="hidden" name="url" value="' . htmlspecialchars($url) . '">';
            $html .= '<button type="submit" title="Request a fresh snapshot">Force Update</button>';
            $html .= '</form>';
            $html .= '</div>';
        }
        $html .= '</div>';

        if ($snapshot && !empty($snapshot['local_pdf_path'])) {
            $pdfUrl = htmlspecialchars((string) $snapshot['local_pdf_path']);
            $html .= "<iframe class=\"archive-frame\" src=\"{$pdfUrl}\"></iframe>";
        } elseif ($snapshot && !empty($snapshot['archive_org_url'])) {
            $archiveUrl = htmlspecialchars((string) $snapshot['archive_org_url']);
            $html .= "<iframe class=\"archive-frame\" src=\"{$archiveUrl}\"></iframe>";
        } else {
            $html .= "<div style='padding: 20px;'>No local snapshot available yet. The background worker may still be processing it.</div>";
        }

        $html .= '</body></html>';
        return $html;
    }
}
