# Coding Style Guidelines

This document outlines the coding standards and styling guidelines for the IndieInABox project. All contributors must adhere to these guidelines to maintain a clean, readable, and consistent codebase.

## 1. Line Length Limit

*   **Strict Limit:** All source files, configuration files, and test files owned by the project must have a maximum line length of **120 characters**.
*   **Exceptions:**
    *   Third-party vendor libraries (e.g., code under `app/functions/parsedown.php` or `app/Yaml.php`) are exempted to maintain compatibility with upstream changes.
    *   Automatically compiled files (e.g., `indieinabox.php`) are exempted.
*   **Best Practices:**
    *   Break long strings into multiple concatenated lines.
    *   Wrap long logical expressions, arrays, and function definitions using multi-line syntax.

## 2. PHP Standard Recommendations

The codebase generally adheres to the standard **PSR-12** formatting guidelines:

### Indentation and Spacing
*   Use **4 spaces** for indentation. Do not use tabs.
*   Lines must not contain trailing whitespace (spaces or tabs at the end of lines).
*   All files must end with exactly one trailing newline (`\n`) character. Useless trailing empty lines and spaces at the end of files must be removed.

### Strict Types
*   Every PHP file must declare strict types at the very top of the file:
    ```php
    <?php

    declare(strict_types=1);
    ```

### Class and Member Naming
*   **Classes & Namespaces:** Must use `PascalCase` (e.g., `LanguageProcessor`, `FileProcessor`).
*   **Methods & Properties:** Must use `camelCase` (e.g., `determineLayout()`, `urlTranslations`).
*   **Constants:** Must be declared in `UPPER_CASE` with underscore separators (e.g., `DS`).
*   **Functions (legacy/global):** Use `camelCase` for new functions. Legacy global helper functions may use `lowercase` for backward compatibility.

## 3. Web & Assets (CSS/JS)

For themes and template resources:
*   CSS rules should use standard indentation and property wrapping (avoid putting entire rules/selectors on a single line).
*   Avoid inline styling; place styles in the theme's sass or stylesheet files.
