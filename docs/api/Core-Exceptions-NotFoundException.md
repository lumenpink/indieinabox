# NotFoundException
**Namespace:** `Indieinabox\Core\Exceptions`

Exception thrown when an entry or service is not found in the container.

## Properties

### `protected mixed $message`

### `protected mixed $code`

### `protected string $file`

### `protected int $line`

## Methods

### __construct()
`public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)`

### __wakeup()
`public function __wakeup()`

### getMessage()
`final public function getMessage(): string`

### getCode()
`final public function getCode()`

### getFile()
`final public function getFile(): string`

### getLine()
`final public function getLine(): int`

### getTrace()
`final public function getTrace(): array`

### getPrevious()
`final public function getPrevious(): ?Throwable`

### getTraceAsString()
`final public function getTraceAsString(): string`

### __toString()
`public function __toString(): string`
