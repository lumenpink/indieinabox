# Portable Export & Import Specification

Indieinabox provides full data portability and ownership through self-contained, portable export bundles. This allows users to easily backup, transfer, or restore their complete micro-publishing instance across different hosts or installations.

## Portable Bundle Format

An export archive (`.tar.gz` or `.zip`) contains all critical database records, authored content, uploaded media, and a cryptographically hashed manifest.

```
export-YYYY-MM-DD-HHMMSS.tar.gz
├── manifest.json
├── database.sqlite
├── content/
│   ├── notes/
│   ├── articles/
│   └── ...
└── public_media/
    └── uploads/
```

### Manifest Specification (`manifest.json`)

The manifest contains metadata, engine versions, export timestamps, and SHA-256 integrity checksums for every bundled file:

```json
{
  "format_version": "1.0",
  "generator": "indieinabox",
  "version": "0.1.0-dev.476",
  "created_at": "2026-09-21T10:00:00+00:00",
  "app_url": "https://example.com",
  "author_name": "Author",
  "contents": {
    "database": true,
    "content": true,
    "media": true
  },
  "files": [
    {
      "path": "database.sqlite",
      "sha256": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
      "size": 102400
    },
    {
      "path": "content/notes/example.md",
      "sha256": "4b227777d4dd1fc61c6f884f48641d02b4d121d3fd328cb08b5531fcacdabf8a",
      "size": 342
    }
  ]
}
```

## ExportService

Implemented in [`app/Services/ExportService.php`](file:///home/lumen/indieinabox/app/Services/ExportService.php) implementing [`ExportServiceInterface`](file:///home/lumen/indieinabox/app/Services/Contracts/ExportServiceInterface.php).

### Features
- **Deterministic Checksums**: Computes SHA-256 for all exported components.
- **Selective Bundling**: Supports options such as `skip_media` and `skip_content` for lightweight database-only exports.
- **Zero-Dependency Archiving**: Uses built-in PHP `PharData` for `.tar.gz` and `.tar` compression, with seamless fallback for environments without `ext-zip`.
