<?php

declare(strict_types=1);

namespace Indieinabox\Events;

/**
 * Dispatched when a post (note, article, bookmark, etc.) is successfully authored and published.
 */
final class PostPublishedEvent extends DomainEvent
{
    private string $slug;
    private string $filepath;
    private string $kind;
    private ?string $title;
    private string $content;
    /** @var array<int, string> */
    private array $mediaPaths;

    /**
     * @param string $slug
     * @param string $filepath
     * @param string $kind
     * @param string|null $title
     * @param string $content
     * @param array<int, string> $mediaPaths
     */
    public function __construct(
        string $slug,
        string $filepath,
        string $kind,
        ?string $title,
        string $content,
        array $mediaPaths = []
    ) {
        parent::__construct();
        $this->slug = $slug;
        $this->filepath = $filepath;
        $this->kind = $kind;
        $this->title = $title;
        $this->content = $content;
        $this->mediaPaths = $mediaPaths;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getFilepath(): string
    {
        return $this->filepath;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @return array<int, string>
     */
    public function getMediaPaths(): array
    {
        return $this->mediaPaths;
    }
}
