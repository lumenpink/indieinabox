<?php

declare(strict_types=1);

namespace Indieinabox\Services;

use Override;
use PDO;
use Exception;
use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use Indieinabox\Services\Contracts\WebPushServiceInterface;

/**
 * Service managing Web Push notifications, VAPID key lifecycle, and browser subscriptions.
 */
class WebPushService implements WebPushServiceInterface
{
    private PDO $db;
    private string $subject;
    /** @var (callable(string, array<string, string>, string): int)|null */
    private $customSender;

    private ?string $cachedPublicKey = null;
    private ?string $cachedPrivatePem = null;

    /**
     * @param ?PDO $db
     * @param ?string $subject Contact URI or mailto for VAPID sub claim.
     * @param (callable(string, array<string, string>, string): int)|null $customSender
     */
    public function __construct(
        ?PDO $db = null,
        ?string $subject = null,
        ?callable $customSender = null
    ) {
        $container = class_exists(Container::class) ? Container::getInstance() : null;
        $this->db = $db ?? ($container && $container->has(PDO::class) ? $container->get(PDO::class) : Database::getDb());
        $this->subject = $subject ?? 'mailto:admin@localhost';
        $this->customSender = $customSender;
    }

    private function ensureSubscriptionsTable(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS push_subscriptions (' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
            'endpoint TEXT NOT NULL UNIQUE, ' .
            'p256dh TEXT NOT NULL, ' .
            'auth TEXT NOT NULL, ' .
            'user_agent TEXT, ' .
            'created_at INTEGER NOT NULL)'
        );
    }

    #[Override]
    public function getVapidPublicKey(): string
    {
        $keys = $this->getVapidKeys();
        return $keys['publicKey'];
    }

