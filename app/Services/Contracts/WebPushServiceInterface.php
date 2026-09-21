<?php

declare(strict_types=1);

namespace Indieinabox\Services\Contracts;

/**
 * Contract for Web Push notifications, VAPID key management, and browser subscription persistence.
 */
interface WebPushServiceInterface
{
    /**
     * Retrieves the URL-safe base64 encoded uncompressed VAPID public key (P-256).
     */
    public function getVapidPublicKey(): string;

    /**
     * Retrieves the stored or generated VAPID public and private keys.
     *
     * @return array{publicKey: string, privateKey: string}
     */
    public function getVapidKeys(): array;

    /**
     * Generates a signed VAPID JWT (ES256) for a given push service audience.
     *
     * @param string $audience Target push service origin (e.g. "https://fcm.googleapis.com").
     * @param int|null $expiresIn Expiration in seconds from now (defaults to 86400).
     * @return string Signed JWT token.
     */
    public function createVapidJwt(string $audience, ?int $expiresIn = null): string;

    /**
     * Registers or updates a client browser push subscription.
     *
     * @param string $endpoint Push service delivery URL.
     * @param string $p256dh Client public key (base64url).
     * @param string $auth Client authentication secret (base64url).
     * @param string|null $userAgent Optional client user agent string.
     * @return bool
     */
    public function subscribe(string $endpoint, string $p256dh, string $auth, ?string $userAgent = null): bool;

    /**
     * Removes an active browser push subscription by endpoint URL.
     *
     * @param string $endpoint
     * @return bool
     */
    public function unsubscribe(string $endpoint): bool;

    /**
     * Retrieves all active browser push subscriptions.
     *
     * @return array<int, array{id: int|string, endpoint: string, p256dh: string, auth: string, user_agent: ?string, created_at: int|string}>
     */
    public function getSubscriptions(): array;

    /**
     * Sends a push notification payload to a single subscription.
     *
     * @param array<string, mixed> $subscription
     * @param array<string, mixed> $payload
     * @return bool True on success; false on failure or if endpoint was removed (404/410).
     */
    public function sendNotification(array $subscription, array $payload): bool;

    /**
     * Broadcasts a push notification to all active subscribers.
     *
     * @param string $title Notification title.
     * @param string $body Notification message content.
     * @param string|null $url Target URL to open on click.
     * @param string|null $tag Notification collapse tag.
     * @return int Count of successful deliveries.
     */
    public function broadcast(string $title, string $body, ?string $url = null, ?string $tag = null): int;
}
