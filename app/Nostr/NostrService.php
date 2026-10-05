<?php

declare(strict_types=1);

namespace Indieinabox\Nostr;

use Indieinabox\Core\Database;
use Indieinabox\Site\Site;

/**
 * Class NostrService
 *
 * Coordinates Nostr identity, NIP-05 DNS verification, and relay configurations.
 */
class NostrService
{
    /**
     * Builds compliant NIP-05 verification payload.
     *
     * @param string $pubKeyHex 64-character lowercase hex public key.
     * @param string $nip05Name Configured NIP-05 local part (e.g., "lumen").
     * @param array<int, string|array{url: string, active?: bool}> $relays Configured relays.
     * @param string|null $requestedName The 'name' query parameter requested by client.
     * @return array{names: array<string, string>, relays: array<string, array<int, string>>}
     */
    public static function buildNip05Payload(
        string $pubKeyHex,
        string $nip05Name,
        array $relays = [],
        ?string $requestedName = null
    ): array {
        $cleanPub = trim($pubKeyHex);
        if (str_starts_with($cleanPub, 'npub1')) {
            $cleanPub = NostrKey::npubToHex($cleanPub);
        } else {
            $cleanPub = strtolower($cleanPub);
        }

        $cleanRelays = [];
        foreach ($relays as $r) {
            if (is_array($r)) {
                if (!empty($r['active']) && !empty($r['url'])) {
                    $cleanRelays[] = (string) $r['url'];
                }
            } elseif (is_string($r) && !empty($r)) {
                $cleanRelays[] = $r;
            }
        }

        $names = [];
        $nip05Lower = strtolower(trim($nip05Name));

        if ($requestedName === null) {
            if ($nip05Lower !== '') {
                $names[$nip05Lower] = $cleanPub;
            }
            $names['_'] = $cleanPub;
        } else {
            $reqLower = strtolower(trim($requestedName));
            if ($reqLower === '_' || ($nip05Lower !== '' && $reqLower === $nip05Lower)) {
                $names[$requestedName] = $cleanPub;
            }
        }

        $relaysMap = [];
        if (!empty($names) && !empty($cleanRelays)) {
            $relaysMap[$cleanPub] = array_values($cleanRelays);
        }

        return [
            'names' => $names,
            'relays' => $relaysMap,
        ];
    }

    private static function getSetting(string $key, mixed $default = null): mixed
    {
        if (Database::isConnected()) {
            return Database::getSetting($key, $default);
        }
        return $default;
    }

    /**
     * Checks if Nostr federation is enabled.
     */
    public static function isEnabled(?Site $site = null): bool
    {
        if ($site !== null && isset($site->config['nostr_enabled'])) {
            return (bool) $site->config['nostr_enabled'];
        }

        return (bool) self::getSetting('nostr_enabled', false);
    }

    /**
     * Returns the configured Nostr hex public key.
     */
    public static function getPublicKey(?Site $site = null): ?string
    {
        $pub = null;
        if ($site !== null && isset($site->config['nostr_pubkey'])) {
            $pub = (string) $site->config['nostr_pubkey'];
        } else {
            $pub = self::getSetting('nostr_pubkey');
        }

        if (empty($pub)) {
            return null;
        }

        $pub = trim((string) $pub);
        if (str_starts_with($pub, 'npub1')) {
            return NostrKey::npubToHex($pub);
        }

        return strtolower($pub);
    }

    /**
     * Returns the configured Nostr hex private key.
     */
    public static function getPrivateKey(?Site $site = null): ?string
    {
        $priv = null;
        if ($site !== null && isset($site->config['nostr_privkey'])) {
            $priv = (string) $site->config['nostr_privkey'];
        } else {
            $priv = self::getSetting('nostr_privkey');
        }

        if (empty($priv)) {
            return null;
        }

        $priv = trim((string) $priv);
        if (str_starts_with($priv, 'nsec1')) {
            return NostrKey::nsecToHex($priv);
        }

        return strtolower($priv);
    }

    /**
     * Returns configured relays or pre-configured defaults.
     *
     * @return array<int, array{url: string, active: bool}>
     */
    public static function getRelays(?Site $site = null): array
    {
        $relays = null;
        if ($site !== null && isset($site->config['nostr_relays'])) {
            $relays = $site->config['nostr_relays'];
        } else {
            $relays = self::getSetting('nostr_relays');
        }

        if (is_string($relays)) {
            $decoded = json_decode($relays, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $relays = $decoded;
            }
        }

        if (is_array($relays) && !empty($relays)) {
            $normalized = [];
            foreach ($relays as $item) {
                if (is_array($item) && !empty($item['url'])) {
                    $normalized[] = [
                        'url' => (string) $item['url'],
                        'active' => !empty($item['active']),
                    ];
                } elseif (is_string($item) && !empty($item)) {
                    $normalized[] = [
                        'url' => $item,
                        'active' => true,
                    ];
                }
            }
            if (!empty($normalized)) {
                return $normalized;
            }
        }

        return NostrRelayClient::getDefaultRelays();
    }
}
