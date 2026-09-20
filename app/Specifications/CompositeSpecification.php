<?php

declare(strict_types=1);

namespace Indieinabox\Specifications;

use Indieinabox\Specifications\Contracts\SpecificationInterface;

/**
 * Abstract base class providing composition methods for specifications.
 */
abstract class CompositeSpecification implements SpecificationInterface
{
    /**
     * @param array<string, mixed> $candidate
     */
    #[\Override]
    abstract public function isSatisfiedBy(array $candidate): bool;

    #[\Override]
    public function and(SpecificationInterface $other): SpecificationInterface
    {
        return new AndSpecification($this, $other);
    }

    #[\Override]
    public function or(SpecificationInterface $other): SpecificationInterface
    {
        return new OrSpecification($this, $other);
    }

    #[\Override]
    public function not(): SpecificationInterface
    {
        return new NotSpecification($this);
    }
}
