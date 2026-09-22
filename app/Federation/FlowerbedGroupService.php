<?php

declare(strict_types=1);

namespace Indieinabox\Federation;

use Indieinabox\ActivityPub\KeyManager;
use Indieinabox\Federation\Contracts\FlowerbedGroupInterface;
use Indieinabox\Repositories\Contracts\ActivityPubRepositoryInterface;
use Indieinabox\Site\Site;

/**
 * Service implementing Group Actors for Flowerbeds (FEP-1b12).
 */
class FlowerbedGroupService implements FlowerbedGroupInterface
{
    private Site $site;
    private ActivityPubRepositoryInterface $repository;
    private KeyManager $keyManager;

    public function __construct(
        Site $site,
        ActivityPubRepositoryInterface $repository,
        KeyManager $keyManager
    ) {
        $this->site = $site;
        $this->repository = $repository;
        $this->keyManager = $keyManager;
    }

    #[\Override]
    public function getActor(string $slug): array
    {
        $slug = strtolower(trim($slug));
        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        $groupUri = $fqdn . '/@flowerbed/' . $slug;
        $pubKey = $this->keyManager->getPublicKey('main-key') ?? '';
        $readableName = ucwords(str_replace(['-', '_'], ' ', $slug));

        return [
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                'https://w3id.org/security/v1',
            ],
            'id' => $groupUri,
            'type' => 'Group',
            'preferredUsername' => $slug,
            'name' => $readableName . ' Flowerbed',
            'summary' => 'Community group and digital garden flowerbed for #' . $slug,
            'url' => $fqdn . '/flowerbed/' . $slug . '/',
            'inbox' => $groupUri . '/inbox',
            'outbox' => $groupUri . '/outbox',
            'followers' => $groupUri . '/followers',
            'publicKey' => [
                'id' => $groupUri . '#main-key',
                'owner' => $groupUri,
                'publicKeyPem' => $pubKey,
            ],
        ];
    }

    #[\Override]
    public function handleInbox(string $slug, array $activity): array
    {
        $slug = strtolower(trim($slug));
        $type = (string) ($activity['type'] ?? '');
        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        $groupUri = $fqdn . '/@flowerbed/' . $slug;

        if ($type === 'Follow') {
            $actorUrl = is_string($activity['actor'] ?? null)
                ? $activity['actor']
                : (is_array($activity['actor'] ?? null) ? ($activity['actor']['id'] ?? '') : '');

            if (!empty($actorUrl)) {
                $inboxUrl = is_array($activity['actor'] ?? null) && !empty($activity['actor']['inbox'])
                    ? (string) $activity['actor']['inbox']
                    : rtrim($actorUrl, '/') . '/inbox';

                $sharedInbox = is_array($activity['actor'] ?? null) && !empty($activity['actor']['endpoints']['sharedInbox'])
                    ? (string) $activity['actor']['endpoints']['sharedInbox']
                    : null;

                $this->repository->addFlowerbedFollower($slug, $actorUrl, $inboxUrl, $sharedInbox);

                // Build & queue Accept activity
                $accept = [
                    '@context' => 'https://www.w3.org/ns/activitystreams',
                    'id' => $groupUri . '/activities/' . bin2hex(random_bytes(12)),
                    'type' => 'Accept',
                    'actor' => $groupUri,
                    'object' => $activity,
                ];

                $this->repository->enqueueOutbox(
                    json_encode($accept, JSON_UNESCAPED_SLASHES),
                    $sharedInbox ?? $inboxUrl
                );

                return ['status' => 202, 'body' => ['status' => 'accepted', 'group' => $groupUri]];
            }
        }

        if ($type === 'Undo') {
            $object = is_array($activity['object'] ?? null) ? $activity['object'] : [];
            if (($object['type'] ?? '') === 'Follow') {
                $actorUrl = is_string($activity['actor'] ?? null)
                    ? $activity['actor']
                    : (is_array($activity['actor'] ?? null) ? ($activity['actor']['id'] ?? '') : '');

                if (!empty($actorUrl)) {
                    $this->repository->removeFlowerbedFollower($slug, $actorUrl);
                    return ['status' => 200, 'body' => ['status' => 'unfollowed', 'group' => $groupUri]];
                }
            }
        }

        return ['status' => 202, 'body' => ['status' => 'received']];
    }

    #[\Override]
    public function getOutbox(string $slug): array
    {
        $slug = strtolower(trim($slug));
        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        $groupUri = $fqdn . '/@flowerbed/' . $slug;

        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $groupUri . '/outbox',
            'type' => 'OrderedCollection',
            'totalItems' => 0,
            'orderedItems' => [],
        ];
    }

    #[\Override]
    public function getFollowers(string $slug): array
    {
        $slug = strtolower(trim($slug));
        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        $groupUri = $fqdn . '/@flowerbed/' . $slug;
        $inboxes = $this->repository->getFlowerbedFollowerInboxes($slug);

        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $groupUri . '/followers',
            'type' => 'OrderedCollection',
            'totalItems' => count($inboxes),
            'orderedItems' => [],
        ];
    }

    #[\Override]
    public function fanOutPost(string $slug, string $postUrl, string $title = ''): int
    {
        $slug = strtolower(trim($slug));
        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        $groupUri = $fqdn . '/@flowerbed/' . $slug;
        $inboxes = $this->repository->getFlowerbedFollowerInboxes($slug);

        if (empty($inboxes)) {
            return 0;
        }

        $announce = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $groupUri . '/announces/' . bin2hex(random_bytes(12)),
            'type' => 'Announce',
            'actor' => $groupUri,
            'published' => gmdate('c'),
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            'cc' => [$groupUri . '/followers'],
            'object' => $postUrl,
        ];

        $payloadJson = json_encode($announce, JSON_UNESCAPED_SLASHES);
        $this->repository->recordActivity($announce['id'], 'Announce', $groupUri, $payloadJson);

        $sent = 0;
        foreach ($inboxes as $inbox) {
            $this->repository->enqueueOutbox($payloadJson, $inbox);
            $sent++;
        }

        return $sent;
    }
}
