# SEO and Meta Tags Documentation

## 1. Page Architecture and Information Placement

To protect the **TTFB** (Time to First Byte) and ensure fast visual rendering for the user, the code should be strategically divided between the top (`<head>`) and the bottom (`<body>`) of the page.

* **In `<head>` (Top):** Place only the standard meta tags and the **Open Graph / Twitter Card** tags. They are lightweight and need to be read immediately by social media bots (which do not scroll the page).
* **At the end of `<body>` (Bottom):** Place the **JSON-LD** block. Since it can be heavy, placing it before the closing `</body>` tag ensures the browser draws the screen for the user before processing this chunk of structured data.

---

## 2. Image Dimension Standards

Since you will be using a two-color dithered identity, use **GIF** files in the blog body to keep it lightweight. However, for preview meta tags (`og:image` and JSON-LD), strictly use **PNG (PNG-8)** to prevent bots from rejecting or breaking the format.

### Image Specifications Table

| Channel / Format | Aspect Ratio | Recommended Dimension | Objective / Use |
| --- | --- | --- | --- |
| **Open Graph / Twitter** | 1.91:1 | **1200 x 630 px** | Preview cards on WhatsApp, LinkedIn, X, etc. |
| **JSON-LD (Widescreen)** | 16:9 | **1920 x 1080 px** | Google requirement (Minimum 1200px width for Discover). |
| **JSON-LD (Classic)** | 4:3 | **1440 x 1080 px** | Alternative Google search layouts. |
| **JSON-LD (Square)** | 1:1 | **1080 x 1080 px** | Thumbnails and mobile searches. |

> ⚠️ **Golden Rule for Dithering:** Ensure high contrast in the silhouettes of the artworks so Google's computer vision AI can understand the object. Always use a descriptive `alt=""` attribute in the HTML to compensate for the artistic stylization.

---

## 3. Implementation Template (Ready Code)

Below is the exact skeleton that your template system should generate for each blog post. Dynamic values are represented inside curly braces `{}`.

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{Post Title} | DevLumen Blog</title>

    <meta name="description" content="{Post summary up to 160 characters.}">
    <link rel="canonical" href="{POST_ABSOLUTE_URL}">

    <meta property="og:site_name" content="DevLumen Blog" />
    <meta property="og:type" content="article" />
    <meta property="og:title" content="{Post Title}" />
    <meta property="og:description" content="{Post summary.}" />
    <meta property="og:url" content="{POST_ABSOLUTE_URL}" />
    <meta property="og:image" content="{IMAGE_URL_1200x630.png}" />
    <meta property="article:published_time" content="{DATE_ISO_8601_EX_2026-07-01T09:00:00-03:00}" />
    <meta property="article:author" content="Lumen" />

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{Post Title}">
    <meta name="twitter:description" content="{Post summary.}">
    <meta name="twitter:image" content="{IMAGE_URL_1200x630.png}">

</head>
<body>

    <main>
        <article>
            <h1>{Post Title}</h1>
            <img src="{INTERNAL_IMAGE.gif}" alt="{Detailed text description of the dithered image}">
        </article>
    </main>

    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "BlogPosting",
      "mainEntityOfPage": {
        "@type": "WebPage",
        "@id": "{POST_ABSOLUTE_URL}"
      },
      "headline": "{Post Title}",
      "description": "{Post summary.}",
      "image": [
        "{IMAGE_URL_1920x1080_16x9.png}",
        "{IMAGE_URL_1440x1080_4x3.png}",
        "{IMAGE_URL_1080x1080_1x1.png}"
      ],
      "datePublished": "{PUBLICATION_DATE_ISO_8601}",
      "dateModified": "{MODIFICATION_DATE_ISO_8601_UPDATE_WHENEVER_EDITED}",
      "author": {
        "@type": "Person",
        "name": "Lumen",
        "jobTitle": "Software Engineer",
        "url": "https://devlumen.com.br/about"
      },
      "publisher": {
        "@type": "Organization",
        "name": "DevLumen",
        "logo": {
          "@type": "ImageObject",
          "url": "https://devlumen.com.br/assets/images/logo.png"
        }
      }
    }
    </script>
</body>
</html>
```

---

## 4. Homologation Checklist (Testing)

Before deploying to production, validate the implementation by running the URL through two free tools:

1. **Schema Validator (schema.org):** To ensure the JSON-LD injected in the footer has no syntax errors (like missing commas).
2. **Google Rich Results Test:** To ensure Google's bot can read the three image aspect ratios and validate the post for Google Discover.
3. **WhatsApp Cache Trick:** During sharing tests, if you update the dithered image and WhatsApp does not show the change, send the link with the `?v=1` parameter at the end to force their cache to update.
