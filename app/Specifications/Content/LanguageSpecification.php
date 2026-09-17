<?php

declare(strict_types=1);

namespace Indieinabox\Specifications\Content;

use Indieinabox\Specifications\CompositeSpecification;

/**
 * Specification matching content by language.
 */
class LanguageSpecification extends CompositeSpecification
{
    private string $language;

    public function __construct(string $language)
    {
        $this->language = strtolower(trim($language));
    }

    /**
     * @param array<string, mixed> $candidate
     */
    public function isSatisfiedBy(array $candidate): bool
    {
        $lang = $candidate['lang'] ?? ($candidate['frontmatter']['lang'] ?? ($candidate['frontmatter']['language'] ?? ''));
        return strtolower(trim((string) $lang)) === $this->language;
    }
}
