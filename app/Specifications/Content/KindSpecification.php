<?php

declare(strict_types=1);

namespace Indieinabox\Specifications\Content;

use Indieinabox\Specifications\CompositeSpecification;

/**
 * Specification matching content by kind (e.g. article, note, bookmark, reply).
 */
class KindSpecification extends CompositeSpecification
{
    /** @var array<int, string> */
    private array $kinds;

    /**
     * @param string|array<int, string> $kinds Single kind or array of acceptable kinds.
     */
    public function __construct(string|array $kinds)
    {
        $this->kinds = array_map('strtolower', is_array($kinds) ? $kinds : [$kinds]);
    }

    /**
     * @param array<string, mixed> $candidate
     */
    #[\Override]
    public function isSatisfiedBy(array $candidate): bool
    {
        $candidateKind = strtolower((string) ($candidate['kind'] ?? ''));
        return in_array($candidateKind, $this->kinds, true);
    }
}
