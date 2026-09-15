<?php

declare(strict_types=1);

namespace Indieinabox\Views;

use Indieinabox\Support\DateFormatter;

/**
 * Class ArchiveView
 *
 * Renders the HTML toolbar and viewer markup for archived snapshots.
 */
class ArchiveView
{
    /**
     * Renders the archive iframe toolbar and viewer HTML markup.
     *
     * @param string $url Target URL.
     * @param array<string, mixed>|null $snapshot Snapshot row if found.
     * @return string
     */
    public static function render(string $url, ?array $snapshot = null): string
    {
        $escapedUrl = htmlspecialchars($url);

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
        if ($snapshot !== null) {
            $tsSnapshot = (int) $snapshot['timestamp'];
            $date = date('Y-m-d H:i', $tsSnapshot);
            $diffStr = DateFormatter::timeAgo($tsSnapshot);

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
            $html .= '<a href="' . $escapedUrl . '" target="_blank" style="color: #ff9999;">Original Site</a>';
            $html .= '<form method="POST" action="/archive/force">';
            $html .= '<input type="hidden" name="url" value="' . $escapedUrl . '">';
            $html .= '<button type="submit" title="Request a fresh snapshot">Force Update</button>';
            $html .= '</form>';
            $html .= '</div>';
        } else {
            $html .= "<div class=\"meta\">Snapshot processing or not available locally.</div>";
            $html .= '<div class="actions">';
            $html .= '<a href="' . $escapedUrl . '" target="_blank" style="color: #ff9999;">Original Site</a>';
            $html .= '<form method="POST" action="/archive/force">';
            $html .= '<input type="hidden" name="url" value="' . $escapedUrl . '">';
            $html .= '<button type="submit" title="Request a fresh snapshot">Force Update</button>';
            $html .= '</form>';
            $html .= '</div>';
        }
        $html .= '</div>';

        if ($snapshot !== null && !empty($snapshot['local_pdf_path'])) {
            $pdfUrl = htmlspecialchars((string) $snapshot['local_pdf_path']);
            $html .= "<iframe class=\"archive-frame\" src=\"{$pdfUrl}\"></iframe>";
        } elseif ($snapshot !== null && !empty($snapshot['archive_org_url'])) {
            $archiveUrl = htmlspecialchars((string) $snapshot['archive_org_url']);
            $html .= "<iframe class=\"archive-frame\" src=\"{$archiveUrl}\"></iframe>";
        } else {
            $html .= "<div style='padding: 20px;'>No local snapshot available yet. The background worker may still be processing it.</div>";
        }

        $html .= '</body></html>';
        return $html;
    }
}
