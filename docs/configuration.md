# Configuration Guide

## Settings Overview

AJC Bridge configuration is organized into two tabs:

### Tab 1: General Settings

Define your publishing strategy, SSG type, and URLs.

### Tab 2: Credentials

Secure API tokens for GitHub and dev.to.

---

## General Settings

### Publishing Strategy

Choose how your content flows:

| Strategy | WordPress | GitHub | dev.to | Use Case |
|----------|-----------|--------|---------|----------|
| **wordpress_only** | Public | — | — | Traditional WordPress site |
| **wordpress_devto** | Public | — | Optional | WordPress + cross-post to dev.to |
| **github_only** | Admin-only | Public | — | Headless WordPress → GitHub |
| **devto_only** | Admin-only | — | Public | WordPress CMS → dev.to only |
| **dual_github_devto** | Admin-only | Public | Optional | Max reach: GitHub + dev.to |

**Admin-only WordPress** means:
- WordPress backend remains fully functional
- Frontend visitors see 301 redirect to actual site (GitHub Pages or dev.to)
- Contributors access WordPress normally through `/wp-admin`

### Repository Configuration

**Repository URL**
- Format: `username/repository`
- Example: `pcescato/my-blog`
- Determines where GitHub commits are pushed

**Branch**
- Default: `main`
- GitHub Pages: Use `gh-pages` if deployed there
- Custom: Any branch your deployment watches

**Site URL (GitHub)**
- Your deployed static site URL
- Example: `https://pcescato.github.io/my-blog`
- Used for canonical URLs and redirects

**Site URL (dev.to)**
- Your dev.to profile or canonical URL
- Example: `https://dev.to/pcescato`
- Used for canonical URL management in dual mode

### SSG Type

Choose your static site generator:

- **Hugo** → See [Adapters: Hugo](adapters.md#hugo)
- **Astro** → See [Adapters: Astro](adapters.md#astro)
- **Jekyll** → See [Adapters: Jekyll](adapters.md#jekyll)

Each has different:
- Front matter format (YAML/TOML)
- Directory structure
- File naming conventions

---

## Credentials Tab

### GitHub Personal Access Token

**How to generate:**

1. Go to GitHub → **Settings** → **Developer settings** → **Personal access tokens**
2. Select **Tokens (classic)** (not "Fine-grained tokens")
3. Click **Generate new token (classic)**
4. Fill in:
   - **Token name**: `AJC Bridge WordPress`
   - **Expiration**: No expiration (optional)
   - **Scopes**: Check only `repo` ✓
5. Click **Generate token**
6. Copy the token immediately (won't be shown again)

**Paste into**: **GitHub Token** field

**Security notes:**
- Token is encrypted in database at rest
- Never displayed in plain text in UI
- Stored using WordPress encryption functions
- Can be revoked anytime from GitHub

### Dev.to API Key

**How to generate:**

1. Go to dev.to → **Settings** → **Account**
2. Scroll to **API Keys** section
3. Your API key is displayed there
4. Copy it

**Paste into**: **Dev.to API Key** field

**Security notes:**
- Only needed if using `wordpress_devto`, `devto_only`, or `dual_github_devto` strategies
- Encrypted in database like GitHub token
- Can be revoked from dev.to anytime

---

## Test Connections

Before publishing, verify your credentials:

**Test GitHub Connection**
- Clicks to authenticate with your token
- Verifies read/write access to repository
- Shows ✓ if successful, ✗ if failed

**Test Dev.to Connection**
- Validates API key with dev.to
- Confirms account access
- Shows ✓ if successful, ✗ if failed

---

## Per-Post Controls

Each post editor has sync options:

### Sync to Dev.to (Checkbox)

Only appears if using strategies:
- `wordpress_devto`
- `dual_github_devto`

**Checked** = Post will be syndicated to dev.to on publish  
**Unchecked** = Post won't be syndicated to dev.to

### Sync to GitHub (Built-in)

For `github_only` and `dual_github_devto`:
- Toggle **Sync enabled** in the editor sidebar
- Checked = Post publishes to GitHub
- Unchecked = Post stays WordPress-only (not synced)

---

## Advanced Settings

### Debug Mode

Enable detailed logging:

1. Go to **Settings** → **General** → Scroll down
2. Check **Enable Debug Mode**
3. All sync operations log to `wp-content/debug.log`

Access logs:
- WordPress Admin → **AJC Bridge** → **Logs** (if available)
- Or: SSH to server, view `wp-content/debug.log`

### Custom Front Matter

For advanced users, define custom front matter templates:

1. Go to **Settings** → **General** → **Front Matter**
2. Choose YAML or TOML format
3. Use placeholders:
   - `{{title}}` — Post title
   - `{{date}}` — Publication date
   - `{{author}}` — Post author
   - `{{slug}}` — URL-friendly slug
   - `{{id}}` — Post ID
   - `{{image_avif}}` — Featured image (AVIF)
   - `{{image_webp}}` — Featured image (WebP)
   - `{{image_original}}` — Featured image (original)

**Example Hugo front matter:**
```yaml
---
title: {{title}}
date: {{date}}
author: {{author}}
slug: {{slug}}
image: {{image_webp}}
categories: [tech, wordpress]
---
```

---

## Common Configuration Scenarios

### Scenario 1: Traditional WordPress Blog

```
Strategy: wordpress_only
GitHub: Not needed
Dev.to: Not needed

→ Leave GitHub/Dev.to fields blank
→ No sync occurs
→ WordPress displays normally
```

### Scenario 2: WordPress + Dev.to Syndication

```
Strategy: wordpress_devto
GitHub: Not needed
Dev.to: Add API key

→ Posts automatically created as drafts on dev.to
→ Check "Sync to Dev.to" for each post you want to cross-post
→ Check canonical URL in dev.to to link back to WordPress
```

### Scenario 3: Headless WordPress → Hugo

```
Strategy: github_only
GitHub: Add token, select "main" branch
Dev.to: Not needed

→ All published posts sync to GitHub automatically
→ Frontend redirects 301 to Hugo site
→ WordPress remains functional in /wp-admin only
```

### Scenario 4: WordPress + Hugo + Dev.to

```
Strategy: dual_github_devto
GitHub: Add token
Dev.to: Add API key

→ All posts sync to GitHub (canonical)
→ Check "Sync to Dev.to" for each post you want on dev.to
→ Dev.to articles include canonical_url pointing to Hugo site
```

---

## Resetting Configuration

To reset all settings:

1. Go to **Settings** → **General** → Scroll to bottom
2. Click **Reset to Defaults** button
3. Confirm the action
4. All settings return to blank state
5. Credentials are securely deleted

---

**Next:** [Learn about workflows →](workflows.md)
