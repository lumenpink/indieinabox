<?php

declare(strict_types=1);

namespace Indieinabox\BackgroundWorker;

use Indieinabox\Site;
use Indieinabox\Webmention\WebmentionSender;
use PDO;

/**
 * Class OutgoingWebmentionDispatcher
 *
 * Processes pending outgoing Webmentions queued in outgoing_webmentions table,
 * discovers target endpoints, and dispatches POST requests.
 */
class OutgoingWebmentionDispatcher
{
    private Site $site;
    private PDO $db;

    public function __construct(Site $site, PDO $db)
    {
        $this->site = $site;
        $this->db = $db;
    }

    /**
     * Processes pending outgoing webmentions.
     *
     * @return void
     */
    public function process(): void
    {
        echo "Running Outgoing Webmention processor...\n";

        // Clean up old ones (older than 7 days)
        $sevenDaysAgo = time() - (7 * 86400);
        $this->db->exec("DELETE FROM outgoing_webmentions WHERE status IN ('sent', 'failed') AND created_at < $sevenDaysAgo");

        $stmt = $this->db->query("SELECT id, source_url, target_url FROM outgoing_webmentions WHERE status = 'pending' LIMIT 20");
        $webmentions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($webmentions)) {
            echo "No outgoing webmentions pending.\n";
            return;
        }

        foreach ($webmentions as $wm) {
            $id = (int) $wm['id'];
            $source = (string) $wm['source_url'];
            $target = (string) $wm['target_url'];

            echo "Sending webmention from $source to $target...\n";

            $endpoint = WebmentionSender::discoverEndpoint($target);
            if (!$endpoint) {
                echo "No webmention endpoint found for $target\n";
                $this->db->prepare("UPDATE outgoing_webmentions SET status = 'failed' WHERE id = ?")->execute([$id]);
                continue;
            }

            echo "Found endpoint: $endpoint\n";

            $result = WebmentionSender::sendWebmention($endpoint, $source, $target);
            $code = $result['http_code'] ?? 0;

            if (!empty($result['success'])) {
                echo "Webmention sent successfully.\n";
                $this->db->prepare("UPDATE outgoing_webmentions SET status = 'sent' WHERE id = ?")->execute([$id]);
            } else {
                echo "Webmention failed with HTTP $code.\n";
                $this->db->prepare("UPDATE outgoing_webmentions SET status = 'failed' WHERE id = ?")->execute([$id]);
            }
        }

        echo "Outgoing Webmention processor done.\n";
    }
}
