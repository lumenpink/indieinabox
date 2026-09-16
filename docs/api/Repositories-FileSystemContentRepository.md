# FileSystemContentRepository
**Namespace:** `Indieinabox\Repositories`

Filesystem-backed implementation of ContentRepositoryInterface.

## Properties

### `private string $contentBaseDir`

### `private string $defaultLang`

## Methods

### __construct()
`public function __construct(?string $contentBaseDir = null, ?Indieinabox\Site\Site $site = null)`

### resolveTargetDirectory()
`public function resolveTargetDirectory(string $kind, ?string $lang = null, ?string $year = null, ?string $month = null): string`

### generateUniqueSlug()
`public function generateUniqueSlug(string $kind, string $baseSlug, ?string $lang = null, ?string $year = null, ?string $month = null): string`

### buildFrontmatterMarkdown()
`public function buildFrontmatterMarkdown(array $frontmatter, string $body): string`

### parseFrontmatterMarkdown()
`public function parseFrontmatterMarkdown(string $rawContent): array`

### save()
`public function save(string $kind, string $slug, string $content, array $frontmatter = [], ?string $lang = null, ?string $year = null, ?string $month = null): string`

### findByPath()
`public function findByPath(string $filepath): ?string`

### delete()
`public function delete(string $filepath): bool`

### exists()
`public function exists(string $filepath): bool`

### scan()
`public function scan(string $dir): array`
