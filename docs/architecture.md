# Architecture Guide

Technical deep-dive into AJC Bridge design patterns, data structures, and how components work together.

---

## Design Principles

### Core Principles

1. **Adapter Pattern** — Each SSG (Hugo, Astro, Jekyll) handled by separate adapter class
2. **Strategy Pattern** — 5 publishing strategies for different workflows
3. **Async Processing** — Background jobs via Action Scheduler (no blocking requests)
4. **Atomic Operations** — Commits only succeed if all files (content + images) succeed
5. **Security by Default** — Encrypted credentials, nonce verification, sanitized inputs
6. **Single Responsibility** — Each class does one thing well

### Non-Goals

- ❌ Sync in real-time (async is by design)
- ❌ Support all static site generators (focus on Hugo/Astro/Jekyll)
- ❌ Advanced WordPress features (focus on posts & pages)
- ❌ Dynamic content (static generation only)

---

## Publishing Strategies

### Strategy: WordPress Only

```
Post published in WordPress
→ No sync (disabled)
→ Post visible on WordPress site
```

**Use case:** Traditional WordPress site, plugin ready for later activation.

---

### Strategy: WordPress + Dev.to

```
Post published in WordPress
→ Check "Publish to dev.to" checkbox
├─ YES → Create draft on dev.to (with canonical_url → WordPress)
└─ NO → WordPress only
```

**Key logic:**
- Per-post control via checkbox in editor
- dev.to posts created as draft (manual review)
- Canonical URL points back to WordPress (SEO)
- Updating WordPress post updates dev.to

---

### Strategy: GitHub Only

```
Post published in WordPress
→ Immediately sync to GitHub
├─ Create markdown file (content/posts/YYYY-MM-DD-slug.md)
├─ Optimize featured image → WebP + AVIF
├─ Extract content images → Optimize + upload
├─ Create atomic commit (1 commit = all files)
└─ GitHub Actions auto-deploys to Hugo/Jekyll
→ 301 redirect: WordPress frontend → GitHub Pages
```

**Key features:**
- Automatic (all posts sync)
- Atomic commits (all or nothing)
- Image optimization included
- WordPress is admin-only interface

---

### Strategy: Dev.to Only

```
Post published in WordPress
→ Immediately sync to dev.to
├─ Create draft article (manual review)
├─ Include featured image (absolute URL)
├─ Optimize content images in markdown
└─ Set canonical_url (optional, points to dev.to profile)
→ 301 redirect: WordPress frontend → dev.to profile
```

**Key features:**
- Automatic (all posts sync)
- API-based (no Git commits)
- dev.to as public platform
- WordPress is admin-only interface

---

### Strategy: Dual Publishing (Max Reach)

```
Post published in WordPress
→ Step 1: Sync to GitHub (MUST succeed first)
├─ Create markdown file in content/posts/
├─ Optimize & upload images
├─ Atomic commit
└─ GitHub Actions deploys
→ Step 2: Check "Publish to dev.to" checkbox
├─ YES → Create draft on dev.to
│       → Canonical URL → GitHub site
│       → Reaches dev.to audience
└─ NO → GitHub only (no dev.to)
→ 301 redirect: WordPress frontend → GitHub Pages
```

**Key features:**
- GitHub is canonical (primary)
- dev.to is optional syndication
- Per-post control for dev.to
- Dual reach with proper SEO

---

## File Structure

```
wp-content/plugins/ajc-bridge/
├─ wp-jamstack-sync.php          # Plugin header + bootstrap
├─ core/                          # Core functionality
│  ├─ class-plugin.php            # Singleton entry point
│  ├─ class-sync-runner.php       # Main sync orchestrator
│  ├─ class-media-processor.php   # Image optimization
│  ├─ class-queue-manager.php     # Async job scheduling
│  ├─ class-devto-api.php         # Dev.to REST API client
│  └─ class-logger.php            # Unified logging
├─ adapters/                      # SSG adapters
│  ├─ interface-adapter.php       # Adapter contract
│  ├─ class-hugo-adapter.php      # Hugo specifics
│  ├─ class-astro-adapter.php     # Astro specifics
│  ├─ class-jekyll-adapter.php    # Jekyll specifics
│  └─ class-devto-adapter.php     # Dev.to specifics
├─ admin/                         # WordPress admin UI
│  ├─ class-admin-settings.php    # Settings page
│  ├─ class-post-meta-box.php     # Post editor UI
│  └─ css/                        # Admin styles
├─ includes/                      # Helpers
│  ├─ class-adapter-factory.php   # Create adapter instances
│  ├─ class-github-api.php        # GitHub REST API
│  └─ helpers.php                 # Utility functions
└─ vendor/                        # Composer dependencies
```

