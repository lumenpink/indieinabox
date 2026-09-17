# SpecificationInterface
**Namespace:** `Indieinabox\Specifications\Contracts`

Interface SpecificationInterface

Defines the contract for the Specification pattern to evaluate domain candidates and compose rules.

## Methods

### isSatisfiedBy()
`abstract public function isSatisfiedBy(array $candidate): bool`

Evaluates whether a candidate satisfies the specification criteria.

@param array<string, mixed> $candidate
@return bool

### and()
`abstract public function and(Indieinabox\Specifications\Contracts\SpecificationInterface $other): Indieinabox\Specifications\Contracts\SpecificationInterface`

Combines this specification with another via a logical AND.

### or()
`abstract public function or(Indieinabox\Specifications\Contracts\SpecificationInterface $other): Indieinabox\Specifications\Contracts\SpecificationInterface`

Combines this specification with another via a logical OR.

### not()
`abstract public function not(): Indieinabox\Specifications\Contracts\SpecificationInterface`

Negates this specification via a logical NOT.
