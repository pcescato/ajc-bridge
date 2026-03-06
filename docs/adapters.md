# Static Site Generator Adapters

AJC Bridge supports multiple static site generators through adapter pattern. Each adapter handles SSG-specific:
- Directory structure
- Front matter format
- File naming
- Image optimization
- Content conversion

Choose your SSG in **Settings** → **General** → **SSG Type**.

---

## Hugo

**Best for:** Fast static sites, large content libraries, theme variety

### Directory Structure

```
hugo-site/
├─ content/
│  └─ posts/
│     ├─ 2026-01-15-my-post.md
│     └─ 2026-01-20-another.md
├─ static/
│  └─ images/
│     ├─ 1/  (post ID folders)
│     │  ├─ featured.webp
│     │  ├─ featured.avif
│     │  └─ screenshot.webp
│     └─ 2/
│        └─ featured.avif
└─ .github/workflows/
   └─ build.yml
```

### File Format

**File naming:**
```
YYYY-MM-DD-slug.md
2026-01-15-my-first-post.md
2026-02-17-announcements.md
```

**Front matter (YAML):**
```yaml
---
title: "My First Post"
date: 2026-01-15T14:30:00Z
author: "Your Name"
slug: "my-first-post"
categories: ["WordPress", "Hugo"]
tags: ["cms", "tutorial"]
image: "featured.webp"
---

Your post content here...
```

**Date format:**
- ISO 8601: `2026-01-15T14:30:00Z`
- Automatically set from WordPress publish date
- Timezone aware

### Image Handling

**Featured image:**
- Stored: `static/images/{post_id}/featured.webp`
- Also generated: `featured.avif`
- Original: Kept as backup

**Content images:**
- Extracted from post body
- Stored: `static/images/{post_id}/image-name.webp`
- Also generated: `.avif` versions

**Image in markdown:**
```markdown
![Screenshot](/images/1/screenshot.webp)
```

### Setup in WordPress

1. Go to **Settings** → **General**
2. **SSG Type:** Select "Hugo"
3. **Repository:** `username/hugo-blog`
4. **Branch:** `main` (or `master`)
5. **Site URL:** `https://yourblog.com`

### Theme Compatibility

Hugo works with thousands of themes. Ensure:
- Front matter format matches theme expectations (usually YAML)
- Content directory is `content/posts/` (configurable in `hugo.toml`)
- Images accessible from `static/images/`

**Recommended themes:**
- **PaperMod** — Minimal, fast
- **Blowfish** — Modern, feature-rich
- **LoveIt** — Beautiful design

### Configuration file check

In your Hugo site, verify `hugo.toml` includes:

```toml
[module]
imports = []

[content]
path = "content/posts"

[outputs]
page = ["HTML"]
home = ["HTML"]
```

---

## Astro

**Best for:** Modern content collections, hybrid rendering, `.mdx` support

### Directory Structure

```
astro-site/
├─ src/
│  └─ content/
│     └─ posts/
│        ├─ my-post.mdx
│        └─ another-post.mdx
├─ public/
│  └─ image/
│     ├─ featured.avif
│     ├─ featured.webp
│     └─ screenshot.webp
└─ .github/workflows/
   └─ deploy.yml
```

### File Format

**File naming (NO date prefix):**
```
slug.mdx
my-first-post.mdx
announcements.mdx
```

**Front matter (YAML):**
```yaml
---
title: "My First Post"
pubDate: 2026-01-15T14:30:00Z
updated: 2026-01-16T10:00:00Z
slug: "my-first-post"
draft: false
categories: ["WordPress", "Astro"]
tags: ["cms", "tutorial"]
description: "Short post excerpt"
image: /image/featured.avif
---

Your post content here using Astro components...
```

**Key differences from Hugo:**
- No date in filename
- Uses `pubDate` (not `date`)
- Optional `updated` field
- `draft: true/false` (boolean)
- Support for `.mdx` (Markdown + JSX)

### Image Handling

**Featured image:**
- Stored: `public/image/featured.avif` (flat structure)
- Also generated: `featured.webp`
- **No post ID folders** (Astro uses flat structure)

**Content images:**
- Stored: `public/image/image-name.avif`
- Also generated: `.webp`
- All images in one folder (flat)

**Image in markdown:**
```markdown
![Screenshot](/image/screenshot.avif)
```

### Setup in WordPress

1. Go to **Settings** → **General**
2. **SSG Type:** Select "Astro"
3. **Repository:** `username/astro-blog`
4. **Branch:** `main`
5. **Site URL:** `https://yourblog.com`

### Content Collections Setup

In your Astro project, ensure `src/content/config.ts` includes:

