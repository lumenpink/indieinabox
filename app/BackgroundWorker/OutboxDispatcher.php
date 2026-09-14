<?php

declare(strict_types=1);

namespace Indieinabox\BackgroundWorker;

use Indieinabox\Site;
use Indieinabox\Database;
use Indieinabox\HttpSignature;
use PDO;

/**
 * Class OutboxDispatcher
 *
 * Processes pending ActivityPub messages in activitypub_outbox,
 * cryptographically signs HTTP POST requests using the instance RSA key,
 * and delivers them to remote Fediverse inboxes.
 */
class OutboxDispatcher
{
    private Site $site;
    private PDO $db;

    public function __construct(Site $site, PDO $db)
    {
        $this->site = $site;
        $this->db = $db;
    }

    /**
     * Processes the outbox queue.
     *
     * @return void
     */
    public function process(): void
    {
        echo "Running ActivityPub outbox processor...\n";

        // Prune old outbox entries (7 days) and actors (30 days)
        $sevenDaysAgo = time() - (7 * 86400);
        $thirtyDaysAgo = time() - (30 * 86400);

        $this->db->exec("DELETE FROM activitypub_outbox WHERE status IN ('sent', 'failed') AND created_at < $sevenDaysAgo");
        $this->db->exec("DELETE FROM activitypub_actors WHERE updated_at < $thirtyDaysAgo");

        $stmt = $this->db->query("SELECT private_key FROM activitypub_keys WHERE key_id = 'main-key'");
        $keyRow = $stmt->fetch();
        if (!$keyRow) {
            echo "No RSA key found. Exiting.\n";
            return;
        }
        $privateKey = (string) $keyRow['private_key'];

        $sql = "SELECT id, payload_json, target_inbox FROM activitypub_outbox WHERE status = 'pending' LIMIT 50";
        $stmt = $this->db->query($sql);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($messages)) {
            echo "No pending messages.\n";
            return;
        }

        $fqdn = Database::getSetting('fqdn');
        if (!$fqdn) {
            echo "FQDN not configured in database settings.\n";
            return;
        }
        $fqdn = rtrim((string) $fqdn, '/');

        $keyId = $fqdn . '/actor#main-key';

        foreach ($messages as $msg) {
            $id = $msg['id'];
            $payload = (string) $msg['payload_json'];
            $targetUrl = (string) $msg['target_inbox'];

            echo "Processing message $id for $targetUrl...\n";

            $headers = HttpSignature::sign(
                $keyId,
                $privateKey,
                'POST',
                $targetUrl,
                $payload,
                ['Content-Type' => 'application/activity+json']
            );

            $ch = curl_init($targetUrl);
            $curlHeaders = [];
            foreach ($headers as $k => $v) {
                $curlHeaders[] = "$k: $v";
            }

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                echo "Success ($httpCode).\n";
                $update = $this->db->prepare("UPDATE activitypub_outbox SET status = 'sent' WHERE id = ?");
                $update->execute([$id]);
            } else {
                echo "Failed ($httpCode): $error\n";
                $update = $this->db->prepare("UPDATE activitypub_outbox SET status = 'failed' WHERE id = ?");
                $update->execute([$id]);
            }
        }

        echo "Done.\n";
    }
}
