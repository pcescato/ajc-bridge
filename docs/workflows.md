# Publishing Workflows

Each publishing strategy creates a different content flow. Choose based on your needs.

---

## 1. WordPress Only

**Traditional WordPress site. Sync disabled.**

```
WordPress CMS → Public WordPress Site
```

### When to use
- Existing WordPress site
- Not planning to move to Jamstack yet
- Want to "future-proof" with AJC Bridge installed

### Features available
- Plugin configured but not active
- Settings available for later activation
- No API tokens needed

### Typical workflow
```
1. Write post in WordPress editor
2. Click Publish
3. Post appears on WordPress site
4. No GitHub sync occurs
```

### Setup required
- Install and activate plugin
- Choose `wordpress_only` strategy
- No credentials needed

---

## 2. WordPress + Dev.to Syndication

**WordPress is primary site. Optional per-post syndication to dev.to.**

```
WordPress CMS → Public WordPress Site (canonical)
             └→ dev.to (optional syndication)
```

### When to use
- WordPress site is your main platform
- Want to reach dev.to audience
- SEO: WordPress should be canonical

### Features available
- **Per-post control** — Checkbox to syndicate each post
- **Draft on dev.to** — Posts created as drafts for review
- **Canonical URL** — dev.to articles link back to WordPress
- **Update detection** — Editing WordPress post updates dev.to

### Typical workflow
```
1. Write post in WordPress editor
2. Check "Publish to dev.to" checkbox (optional)
3. Click Publish
4. Post goes live on WordPress
5. If checkbox was checked:
   → Post created as draft on dev.to
   → Canonical URL set to WordPress post
   → Article appears on your dev.to profile
```

### Dev.to article metadata
```
Canonical URL: https://yourwordpress.com/post-slug
Published: false (draft by default)
Series: Optional (synced from WordPress)
Cover image: Synced from WordPress featured image
```

### Setup required
- Strategy: `wordpress_devto`
- GitHub: Not needed
- Dev.to: Add API key

---

## 3. GitHub Only (Headless)

**WordPress is admin interface. GitHub Pages is public site.**

```
WordPress CMS (admin-only) → GitHub → Hugo/Jekyll → Public Site
                           ↓
                      301 redirect
```

### When to use
- Want Git as source of truth
- GitHub Pages as main public site
- Zero server runtime
- Full Git history for content

### Features available
- **Atomic commits** — Content + images in one commit
- **Automatic deployment** — GitHub Actions triggers on commit
- **301 redirects** — Frontend redirects to GitHub Pages
- **Admin-only WordPress** — Full WordPress backend
- **Bulk sync** — Sync all posts with one click

### Typical workflow
```
1. Write post in WordPress editor
2. Click Publish
3. Post syncs to GitHub automatically
   → Creates commit with markdown + images
   → GitHub Actions builds & deploys
   → Site updates within 2 minutes
4. Visitors to WordPress frontend redirected 301 to GitHub site
```

### GitHub repository structure
```
hugo-site/
├─ content/posts/
│  ├─ 2026-01-01-first-post.md
│  └─ 2026-01-02-second-post.md
├─ static/images/
│  ├─ 1/
│  │  ├─ featured.webp
│  │  ├─ featured.avif
│  │  └─ screenshot.webp
│  └─ 2/
│     └─ featured.avif
└─ .github/workflows/
   └─ build.yml
```

### Setup required
- Strategy: `github_only`
- GitHub: Add token
- Branch: Select where to commit (`main`, `gh-pages`)
- Dev.to: Not needed

### WordPress redirects
- WordPress homepage → 301 to GitHub Pages
- WordPress post pages → 301 to GitHub site equivalent

---

## 4. Dev.to Only (Headless)

**WordPress is admin interface. Dev.to is public platform.**

```
WordPress CMS (admin-only) → dev.to → Public Profile
                           ↓
                      301 redirect
```

### When to use
- Want dev.to as main platform
- WordPress for backend editing comfort
- Community engagement on dev.to
- No GitHub/static hosting needed

### Features available
- **Per-post syncing** — All posts go to dev.to
- **Draft creation** — Posts start as drafts for review
- **API optimization** — Markdown specifically for dev.to
- **301 redirects** — Frontend → dev.to profile

### Typical workflow
```
1. Write post in WordPress editor
2. Click Publish
3. Post syncs to dev.to as draft
4. Review/edit on dev.to
5. Publish on dev.to when ready
6. Visitors redirected to dev.to profile
```

### Dev.to metadata
```
Canonical URL: https://dev.to/yourprofile (no need for external)
Author: Your dev.to account
Cover image: Synced from WordPress featured
Series: Optional (synced from WordPress)
```

