# DateFormatter
**Namespace:** `Indieinabox\Support`

Class DateFormatter

Provides date/time formatting utilities, human-readable relative time (timeAgo),
and localized date translations.

## Properties

### `private static ?array $intlConfig`

### `private static ?array $originalDaysOfWeek`

### `private static ?array $originalMonths`

## Methods

### setConfig()
`public static function setConfig(?array $intl = null, ?array $daysOfWeek = null, ?array $months = null): void`

### timeAgo()
`public static function timeAgo(string|int $time): string`

Calculates a human-readable relative time string (e.g. "5 minutes ago").

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
