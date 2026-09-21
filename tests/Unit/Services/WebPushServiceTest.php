<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Indieinabox\Core\Database;
use Indieinabox\Services\WebPushService;
use Indieinabox\Support\FileUtils;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/webpush_service_unit_' . uniqid('', true);
    mkdir($this->tempDir, 0755, true);

    Database::disconnect();
    $dbPath = $this->tempDir . '/test.sqlite';
    Database::$dataDir = $this->tempDir . '/data';
    Database::connect($dbPath);
    $this->db = Database::getDb();
    $schema = file_get_contents(__DIR__ . '/../../../database.sql');
    $this->db->exec($schema);

    $this->service = new WebPushService($this->db, 'mailto:test@example.com');
});

afterEach(function () {
    Database::disconnect();
    Database::$dataDir = '';
    FileUtils::recursiveRmdir($this->tempDir);
});

describe('WebPushService VAPID key lifecycle and JWT creation', function () {
    it('generates and persists valid P-256 VAPID keys in settings table', function () {
        $publicKey = $this->service->getVapidPublicKey();
        expect($publicKey)->toBeString();
        expect($publicKey)->not->toBeEmpty();

        // Decoded uncompressed public key must be exactly 65 bytes starting with 0x04
        $padded = str_pad(strtr($publicKey, '-_', '+/'), (int) (ceil(strlen($publicKey) / 4) * 4), '=');
        $raw = base64_decode($padded);
        expect(strlen($raw))->toBe(65);
        expect(ord($raw[0]))->toBe(4);

        // Keys should be cached and persistent in settings
        $keys2 = $this->service->getVapidKeys();
        expect($keys2['publicKey'])->toBe($publicKey);

        // A new instance pointing to same DB should load identical keys
        $freshService = new WebPushService($this->db, 'mailto:test@example.com');
        expect($freshService->getVapidPublicKey())->toBe($publicKey);
    });

    it('generates a valid ES256 VAPID JWT token with correct claims', function () {
        $jwt = $this->service->createVapidJwt('https://fcm.googleapis.com');
        $parts = explode('.', $jwt);
        expect($parts)->toHaveCount(3);

        $header = json_decode(base64_decode(str_pad(strtr($parts[0], '-_', '+/'), 4 * (int)ceil(strlen($parts[0])/4), '=')), true);
        $payload = json_decode(base64_decode(str_pad(strtr($parts[1], '-_', '+/'), 4 * (int)ceil(strlen($parts[1])/4), '=')), true);

        expect($header['alg'])->toBe('ES256');
        expect($header['typ'])->toBe('JWT');
        expect($payload['aud'])->toBe('https://fcm.googleapis.com');
        expect($payload['sub'])->toBe('mailto:test@example.com');
        expect($payload['exp'])->toBeGreaterThan(time());

        // Raw IEEE P1363 signature in part 3 must be 64 bytes
        $rawSig = base64_decode(str_pad(strtr($parts[2], '-_', '+/'), 4 * (int)ceil(strlen($parts[2])/4), '='));
        expect(strlen($rawSig))->toBe(64);
    });
});

describe('WebPushService subscriptions persistence', function () {
    it('manages browser push subscriptions lifecycle', function () {
        expect($this->service->getSubscriptions())->toBeEmpty();

        // Subscribe new endpoint
        $res = $this->service->subscribe(
            'https://fcm.googleapis.com/fcm/send/sample-client-1',
            'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QT9P04MgPyGZ9IzVnR6beGe8enivgv',
            'tBHItJI5svbpez7KI4CCXg',
            'Mozilla/5.0 (X11; Linux x86_64)'
        );
        expect($res)->toBeTrue();

        $subs = $this->service->getSubscriptions();
        expect($subs)->toHaveCount(1);
        expect($subs[0]['endpoint'])->toBe('https://fcm.googleapis.com/fcm/send/sample-client-1');
        expect($subs[0]['user_agent'])->toBe('Mozilla/5.0 (X11; Linux x86_64)');

        // Updating same endpoint updates keys without creating duplicate
        $this->service->subscribe(
            'https://fcm.googleapis.com/fcm/send/sample-client-1',
            'updated-key',
            'updated-auth'
        );
        $subsAfterUpdate = $this->service->getSubscriptions();
        expect($subsAfterUpdate)->toHaveCount(1);
        expect($subsAfterUpdate[0]['p256dh'])->toBe('updated-key');

        // Unsubscribe
        expect($this->service->unsubscribe('https://fcm.googleapis.com/fcm/send/sample-client-1'))->toBeTrue();
        expect($this->service->getSubscriptions())->toBeEmpty();
    });
});

describe('WebPushService sending and broadcasting', function () {
    it('delivers notification and cleans up 410 Gone subscriptions', function () {
        $sentRequests = [];
        $mockSender = function (string $endpoint, array $headers, string $body) use (&$sentRequests): int {
            $sentRequests[] = ['endpoint' => $endpoint, 'headers' => $headers, 'body' => $body];
            if (str_contains($endpoint, 'expired-client')) {
                return 410; // Gone
            }
            return 201; // Created
        };

        $service = new WebPushService($this->db, 'mailto:test@example.com', $mockSender);

        $service->subscribe('https://push.services.mozilla.com/active-client', 'p256', 'auth');
        $service->subscribe('https://push.services.mozilla.com/expired-client', 'p256', 'auth');
        expect($service->getSubscriptions())->toHaveCount(2);

        // Broadcast alert
        $count = $service->broadcast('New Mention', 'Alice mentioned you in a post', '/notifications');
        expect($count)->toBe(1);
        expect($sentRequests)->toHaveCount(2);

        // Expired client returning 410 should be automatically removed
        $remainingSubs = $service->getSubscriptions();
        expect($remainingSubs)->toHaveCount(1);
        expect($remainingSubs[0]['endpoint'])->toBe('https://push.services.mozilla.com/active-client');
    });
});
