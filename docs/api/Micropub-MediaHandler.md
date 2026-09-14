# MediaHandler
**Namespace:** `Indieinabox\Micropub`

Class MediaHandler

Handles file uploads to the Micropub media endpoint (/micropub/media).

## Methods

### handleUpload()
`public static function handleUpload(Indieinabox\Site $site, array $file, ?callable $mover = null): array`

Processes an uploaded media file, moves it to the media storage, and returns its public URL.

@param Site $site Global site configuration.
@param array<string, mixed> $file Uploaded file information ($_FILES['file']).
@param ?callable $mover Optional callback to move uploaded file: fn(string $src, string $dst): bool.
@return array{status: int, headers?: array<string, string>, error?: string, error_description?: string}
