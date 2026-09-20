<?php

declare(strict_types=1);

namespace Indieinabox\Specifications\Content;

use Indieinabox\Specifications\CompositeSpecification;

/**
 * Specification matching content by slug.
 */
class SlugSpecification extends CompositeSpecification
{
    private string $slug;

    public function __construct(string $slug)
    {
        $this->slug = trim($slug, '/');
    }

    /**
     * @param array<string, mixed> $candidate
     */
    #[\Override]
    public function isSatisfiedBy(array $candidate): bool
    {
        $candidateSlug = trim((string) ($candidate['slug'] ?? ''), '/');
        return $candidateSlug === $this->slug;
    }
}
