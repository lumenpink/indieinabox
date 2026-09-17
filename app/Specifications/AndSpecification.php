<?php

declare(strict_types=1);

namespace Indieinabox\Specifications;

use Indieinabox\Specifications\Contracts\SpecificationInterface;

/**
 * Composite specification representing a logical AND of two specifications.
 */
class AndSpecification extends CompositeSpecification
{
    private SpecificationInterface $left;
    private SpecificationInterface $right;

    public function __construct(SpecificationInterface $left, SpecificationInterface $right)
    {
        $this->left = $left;
        $this->right = $right;
    }

    /**
     * @param array<string, mixed> $candidate
     */
    public function isSatisfiedBy(array $candidate): bool
    {
        return $this->left->isSatisfiedBy($candidate) && $this->right->isSatisfiedBy($candidate);
    }
}
