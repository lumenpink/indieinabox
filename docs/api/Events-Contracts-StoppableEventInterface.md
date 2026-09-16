# StoppableEventInterface
**Namespace:** `Indieinabox\Events\Contracts`

Interface for events whose propagation can be halted.

## Methods

### isPropagationStopped()
`abstract public function isPropagationStopped(): bool`

Determines whether listener execution should stop.

### stopPropagation()
`abstract public function stopPropagation(): void`

Halts further listener propagation.
