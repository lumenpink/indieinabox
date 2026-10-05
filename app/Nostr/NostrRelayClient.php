<?php

declare(strict_types=1);

namespace Indieinabox\Nostr;

use Indieinabox\Nostr\Contracts\NostrRelayClientInterface;
use Throwable;

/**
 * Class NostrRelayClient
 *
 * Lightweight WebSocket client for publishing signed Nostr events to multiple relays
 * using standard PHP stream sockets over TLS without heavy external libraries.
 */
class NostrRelayClient implements NostrRelayClientInterface
{
    /** @var array<int, array{url: string, active: bool}> */
    private array $defaultRelays;
    private float $timeout;

    /**
     * @param array<int, array{url: string, active: bool}>|null $defaultRelays
     * @param float $timeout Timeout per relay in seconds (default 2.5s).
     */
    public function __construct(?array $defaultRelays = null, float $timeout = 2.5)
    {
        $this->defaultRelays = $defaultRelays ?? self::getDefaultRelays();
        $this->timeout = $timeout;
    }

    /**
     * @return array<int, array{url: string, active: bool}>
     */
    #[\Override]
    public static function getDefaultRelays(): array
    {
        return [
            ['url' => 'wss://relay.damus.io', 'active' => true],
            ['url' => 'wss://nos.lol', 'active' => true],
            ['url' => 'wss://relay.nostr.band', 'active' => true],
            ['url' => 'wss://nostr.mom', 'active' => true],
        ];
    }

    /**
     * Formats NIP-01 WebSocket envelope: ["EVENT", <eventData>]
     *
     * @param array<string, mixed> $eventData
     */
    #[\Override]
    public function buildEventEnvelope(array $eventData): string
    {
        return (string) json_encode(['EVENT', $eventData], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Parses raw JSON relay message.
     *
     * @return array{type: string, eventId?: string, success?: bool, message: string}|null
     */
    #[\Override]
    public function parseRelayMessage(string $rawMessage): ?array
    {
        $decoded = json_decode(trim($rawMessage), true);
        if (!is_array($decoded) || empty($decoded)) {
            return null;
        }

        $type = (string) ($decoded[0] ?? '');
        if ($type === 'OK') {
            return [
                'type' => 'OK',
                'eventId' => (string) ($decoded[1] ?? ''),
                'success' => (bool) ($decoded[2] ?? false),
                'message' => (string) ($decoded[3] ?? ''),
            ];
        }

        if ($type === 'NOTICE') {
            return [
                'type' => 'NOTICE',
                'message' => (string) ($decoded[1] ?? ''),
            ];
        }

        return [
            'type' => $type,
            'message' => (string) ($decoded[1] ?? ''),
        ];
    }

    /**
     * Publishes signed event data to all active relays.
     *
     * @param array<string, mixed> $eventData
     * @param array<int, string|array{url: string, active?: bool}>|null $relays
     * @return array<string, array{success: bool, message: string}> Map of relayUrl => result
     */
    #[\Override]
    public function publish(array $eventData, ?array $relays = null): array
    {
        $targetRelays = $relays ?? $this->defaultRelays;
        $results = [];
        $envelope = $this->buildEventEnvelope($eventData);

        foreach ($targetRelays as $r) {
            $url = is_array($r) ? ($r['url'] ?? '') : (string) $r;
            $active = is_array($r) ? ($r['active'] ?? true) : true;

            $url = trim($url);
            if ($url === '' || !$active) {
                continue;
            }

            $results[$url] = $this->publishToSingleRelay($url, $envelope, (string) ($eventData['id'] ?? ''));
        }

        return $results;
    }

    /**
     * Publishes to a single relay over WebSocket with TLS.
     *
     * @return array{success: bool, message: string}
     */
    private function publishToSingleRelay(string $url, string $envelope, string $expectedEventId): array
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'])) {
            return ['success' => false, 'message' => 'Malformed relay URL'];
        }

        $scheme = $parts['scheme'] ?? 'wss';
        $host = $parts['host'];
        $port = $parts['port'] ?? ($scheme === 'ws' ? 80 : 443);
        $path = $parts['path'] ?? '/';
        if (isset($parts['query'])) {
            $path .= '?' . $parts['query'];
        }

        $transport = ($scheme === 'wss') ? 'ssl://' : 'tcp://';
        $socketAddress = $transport . $host . ':' . $port;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $host,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            $socketAddress,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            return ['success' => false, 'message' => "Connection failed: {$errstr} ({$errno})"];
        }

