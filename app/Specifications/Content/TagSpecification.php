<?php

declare(strict_types=1);

namespace Indieinabox\Specifications\Content;

use Indieinabox\Specifications\CompositeSpecification;

/**
 * Specification matching content by tags.
 */
class TagSpecification extends CompositeSpecification
{
    /** @var array<int, string> */
    private array $tags;
    private bool $matchAll;

    /**
     * @param string|array<int, string> $tags Tag or tags to search for.
     * @param bool $matchAll If true, candidate must contain all specified tags; if false, any tag matches.
     */
    public function __construct(string|array $tags, bool $matchAll = false)
    {
        $this->tags = array_map('strtolower', is_array($tags) ? $tags : [$tags]);
        $this->matchAll = $matchAll;
    }

    /**
     * @param array<string, mixed> $candidate
     */
    public function isSatisfiedBy(array $candidate): bool
    {
        $candidateTags = $candidate['frontmatter']['tags'] ?? ($candidate['tags'] ?? []);
        if (!is_array($candidateTags)) {
            $candidateTags = is_string($candidateTags) ? [$candidateTags] : [];
        }
        $normalizedCandidateTags = array_map('strtolower', array_map('strval', $candidateTags));

        if (empty($this->tags)) {
            return true;
        }

        if ($this->matchAll) {
            foreach ($this->tags as $tag) {
                if (!in_array($tag, $normalizedCandidateTags, true)) {
                    return false;
                }
            }
            return true;
        }

        foreach ($this->tags as $tag) {
            if (in_array($tag, $normalizedCandidateTags, true)) {
                return true;
            }
        }

        return false;
    }
}
