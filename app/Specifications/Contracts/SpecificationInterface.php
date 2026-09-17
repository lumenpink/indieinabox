<?php

declare(strict_types=1);

namespace Indieinabox\Specifications\Contracts;

/**
 * Interface SpecificationInterface
 *
 * Defines the contract for the Specification pattern to evaluate domain candidates and compose rules.
 */
interface SpecificationInterface
{
    /**
     * Evaluates whether a candidate satisfies the specification criteria.
     *
     * @param array<string, mixed> $candidate
     * @return bool
     */
    public function isSatisfiedBy(array $candidate): bool;

    /**
     * Combines this specification with another via a logical AND.
     */
    public function and(SpecificationInterface $other): SpecificationInterface;

    /**
     * Combines this specification with another via a logical OR.
     */
    public function or(SpecificationInterface $other): SpecificationInterface;

    /**
     * Negates this specification via a logical NOT.
     */
    public function not(): SpecificationInterface;
}