---

## Data Flow

### Publishing a Post (Simplified)

```
1. User writes post in WordPress editor
   ↓
2. User clicks "Publish"
   ↓
3. WordPress fires `publish_post` hook
   ↓
4. Plugin grabs post content + featured image
   ↓
5. Determine publishing strategy (from settings)
   ├─ WordPress only → STOP
   ├─ WordPress + dev.to → Continue to dev.to step
   ├─ GitHub only → Go to GitHub step
   ├─ Dev.to only → Go to dev.to step
   └─ Dual → GitHub first, then optional dev.to
   ↓
6. GITHUB STEP (if applicable):
   ├─ Select appropriate adapter (Hugo/Astro/Jekyll)
   ├─ Optimize featured image:
   │  ├─ Generate WebP version
   │  ├─ Generate AVIF version
   │  └─ Prepare upload
   ├─ Extract content images:
   │  ├─ Find all <img> tags
   │  ├─ For each image:
   │  │  ├─ Download from WordPress
   │  │  ├─ Generate WebP + AVIF
   │  │  └─ Queue for upload
   │  └─ Update content URLs (→ GitHub paths)
   ├─ Convert HTML → Markdown:
   │  ├─ Parse WordPress content
   │  ├─ Convert blocks to Markdown
   │  └─ Update image references
   ├─ Generate front matter:
   │  ├─ Call adapter->get_front_matter()
   │  ├─ Include metadata (title, date, tags, etc.)
   │  └─ Adapter decides format (YAML/TOML)
   ├─ Prepare GitHub commit:
   │  ├─ Create file blob (markdown content)
   │  ├─ Create image blobs (WebP + AVIF)
   │  ├─ Build commit tree
   │  └─ Push to GitHub branch
   ├─ Store article ID in post meta
   └─ Log success
   ↓
7. DEV.TO STEP (if applicable):
   ├─ Check if "Publish to dev.to" enabled
   ├─ If YES:
   │  ├─ Fetch current article (if updating)
   │  ├─ Get featured image URL (from WordPress)
   │  ├─ Convert content to dev.to Markdown
   │  ├─ Call dev.to API (POST/PUT)
   │  ├─ Get back article ID
   │  └─ Store in post meta (_ajc_bridge_devto_id)
   └─ Log success
   ↓
8. Update sync metadata:
   ├─ _ajc_sync_status = "success"
   ├─ _ajc_sync_last = current timestamp
   └─ _ajc_last_commit_url = GitHub commit URL
   ↓
9. User sees success message in editor
```

---

## Data Structures

### Post Metadata

Each post stores sync-related metadata:

```php
// GitHub sync
_ajc_sync_status          // "success", "failed", "processing"
_ajc_sync_last            // Unix timestamp of last sync
_ajc_last_commit_url      // Link to GitHub commit

// Dev.to sync
_ajc_bridge_devto_id      // Article ID from dev.to API
_ajc_bridge_devto_sync_time  // Last dev.to sync timestamp
```

### Settings (option: `ajc_bridge_settings`)

```php
[
    'ssg_type'            => 'hugo',  // hugo, astro, jekyll
    'repository'          => 'username/repo',
    'branch'              => 'main',
    'publishing_strategy' => 'dual_github_devto',
    'site_url'            => 'https://mysite.com',
    'devto_site_url'      => 'https://dev.to/username',
    'github_token'        => '[encrypted]',  // Encrypted at rest
    'devto_api_key'       => '[encrypted]',  // Encrypted at rest
    'front_matter_template' => '---\n...',
]
```

---

## Adapter Pattern

### Adapter Interface

