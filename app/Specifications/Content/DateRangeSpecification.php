<?php

declare(strict_types=1);

namespace Indieinabox\Specifications\Content;

use DateTimeImmutable;
use DateTimeInterface;
use Indieinabox\Specifications\CompositeSpecification;

/**
 * Specification matching content within a date/time range.
 */
class DateRangeSpecification extends CompositeSpecification
{
    private ?DateTimeImmutable $from;
    private ?DateTimeImmutable $to;

    public function __construct(
        DateTimeInterface|string|null $from = null,
        DateTimeInterface|string|null $to = null
    ) {
        $this->from = $this->parseDate($from);
        $this->to = $this->parseDate($to);
    }

    /**
     * @param array<string, mixed> $candidate
     */
    public function isSatisfiedBy(array $candidate): bool
    {
        $dateStr = $candidate['date'] ?? ($candidate['frontmatter']['date'] ?? null);
        if (!$dateStr) {
            return false;
        }

        $candidateDate = $this->parseDate($dateStr);
        if (!$candidateDate) {
            return false;
        }

        if ($this->from !== null && $candidateDate < $this->from) {
            return false;
        }

        if ($this->to !== null && $candidateDate > $this->to) {
            return false;
        }

        return true;
    }

    private function parseDate(DateTimeInterface|string|null $date): ?DateTimeImmutable
    {
        if ($date === null) {
            return null;
        }
        if ($date instanceof DateTimeImmutable) {
            return $date;
        }
        if ($date instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($date);
        }

        try {
            return new DateTimeImmutable((string) $date);
        } catch (\Throwable) {
            return null;
        }
    }
}
