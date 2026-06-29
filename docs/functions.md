# Legacy Procedural Functions

The core generator pipeline incorporates several procedural functions under `app/functions/` to assist in scanning directories, localized formats, templates generation, and internationalization.

---

## 📂 Scanning & Routing (`app/functions/general.php`)

*   **`scan(string $dir)`**: Recursively scans the content directories (`content/`), skips hidden files and engine folders, calls `parse($path)` on Markdown documents, and adds the resulting `Page` objects to the `$pages` collection.
*   **`getDirContents(string $dir, array &$results)`**: Returns a flat array of absolute paths for all files inside a target directory.
*   **`slugize(string $text)`**: Converts accented/special string characters into lowercase, hyphen-separated slugs (safe for URLs).
*   **`utf8ToAscii(string $str)`**: Low-level helper that replaces UTF-8 multibyte characters with ASCII equivalents using translation tables loaded from `data/chars.php`.
*   **`recursiveRmdir(string $dir)`**: Deletes a directory and all of its contents recursively (used to wipe the `public/` output folder before building).

---

## 🛠️ Page Renderer (`app/functions/createhtml.php`)

*   **`createHTMLFile(Page $page)`**:
    - Verifies the page is not marked as `draft` in tags.
    - Generates target folder path inside `public/` mirroring the page slug.
    - Uses PHP output buffering (`ob_start()`) to include the page's requested template layout (e.g. `resources/views/page.php` or `resources/views/home.php`).
    - Performs HTML minification (via `minifyhtml()`) or beautification (via `beautifyhtml()`) based on site config and development flags.
    - Writes the processed markup to the destination `index.html` file.

---

## 📅 Localized Dates (`app/functions/date.php`)

*   **`localizeddate(Page|array $page)`**: Computes the localized date representation based on the page's language and timezone (`America/Sao_Paulo`). It returns:
    - `"long"`: Fully written date text (e.g., `"Sexta-feira, 15 de Junho de 2026"`).
    - `"iso"`: ISO-8601 string representation (used in `datetime` attributes).
    - Translates month and weekday names utilizing locales data defined in `data/intl.php`.

---

## 💬 Translation Engine (`app/functions/translate.php`)

*   **`translate(string $text, ?string $lang)`**: Translates a string key into the specified target language using the dictionary in `data/translations.php`. If a translation is missing, it dynamically appends the key into the dictionary file (so it can be translated later) and alphabetically sorts the file.
*   **Shorthand Aliases**:
  - `t(string $text, ?string $lang)`: Standard alias for `translate()`.
  - `ts(string $text)`: Translates a string and slugizes the result (useful for link paths).
  - `tl(string $text)`: Translates a string and converts it to lowercase.

---

## 🏷️ Post Classification (`app/functions/kind.php`)

*   **`kind(Page|array $page)`**: Determines the post "kind" (note, bookmark, article, like, reply, photo, etc.) based on the folder path or frontmatter configuration. Returns an array containing the `"kind"` slug and its `"localized"` name (mapped using dictionaries).

---

## 🏷️ Post Summary Feed (`app/functions/listposts.php`)

*   **`listposts()`**: Returns the HTML summary list of the latest 10 posts.
    - Filters out pages and generic notes using `removegeneric()`.
    - Sorts posts in descending chronological order using the parsed post dates.
    - Buffers the inclusion of `resources/views/includes/summary.php` for each item.
