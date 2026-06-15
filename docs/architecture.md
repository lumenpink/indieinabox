# Project Architecture

This document describes the high-level architecture and folder structure of the Indieinabox static site generator.

## Build Pipeline Flow

Indieinabox is a lightweight static site generator. The build pipeline is executed via CLI by running `build.php`. Below is a visualization of the pipeline flow:

```mermaid
graph TD
    A[Start Build] --> B[Load config.yml Settings]
    B --> C[Instantiate Site & Configure Paths/Options]
    C --> D[Clean public/ Directory]
    D --> E[Scan content/ Folder]
    E --> F[Process MD Files: YAML Frontmatter + Markdown]
    F --> G[Render PHP Templates in resources/views/]
    G --> H[Apply Post-processing: Minify/Beautify]
    H --> I[Generate index.html Files in public/]
    I --> J[Generate RSS/Atom Feed if applicable]
    J --> K[Copy js/css Assets from resources/views]
    K --> L[Copy Static Files from resources/static]
    L --> M[Build Complete]
```

## Directory Structure

Here is a breakdown of the workspace layout and its main contents:

- **`app/`**: Object-oriented, namespaced code representing generator components (Page, Site, etc.) mapped to the `Indieinabox\` namespace under PSR-4.
  - **`functions/`**: Procedural code containing fallback utility functions and file writers.
- **`bootstrap/`**: Application bootstrapper.
  - **`app.php`**: Registers the autoloader and procedural helpers/data files.
- **`content/`**: Contains the input Markdown and plain text source files representing your content. They are parsed and structured hierarchically.
- **`data/`**: PHP arrays acting as dynamic configuration/translation tables (e.g., Unicode character mappings, translation tables, international localized strings).
- **`build.php`**: Entry point orchestrating static site generation.
- **`resources/`**: Frontend design assets and templates.
  - **`views/`**: Contains layout layouts, headers, and footer inclusions (PHP/HTML templates) used to format the visual style of pages.
  - **`static/`**: Contains static assets that are copied directly to the output directory.
- **`_theme/`**: Front-end build tools (PostCSS, Webpack) and source assets.
- **`public/`**: Generated static HTML pages and compiled assets written here at the end of the build pipeline. This is the output folder to deploy.
- **`docs/`**: Documentation files describing the codebase structure and roadmap.
- **`tests/`**: Unit and integration test suites using Pest PHP.