    #[Override]
    public function getVapidKeys(): array
    {
        if ($this->cachedPublicKey !== null && $this->cachedPrivatePem !== null) {
            return [
                'publicKey' => $this->cachedPublicKey,
                'privateKey' => $this->cachedPrivatePem,
            ];
        }

        // Try reading from settings table
        $stmt = $this->db->prepare('SELECT key, value FROM settings WHERE key IN (\'vapid_public_key\', \'vapid_private_pem\')');
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        if (!empty($rows['vapid_public_key']) && !empty($rows['vapid_private_pem'])) {
            $this->cachedPublicKey = (string) $rows['vapid_public_key'];
            $this->cachedPrivatePem = (string) $rows['vapid_private_pem'];
            return [
                'publicKey' => $this->cachedPublicKey,
                'privateKey' => $this->cachedPrivatePem,
            ];
        }

        // Generate ECDSA P-256 (prime256v1) key pair
        $keyResource = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        if ($keyResource === false) {
            throw new Exception('Failed to generate VAPID EC key pair via OpenSSL');
        }

        openssl_pkey_export($keyResource, $privatePem);
        $details = openssl_pkey_get_details($keyResource);

        if ($details === false || empty($details['ec']['x']) || empty($details['ec']['y'])) {
            throw new Exception('Failed to extract EC coordinate details for VAPID key');
        }

        $x = str_pad((string) $details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
        $y = str_pad((string) $details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
        $uncompressedPublic = "\x04" . $x . $y;
        $publicKey = $this->base64UrlEncode($uncompressedPublic);

        // Store in settings table
        $stmtSave = $this->db->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (:key, :val)');
        $stmtSave->execute([':key' => 'vapid_public_key', ':val' => $publicKey]);
        $stmtSave->execute([':key' => 'vapid_private_pem', ':val' => $privatePem]);

        $this->cachedPublicKey = $publicKey;
        $this->cachedPrivatePem = $privatePem;

        return [
            'publicKey' => $publicKey,
            'privateKey' => $privatePem,
        ];
    }

    #[Override]
    public function createVapidJwt(string $audience, ?int $expiresIn = null): string
    {
        $keys = $this->getVapidKeys();

        $header = [
            'typ' => 'JWT',
            'alg' => 'ES256',
        ];

        $payload = [
            'aud' => $audience,
            'exp' => time() + ($expiresIn ?? 86400),
            'sub' => $this->subject,
        ];

        $headerEncoded = $this->base64UrlEncode((string) json_encode($header));
        $payloadEncoded = $this->base64UrlEncode((string) json_encode($payload));
        $dataToSign = $headerEncoded . '.' . $payloadEncoded;

        $derSignature = '';
        $success = openssl_sign($dataToSign, $derSignature, $keys['privateKey'], OPENSSL_ALGO_SHA256);

        if (!$success || $derSignature === '') {
            throw new Exception('Failed to sign VAPID JWT via OpenSSL ES256');
        }

        $rawSignature = $this->derToP1363($derSignature);
        $signatureEncoded = $this->base64UrlEncode($rawSignature);

        return $dataToSign . '.' . $signatureEncoded;
    }

    /**
     * Converts an ASN.1 DER ECDSA signature to IEEE P1363 (raw r || s, 64 bytes).
     */
    public function derToP1363(string $der): string
    {
        // DER sequence: 0x30 [len] 0x02 [rlen] [r] 0x02 [slen] [s]
        $offset = 2;
        if (ord($der[1]) > 0x80) {
            $offset += (ord($der[1]) & 0x7f);
        }

        // Tag for r
        $offset++; // skip 0x02
        $rLen = ord($der[$offset++]);
        $r = substr($der, $offset, $rLen);
        $offset += $rLen;

        // Tag for s
        $offset++; // skip 0x02
        $sLen = ord($der[$offset++]);
        $s = substr($der, $offset, $sLen);

        // Normalize r and s to exactly 32 bytes (strip leading null bytes if > 32, pad left with null bytes if < 32)
        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");

        $r32 = str_pad($r, 32, "\x00", STR_PAD_LEFT);
        $s32 = str_pad($s, 32, "\x00", STR_PAD_LEFT);

        return $r32 . $s32;
    }

    #[Override]
    public function subscribe(string $endpoint, string $p256dh, string $auth, ?string $userAgent = null): bool
    {
        $this->ensureSubscriptionsTable();

        $endpointTrimmed = trim($endpoint);
        if ($endpointTrimmed === '') {
            return false;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO push_subscriptions (endpoint, p256dh, auth, user_agent, created_at) ' .
            'VALUES (:endpoint, :p256dh, :auth, :ua, :created) ' .
            'ON CONFLICT(endpoint) DO UPDATE SET ' .
            'p256dh = :p256dh, auth = :auth, user_agent = :ua, created_at = :created'
        );

        $stmt->bindValue(':endpoint', $endpointTrimmed, PDO::PARAM_STR);
        $stmt->bindValue(':p256dh', trim($p256dh), PDO::PARAM_STR);
        $stmt->bindValue(':auth', trim($auth), PDO::PARAM_STR);
        $stmt->bindValue(':ua', $userAgent, $userAgent !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':created', time(), PDO::PARAM_INT);

        return $stmt->execute();
    }

    #[Override]
    public function unsubscribe(string $endpoint): bool
    {
        $this->ensureSubscriptionsTable();

        $stmt = $this->db->prepare('DELETE FROM push_subscriptions WHERE endpoint = :endpoint');
        $stmt->bindValue(':endpoint', trim($endpoint), PDO::PARAM_STR);
        return $stmt->execute();
    }

    #[Override]
    public function getSubscriptions(): array
    {
        $this->ensureSubscriptionsTable();

        $stmt = $this->db->query('SELECT * FROM push_subscriptions ORDER BY id DESC');
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    #[Override]
    public function sendNotification(array $subscription, array $payload): bool
    {
        $endpoint = (string) ($subscription['endpoint'] ?? '');
        if ($endpoint === '') {
            return false;
        }

        $parsedScheme = parse_url($endpoint, PHP_URL_SCHEME) ?? 'https';
        $parsedHost = parse_url($endpoint, PHP_URL_HOST) ?? '';
        $parsedPort = parse_url($endpoint, PHP_URL_PORT);
        $audience = $parsedScheme . '://' . $parsedHost . ($parsedPort ? ':' . $parsedPort : '');

        try {
            $jwt = $this->createVapidJwt($audience);
        } catch (Exception) {
            return false;
        }

        $publicKey = $this->getVapidPublicKey();
        $payloadJson = (string) json_encode($payload);

        $headers = [
            'TTL' => '86400',
            'Urgency' => 'normal',
            'Authorization' => 'vapid t=' . $jwt . ', k=' . $publicKey,
            'Content-Type' => 'application/json',
            'Content-Length' => (string) strlen($payloadJson),
        ];

        if ($this->customSender !== null) {
            $statusCode = ($this->customSender)($endpoint, $headers, $payloadJson);
        } else {
            $statusCode = $this->dispatchHttp($endpoint, $headers, $payloadJson);
        }

        // 200, 201, 202: Success
        if ($statusCode >= 200 && $statusCode < 300) {
            return true;
        }

        // 404 or 410: Subscription expired or revoked -> delete from database
        if ($statusCode === 404 || $statusCode === 410) {
            $this->unsubscribe($endpoint);
        }

        return false;
    }

    private function dispatchHttp(string $endpoint, array $headers, string $body): int
    {
        $formatted = [];
        foreach ($headers as $k => $v) {
            $formatted[] = $k . ': ' . $v;
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $formatted) . "\r\n",
                'content' => $body,
                'timeout' => 8,
                'ignore_errors' => true,
            ],
        ]);

        $fp = @fopen($endpoint, 'rb', false, $ctx);
        if (!$fp) {
            return 500;
        }

        $meta = stream_get_meta_data($fp);
        fclose($fp);

        $statusLine = $meta['wrapper_data'][0] ?? '';
        if (preg_match('#HTTP/\S+\s+(\d+)#', $statusLine, $m)) {
            return (int) $m[1];
        }

        return 200;
    }

    #[Override]
    public function broadcast(string $title, string $body, ?string $url = null, ?string $tag = null): int
    {
        $subscriptions = $this->getSubscriptions();
        if (empty($subscriptions)) {
            return 0;
        }

        $payload = [
            'title' => $title,
            'body' => $body,
            'url' => $url ?? '/microsub/reader?channel=notifications',
            'tag' => $tag ?? 'indieinabox-alert',
            'timestamp' => time(),
        ];

        $delivered = 0;
        foreach ($subscriptions as $sub) {
            if ($this->sendNotification($sub, $payload)) {
                $delivered++;
            }
        }

        return $delivered;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
