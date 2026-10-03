<?php

declare(strict_types=1);

namespace Indieinabox\AtProto\Contracts;

/**
 * Interface AtProtoClientInterface
 *
 * Defines the contract for XRPC communication with an AT Protocol Personal Data Server (PDS).
 */
interface AtProtoClientInterface
{
    /**
     * Authenticates with the PDS using an identifier (handle or DID) and password/app-password.
     *
     * @return array{accessJwt: string, refreshJwt: string, did: string, handle: string, pdsUrl: string}
     */
    public function createSession(string $identifier, string $password, ?string $pdsUrl = null): array;

    /**
     * Refreshes an expired access JWT using the refresh JWT.
     *
     * @return array{accessJwt: string, refreshJwt: string, did: string, handle: string, pdsUrl: string}|null
     */
    public function refreshSession(string $refreshJwt, string $pdsUrl): ?array;

    /**
     * Uploads an image or media blob to the PDS for use in post embeds.
     *
     * @return array{blob: array<string, mixed>}|null
     */
    public function uploadBlob(string $binaryData, string $mimeType, string $accessJwt, string $pdsUrl): ?array;

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
    ): array;

    /**
     * Resolves a handle into its DID via com.atproto.identity.resolveHandle.
     */
    public function resolveHandle(string $handle, ?string $pdsUrl = null): ?string;
}