All SSG adapters implement `Adapter_Interface`:

```php
interface Adapter_Interface {
    
    // Get markdown content with front matter
    public function convert(WP_Post $post, array $image_mapping = [], string $featured_image_path = ''): string;
    
    // Get file path in repository (e.g., "content/posts/slug.md")
    public function get_file_path(WP_Post $post): string;
    
    // Get front matter metadata
    public function get_front_matter(WP_Post $post, ?string $canonical_url = null): array;
    
    // Get directory where images are stored
    public function get_images_dir(?int $post_id = null): string;
    
    // Get featured image filename (adapter determines naming)
    public function get_featured_image_name(string $original_basename, string $extension): string;
}
```

### Hugo Adapter

```php
class Hugo_Adapter implements Adapter_Interface {
    
    // File structure:
    // content/posts/2026-01-15-my-post.md
    public function get_file_path(WP_Post $post): string {
        $date = get_the_date('Y-m-d', $post->ID);
        return "content/posts/{$date}-{$post->post_name}.md";
    }
    
    // Images directory:
    // static/images/{post_id}/
    public function get_images_dir(?int $post_id = null): string {
        return $post_id ? "static/images/{$post_id}" : "static/images";
    }
    
    // Featured image naming:
    // featured.webp, featured.avif
    public function get_featured_image_name(string $original_basename, string $extension): string {
        return "featured.{$extension}";
    }
}
```

### Astro Adapter

```php
class Astro_Adapter implements Adapter_Interface {
    
    // File structure:
    // src/content/posts/my-post.mdx (no date!)
    public function get_file_path(WP_Post $post): string {
        return "src/content/posts/{$post->post_name}.mdx";
    }
    
    // Images directory:
    // public/image/ (flat, no post ID folders)
    public function get_images_dir(?int $post_id = null): string {
        return "public/image";  // Ignores post_id (flat structure)
    }
    
    // Featured image naming:
    // preserves original filename
    public function get_featured_image_name(string $original_basename, string $extension): string {
        return "{$original_basename}.{$extension}";
    }
}
```

### Why Adapters?

Each SSG has different conventions:

| Hugo | Astro | Jekyll |
|------|-------|--------|
| Date in filename | No date in filename | Date in filename |
| `content/posts/` | `src/content/posts/` | `_posts/` |
| Per-post image folders | Flat image structure | Flat image structure |
| `featured.webp` | `featured.webp` (original name) | `featured.webp` |
| YAML/TOML front matter | YAML front matter | YAML front matter |

**Solution:** Adapter handles all SSG-specific logic. Core code stays generic.

---

## Async Processing

### Action Scheduler Flow

```
1. Post published in WordPress
   ↓
2. Plugin enqueues async job:
   wp_schedule_single_event(
       time() + 5,  // Run in 5 seconds
       'ajc_bridge_sync',
       ['post_id' => 123]
   )
   ↓
3. Action Scheduler executes job:
   ├─ Acquires lock (prevents concurrent syncs)
   ├─ Calls Sync_Runner::run($post_id)
   ├─ All sync logic happens here
   ├─ Releases lock
   └─ Logs result
   ↓
4. On failure:
   ├─ Log error
   ├─ Update post meta (_ajc_sync_status = "failed")
   └─ Schedule retry (exponential backoff)
```

### Why Async?

- **No blocking:** WordPress response returns before sync completes
- **Reliable:** Retries on failure
- **Scalable:** Multiple jobs can queue
- **Logging:** Full history of all syncs

---

## Image Processing Pipeline

### Featured Image

```
1. Get attachment ID from post
2. Download original file
3. Analyze: determine format (JPEG, PNG, etc.)
4. Generate optimizations:
   ├─ WebP version (smaller, broader browser support)
   ├─ AVIF version (smaller, modern browsers)
   └─ Original (fallback)
5. Queue all versions for upload to GitHub
6. Update front matter with image path
```

### Content Images

```
1. Parse post HTML content
2. Find all <img> tags
3. For each image:
   ├─ Extract src URL
   ├─ Download from WordPress
   ├─ Generate WebP + AVIF
   ├─ Queue for GitHub upload
   └─ Update markdown image URL (→ GitHub path)
4. Prepare GitHub upload tree with all images
```

