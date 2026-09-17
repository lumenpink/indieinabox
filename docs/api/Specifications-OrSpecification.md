# OrSpecification
**Namespace:** `Indieinabox\Specifications`

Composite specification representing a logical OR of two specifications.

## Properties

### `private Indieinabox\Specifications\Contracts\SpecificationInterface $left`

### `private Indieinabox\Specifications\Contracts\SpecificationInterface $right`

## Methods

### __construct()
`public function __construct(Indieinabox\Specifications\Contracts\SpecificationInterface $left, Indieinabox\Specifications\Contracts\SpecificationInterface $right)`

### isSatisfiedBy()
`public function isSatisfiedBy(array $candidate): bool`

@param array<string, mixed> $candidate

### and()
`public function and(Indieinabox\Specifications\Contracts\SpecificationInterface $other): Indieinabox\Specifications\Contracts\SpecificationInterface`

### or()
`public function or(Indieinabox\Specifications\Contracts\SpecificationInterface $other): Indieinabox\Specifications\Contracts\SpecificationInterface`

### not()
`public function not(): Indieinabox\Specifications\Contracts\SpecificationInterface`
