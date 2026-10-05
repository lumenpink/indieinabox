<?php

declare(strict_types=1);

namespace Indieinabox\Nostr\Contracts;

interface NostrRelayClientInterface
{
    /**
     * Returns the pre-configured default public relays with active flags.
     *
     * @return array<int, array{url: string, active: bool}>
     */
    public static function getDefaultRelays(): array;

    /**
     * Formats a NIP-01 WebSocket envelope: ["EVENT", <eventData>]
     *
     * @param array<string, mixed> $eventData
     */
    public function buildEventEnvelope(array $eventData): string;

    /**
     * Parses a raw JSON relay response message (e.g. OK, NOTICE).
     *
     * @return array{type: string, eventId?: string, success?: bool, message: string}|null
     */
    public function parseRelayMessage(string $rawMessage): ?array;

    /**
     * Publishes a signed Nostr event to configured relays.
     *
     * @param array<string, mixed> $eventData
     * @param array<int, string|array{url: string, active: bool}>|null $relays
     * @return array<string, array{success: bool, message: string}>
     */
    public function publish(array $eventData, ?array $relays = null): array;
}
