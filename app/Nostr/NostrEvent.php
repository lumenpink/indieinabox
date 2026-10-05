<?php

declare(strict_types=1);

namespace Indieinabox\Nostr;

use InvalidArgumentException;
use RuntimeException;

/**
 * Class NostrEvent
 *
 * Implements NIP-01 canonical event structure, serialization, hashing, and Schnorr signing.
 */
class NostrEvent
{
    private string $pubkey;
    private int $createdAt;
    private int $kind;
    /** @var array<int, array<int, string>> */
    private array $tags;
    private string $content;
    private ?string $id = null;
    private ?string $sig = null;

    /**
     * @param string $pubkey 64-character lowercase hex public key.
     * @param int|null $createdAt Unix timestamp (seconds).
     * @param int $kind Event kind (1 = short text note).
     * @param array<int, array<int, string>> $tags Event tags.
     * @param string $content Event body text.
     * @param string|null $id 64-character lowercase hex event ID.
     * @param string|null $sig 128-character lowercase hex signature.
     */
    public function __construct(
        string $pubkey,
        ?int $createdAt = null,
        int $kind = 1,
        array $tags = [],
        string $content = '',
        ?string $id = null,
        ?string $sig = null
    ) {
        $cleanPub = trim($pubkey);
        if (str_starts_with($cleanPub, 'npub1')) {
            $this->pubkey = NostrKey::npubToHex($cleanPub);
        } else {
            $this->pubkey = strtolower($cleanPub);
        }

        $this->createdAt = $createdAt ?? time();
        $this->kind = $kind;
        $this->tags = $tags;
        $this->content = $content;
        $this->id = $id;
        $this->sig = $sig;
    }

    /**
     * Canonical NIP-01 JSON serialization: [0, <pubkey>, <created_at>, <kind>, <tags>, <content>]
     */
    public function serialize(): string
    {
        $payload = [
            0,
            $this->pubkey,
            $this->createdAt,
            $this->kind,
            $this->tags,
            $this->content,
        ];

        return (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Returns or computes the deterministic 64-character SHA-256 event ID.
     */
    public function getId(): string
    {
        if ($this->id === null) {
            $this->id = hash('sha256', $this->serialize());
        }
        return $this->id;
    }

    /**
     * Signs the event with the author's private key (hex or nsec).
     *
     * @param string $privateKeyHexOrNsec
     * @param string|null $auxRand Optional 32-byte aux randomness.
     * @return string 128-character hex signature.
     */
    public function sign(string $privateKeyHexOrNsec, ?string $auxRand = null): string
    {
        $clean = trim($privateKeyHexOrNsec);
        if (str_starts_with($clean, 'nsec1')) {
            $privHex = NostrKey::nsecToHex($clean);
        } else {
            $privHex = strtolower($clean);
        }

        if (strlen($privHex) !== 64) {
            throw new InvalidArgumentException('Invalid private key for signing.');
        }

        $id = $this->getId();
        $msgBytes = (string) hex2bin($id);
        $secKeyBytes = (string) hex2bin($privHex);

        $sigBytes = Bip340::sign($msgBytes, $secKeyBytes, $auxRand);
        $this->sig = bin2hex($sigBytes);

        return $this->sig;
    }

    public function getPubkey(): string
    {
        return $this->pubkey;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getKind(): int
    {
        return $this->kind;
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getSig(): ?string
    {
        return $this->sig;
    }

    /**
     * Returns array representation per NIP-01.
     *
     * @return array{id: string, pubkey: string, created_at: int, kind: int, tags: array<int, array<int, string>>, content: string, sig: string}
     */
    public function toArray(): array
    {
        if ($this->sig === null) {
            throw new RuntimeException('Event must be signed before conversion to array.');
        }

        return [
            'id' => $this->getId(),
            'pubkey' => $this->pubkey,
            'created_at' => $this->createdAt,
            'kind' => $this->kind,
            'tags' => $this->tags,
            'content' => $this->content,
            'sig' => $this->sig,
        ];
    }
}
