# TagSpecification
**Namespace:** `Indieinabox\Specifications\Content`

Specification matching content by tags.

## Properties

### `private array $tags`

/** @var array<int, string> */

### `private bool $matchAll`

## Methods

### __construct()
`public function __construct(array|string $tags, bool $matchAll = false)`

@param string|array<int, string> $tags Tag or tags to search for.
@param bool $matchAll If true, candidate must contain all specified tags; if false, any tag matches.

### isSatisfiedBy()
`public function isSatisfiedBy(array $candidate): bool`

@param array<string, mixed> $candidate

### and()
`public function and(Indieinabox\Specifications\Contracts\SpecificationInterface $other): Indieinabox\Specifications\Contracts\SpecificationInterface`

### or()
`public function or(Indieinabox\Specifications\Contracts\SpecificationInterface $other): Indieinabox\Specifications\Contracts\SpecificationInterface`

### not()
`public function not(): Indieinabox\Specifications\Contracts\SpecificationInterface`
