# Themes & Official Starter Packages

IndieInABox provides a flexible, modular theme system with strict static compilation. Themes define the HTML templates, CSS styles, and visual identity of the generated site while guaranteeing 100% static output for web, Gemini, and Gopher.

---

## 📦 Official Starter Themes

IndieInABox ships with three official starter theme packages located in `themes/`:

### 1. Cyberpunk Dark (`cyberpunk-dark`)
* **Aesthetic**: Retro-futuristic dark terminal theme inspired by cyberpunk classics and terminal hacker decks.
* **Palette**: Neon Phosphor Green (`#00ff66`), Cyber Cyan (`#00f0ff`), Hot Pink (`#ff0055`), and Dark Void (`#0a0c10`).
* **Features**: Monospaced typography, glowing accents, high contrast meeting WCAG AAA standards, and clean code blocks.

### 2. Minimal Reader (`minimal-reader`)
* **Aesthetic**: Distraction-free, typography-focused editorial experience designed for long-form reading.
* **Palette**: Warm Cream (`#faf9f6`), Charcoal Ink (`#24292e`), Editorial Blue (`#0969da`), and Slate Gray (`#57606a`).
* **Features**: Serif typography, generous whitespace, focused margin layout, and serene readability.

### 3. Digital Garden (`digital-garden`)
* **Aesthetic**: Botanical and bi-directional knowledge repository reflecting interconnected thoughts, growth stages, and evergreen notes.
* **Palette**: Garden White (`#f9f8f3`), Forest Deep (`#2c352e`), Evergreen Moss (`#2e6930`), and Terracotta (`#b85433`).
* **Features**: Growth stage indicators (seedling, budding, evergreen), interconnected backlinks, and botanical card layouts.

---

## ⚙️ The `theme.json` Specification

Every theme must include a `theme.json` manifest at its root directory. This manifest informs IndieInABox of the theme's identity, version, author, and layout structure:

```json
{
  "name": "Cyberpunk Dark",
  "slug": "cyberpunk-dark",
  "version": "1.0.0",
  "description": "Retro-futuristic terminal aesthetic with neon phosphor accents.",
  "author": "IndieInABox Core",
  "license": "MIT",
  "preview_colors": [
    "#0a0c10",
    "#00ff66",
    "#00f0ff",
    "#ff0055"
  ],
  "templates": {
    "home": "views/home.php",
    "page": "views/page.php",
    "includes": {
      "head": "views/includes/head.php",
      "header": "views/includes/header.php",
      "footer": "views/includes/footer.php"
    }
  }
}
```

### Manifest Fields
* `name` *(string, required)*: Human-readable theme display name.
* `slug` *(string, optional)*: Unique slug identifier for the theme.
* `version` *(string, required)*: SemVer version string (e.g. `1.0.0`).
* `description` *(string, optional)*: Overview of the theme's design and intent.
* `author` *(string, optional)*: Author name or organization.
* `preview_colors` *(array of hex strings, optional)*: Palette colors showcased in the Admin Cockpit cards.
* `templates` *(object, optional)*: Mapping of template names to relative paths within the theme folder. All paths are validated against directory traversal attacks (`..`).

---

## 📁 Theme Directory Structure

A complete IndieInABox theme has the following file structure:

```
themes/my-theme/
├── theme.json               # Required manifest
├── static/                  # Static assets copied directly to output
│   └── css/
│       └── style.css
└── views/                   # PHP view templates (rendered statically at build time)
    ├── home.php             # Homepage and post index
    ├── page.php             # Single post / page view
    └── includes/            # Reusable partials
        ├── head.php         # <head> metadata, scripts, stylesheets
        ├── header.php       # Global top navigation bar
        └── footer.php       # Semantic footer & h-card
```

---

## ⚡ 1-Click Installation & Activation

In the Admin Cockpit (`/admin/config` under **01 General -> Theme Selection**):

1. **Card Grid**: All official starter themes are showcased as interactive cards with color palette swatches, version badges, and descriptions.
2. **1-Click Install & Activate**: Clicking **⚡ Install & Activate** automatically copies the theme into your active themes directory and sets it as the active theme.
3. **Standalone ZIP Package Download**: Every official theme card includes a **⬇ Download ZIP** button (`/admin/theme-download?slug=...`) to package and download standalone portable archives for distribution or offline customization.
