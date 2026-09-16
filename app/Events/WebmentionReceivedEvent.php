<?php

declare(strict_types=1);

namespace Indieinabox\Events;

/**
 * Dispatched when a verified incoming Webmention is recorded.
 */
final class WebmentionReceivedEvent extends DomainEvent
{
    private string $source;
    private string $target;
    private string $type;
    /** @var array<string, mixed>|null */
    private ?array $author;
    private ?string $summary;

    /**
     * @param string $source
     * @param string $target
     * @param string $type e.g. 'mention', 'like', 'reply', 'repost'
     * @param array<string, mixed>|null $author
     * @param string|null $summary
     */
    public function __construct(
        string $source,
        string $target,
        string $type = 'mention',
        ?array $author = null,
        ?string $summary = null
    ) {
        parent::__construct();
        $this->source = $source;
        $this->target = $target;
        $this->type = $type;
        $this->author = $author;
        $this->summary = $summary;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getAuthor(): ?array
    {
        return $this->author;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }
}