### Setup required
- Strategy: `devto_only`
- GitHub: Not needed
- Dev.to: Add API key
- Branch: Not applicable

---

## 5. Dual Publishing (Max Reach)

**WordPress is CMS. GitHub site is canonical. Dev.to is syndication.**

```
WordPress CMS (admin-only) → GitHub → Hugo/Jekyll (canonical)
                           ↓          ↓
                      301 redirect    └→ dev.to (optional syndication)
                                          Canonical URL → Hugo site
```

### When to use
- Maximum reach (Hugo + dev.to audiences)
- Hugo site is your main platform
- Want dev.to syndication with proper SEO
- Full Git history + community engagement

### Features available
- **Hugo as canonical** — GitHub/Hugo site is primary
- **Dev.to syndication** — Optional per-post
- **Canonical URLs** — dev.to articles link to Hugo
- **Per-post control** — Choose which posts go to dev.to
- **Atomic commits** — All changes in single Git commit

### Typical workflow
```
1. Write post in WordPress editor
2. Check "Publish to dev.to" (optional)
3. Click Publish
4. Post syncs to GitHub automatically
   → Creates commit with markdown + images
   → GitHub Actions builds & deploys
   → Hugo site updates (~2 min)
5. If dev.to was checked:
   → Post created as draft on dev.to
   → Canonical URL set to Hugo site
   → Appears on dev.to profile
6. All visitors hit Hugo site (WordPress frontend redirects 301)
```

### URL flow
```
Browser → WordPress.com → 301 Redirect
                        └→ hugo-site.com (canonical)
                        
dev.to article → View article → Canonical URL → hugo-site.com
```

### GitHub repository structure
```
hugo-site/
├─ content/posts/
│  └─ 2026-01-01-first-post.md
├─ static/images/
│  └─ 1/
│     └─ featured.webp
└─ .github/workflows/
   └─ build.yml
```

### Dev.to article includes
```yaml
---
title: My Post
canonical_url: https://hugo-site.com/posts/my-post/
series: Optional series name
cover_image: URL to featured image
---
```

### Setup required
- Strategy: `dual_github_devto`
- GitHub: Add token
- Dev.to: Add API key
- Branch: Select where to commit

---

## Comparing Strategies

| Need | Strategy | WordPress is | GitHub sync | Dev.to sync |
|------|----------|--------------|-----------|-----------|
| Keep WordPress only | `wordpress_only` | Public | ❌ | ❌ |
| Add dev.to | `wordpress_devto` | Public | ❌ | Optional |
| Full headless | `github_only` | Admin | ✅ Auto | ❌ |
| Dev.to only | `devto_only` | Admin | ❌ | ✅ Auto |
| Max reach | `dual_github_devto` | Admin | ✅ Auto | Optional |

---

## Switching Strategies

You can change strategies anytime:

1. Go to **Settings** → **General**
2. Select new strategy
3. Click **Save Settings**

**Important:** Switching strategies:
- ✅ Won't delete existing posts
- ✅ Won't affect WordPress data
- ⚠️ May change sync behavior going forward
- ⚠️ Old synced posts stay where they are (GitHub, dev.to)

**Example:** Switching from `github_only` → `wordpress_only`
- Hugo site remains unchanged
- GitHub repo still has old commits
- New posts won't sync to GitHub
- WordPress frontend still redirects 301

---

## Real-World Example: Tech Blog

**Goal:** Build audience on GitHub Pages AND dev.to, with WordPress as CMS.

**Setup:**
```
Strategy: dual_github_devto
Site: mytech.blog (GitHub Pages)
Dev.to: dev.to/myusername

GitHub token: ✓ Added
Dev.to key: ✓ Added
```

**Publishing workflow:**

```
Post 1: "React Hooks"
├─ Check "Publish to dev.to" ✓
├─ Publish
└─ Result:
   → Commits to GitHub
   → Hugo site shows post
   → Dev.to shows (draft) with canonical_url to Hugo

Post 2: "TypeScript Tricks"
├─ Leave "Publish to dev.to" unchecked
├─ Publish
└─ Result:
   → Commits to GitHub
   → Hugo site shows post
   → Dev.to: not synced (only Hugo audience)

Post 3: "WordPress Tips"
├─ Check "Publish to dev.to" ✓
├─ Publish
└─ Result:
   → Commits to GitHub
   → Hugo site shows post
   → Dev.to shows (draft) for WordPress community
```

**Traffic sources:**
- Hugo site (SEO, social)
- Dev.to profile (community)
- GitHub (developers)
- All with proper SEO via canonical URLs

---

**Next:** [Learn about adapters (Hugo/Astro/Jekyll) →](adapters.md)
