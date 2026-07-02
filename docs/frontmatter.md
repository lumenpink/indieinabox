# Front-matter Tags

Markdown files in IndieinaBox can include a YAML front-matter block at the top of the file to configure metadata and behavior for that specific page. 

## Supported Tags

- **`title`** (string): The title of the page. Default: `Untitled`.
- **`category`** (array): Categories the page belongs to.
- **`tags`** (array): Tags associated with the page.
- **`nick`** (string): The author's nickname (used in twtxt feeds). Default: `untitled`.
- **`noauthor`** (boolean): Set to `true` to hide author information for the page.
- **`kind`** (string): Defines the content type (e.g., `note`, `article`, `bookmark`, `page`). 
- **`layout`** (string): The view layout file used to render the page (e.g., `page`, `home`).
- **`maturity`** (string): Indicates the maturity state of a digital garden note (e.g., `seedling`, `budding`, `evergreen`).
- **`reliability`** (string): Indicates the reliability of the note (e.g., `certain`, `uncertain`).
- **`slug`** (string): Overrides the default generated URL slug.
- **`publish`** (boolean): Set to `false` to prevent the page from being compiled into the final site. Pages are compiled by default unless disabled.

## Menu Tags

Pages can be automatically added to the site's navigation menus using these tags:

- **`menu`** (string): Controls where the page link appears.
  - Omitted (or any unrecognized value): Displays in the footer menu (default behavior for generic pages).
  - `header`: Displays only in the header navigation.
  - `footer`: Displays only in the footer navigation.
  - `both`: Displays in both header and footer navigation.
  - `hide`: Hides the page from all menus.
- **`menu_order`** (integer): Defines the sorting order of the page in the menu. Lower numbers appear first. Items without `menu_order` are sorted alphabetically after ordered items.
