<?php

declare(strict_types=1);

namespace Indieinabox\AtProto;

use Indieinabox\AtProto\Contracts\AtProtoClientInterface;
use RuntimeException;

/**
 * Class AtProtoClient
 *
 * Handles HTTP XRPC communication with Bluesky/ATProto Personal Data Servers (PDS),
 * including session creation, record publishing, and blob uploads.
 */
class AtProtoClient implements AtProtoClientInterface
{
    public const DEFAULT_PDS_URL = 'https://bsky.social';

    /**
     * @var callable|null Custom transport for testing: fn(string $url, string $method, array $headers, ?string $body): array{code: int, body: string, error: string}
     */
    private $transport;

    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport;
    }

    /**
     * Authenticates with the PDS using an identifier (handle or DID) and password/app-password.
     *
     * @return array{accessJwt: string, refreshJwt: string, did: string, handle: string, pdsUrl: string}
     */
    public function createSession(string $identifier, string $password, ?string $pdsUrl = null): array
    {
        $pds = rtrim($pdsUrl ?: self::DEFAULT_PDS_URL, '/');
        $endpoint = "{$pds}/xrpc/com.atproto.server.createSession";

        $payload = json_encode([
            'identifier' => trim($identifier),
            'password' => trim($password),
        ], JSON_UNESCAPED_SLASHES);

        $res = $this->request($endpoint, 'POST', [
            'Content-Type: application/json',
            'Accept: application/json',
        ], (string) $payload);

        if ($res['code'] !== 200) {
            $err = json_decode($res['body'], true);
            $msg = $err['message'] ?? $res['error'] ?? "HTTP {$res['code']}";
            throw new RuntimeException("ATProto session creation failed: {$msg}");
        }

        $data = json_decode($res['body'], true) ?: [];

        return [
            'accessJwt' => (string) ($data['accessJwt'] ?? ''),
            'refreshJwt' => (string) ($data['refreshJwt'] ?? ''),
            'did' => (string) ($data['did'] ?? ''),
            'handle' => (string) ($data['handle'] ?? $identifier),
            'pdsUrl' => $pds,
        ];
    }

    /**
     * Refreshes an expired access JWT using the refresh JWT.
     *
     * @return array{accessJwt: string, refreshJwt: string, did: string, handle: string, pdsUrl: string}|null
     */
    public function refreshSession(string $refreshJwt, string $pdsUrl): ?array
    {
        $pds = rtrim($pdsUrl ?: self::DEFAULT_PDS_URL, '/');
        $endpoint = "{$pds}/xrpc/com.atproto.server.refreshSession";

        $res = $this->request($endpoint, 'POST', [
            "Authorization: Bearer {$refreshJwt}",
            'Accept: application/json',
        ], null);

        if ($res['code'] !== 200) {
            return null;
        }

        $data = json_decode($res['body'], true) ?: [];

        return [
            'accessJwt' => (string) ($data['accessJwt'] ?? ''),
            'refreshJwt' => (string) ($data['refreshJwt'] ?? $refreshJwt),
            'did' => (string) ($data['did'] ?? ''),
            'handle' => (string) ($data['handle'] ?? ''),
            'pdsUrl' => $pds,
        ];
    }

    /**
     * Uploads an image or media blob to the PDS for use in post embeds.
     *
     * @return array{blob: array<string, mixed>}|null
     */
    public function uploadBlob(string $binaryData, string $mimeType, string $accessJwt, string $pdsUrl): ?array
    {
        $pds = rtrim($pdsUrl ?: self::DEFAULT_PDS_URL, '/');
        $endpoint = "{$pds}/xrpc/com.atproto.repo.uploadBlob";

        $res = $this->request($endpoint, 'POST', [
            "Authorization: Bearer {$accessJwt}",
            "Content-Type: {$mimeType}",
            'Accept: application/json',
        ], $binaryData);

        if ($res['code'] !== 200) {
            return null;
        }

        $data = json_decode($res['body'], true);
        return is_array($data) ? $data : null;
    }

    /**
     * Creates a record in a repository (e.g. app.bsky.feed.post).
     *
     * @param string $repo DID of the repository.
     * @param string $collection Collection name (e.g. 'app.bsky.feed.post').
     * @param array<string, mixed> $record The lexicon record payload.
     * @return array{uri: string, cid: string, commit: array<string, mixed>}
     */
    public function createRecord(
        string $repo,
        string $collection,
        array $record,
        string $accessJwt,
        string $pdsUrl
    ): array {
        $pds = rtrim($pdsUrl ?: self::DEFAULT_PDS_URL, '/');
        $endpoint = "{$pds}/xrpc/com.atproto.repo.createRecord";

        $payload = json_encode([
            'repo' => $repo,
            'collection' => $collection,
            'record' => $record,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $res = $this->request($endpoint, 'POST', [
            "Authorization: Bearer {$accessJwt}",
            'Content-Type: application/json',
            'Accept: application/json',
        ], (string) $payload);

        if ($res['code'] !== 200) {
            $err = json_decode($res['body'], true);
            $msg = $err['message'] ?? $res['error'] ?? "HTTP {$res['code']}";
            throw new RuntimeException("ATProto createRecord failed: {$msg}");
        }

        $data = json_decode($res['body'], true) ?: [];

        return [
            'uri' => (string) ($data['uri'] ?? ''),
            'cid' => (string) ($data['cid'] ?? ''),
            'commit' => (array) ($data['commit'] ?? []),
        ];
    }

    /**
     * Resolves a handle into its DID via com.atproto.identity.resolveHandle.
     */
    public function resolveHandle(string $handle, ?string $pdsUrl = null): ?string
    {
        $pds = rtrim($pdsUrl ?: self::DEFAULT_PDS_URL, '/');
        $endpoint = "{$pds}/xrpc/com.atproto.identity.resolveHandle?handle=" . urlencode($handle);

        $res = $this->request($endpoint, 'GET', [
            'Accept: application/json',
        ], null);

        if ($res['code'] !== 200) {
            return null;
        }

        $data = json_decode($res['body'], true) ?: [];
        return !empty($data['did']) ? (string) $data['did'] : null;
    }

    /**
     * Resolves the canonical public web URL on bsky.app for an at:// post URI.
     */
    public static function canonicalPostUrl(string $atUri, string $handle): string
    {
        $rkey = basename($atUri);
        $cleanHandle = ltrim($handle, '@');
        return "https://bsky.app/profile/{$cleanHandle}/post/{$rkey}";
    }

    /**
     * Executes an HTTP request using cURL or the injected transport.
     *
     * @param array<int, string> $headers
     * @return array{code: int, body: string, error: string}
     */
    private function request(string $url, string $method, array $headers, ?string $body): array
    {
        if ($this->transport !== null) {
            return ($this->transport)($url, $method, $headers, $body);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        return [
            'code' => $code,
            'body' => $responseBody,
            'error' => $err,
        ];
    }
}