### Image Optimization

Handled by `intervention/image` library:

```php
$image = Image::read($local_path);

// Generate AVIF (best quality)
$image->toAvif(quality: 75)->save($output_avif);

// Generate WebP (good quality, broad support)
$image->toWebp(quality: 80)->save($output_webp);

// Keep original (fallback)
copy($local_path, $output_original);
```

---

## GitHub API Integration

### Trees API (Efficient)

Instead of creating commits directly, use GitHub Trees API:

```
Traditional API (many calls):
POST /repos/{repo}/contents/{path}  ← Create markdown
POST /repos/{repo}/contents/{path}  ← Upload image 1
POST /repos/{repo}/contents/{path}  ← Upload image 2
...

Trees API (1 call):
POST /repos/{repo}/git/trees
  ├─ All markdown files
  ├─ All image files
  └─ All metadata in single request
```

**Benefits:**
- 70% fewer API calls
- Single atomic commit
- Better performance
- All-or-nothing commit

---

## Dev.to API Integration

### Publishing Flow

```
1. Get article metadata from WordPress:
   - Title, excerpt, content
   - Featured image URL
   - Tags, categories
   
2. Convert to dev.to format:
   - HTML → Markdown
   - Image URLs → absolute (dev.to can't access local files)
   
3. Check if article already exists:
   - Look up _ajc_bridge_devto_id in post meta
   
4. Create or Update:
   - POST /api/articles (new)
   - PUT /api/articles/{id} (update)
   
5. Store response:
   - Article ID
   - Published status
   - URL
   - Last sync timestamp
```

### Published Status Preservation

**Problem:** WordPress edits might change article status on dev.to.

**Solution:**
```
Before updating article:
├─ GET /api/articles/{id}
├─ Extract published_timestamp (indicates if published)
└─ Include in markdown front matter

When API receives update:
├─ Front matter has published: true/false
└─ Article status preserved
```

---

## Security

### Token Encryption

Credentials stored encrypted in WordPress database:

```php
// Storing (encryption)
$encrypted = wp_json_encode(
    openssl_encrypt(
        $token,
        'aes-256-cbc',
        wp_hash('nonce_base', 'auth'),
        true
    )
);
update_option('ajc_bridge_settings', ['github_token' => $encrypted]);

// Retrieving (decryption)
$decrypted = openssl_decrypt(
    $encrypted,
    'aes-256-cbc',
    wp_hash('nonce_base', 'auth'),
    true
);
```

### Input Sanitization

All user inputs sanitized:

```php
$repo = sanitize_text_field($_POST['repository']);     // Remove tags
$branch = sanitize_text_field($_POST['branch']);       // Safe string
$url = esc_url($_POST['site_url']);                    // Safe URL
$token = sanitize_text_field($_POST['github_token']);  // Safe string
```

### Nonce Verification

All forms protected:

```php
wp_verify_nonce($_POST['_wpnonce'], 'ajc_bridge_settings');
```

---

## Testing Checklist

### Manual Tests

**Strategy: GitHub Only**
- [ ] Publish new post → Verify commit on GitHub
- [ ] Update post → Verify update commit
- [ ] Delete post → Verify deletion commit
- [ ] Featured image → Verify WebP/AVIF uploaded
- [ ] Content images → Verify all extracted + optimized
- [ ] Bulk sync → Verify all posts synced

**Strategy: Dev.to**
- [ ] Publish → Verify article created as draft
- [ ] Update → Verify article updates
- [ ] Manual publish on dev.to → Verify status preserved on next update
- [ ] Canonical URL → Verify correct in dev.to article

**Strategy: Dual**
- [ ] Publish with dev.to checkbox → Both platforms
- [ ] Publish without checkbox → GitHub only
- [ ] Check canonical URLs

---

## Future Improvements

- **Unit tests** (PHPUnit)
- **Elevent adapter** (11ty support)
- **Custom post types** (beyond posts + pages)
- **Scheduled publishing** (publish at specific times)
- **Rollback capability** (undo bad syncs)

---

**See also:** [Configuration](configuration.md) | [Troubleshooting](troubleshooting.md)
