<?php

declare(strict_types=1);

namespace Indieinabox\Federation\Adapters;

use Indieinabox\ActivityPub\ActivityBuilder;
use Indieinabox\Federation\ActivityPubAdapter;
use Indieinabox\Federation\Contracts\FederationAdapter;
use Indieinabox\Site\Site;

/**
 * Protocol adapter for Lemmy community forums and threaded discussions.
 *
 * Implements Lemmy / FEP-1b12 group federation:
 * - Community / Group targeting
 * - Upvotes and Downvotes (Like and Dislike activities)
 * - Threaded comment trees (inReplyTo)
 */
class LemmyAdapter extends ActivityPubAdapter implements FederationAdapter
{
    public function __construct(Site $site, ?callable $transport = null)
    {
        parent::__construct($site, $transport);
    }

    #[\Override]
    public function getProtocol(): string
    {
        return 'lemmy';
    }

    #[\Override]
    public function supports(string $protocol): bool
    {
        return in_array(strtolower($protocol), ['lemmy', 'fep-1b12', 'forum'], true);
    }

    /**
     * Builds an upvote activity (ActivityStreams 'Like').
     */
    #[\Override]
    public function buildLikeActivity(string $targetUrl): array
    {
        return parent::buildLikeActivity($targetUrl);
    }

    /**
     * Builds a downvote activity (ActivityStreams 'Dislike').
     *
     * @param string $targetUrl Post or comment URI.
     * @return array<string, mixed>
     */
    public function buildDislikeActivity(string $targetUrl): array
    {
        $actorUri = $this->getActorUri();
        $activityId = $this->getFqdn() . '/activity/' . uniqid();
        return ActivityBuilder::buildInteractionActivity($activityId, 'Dislike', $actorUri, $targetUrl);
    }

    /**
     * Builds a community post submission (Create Page/Article targeted to a Lemmy Group).
     *
     * @param string $communityActorUri Lemmy community actor URI (e.g. https://lemmy.ml/c/technology).
     * @param string $title Post title.
     * @param string $content Post body or URL.
     * @param string|null $url Optional external link URL.
     * @return array<string, mixed>
     */
    public function buildCommunityPostActivity(string $communityActorUri, string $title, string $content, ?string $url = null): array
    {
        $fqdn = $this->getFqdn();
        $actorUri = $this->getActorUri();
        $objectId = $fqdn . '/posts/' . date('Y-m-d-H-i-s') . '-' . uniqid();

        $metadata = [
            'syndicate_to' => [$communityActorUri]
        ];
        if ($url !== null) {
            $metadata['url'] = $url;
        }

        $object = ActivityBuilder::buildObjectForPageArray(
            $objectId,
            $actorUri,
            $fqdn,
            $content,
            $title,
            $metadata
        );

        // Community is recipient and audience
        $object['to'] = [$communityActorUri, 'https://www.w3.org/ns/activitystreams#Public'];
        $object['audience'] = $communityActorUri;

        $activityId = $objectId . '#activity';
        $activity = ActivityBuilder::buildCreateActivity($activityId, $actorUri, $object);
        $activity['to'] = $object['to'];

        return $activity;
    }

    /**
     * Builds a threaded comment inside a Lemmy discussion.
     */
    #[\Override]
    public function buildReplyActivity(string $targetUrl, string $content, ?string $inReplyTo = null): array
    {
        $parentUrl = $inReplyTo ?: $targetUrl;
        return parent::buildReplyActivity($targetUrl, $content, $parentUrl);
    }
}