```typescript
import { defineCollection, z } from 'astro:content';

const posts = defineCollection({
  schema: z.object({
    title: z.string(),
    pubDate: z.coerce.date(),
    updated: z.coerce.date().optional(),
    slug: z.string(),
    draft: z.boolean().default(false),
    categories: z.array(z.string()).optional(),
    tags: z.array(z.string()).optional(),
    description: z.string().optional(),
    image: z.string().optional(),
  }),
});

export const collections = { posts };
```

### Rendering

In your Astro layout (`src/layouts/Post.astro`):

```astro
---
import { getCollection } from 'astro:content';
import type { CollectionEntry } from 'astro:content';

interface Props {
  entry: CollectionEntry<'posts'>;
}

const { entry } = Astro.props;
const { title, pubDate, slug, image, tags } = entry.data;
---

<article>
  <h1>{title}</h1>
  <time>{pubDate.toDateString()}</time>
  {image && <img src={image} alt={title} />}
  <slot />
</article>
```

### Hybrid Rendering

Astro supports mixing static and dynamic content:

```astro
---
export const prerender = true; // Static generation
---
```

All AJC Bridge content is pre-rendered to `.html`.

---

## Jekyll

**Best for:** GitHub Pages default, simple setups, Ruby ecosystem

### Directory Structure

```
jekyll-site/
├─ _posts/
│  ├─ 2026-01-15-my-post.markdown
│  └─ 2026-01-20-another.markdown
├─ assets/
│  └─ images/
│     ├─ featured.webp
│     └─ featured.avif
└─ .github/workflows/
   └─ pages.yml
```

### File Format

**File naming (date-prefixed):**
```
YYYY-MM-DD-slug.markdown
2026-01-15-my-first-post.markdown
```

**Front matter (YAML):**
```yaml
---
layout: post
title: "My First Post"
date: 2026-01-15 14:30:00 +0000
author: "Your Name"
categories: ["WordPress", "Jekyll"]
tags: ["cms", "tutorial"]
image: /assets/images/featured.webp
---

Your post content here...
```

### Image Handling

**Featured image:**
- Stored: `assets/images/featured.webp`
- Also generated: `featured.avif`
- Reference in markdown: `/assets/images/featured.webp`

**Content images:**
- Stored: `assets/images/content-image.webp`
- Also generated: `.avif`

### Setup in WordPress

1. Go to **Settings** → **General**
2. **SSG Type:** Select "Jekyll"
3. **Repository:** `username/jekyll-blog`
4. **Branch:** `main` (or `gh-pages`)
5. **Site URL:** `https://username.github.io`

### Jekyll Configuration

In `_config.yml`:

```yaml
title: My Blog
description: Powered by WordPress + AJC Bridge

permalink: /:year/:month/:day/:title/

plugins:
  - jekyll-feed
  - jekyll-sitemap
```

### GitHub Pages Deployment

Jekyll builds automatically on GitHub when you push to your branch.

Configure in repo **Settings** → **Pages**:
- **Source:** Deploy from branch
- **Branch:** `main` (or `gh-pages`)

---

## Comparison

| Feature | Hugo | Astro | Jekyll |
|---------|------|-------|--------|
| Date in filename | ✅ Yes | ❌ No | ✅ Yes |
| Front matter | YAML/TOML | YAML | YAML |
| Image structure | Per-post folders | Flat | Flat |
| Content format | Markdown | `.mdx` | Markdown |
| Learning curve | Low | Medium | Low |
| Performance | Excellent | Excellent | Good |
| Theme variety | Huge | Growing | Moderate |
| GitHub Pages | ✅ Works | ✅ Works | ✅ Default |

---

## Image Optimization

All adapters generate optimized images:

**Automatic conversions:**
- Original → WebP (better compression)
- Original → AVIF (best quality, newer browsers)
- Responsive sizes: 320px, 640px, 1280px

**Example output for `featured.jpg`:**
```
featured.webp (320px, 640px, 1280px)
featured.avif (320px, 640px, 1280px)
featured.jpg (original preserved)
```

**Markup in your SSG:**
```html
<picture>
  <source srcset="/image/featured-1280.avif" media="(min-width: 1280px)" type="image/avif">
  <source srcset="/image/featured-640.avif" media="(min-width: 640px)" type="image/avif">
  <source srcset="/image/featured.avif" type="image/avif">
  <img src="/image/featured.webp" alt="Featured">
</picture>
```

---

## Front Matter Customization

Each adapter uses default templates, but you can customize:

1. Go to **Settings** → **General** → **Front Matter Template**
2. Edit the template for your SSG
3. Use placeholders: `{{title}}`, `{{date}}`, `{{slug}}`, etc.

See [Configuration](configuration.md#custom-front-matter) for full details.

---

**Next:** [Troubleshooting →](troubleshooting.md)
