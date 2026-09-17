# DateRangeSpecification
**Namespace:** `Indieinabox\Specifications\Content`

Specification matching content within a date/time range.

## Properties

### `private ?DateTimeImmutable $from`

### `private ?DateTimeImmutable $to`

## Methods

### __construct()
`public function __construct(DateTimeInterface|string|null $from = null, DateTimeInterface|string|null $to = null)`

### isSatisfiedBy()
`public function isSatisfiedBy(array $candidate): bool`

@param array<string, mixed> $candidate

### parseDate()
`private function parseDate(DateTimeInterface|string|null $date): ?DateTimeImmutable`

### and()
`public function and(Indieinabox\Specifications\Contracts\SpecificationInterface $other): Indieinabox\Specifications\Contracts\SpecificationInterface`

### or()
`public function or(Indieinabox\Specifications\Contracts\SpecificationInterface $other): Indieinabox\Specifications\Contracts\SpecificationInterface`

### not()
`public function not(): Indieinabox\Specifications\Contracts\SpecificationInterface`
