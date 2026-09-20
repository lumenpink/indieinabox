<?php

declare(strict_types=1);

namespace Indieinabox\Federation;

use Indieinabox\ActivityPub\ActivityBuilder;
use Indieinabox\ActivityPub\KeyManager;
use Indieinabox\Core\Database;
use Indieinabox\Federation\Contracts\FederationAdapter;
use Indieinabox\Site\Site;
use Throwable;

/**
 * Protocol adapter implementing W3C ActivityPub / ActivityStreams 2.0 federation.
 */
class ActivityPubAdapter implements FederationAdapter
{
    private Site $site;
    /**
     * @var callable|null
     */
    private $transport;

    /**
     * @param Site $site
     * @param callable|null $transport Optional HTTP client hook: fn(string $url, array $headers, string $body): array{code: int, body: string, error: string}
     */
    public function __construct(Site $site, ?callable $transport = null)
    {
        $this->site = $site;
        $this->transport = $transport;
    }

    #[\Override]
    public function getProtocol(): string
    {
        return 'activitypub';
    }

    #[\Override]
    public function supports(string $protocol): bool
    {
        return in_array(strtolower($protocol), ['activitypub', 'ap'], true);
    }

    #[\Override]
    public function buildLikeActivity(string $targetUrl): array
    {
        $actorUri = $this->getActorUri();
        $activityId = $this->getFqdn() . '/activity/' . uniqid();
        return ActivityBuilder::buildInteractionActivity($activityId, 'Like', $actorUri, $targetUrl);
    }

    #[\Override]
    public function buildReplyActivity(string $targetUrl, string $content, ?string $inReplyTo = null): array
    {
        $fqdn = $this->getFqdn();
        $actorUri = $this->getActorUri();
        $objectId = $fqdn . '/notes/' . date('Y-m-d-H-i-s');

        $metadata = [];
        if ($inReplyTo !== null) {
            $metadata['reply'] = $inReplyTo;
        } elseif ($targetUrl !== '') {
            $metadata['reply'] = $targetUrl;
        }

        $object = ActivityBuilder::buildObjectForPageArray(
            $objectId,
            $actorUri,
            $fqdn,
            $content,
            null,
            $metadata
        );

        $activityId = $objectId . '#activity';
        return ActivityBuilder::buildCreateActivity($activityId, $actorUri, $object);
    }

    #[\Override]
    public function buildFollowActivity(string $targetActorUri): array
    {
        $actorUri = $this->getActorUri();
        $activityId = $this->getFqdn() . '/activity/' . uniqid();
        return ActivityBuilder::buildInteractionActivity($activityId, 'Follow', $actorUri, $targetActorUri);
    }

    #[\Override]
    public function deliverActivity(array|string $activity, string $destinationUrl): bool
    {
        $payload = is_array($activity)
            ? (string) json_encode($activity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : $activity;

        $db = Database::getDb();
        $keyManager = new KeyManager($db);
        $keyManager->ensureKeys('main-key');
        $privateKey = (string) $keyManager->getPrivateKey('main-key');

        $keyId = $this->getActorUri() . '#main-key';

        $headers = HttpSignature::sign(
            $keyId,
            $privateKey,
            'POST',
            $destinationUrl,
            $payload,
            ['Content-Type' => 'application/activity+json']
        );

        if ($this->transport !== null) {
            $result = ($this->transport)($destinationUrl, $headers, $payload);
            return ($result['code'] ?? 0) >= 200 && ($result['code'] ?? 0) < 300;
        }

        return $this->sendCurl($destinationUrl, $headers, $payload);
    }

    #[\Override]
    public function parseActivity(string $payload): ?array
    {
        try {
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            return is_array($data) ? $data : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function getFqdn(): string
    {
        $fqdn = $this->site->metadata->fqdn ?? (string) Database::getSetting('fqdn');
        return rtrim($fqdn ?: 'http://localhost:8080', '/');
    }

    private function getActorUri(): string
    {
        $handle = (string) (Database::getSetting('activitypub_handle') ?: 'author');
        return $this->getFqdn() . '/@' . $handle;
    }

    /**
     * Sends payload via standard cURL with HTTP signature headers.
     *
     * @param string $targetUrl
     * @param array<string, string> $headers
     * @param string $payload
     * @return bool
     */
    private function sendCurl(string $targetUrl, array $headers, string $payload): bool
    {
        $ch = curl_init($targetUrl);
        $curlHeaders = [];
        foreach ($headers as $k => $v) {
            $curlHeaders[] = "$k: $v";
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
    }
}
