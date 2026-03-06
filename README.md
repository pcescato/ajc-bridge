# AJC Bridge

[![WordPress](https://img.shields.io/badge/WordPress-6.9%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPLv3-green.svg)](https://www.gnu.org/licenses/gpl-3.0.html)

**Write once, publish everywhere.** WordPress as your headless CMS for static sites (Hugo, Astro, Jekyll) via GitHub.

Edit in WordPress (familiar Gutenberg interface), one-click sync to GitHub, automatic deployment to static hosting. Zero server runtime, optimized images (AVIF/WebP), full control.

---

## Why AJC Bridge?

🎯 **Multi-platform publishing** — WordPress, GitHub, dev.to, static sites  
🔒 **Production-ready** — Encrypted credentials, atomic commits, error handling  
⚡ **Performance** — Async processing, WebP/AVIF optimization, responsive images  
🎨 **Customizable** — Front Matter templates, per-post sync control  
🛡️ **SEO-friendly** — Smart canonical URLs, automatic redirects  
📊 **Monitoring** — Real-time sync status, detailed logs, one-click retry  

---

## Who is this for?

- **Developers** building JAMstack sites needing a user-friendly CMS
- **Content teams** familiar with WordPress publishing to static hosting
- **Tech writers** managing docs with WordPress → GitHub workflow
- **Solo creators** wanting WordPress editing + static site performance

---

## Table of Contents

- [Quick Start](#quick-start)
- [Features](#features)
- [Documentation](#documentation)
- [Examples](#examples)
- [Contributing](#contributing)
- [License](#license)

---

## Quick Start

**1. Install**
```bash
# Download latest release
wget https://github.com/pcescato/ajc-bridge/releases/latest/download/ajc-bridge.zip

# WordPress → Plugins → Upload → Activate
```

**2. Configure**
```
Settings → AJC Bridge
├─ GitHub Token (from GitHub Settings)
├─ Repository URL (your-username/your-repo)
└─ SSG Type (Hugo/Astro/Jekyll)
```

**3. Publish**
```
Write post → Click "Publish"
→ Syncs to GitHub
→ Deploys to static site (~2 min)
```

**[Detailed Installation Guide →](docs/installation.md)**

---

## Features

### Publishing Modes

- ✅ **WordPress only** — Traditional WP site
- ✅ **WordPress + dev.to** — Cross-posting syndication
- ✅ **WordPress + Static** — Headless CMS workflow
- ✅ **Static only** — WordPress as editor interface
- ✅ **Multi-platform** — All of the above simultaneously

### Static Site Generators

- ✅ **Hugo** — YAML/TOML front matter, date-prefixed files
- ✅ **Astro** — Content collections, .mdx support, ISO 8601 dates
- ✅ **Jekyll** — YAML front matter, Ruby-style conventions
- 🔄 **Eleventy** — Coming soon

### Content Processing

- ✅ **Gutenberg → Markdown** — Full block conversion
- ✅ **Image optimization** — AVIF/WebP, responsive sizes (320/640/1280px)
- ✅ **Front Matter** — Customizable per SSG
- ✅ **Code blocks** — Syntax highlighting preserved
- ✅ **Tables** — Markdown table conversion

### Developer Experience

- ✅ **Auto-updates** — GitHub releases integration
- ✅ **Async processing** — Action Scheduler (no blocking)
- ✅ **Logging** — Detailed sync logs, debug mode
- ✅ **Error handling** — Retry mechanism, status tracking
- ✅ **Atomic commits** — Markdown + images in single commit

---

## Documentation

- **[Installation](docs/installation.md)** — Step-by-step setup
- **[Configuration](docs/configuration.md)** — All settings explained
- **[Workflows](docs/workflows.md)** — Use cases & examples
- **[Adapters](docs/adapters.md)** — SSG-specific guides (Hugo/Astro/Jekyll)
- **[Troubleshooting](docs/troubleshooting.md)** — Common issues & solutions
- **[Architecture](docs/architecture.md)** — Technical deep-dive

---

## Examples

**Production sites using AJC Bridge:**

- [benchwiseunderflow.in](https://benchwiseunderflow.in) — Dual-language Astro deployment

---

## Installation

**From GitHub Release:**
1. Download `ajc-bridge-x.x.x.zip` from [Releases](https://github.com/pcescato/ajc-bridge/releases)
2. WordPress → Plugins → Upload Plugin
3. Activate

**Updates:**  
Automatic via GitHub releases (notification in WordPress admin).

**[Full installation guide →](docs/installation.md)**

---

## Contributing

We welcome contributions! See [CONTRIBUTING.md](CONTRIBUTING.md) for:
- Development setup
- Code standards
- Pull request process
- Testing guidelines

---

## License

GPL-2.0+ — See [LICENSE](LICENSE)

---

**Built with GitHub Copilot CLI** | [Read the build story →](https://dev.to)
