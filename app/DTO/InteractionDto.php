<?php

declare(strict_types=1);

namespace Indieinabox\DTO;

use DateTimeImmutable;

/**
 * Data Transfer Object representing a normalized social interaction (Webmention or ActivityPub).
 */
final class InteractionDto
{
    private string $id;
    private string $target;
    private string $source;
    private string $type;
    private string $protocol;
    private string $authorName;
    private ?string $authorUrl;
    private ?string $authorPhoto;
    private string $content;
    private DateTimeImmutable $publishedAt;
    private string $status;
    /** @var array<string, mixed> */
    private array $metadata;

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        string $id,
        string $target,
        string $source,
        string $type,
        string $protocol,
        string $authorName,
        ?string $authorUrl,
        ?string $authorPhoto,
        string $content,
        DateTimeImmutable $publishedAt,
        string $status = "pending",
        array $metadata = []
    ) {
        $this->id = $id;
        $this->target = $target;
        $this->source = $source;
        $this->type = strtolower($type);
        $this->protocol = strtolower($protocol);
        $this->authorName = $authorName;
        $this->authorUrl = $authorUrl;
        $this->authorPhoto = $authorPhoto;
        $this->content = $content;
        $this->publishedAt = $publishedAt;
        $this->status = strtolower($status);
        $this->metadata = $metadata;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getProtocol(): string
    {
        return $this->protocol;
    }

    public function getAuthorName(): string
    {
        return $this->authorName;
    }

    public function getAuthorUrl(): ?string
    {
        return $this->authorUrl;
    }

    public function getAuthorPhoto(): ?string
    {
        return $this->authorPhoto;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getPublishedAt(): DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Creates an InteractionDto from verified Webmention content.
     *
     * @param string $source
     * @param string $target
     * @param array<string, mixed> $verifiedContent
     * @param string $status
     * @return self
     */
    public static function fromWebmention(
        string $source,
        string $target,
        array $verifiedContent,
        string $status = "pending"
    ): self {
        $targetSlug = trim(parse_url($target, PHP_URL_PATH) ?? $target, "/");
        if ($targetSlug === "") {
            $targetSlug = "home";
        }
        $targetHash = md5($targetSlug);
        $id = $targetHash . "_" . md5($source);

        $interactionType = (string) ($verifiedContent["interaction_type"] ?? "webmention");
        $authorName = (string) ($verifiedContent["author_name"] ?? ($verifiedContent["title"] ?? ""));
        if ($authorName === "") {
            $host = parse_url($source, PHP_URL_HOST);
            $authorName = "Webmention from " . ($host ?: $source);
        }

        $authorUrl = isset($verifiedContent["author_url"]) && $verifiedContent["author_url"] !== ""
            ? (string) $verifiedContent["author_url"]
            : $source;

        $authorPhoto = isset($verifiedContent["author_photo"]) && $verifiedContent["author_photo"] !== ""
            ? (string) $verifiedContent["author_photo"]
            : null;

        $content = (string) ($verifiedContent["text"] ?? ($verifiedContent["content"] ?? ""));

        $metadata = [];
        if (isset($verifiedContent["whostyle"]) && is_array($verifiedContent["whostyle"])) {
            $metadata["whostyle"] = $verifiedContent["whostyle"];
        }
        if (isset($verifiedContent["rsvp"])) {
            $metadata["rsvp"] = $verifiedContent["rsvp"];
        }

        return new self(
            $id,
            $target,
            $source,
            $interactionType,
            "webmention",
            $authorName,
            $authorUrl,
            $authorPhoto,
            $content,
            new DateTimeImmutable(),
            $status,
            $metadata
        );
    }

    /**
     * Creates an InteractionDto from an ActivityPub activity.
     *
     * @param array<string, mixed> $activity
     * @param array<string, mixed>|null $actorData
     * @param string $status
     * @return self|null
     */
    public static function fromActivityPub(
        array $activity,
        ?array $actorData = null,
        string $status = "pending"
    ): ?self {
        $activityType = (string) ($activity["type"] ?? "");
        $actorUri = (string) (is_array($activity["actor"] ?? null) ? ($activity["actor"]["id"] ?? "") : ($activity["actor"] ?? ""));

        if ($actorUri === "" || $activityType === "") {
            return null;
        }

        $authorName = $actorUri;
        $authorPhoto = null;
        if ($actorData !== null) {
            $authorName = (string) ($actorData["name"] ?? ($actorData["preferredUsername"] ?? $actorUri));
            if (isset($actorData["icon"]["url"])) {
                $authorPhoto = (string) $actorData["icon"]["url"];
            }
        }

        $activityId = (string) ($activity["id"] ?? (md5($actorUri . microtime())));
        $object = $activity["object"] ?? null;

        $target = "";
        $type = "mention";
        $content = "";
        $published = new DateTimeImmutable();
        $meta = ["activity_id" => $activityId];

        if ($activityType === "Like") {
            $target = is_string($object) ? $object : (string) ($object["id"] ?? "");
            $type = "like";
        } elseif ($activityType === "Announce") {
            $target = is_string($object) ? $object : (string) ($object["id"] ?? "");
            $type = "repost";
        } elseif ($activityType === "Create" && is_array($object)) {
            $target = (string) ($object["inReplyTo"] ?? "");
            $content = (string) ($object["content"] ?? ($object["summary"] ?? ""));
            $published = isset($object["published"]) ? new DateTimeImmutable((string) $object["published"]) : new DateTimeImmutable();
            $type = $target !== "" ? "reply" : "mention";
            $meta["object_id"] = (string) ($object["id"] ?? "");
            if (isset($object["inReplyToBook"])) {
                $meta["read_of"] = $object["inReplyToBook"];
                if (isset($object["rating"])) {
                    $meta["rating"] = $object["rating"];
                }
                if (isset($object["readingStatus"])) {
                    $meta["read_status"] = $object["readingStatus"];
                }
            }
        } else {
            return null;
        }

        $objectId = is_array($object) ? (string) ($object["id"] ?? "") : "";
        if (is_array($object) && isset($object["url"])) {
            $meta["url"] = (string) $object["url"];
        }

        if ($target !== "") {
            $targetSlug = trim(parse_url($target, PHP_URL_PATH) ?? $target, "/");
            if ($targetSlug === "") {
                $targetSlug = "home";
            }
            $targetHash = md5($targetSlug);
            $id = $targetHash . "_" . md5($objectId ?: $activityId);
        } else {
            $id = md5($objectId ?: ($activityId ?: ($actorUri . microtime())));
        }

        return new self(
            $id,
            $target,
            $actorUri,
            $type,
            "activitypub",
            $authorName,
            $actorUri,
            $authorPhoto,
            $content,
            $published,
            $status,
            $meta
        );
    }

    /**
     * Constructs an InteractionDto from an associative array.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $published = isset($data["published"])
            ? (is_numeric($data["published"])
                ? (new DateTimeImmutable())->setTimestamp((int) $data["published"])
                : new DateTimeImmutable((string) $data["published"]))
            : new DateTimeImmutable();

        return new self(
            (string) ($data["id"] ?? md5(uniqid("", true))),
            (string) ($data["target"] ?? ""),
            (string) ($data["source"] ?? ""),
            (string) ($data["interaction_type"] ?? ($data["type"] ?? "mention")),
            (string) ($data["protocol"] ?? "webmention"),
            (string) ($data["author_name"] ?? "Anonymous"),
            isset($data["author_url"]) ? (string) $data["author_url"] : null,
            isset($data["author_photo"]) ? (string) $data["author_photo"] : null,
            (string) ($data["content"] ?? ""),
            $published,
            (string) ($data["status"] ?? "pending"),
            isset($data["metadata"]) && is_array($data["metadata"]) ? $data["metadata"] : []
        );
    }

    /**
     * Converts the DTO to an associative array representation compatible with storage and frontmatter.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            "id" => $this->id,
            "target" => $this->target,
            "source" => $this->source,
            "type" => $this->type,
            "interaction_type" => $this->type,
            "protocol" => $this->protocol,
            "author_name" => $this->authorName,
            "author_url" => $this->authorUrl,
            "author_photo" => $this->authorPhoto,
            "content" => $this->content,
            "published" => $this->publishedAt->getTimestamp(),
            "status" => $this->status,
            "metadata" => $this->metadata,
        ];
    }
}
