# FileUtils
**Namespace:** `Indieinabox\Support`

Class FileUtils

Provides filesystem traversal, directory cleanup, and recursive array manipulation utilities.

## Methods

### recursiveKsort()
`public static function recursiveKsort(array $array): void`

Recursively sorts an associative array by keys.

@param array<string, mixed> $array
@return void

### getDirContents()
`public static function getDirContents(string $dir, array $results = []): array`

Recursively gets directory contents.

@param string $dir
@param array<int, string> $results
@return array<int, string>

### recursiveRmdir()
`public static function recursiveRmdir(string $dir, bool $keepRootDir = false): bool`

Recursively deletes a directory and its contents.

@param string $dir
@param bool $keepRootDir
@return bool
@throws \RuntimeException
