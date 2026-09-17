# KindSpecification
**Namespace:** `Indieinabox\Specifications\Content`

Specification matching content by kind (e.g. article, note, bookmark, reply).

## Properties

### `private array $kinds`

/** @var array<int, string> */

## Methods

### __construct()
`public function __construct(array|string $kinds)`

@param string|array<int, string> $kinds Single kind or array of acceptable kinds.

### isSatisfiedBy()
`public function isSatisfiedBy(array $candidate): bool`

@param array<string, mixed> $candidate

### and()
`public function and(Indieinabox\Specifications\Contracts\SpecificationInterface $other): Indieinabox\Specifications\Contracts\SpecificationInterface`

### or()
`public function or(Indieinabox\Specifications\Contracts\SpecificationInterface $other): Indieinabox\Specifications\Contracts\SpecificationInterface`

### not()
`public function not(): Indieinabox\Specifications\Contracts\SpecificationInterface`
