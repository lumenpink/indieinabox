<?php

declare(strict_types=1);

namespace Indieinabox\Federation\Adapters;

use Indieinabox\ActivityPub\ActivityBuilder;
use Indieinabox\Federation\ActivityPubAdapter;
use Indieinabox\Federation\Contracts\FederationAdapter;
use Indieinabox\Site\Site;

/**
 * Protocol adapter for Bookwyrm social book tracking and reviews.
 *
 * Implements Bookwyrm ActivityPub extensions:
 * - Book reviews (inReplyToBook, rating, review content)
 * - Reading status updates (readingStatus: finished, reading, want-to-read)
 */
class BookwyrmAdapter extends ActivityPubAdapter implements FederationAdapter
{
    public function __construct(Site $site, ?callable $transport = null)
    {
        parent::__construct($site, $transport);
    }

    #[\Override]
    public function getProtocol(): string
    {
        return 'bookwyrm';
    }

    #[\Override]
    public function supports(string $protocol): bool
    {
        return in_array(strtolower($protocol), ['bookwyrm', 'books', 'reading'], true);
    }

    /**
     * Builds a book review activity.
     *
     * @param string $bookUri Remote Bookwyrm book URI (e.g. https://bookwyrm.social/book/12345).
     * @param string $reviewTitle Title of the review.
     * @param string $reviewContent Body content of the review.
     * @param int|null $rating Rating score (1 to 5).
     * @param string $readingStatus Status ('finished', 'reading', 'to-read').
     * @return array<string, mixed>
     */
    public function buildReviewActivity(
        string $bookUri,
        string $reviewTitle,
        string $reviewContent,
        ?int $rating = null,
        string $readingStatus = 'finished'
    ): array {
        $fqdn = $this->getFqdn();
        $actorUri = $this->getActorUri();
        $objectId = $fqdn . '/reviews/' . date('Y-m-d-H-i-s') . '-' . uniqid();

        $metadata = [
            'read_of' => $bookUri,
            'read_status' => $readingStatus,
        ];
        if ($rating !== null) {
            $metadata['rating'] = $rating;
        }

        $object = ActivityBuilder::buildObjectForPageArray(
            $objectId,
            $actorUri,
            $fqdn,
            $reviewContent,
            $reviewTitle,
            $metadata
        );

        $activityId = $objectId . '#activity';
        return ActivityBuilder::buildCreateActivity($activityId, $actorUri, $object);
    }

    /**
     * Builds a reading progress / status change activity.
     *
     * @param string $bookUri Book URI.
     * @param string $status Reading status ('finished', 'reading', 'to-read', 'dropped').
     * @return array<string, mixed>
     */
    public function buildReadingStatusActivity(string $bookUri, string $status): array
    {
        $fqdn = $this->getFqdn();
        $actorUri = $this->getActorUri();
        $objectId = $fqdn . '/reading-log/' . date('Y-m-d-H-i-s');

        $metadata = [
            'read_of' => $bookUri,
            'read_status' => $status
        ];

        $object = ActivityBuilder::buildObjectForPageArray(
            $objectId,
            $actorUri,
            $fqdn,
            "Updated reading status to: " . htmlspecialchars($status),
            null,
            $metadata
        );

        $activityId = $objectId . '#activity';
        return ActivityBuilder::buildCreateActivity($activityId, $actorUri, $object);
    }
}
