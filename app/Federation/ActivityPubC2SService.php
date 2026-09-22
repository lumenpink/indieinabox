<?php

declare(strict_types=1);

namespace Indieinabox\Federation;

use Indieinabox\Federation\Contracts\ActivityPubC2SInterface;
use Indieinabox\Repositories\Contracts\ActivityPubRepositoryInterface;
use Indieinabox\Site\Site;

/**
 * Service implementing W3C ActivityPub Client-to-Server (Section 6).
 */
class ActivityPubC2SService implements ActivityPubC2SInterface
{
    private Site $site;
    private ActivityPubRepositoryInterface $repository;

    public function __construct(Site $site, ActivityPubRepositoryInterface $repository)
    {
        $this->site = $site;
        $this->repository = $repository;
    }

    #[\Override]
    public function handleSubmission(array $payload, array $auth): array
    {
        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        $actorUri = $fqdn . '/actor';

        $activityTypes = [
            'Create', 'Update', 'Delete', 'Follow', 'Accept', 'Reject',
            'Add', 'Remove', 'Like', 'Announce', 'Undo', 'Question',
        ];

        $type = (string) ($payload['type'] ?? '');

        // W3C Section 6.1: If client submits an object rather than an activity, wrap it in a Create activity
        if ($type === '' || !in_array($type, $activityTypes, true)) {
            $object = $payload;
            if (empty($object['id'])) {
                $object['id'] = $fqdn . '/objects/' . bin2hex(random_bytes(12));
            }
            $object['attributedTo'] = $actorUri;
            if (empty($object['published'])) {
                $object['published'] = gmdate('c');
            }

            $payload = [
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'type' => 'Create',
                'actor' => $actorUri,
                'to' => $object['to'] ?? ['https://www.w3.org/ns/activitystreams#Public'],
                'cc' => $object['cc'] ?? [$actorUri . '/followers'],
                'object' => $object,
                'published' => gmdate('c'),
            ];
            $type = 'Create';
        } else {
            $payload['actor'] = $actorUri;
            if (empty($payload['published'])) {
                $payload['published'] = gmdate('c');
            }
            if (empty($payload['@context'])) {
                $payload['@context'] = 'https://www.w3.org/ns/activitystreams';
            }
        }

        if (empty($payload['id'])) {
            $payload['id'] = $fqdn . '/activities/' . bin2hex(random_bytes(12));
        }

        // Persist activity in activities log
        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $this->repository->recordActivity($payload['id'], $type, $actorUri, $payloadJson);

        // Fan-out to recipients/followers
        $targetInboxes = [];
        $recipients = array_merge(
            is_array($payload['to'] ?? null) ? $payload['to'] : (isset($payload['to']) ? [$payload['to']] : []),
            is_array($payload['cc'] ?? null) ? $payload['cc'] : (isset($payload['cc']) ? [$payload['cc']] : [])
        );

        $hasPublicOrFollowers = false;
        foreach ($recipients as $recipient) {
            if (is_string($recipient)) {
                if ($recipient === 'https://www.w3.org/ns/activitystreams#Public' ||
                    $recipient === 'as:Public' ||
                    str_ends_with($recipient, '/followers')) {
                    $hasPublicOrFollowers = true;
                }
            }
        }

        if ($hasPublicOrFollowers) {
            foreach ($this->repository->getDistinctInboxes() as $inbox) {
                $targetInboxes[$inbox] = true;
            }
        }

        foreach (array_keys($targetInboxes) as $inbox) {
            $this->repository->enqueueOutbox($payloadJson, $inbox);
        }

        return [
            'status' => 201,
            'headers' => [
                'Location' => $payload['id'],
                'Content-Type' => 'application/activity+json; charset=utf-8',
            ],
            'body' => $payload,
        ];
    }

    #[\Override]
    public function getOutboxCollection(int $limit = 50, int $offset = 0): array
    {
        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        $outboxUri = $fqdn . '/outbox';
        $total = $this->repository->getActivityCount();
        $rows = $this->repository->getActivities($limit, $offset);

        $orderedItems = [];
        foreach ($rows as $row) {
            $decoded = json_decode((string) $row['payload_json'], true);
            $orderedItems[] = is_array($decoded) ? $decoded : $row['activity_id'];
        }

        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $outboxUri,
            'type' => 'OrderedCollection',
            'totalItems' => $total,
            'orderedItems' => $orderedItems,
        ];
    }
}
