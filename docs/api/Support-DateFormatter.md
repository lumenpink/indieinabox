# DateFormatter
**Namespace:** `Indieinabox\Support`

Class DateFormatter

Formats relative timestamps, localized dates according to site intl settings,
and sorts collections chronologically.

## Methods

### timeAgo()
`public static function timeAgo(int $timestamp): string`

Returns a human-readable relative time string (e.g. "5 minutes ago").

@param int $timestamp
@return string

### localizeddate()
`public static function localizeddate(Indieinabox\Page\Page|array $page): array`

Formats a page's date into localized long and ISO strings.

@param Page|array<string, mixed> $page
@return array{long: string, iso: string}

### sortByDate()
`public static function sortByDate(array $pages): array`

Sorts pages by date descending.

@param array<int, array<string, mixed>|Page> $pages
@return array<int, array<string, mixed>|Page>
