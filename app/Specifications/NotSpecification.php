<?php

declare(strict_types=1);

namespace Indieinabox\Specifications;

use Indieinabox\Specifications\Contracts\SpecificationInterface;

/**
 * Composite specification representing a logical NOT of a specification.
 */
class NotSpecification extends CompositeSpecification
{
    private SpecificationInterface $specification;

    public function __construct(SpecificationInterface $specification)
    {
        $this->specification = $specification;
    }

    /**
     * @param array<string, mixed> $candidate
     */
    #[\Override]
    public function isSatisfiedBy(array $candidate): bool
    {
        return !$this->specification->isSatisfiedBy($candidate);
    }
}
