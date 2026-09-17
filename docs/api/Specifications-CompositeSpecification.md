# CompositeSpecification
**Namespace:** `Indieinabox\Specifications`

Abstract base class providing composition methods for specifications.

## Methods

### isSatisfiedBy()
`abstract public function isSatisfiedBy(array $candidate): bool`

@param array<string, mixed> $candidate

### and()
`public function and(Indieinabox\Specifications\Contracts\SpecificationInterface $other): Indieinabox\Specifications\Contracts\SpecificationInterface`

### or()
`public function or(Indieinabox\Specifications\Contracts\SpecificationInterface $other): Indieinabox\Specifications\Contracts\SpecificationInterface`

### not()
`public function not(): Indieinabox\Specifications\Contracts\SpecificationInterface`
