# Pages
**Namespace:** `Indieinabox`

@extends ArrayObject<string, Page>

## Properties

### `public array $pages`

@var array<string, Page>

## Methods

### __construct()
`public function __construct(array $pages = [])`

@param array<string, Page> $pages

### add()
`public function add(mixed $page, ?string $id = null): void`

@param Page|array<string, mixed> $page
@param string|null $id

### all()
`public function all(): array`

@return array<string, Page>

### get()
`public function get(string $id): ?Indieinabox\Page`

@param string $id
@return Page|null

### offsetExists()
`public function offsetExists(?mixed $key)`

### offsetGet()
`public function offsetGet(?mixed $key)`

### offsetSet()
`public function offsetSet(?mixed $key, ?mixed $value)`

### offsetUnset()
`public function offsetUnset(?mixed $key)`

### append()
`public function append(?mixed $value)`

### getArrayCopy()
`public function getArrayCopy()`

### count()
`public function count()`

### getFlags()
`public function getFlags()`

### setFlags()
`public function setFlags(int $flags)`

### asort()
`public function asort(int $flags = 0)`

### ksort()
`public function ksort(int $flags = 0)`

### uasort()
`public function uasort(callable $callback)`

### uksort()
`public function uksort(callable $callback)`

### natsort()
`public function natsort()`

### natcasesort()
`public function natcasesort()`

### unserialize()
`public function unserialize(string $data)`

### serialize()
`public function serialize()`

### __serialize()
`public function __serialize()`

### __unserialize()
`public function __unserialize(array $data)`

### getIterator()
`public function getIterator()`

### exchangeArray()
`public function exchangeArray(object|array $array)`

### setIteratorClass()
`public function setIteratorClass(string $iteratorClass)`

### getIteratorClass()
`public function getIteratorClass()`

### __debugInfo()
`public function __debugInfo()`
