# NotSpecification
**Namespace:** `Indieinabox\Specifications`

Composite specification representing a logical NOT of a specification.

## Properties

### `private Indieinabox\Specifications\Contracts\SpecificationInterface $specification`

## Methods

### __construct()
`public function __construct(Indieinabox\Specifications\Contracts\SpecificationInterface $specification)`

### isSatisfiedBy()
`public function isSatisfiedBy(array $candidate): bool`

@param array<string, mixed> $candidate

### and()
`public function and(Indieinabox\Specifications\Contracts\SpecificationInterface $other): Indieinabox\Specifications\Contracts\SpecificationInterface`

### or()
`public function or(Indieinabox\Specifications\Contracts\SpecificationInterface $other): Indieinabox\Specifications\Contracts\SpecificationInterface`

### not()
`public function not(): Indieinabox\Specifications\Contracts\SpecificationInterface`