        stream_set_timeout($socket, (int) ceil($this->timeout));

        try {
            // 1. Perform WebSocket Handshake
            $key = base64_encode(random_bytes(16));
            $handshake = "GET {$path} HTTP/1.1\r\n"
                . "Host: {$host}\r\n"
                . "Upgrade: websocket\r\n"
                . "Connection: Upgrade\r\n"
                . "Sec-WebSocket-Key: {$key}\r\n"
                . "Sec-WebSocket-Version: 13\r\n\r\n";

            fwrite($socket, $handshake);

            // Read handshake response
            $response = '';
            while (($line = fgets($socket)) !== false) {
                $response .= $line;
                if (trim($line) === '') {
                    break;
                }
            }

            if (!str_contains($response, '101 Switching Protocols')) {
                fclose($socket);
                return ['success' => false, 'message' => 'WebSocket handshake rejected by relay'];
            }

            // 2. Send Masked WebSocket Text Frame
            $frame = $this->encodeClientFrame($envelope);
            fwrite($socket, $frame);

            // 3. Read Relay Response Frame
            $rawResponse = $this->readServerFrame($socket);
            fclose($socket);

            if ($rawResponse === null || $rawResponse === '') {
                // Many relays accept without immediate response or response is delayed
                return ['success' => true, 'message' => 'Published (no response confirmation)'];
            }

            $parsed = $this->parseRelayMessage($rawResponse);
            if ($parsed !== null) {
                if ($parsed['type'] === 'OK') {
                    return [
                        'success' => $parsed['success'] ?? true,
                        'message' => $parsed['message'] !== '' ? $parsed['message'] : 'Accepted',
                    ];
                }
                return [
                    'success' => true,
                    'message' => $parsed['message'],
                ];
            }

            return ['success' => true, 'message' => 'Published'];
        } catch (Throwable $e) {
            if (is_resource($socket)) {
                fclose($socket);
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Encodes a client-to-server WebSocket frame (RFC 6455 masked text frame).
     */
    private function encodeClientFrame(string $payload): string
    {
        $len = strlen($payload);
        $header = chr(0x81); // FIN + Text opcode

        if ($len <= 125) {
            $header .= chr(0x80 | $len);
        } elseif ($len <= 65535) {
            $header .= chr(0x80 | 126) . pack('n', $len);
        } else {
            $header .= chr(0x80 | 127) . pack('J', $len);
        }

        $maskKey = random_bytes(4);
        $maskedPayload = '';
        for ($i = 0; $i < $len; $i++) {
            $maskedPayload .= chr(ord($payload[$i]) ^ ord($maskKey[$i % 4]));
        }

        return $header . $maskKey . $maskedPayload;
    }

    /**
     * Reads an unmasked or masked server response WebSocket frame.
     */
    private function readServerFrame($socket): ?string
    {
        $header = fread($socket, 2);
        if ($header === false || strlen($header) < 2) {
            return null;
        }

        $len = ord($header[1]) & 0x7F;
        if ($len === 126) {
            $ext = fread($socket, 2);
            if ($ext === false || strlen($ext) < 2) {
                return null;
            }
            $len = (int) unpack('n', $ext)[1];
        } elseif ($len === 127) {
            $ext = fread($socket, 8);
            if ($ext === false || strlen($ext) < 8) {
                return null;
            }
            $len = (int) unpack('J', $ext)[1];
        }

        if ($len <= 0) {
            return '';
        }

        $payload = '';
        while (strlen($payload) < $len) {
            $chunk = fread($socket, min(4096, $len - strlen($payload)));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $payload .= $chunk;
        }

        return $payload;
    }
}
