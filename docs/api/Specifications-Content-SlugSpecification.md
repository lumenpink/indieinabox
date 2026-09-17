# SlugSpecification
**Namespace:** `Indieinabox\Specifications\Content`

Specification matching content by slug.

## Properties

### `private string $slug`

## Methods

### __construct()
`public function __construct(string $slug)`

### isSatisfiedBy()
`public function isSatisfiedBy(array $candidate): bool`

@param array<string, mixed> $candidate

### and()
`public function and(Indieinabox\Specifications\Contracts\SpecificationInterface $other): Indieinabox\Specifications\Contracts\SpecificationInterface`

### or()
`public function or(Indieinabox\Specifications\Contracts\SpecificationInterface $other): Indieinabox\Specifications\Contracts\SpecificationInterface`

### not()
`public function not(): Indieinabox\Specifications\Contracts\SpecificationInterface`
