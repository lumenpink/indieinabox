<?php

declare(strict_types=1);

namespace Indieinabox\ActivityPub;

use PDO;
use Indieinabox\Core\Database;

/**
 * Class KeyManager
 *
 * Handles generation, storage, and retrieval of RSA key pairs for ActivityPub signing.
 */
class KeyManager
{
    /**
     * @var PDO Database connection.
     */
    private PDO $db;

    /**
     * KeyManager constructor.
     *
     * @param ?PDO $db Optional PDO connection. If null, Database::getDb() is used.
     */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getDb();
    }

    /**
     * Ensures an RSA key pair exists for signing ActivityPub payloads.
     * If keys are missing, generates a new 2048-bit RSA pair and saves them in the database.
     *
     * @param string $keyId The key identifier (defaults to 'main-key').
     * @return void
     */
    public function ensureKeys(string $keyId = 'main-key'): void
    {
        $stmt = $this->db->prepare("SELECT public_key FROM activitypub_keys WHERE key_id = ?");
        $stmt->execute([$keyId]);
        if (!$stmt->fetch()) {
            $config = [
                "digest_alg" => "sha256",
                "private_key_bits" => 2048,
                "private_key_type" => OPENSSL_KEYTYPE_RSA,
            ];
            $res = openssl_pkey_new($config);
            if ($res === false) {
                return;
            }
            $privKey = '';
            openssl_pkey_export($res, $privKey);
            $details = openssl_pkey_get_details($res);
            $pubKey = $details["key"] ?? '';

            $sql = "INSERT INTO activitypub_keys (key_id, private_key, public_key, created_at) " .
                   "VALUES (?, ?, ?, ?)";
            $insert = $this->db->prepare($sql);
            $insert->execute([$keyId, $privKey, $pubKey, time()]);
        }
    }

    /**
     * Retrieves the public key PEM string for a given key ID.
     *
     * @param string $keyId The key identifier (defaults to 'main-key').
     * @return ?string
     */
    public function getPublicKey(string $keyId = 'main-key'): ?string
    {
        $stmt = $this->db->prepare("SELECT public_key FROM activitypub_keys WHERE key_id = ?");
        $stmt->execute([$keyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (string)$row['public_key'] : null;
    }

    /**
     * Retrieves the private key PEM string for a given key ID.
     *
     * @param string $keyId The key identifier (defaults to 'main-key').
     * @return ?string
     */
    public function getPrivateKey(string $keyId = 'main-key'): ?string
    {
        $stmt = $this->db->prepare("SELECT private_key FROM activitypub_keys WHERE key_id = ?");
        $stmt->execute([$keyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (string)$row['private_key'] : null;
    }
}
