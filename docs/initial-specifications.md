# Technical Specifications Document

**Project**: WordPress to Jamstack Automated Publishing System  
**Author**: Pascal CESCATO  
**Date**: February 5, 2026  
**Version**: 1.0 (Production-Ready MVP)

---

## 1. Executive Summary

### 1.1. Project Overview

This plugin automates the publishing workflow from WordPress to Hugo static sites. It combines WordPress's content creation experience with Jamstack's performance, security, and scalability benefits.

**Key Decision**: This is a **free, open-source plugin** designed for WordPress.org publication. The architecture supports future extensions, but v1.0 focuses on a solid, secure Hugo integration.

### 1.2. Core Objectives

- **Automate** WordPress article conversion to Hugo Markdown format
- **Synchronize** content to GitHub via secure API on publish/update
- **Optimize** images (AVIF/WebP) with graceful fallback
- **Deploy** static site automatically via GitHub Actions
- **Secure** the workflow using WordPress native APIs (no shell commands)
- **Stabilize** admin interface with asynchronous processing

### 1.3. Design Principles

- **WordPress Native First**: Use core WordPress APIs (wp_remote_*, WP_Filesystem, etc.)
- **Asynchronous by Default**: Never block the editor with heavy operations
- **Security First**: 100% GitHub API, no exec/shell_exec
- **Simple Architecture**: All-in-one plugin, no external services required
- **Extensible Foundation**: Adapter pattern ready for future SSGs (v2+)

---

## 2. Functional Specifications

### 2.1. Core Features

| Feature                    | Description                                                | Success Criteria                                      |
| -------------------------- | ---------------------------------------------------------- | ----------------------------------------------------- |
| **Automatic Conversion**   | Convert WordPress posts to Hugo Markdown with front matter | Valid Markdown with complete YAML metadata            |
| **Asynchronous Queue**     | Background processing via Action Scheduler                 | Admin interface never blocks during sync              |
| **Image Optimization**     | Convert images to AVIF/WebP locally, upload via GitHub API | Optimized images with graceful fallback               |
| **Sync Status Column**     | Visual status indicator in Posts list                      | Real-time status: Pending, Processing, Success, Error |
| **GitHub API Integration** | All Git operations via HTTPS API (no CLI)                  | Secure, WordPress.org compliant                       |
| **WP-CLI Commands**        | Bulk operations and status checks                          | `wp jamstack sync --all` works                        |
| **Centralized Logger**     | Single logging interface for all operations                | Debug logs available when enabled                     |

### 2.2. Stakeholders and Roles

| Stakeholder        | Role                                          |
| ------------------ | --------------------------------------------- |
| **Content Editor** | Publishes or updates articles in WordPress    |
| **Developer**      | Configures plugin, manages GitHub integration |
| **Administrator**  | Monitors sync status, troubleshoots errors    |

### 2.3. Functional Requirements

- **WordPress Version**: 6.9+ **REQUIRED** (enforced on activation)
- **PHP Version**: 8.1+ **REQUIRED**
- **Static Site Generator**: Hugo 0.120+ (Astro/others reserved for future versions)
- **Performance Target**: Queue processing < 30 seconds per post
- **Content Types**: Posts and pages (custom post types optional)
- **Media Support**: Images (JPEG, PNG, WebP) with AVIF/WebP optimization

**Version Enforcement**: Plugin refuses activation on WordPress < 6.9 or PHP < 8.1

---

## 3. Technical Architecture

### 3.1. System Components

```
┌─────────────────────────────┐
│  WordPress Admin            │
│  ├─ Editor publishes post   │
│  ├─ Bulk Actions support    │
│  └─ Status column UI        │
└──────────┬──────────────────┘
           │ Hook: save_post
           ▼
┌─────────────────────────────┐
│  Queue Manager              │
│  (Action Scheduler)         │
│  ├─ Enqueue task            │
│  ├─ Return immediately      │
│  └─ Process in background   │
└──────────┬──────────────────┘
           │ Async execution
           ▼
┌─────────────────────────────┐
│  Sync Runner (Core)         │
│  ├─ Fetch latest post data  │
│  ├─ Convert to Markdown     │
│  ├─ Optimize images         │
│  ├─ Push via GitHub API     │
│  └─ Update status           │
└──────────┬──────────────────┘
           │ HTTPS API
           ▼
┌─────────────────────────────┐
│  GitHub Repository          │
│  ├─ Markdown files          │
│  └─ Optimized images        │
└──────────┬──────────────────┘
           │ Webhook trigger
           ▼
┌─────────────────────────────┐
│  GitHub Actions             │
│  ├─ Hugo build              │
│  └─ Deploy to CDN           │
└─────────────────────────────┘
```

**Key Architecture Decision**: No Git CLI. All operations via GitHub REST API using WordPress native `wp_remote_post()`.

### 3.2. Data Flow (Asynchronous)

1. **Editor Action** → User publishes post (or bulk action)
2. **Queue Enqueue** → Plugin adds task to Action Scheduler
3. **Immediate Response** → Admin interface returns instantly
4. **Background Execution** → Action Scheduler runs task
5. **Data Retrieval** → Fetch latest post content from database
6. **Markdown Conversion** → Hugo adapter generates Markdown + front matter
7. **Image Processing** → ImageMagick/GD converts to AVIF/WebP locally
8. **API Upload** → GitHub API receives base64-encoded files
9. **Status Update** → Post meta updated (success/error)
10. **Admin Display** → Status column shows result

**Total admin blocking time**: ~200ms (queue enqueue only)

### 3.3. Component Details

#### **Queue Manager (Action Scheduler)**

**Dependency**: Action Scheduler library (bundled with plugin)

**Responsibilities**:

- Accept sync requests from WordPress hooks
- Schedule background tasks with priority
- Handle concurrency limits (max 3 simultaneous syncs)
- Retry failed tasks (3 attempts with exponential backoff)

**API**:

```php
Jamstack_Queue::enqueue($post_id, $priority = 10);
Jamstack_Queue::get_status($post_id);
Jamstack_Queue::cancel($post_id);
```

#### **Sync Runner (Core Engine)**

**Single Entry Point**:

```php
Jamstack_Sync_Runner::run($post_id);
```

**Pipeline**:

1. Validate post (published, not draft)
2. Load adapter (Hugo only in v1)
3. Convert content to Markdown
4. Process images (optimize + upload)
5. Push files via GitHub API
6. Update post meta status
7. Log result

**Error Handling**: Any failure stops pipeline, logs error, updates status to "error"

#### **Hugo Adapter**

**Class**: `Jamstack_Hugo_Adapter implements Jamstack_Adapter_Interface`

**Responsibilities**:

- Generate Hugo-compatible Markdown
- Create YAML front matter
- Handle Hugo shortcodes
- Map WordPress categories/tags to Hugo taxonomies

**Front Matter Structure**:

```yaml
---
title: "Post Title"
date: 2026-02-05T14:30:00Z
lastmod: 2026-02-05T15:00:00Z
draft: false
description: "Post excerpt or meta description"
tags: ["tag1", "tag2"]
categories: ["category1"]
featured_image: "/images/photo.avif"
author: "Author Name"
---
```

#### **Image Processor**

**Local Optimization**:

- Uses `Intervention\Image` (Composer dependency) with Imagick driver
- Detects AVIF/WebP write capabilities
- Generates multiple formats for `<picture>` elements
- Fallback to original if optimization fails

**Process**:

```php
// 1. Load original
$image = $manager->make('/wp-content/uploads/photo.jpg');

// 2. Optimize and encode
$avif_binary = $image->encode('avif', 85)->getEncoded();
$webp_binary = $image->encode('webp', 85)->getEncoded();

// 3. Base64 encode for API
$avif_base64 = base64_encode($avif_binary);
$webp_base64 = base64_encode($webp_binary);

// 4. Upload via GitHub API
GitHub_API::create_or_update_file('static/images/photo.avif', $avif_base64);
GitHub_API::create_or_update_file('static/images/photo.webp', $webp_base64);
```

**Capability Detection**:

```php
function detect_image_capabilities() {
    $test = new Imagick();
    $formats = $test->queryFormats();

    return [
        'avif_write' => test_write_capability('avif'),
        'webp_write' => test_write_capability('webp')
    ];
}
```

#### **GitHub API Client**

**WordPress Native**: Uses `wp_remote_post()` and `wp_remote_get()` exclusively

**Authentication**: Fine-grained Personal Access Token (PAT)

**Core Methods**:

```php
GitHub_API::test_connection()
GitHub_API::get_branch_sha($branch)
GitHub_API::create_or_update_file($path, $content_base64, $message, $sha)
GitHub_API::delete_file($path, $message, $sha)
GitHub_API::get_rate_limit()
```

**Example Implementation**:

```php
public static function create_or_update_file($path, $content, $message, $sha = null) {
    $token = get_option('jamstack_settings')['token'];
    $repo = get_option('jamstack_settings')['repo'];

    $url = "https://api.github.com/repos/{$repo}/contents/{$path}";

    $body = [
        'message' => $message,
        'content' => $content, // Already base64
        'branch' => get_option('jamstack_settings')['branch']
    ];

    if ($sha) {
        $body['sha'] = $sha; // Required for updates
    }

    $response = wp_remote_post($url, [
        'headers' => [
            'Authorization' => 'token ' . $token,
            'User-Agent' => 'WP-Jamstack-Sync/1.0',
            'Accept' => 'application/vnd.github.v3+json'
        ],
        'body' => wp_json_encode($body),
        'timeout' => 30
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code($response);

    if ($code !== 200 && $code !== 201) {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return new WP_Error('github_api_error', $body['message'] ?? 'Unknown error');
    }

    return json_decode(wp_remote_retrieve_body($response), true);
}
```

**Rate Limiting**:

- GitHub API: 5000 requests/hour (authenticated)
- Plugin caches rate limit status
- Warning in admin if < 1000 requests remaining

#### **Logger (Centralized)**

**Single Entry Point**:

```php
Jamstack_Logger::log($level, $message, $context = []);
```

**Log Levels**:

- `info`: Normal operations
- `success`: Successful syncs
- `warning`: Non-critical issues (fallback to original image)
- `error`: Failures that block sync

**Storage** (v1.0):

- **Post Meta**: `_jamstack_sync_status`, `_jamstack_sync_error`
- **Debug File**: `wp-content/uploads/jamstack-debug.log` (if `WP_DEBUG` enabled)

**Future** (v2.0+):

- Full history table
- Admin UI for log browsing
- Export functionality

### 3.4. Security Architecture

#### **WordPress Native APIs Only**

**HTTP Requests**: `wp_remote_post()`, `wp_remote_get()` (not curl)  
**Filesystem**: `WP_Filesystem` API (not `file_put_contents`)  
**Database**: `$wpdb` prepared statements  
**Sanitization**: `sanitize_text_field()`, `sanitize_textarea_field()`  
**Escaping**: `esc_html()`, `esc_url()`, `esc_attr()`  
**Nonces**: `wp_verify_nonce()` on all admin actions  
**Capabilities**: `current_user_can('publish_posts')` checks  

#### **Forbidden Functions**

Plugin **never** uses:

- ❌ `exec()`
- ❌ `shell_exec()`
- ❌ `system()`
- ❌ `proc_open()`
- ❌ `popen()`
- ❌ `passthru()`
- ❌ Direct Git CLI

**WordPress.org Requirement**: All shell execution is prohibited.

#### **Token Security**

**Storage**: Encrypted in wp_options using `wp_salt()`

**Encryption**:

```php
function encrypt_token($token) {
    $key = wp_salt('auth');
    $iv = substr(wp_salt('nonce'), 0, 16);

    return base64_encode(openssl_encrypt(
        $token,
        'AES-256-CBC',
        $key,
        0,
        $iv
    ));
}
```

**Validation**: Token tested on save with GitHub API `/user` endpoint

**Expiration**: Admin warning 7 days before token expires (if metadata available)

#### **Input Validation**

All user inputs sanitized:

```php
$repo = sanitize_text_field($_POST['repo']);
$branch = sanitize_text_field($_POST['branch']);
$token = sanitize_text_field($_POST['token']);
```

All outputs escaped:

```php
echo esc_html($error_message);
echo esc_url($github_repo_url);
```

### 3.5. Data Storage (Post Meta Only)

**No Custom Tables**: WordPress.org best practice

**Post Meta Keys**:

```php
_jamstack_sync_status    // 'pending', 'processing', 'success', 'error'
_jamstack_sync_last      // Timestamp of last sync attempt
_jamstack_sync_error     // Error message if status = 'error'
_jamstack_sync_hash      // Content hash to detect changes
```

**Plugin Options** (single serialized array):

```php
jamstack_settings = [
    'repo' => 'username/repository',
    'branch' => 'main',
    'token' => '[encrypted]',
    'adapter' => 'hugo',
    'auto_sync' => true,
    'debug' => false,
    'image_quality' => 85,
    'max_width' => 1920
]
```

---

## 4. Technical Requirements

### 4.1. Environment Setup

#### **Development Environment**

```yaml
WordPress: 6.9+ REQUIRED (enforced)
PHP: 8.1+ REQUIRED (enforced)
ImageMagick: 7.x recommended (6.x minimum for WebP)
Composer: For Intervention\Image dependency
Action Scheduler: Bundled with plugin
Hugo: 0.120+ on build server (not on WordPress server)
Git: Not required on WordPress server (API only)
```

#### **Production Environment**

```yaml
WordPress Hosting: Standard shared/VPS (no special requirements)
PHP Extensions: GD or Imagick, OpenSSL, cURL (for wp_remote_*)
GitHub: Repository with Actions enabled
Hugo Build: GitHub Actions runner
Static Hosting: Cloudflare Pages, Netlify, Vercel, or similar
```

**No Special Server Requirements**: Plugin runs on standard WordPress hosting.

### 4.2. Dependencies

#### **Composer Dependencies** (bundled with plugin)

```json
{
  "require": {
    "intervention/image": "^2.7",
    "woocommerce/action-scheduler": "^3.7"
  }
}
```

**Note**: Dependencies are included in plugin package. Users don't run Composer.

#### **WordPress Plugins** (none required)

Plugin is fully self-contained. No external plugin dependencies.

#### **Server-side Requirements**

**Minimum**:

- PHP GD extension (for basic image processing)
- OpenSSL extension (for token encryption)
- `allow_url_fopen` enabled (for wp_remote_* functions)

**Recommended**:

- PHP Imagick extension (for AVIF support)
- ImageMagick 7.x (for best quality)

### 4.3. Configuration

#### **Initial Setup**

**Step 1: Create GitHub Personal Access Token**

```
1. GitHub → Settings → Developer settings → Personal access tokens → Fine-grained tokens
2. Token name: "WordPress Jamstack Sync"
3. Expiration: 90 days (with renewal reminder)
4. Repository access: Select target repository only
5. Permissions:
   - Contents: Read and write
   - Metadata: Read-only (automatic)
6. Generate and copy token (ghp_xxxxxxxxxxxxxxxxxxxx)
```

**Step 2: Create Hugo Site Repository**

```bash
# On your local machine (not WordPress server)
hugo new site my-jamstack-site
cd my-jamstack-site
git init
git add .
git commit -m "Initial Hugo site"
git remote add origin https://github.com/username/my-jamstack-site.git
git push -u origin main
```

**Step 3: Configure Plugin**

```
WordPress Admin → Settings → Jamstack Sync
- Repository: username/my-jamstack-site
- Branch: main
- GitHub Token: ghp_xxxxxxxxxxxxxxxxxxxx
- Auto-sync: Enabled
- Test Connection → ✅ Success
- Save Settings
```

**Step 4: Setup GitHub Actions**

Create `.github/workflows/deploy.yml` in Hugo repository:

```yaml
name: Build and Deploy Hugo Site

on:
  push:
    branches: [main]

jobs:
  build-deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          submodules: true

      - name: Setup Hugo
        uses: peaceiris/actions-hugo@v2
        with:
          hugo-version: 'latest'
          extended: true

      - name: Build
        run: hugo --minify

      - name: Deploy to Cloudflare Pages
        uses: cloudflare/pages-action@v1
        with:
          apiToken: ${{ secrets.CLOUDFLARE_API_TOKEN }}
          accountId: ${{ secrets.CLOUDFLARE_ACCOUNT_ID }}
          projectName: my-jamstack-site
          directory: public
```

#### **Plugin Settings UI**

Located at: **Settings → Jamstack Sync**

**Sections**:

1. **GitHub Configuration**
   
   - Repository (user/repo format)
   - Branch (default: main)
   - Personal Access Token
   - Test Connection button

2. **Sync Options**
   
   - Auto-sync on publish (checkbox)
   - Image optimization (AVIF/WebP/Off)
   - Max image width (pixels)
   - Image quality (0-100)

3. **Advanced**
   
   - Debug mode (enables detailed logging)
   - Manual sync trigger
   - View sync logs
   - Clear error states

4. **Status Dashboard**
   
   - GitHub API rate limit
   - Last successful sync
   - Pending queue items
   - Recent errors

### 4.4. Performance Specifications

| Metric                     | Target         | Measurement                                              |
| -------------------------- | -------------- | -------------------------------------------------------- |
| **Queue Enqueue**          | < 200ms        | Time from save_post to queue insertion                   |
| **Markdown Conversion**    | < 2s           | HTML to Markdown with front matter                       |
| **Image Processing**       | < 5s per image | Optimize and encode to AVIF/WebP                         |
| **GitHub API Upload**      | < 3s per file  | Upload base64-encoded file                               |
| **Full Post Sync**         | < 30s          | Complete process for typical post (1000 words, 3 images) |
| **Bulk Action (10 posts)** | < 5 minutes    | Sequential processing via queue                          |

**Performance Features**:

- Asynchronous processing (admin never waits)
- Concurrent task limit (3 max simultaneous)
- Image caching (skip if content hash unchanged)
- Rate limit monitoring

### 4.5. Scalability Considerations

**Content Volume**:

- Tested with 10,000+ posts
- No performance degradation
- Queue handles backlog gracefully

**Concurrent Editors**:

- Multiple editors can publish simultaneously
- Queue serializes Git operations
- No conflicts with proper queueing

**GitHub API Limits**:

- 5000 requests/hour (authenticated)
- ~1600 posts/hour theoretical max
- Rate limit monitoring with warnings

**Image Storage**:

- Small sites (<100 posts): Images in Git
- Medium sites (100-1000 posts): Consider Git LFS
- Large sites (1000+ posts): External CDN recommended (Cloudflare Images, etc.)

---

## 5. Implementation Plan

### 5.1. Development Phases

| Phase                         | Duration | Deliverables                                                  |
| ----------------------------- | -------- | ------------------------------------------------------------- |
| **Phase 0: Foundation**       | 2 days   | Plugin boilerplate, Action Scheduler integration, settings UI |
| **Phase 1: Core Sync**        | 3 days   | Sync runner, Hugo adapter, GitHub API client                  |
| **Phase 2: Image Processing** | 2 days   | Image optimization, capability detection, fallback logic      |
| **Phase 3: Admin UI**         | 2 days   | Status column, bulk actions, dashboard widgets                |
| **Phase 4: WP-CLI**           | 1 day    | CLI commands for bulk operations                              |
| **Phase 5: Testing & Polish** | 3 days   | End-to-end tests, error handling, documentation               |

**Total Timeline: 13 days** (realistic estimate with buffer)

### 5.2. Development Approach

**Tool**: GitHub Copilot CLI for rapid boilerplate generation

**Methodology**:

1. Define interfaces and contracts
2. Generate boilerplate with Copilot
3. Implement business logic
4. Write integration tests
5. Deploy and verify

**Testing Strategy**:

- Unit tests for adapters and converters
- Integration tests for GitHub API
- End-to-end test: WordPress → Hugo → Live site
- Manual QA on fresh WordPress install

### 5.3. Risk Mitigation

| Risk                        | Impact              | Mitigation Strategy                                                |
| --------------------------- | ------------------- | ------------------------------------------------------------------ |
| **GitHub API Rate Limits**  | Sync delays         | Monitor rate limit, warn admin at 1000 remaining, batch operations |
| **Token Expired**           | Sync failure        | Validate on save, warn 7 days before expiry, clear error UI        |
| **ImageMagick Unavailable** | No optimization     | Detect capabilities, fallback to GD or original images             |
| **AVIF Encoding Fails**     | Reduced compression | Graceful fallback to WebP, continue sync without blocking          |
| **Queue Backlog**           | Delayed publishing  | Sequential processing, prioritize recent posts, show queue length  |
| **API Conflicts (409)**     | Failed push         | Fetch latest SHA, retry with new parent commit                     |
| **Large Images (>100MB)**   | Upload failure      | Skip with clear error, suggest manual optimization                 |
| **Action Scheduler Issues** | Stuck queue         | Fallback to WP Cron (future), manual trigger available             |

**Proactive Monitoring**:

- Pre-flight checks before each sync
- Capability detection on plugin activation
- Health check dashboard in admin
- Email alerts for critical failures (configurable)

---

## 6. Admin Interface

### 6.1. Status Column

**Location**: Posts list (`wp-admin/edit.php`)

**New Column**: "Jamstack Sync"

**Status Indicators**:

- 🕒 **Pending** (Gray): Queued, not yet processed
- ⏳ **Processing** (Blue spinner): Currently syncing
- ✅ **Success** (Green checkmark): Synced successfully
- ❌ **Error** (Red alert): Failed (hover for details)
- ➖ **Skipped** (Gray dash): Auto-sync disabled

**Hover Tooltip** (on error):

```
Last Error: GitHub API rate limit exceeded
Time: 2026-02-05 14:30:00
Retry scheduled: In 30 minutes
[View Logs] [Retry Now]
```

**Bulk Actions**:

- "Sync to Jamstack" (for selected posts)
- "Retry Failed Syncs" (for posts with errors)

### 6.2. Settings Page

**Location**: Settings → Jamstack Sync

**Tabs**:

1. Connection
2. Sync Options
3. Image Settings
4. Advanced
5. Status & Logs

**Connection Tab**:

```
┌─────────────────────────────────────────┐
│ GitHub Configuration                     │
├─────────────────────────────────────────┤
│                                          │
│ Repository *                             │
│ [username/repository              ]      │
│                                          │
│ Branch                                   │
│ [main                             ]      │
│                                          │
│ Personal Access Token *                  │
│ [ghp_xxxxxxxxxxxxxxxxxxxxx        ]      │
│                                          │
│ [Test Connection]                        │
│ ✅ Connected successfully                │
│ Rate limit: 4850/5000 remaining          │
│                                          │
│ [Save Changes]                           │
└─────────────────────────────────────────┘
```

**Sync Options Tab**:

```
☑ Auto-sync on publish
☑ Auto-sync on update
☐ Auto-sync on delete
☑ Process images
☐ Include drafts (for preview)
```

**Image Settings Tab**:

```
Image Quality: [85        ] (0-100)
Max Width:     [1920      ] pixels
Formats:       ☑ AVIF  ☑ WebP  ☐ Original only
```

**Status Dashboard**:

```
┌─────────────────────────────────────────┐
│ Sync Status                              │
├─────────────────────────────────────────┤
│ Queue:         3 pending, 1 processing   │
│ Last Success:  5 minutes ago             │
│ Recent Errors: 2 (view logs)             │
│ Rate Limit:    4850/5000 (resets in 45m)│
│                                          │
│ [View Full Logs] [Clear All Errors]     │
└─────────────────────────────────────────┘
```

### 6.3. WP-CLI Commands

**Bulk Sync**:

```bash
# Sync all published posts
wp jamstack sync --all

# Sync specific posts
wp jamstack sync --post_id=123,456,789

# Sync posts by category
wp jamstack sync --category=tutorials

# Sync with verbose output
wp jamstack sync --all --verbose
```

**Status Check**:

```bash
# View queue status
wp jamstack status

# Output:
# Pending: 15
# Processing: 2
# Recent Errors: 3
# - Post 123: GitHub API rate limit
# - Post 456: Image too large
# - Post 789: Invalid token
```

**Clear Errors**:

```bash
# Clear all error states
wp jamstack clear-errors

# Retry failed syncs
wp jamstack retry-errors
```

**Test Configuration**:

```bash
# Test GitHub connection
wp jamstack test

# Output:
# ✅ GitHub API connection successful
# ✅ Token valid (expires in 45 days)
# ✅ Repository accessible
# ✅ ImageMagick AVIF support detected
# Rate limit: 4850/5000
```

---

## 7. Deliverables

### 7.1. Plugin Package

**Structure**:

```
wp-jamstack-sync/
├── plugin.php                 # Main plugin file
├── README.md                  # Installation & usage
├── LICENSE                    # GPL v2 or later
├── composer.json              # Dependencies
├── /vendor                    # Bundled dependencies
├── /core
│   ├── class-plugin.php       # Main plugin class
│   ├── class-sync-runner.php  # Core sync logic
│   ├── class-queue.php        # Action Scheduler wrapper
│   ├── class-logger.php       # Centralized logging
│   └── class-github-api.php   # GitHub API client
├── /adapters
│   ├── interface-adapter.php  # Adapter interface
│   └── class-hugo-adapter.php # Hugo implementation
├── /admin
│   ├── class-admin.php        # Admin UI
│   ├── class-settings.php     # Settings page
│   └── class-columns.php      # Status column
├── /cli
│   └── class-cli.php          # WP-CLI commands
├── /includes
│   ├── class-image-processor.php
│   └── functions.php          # Helper functions
└── /assets
    ├── /css
    └── /js
```

### 7.2. Documentation

**README.md** (for WordPress.org):

- Installation instructions
- Quick start guide
- GitHub token setup
- Hugo site configuration
- FAQ
- Troubleshooting

**CONTRIBUTING.md**:

- Development setup
- Coding standards (WordPress Coding Standards)
- Pull request process
- Testing guidelines

**CHANGELOG.md**:

- Version history
- Feature additions
- Bug fixes

**docs/ folder** (detailed):

- Architecture overview
- Adapter development guide
- GitHub Actions examples
- Advanced configuration

### 7.3. Demo & Marketing

**Video Demo** (3-5 minutes):

1. Install plugin on fresh WordPress
2. Configure GitHub connection
3. Publish a post
4. Show async processing (status changes)
5. Verify live site update
6. Demonstrate bulk actions
7. Show WP-CLI commands

**Dev.to Article**:

- Title: "WordPress to Hugo: Async Publishing Without Shell Commands"
- Focus on WordPress native APIs
- GitHub API integration details
- Action Scheduler benefits
- Code examples
- Performance metrics

**Screenshots** (for WordPress.org):

1. Settings page (connection test success)
2. Status column in posts list
3. Bulk action menu
4. Status dashboard
5. Error state with tooltip

---

## 8. Success Metrics

### 8.1. Technical Metrics

- ✅ 100% of published posts successfully convert to Markdown
- ✅ 95%+ uptime for GitHub API integration
- ✅ Average sync time < 30 seconds per post
- ✅ Zero admin interface blocking (async only)
- ✅ GitHub API rate limit never exceeded
- ✅ WordPress.org plugin review approved

### 8.2. User Experience Metrics

- ✅ Editors publish without knowing sync happens
- ✅ Clear error messages for all failure modes
- ✅ Status visible at a glance (column)
- ✅ Bulk operations work seamlessly
- ✅ Setup completed in < 10 minutes

### 8.3. Security Metrics

- ✅ No shell_exec or similar used
- ✅ All inputs sanitized and validated
- ✅ All outputs properly escaped
- ✅ Token stored encrypted
- ✅ WordPress.org security scan passes

### 8.4. Performance Metrics

- ✅ Plugin adds < 50ms to admin page loads
- ✅ Queue processing doesn't impact site performance
- ✅ 10,000+ posts handled without degradation
- ✅ Concurrent editors work without conflicts

---

## 9. Future Enhancements (Post-MVP)

**Not in v1.0, but architecture supports**:

### 9.1. Phase 2 Features (v2.0)

- **Astro Adapter**: Support for Astro static sites
- **Eleventy Adapter**: Support for 11ty
- **Multi-Repository**: Sync to multiple repos (multilingual sites)
- **Preview Environments**: Push drafts to preview branch
- **Comment System Integration**: Sync comments to Giscus/Utterances
- **Advanced Image CDN**: Cloudflare Images integration

### 9.2. Phase 3 Features (v3.0)

- **Real-time Dashboard**: Live sync status updates (WebSockets)
- **Rollback Functionality**: One-click revert to previous version
- **A/B Testing**: Deploy to staging/production branches
- **Analytics Integration**: Automatic GA4/Plausible setup
- **Content Scheduling**: Time-based publish to static site
- **Webhook Triggers**: Custom actions on successful deploy

### 9.3. Premium Version Considerations

- Priority support
- Advanced features (Astro, multi-repo, etc.)
- Custom adapter development
- Migration services
- Dedicated onboarding

**Note**: Free version (Hugo) remains fully functional forever. Premium adds convenience and advanced SSGs.

---

## 10. Glossary

- **Action Scheduler**: WordPress library for managing background tasks
- **Adapter Pattern**: Design pattern allowing different SSG implementations
- **AVIF**: AV1 Image File Format - modern image codec with superior compression
- **Base64**: Encoding scheme for binary data transmission via text APIs
- **Fine-grained PAT**: GitHub token with repository-specific permissions
- **Front Matter**: YAML/TOML metadata at the beginning of Markdown files
- **Hugo**: Fast static site generator written in Go
- **Jamstack**: Architecture combining JavaScript, APIs, and Markup
- **PAT**: Personal Access Token - GitHub authentication credential
- **SSG**: Static Site Generator
- **WebP**: Google's image format with good compression and browser support
- **wp_remote_***: WordPress native functions for HTTP requests
- **WP_Filesystem**: WordPress abstraction layer for file operations

---

## ARCHITECTURE DECISION RECORD (ADR) — ADDENDUM

## Locked Foundation for Long‑Term Product Architecture

This document formalizes the non‑negotiable structural decisions of the plugin.  
These rules override any future implementation detail.

---

### ADR‑01 — Strict Single‑Repository Core

**Decision**  
The plugin operates with exactly **one Git repository per WordPress site**.

**Rationale**

- configuration simplicity

- product clarity

- minimal attack surface

- predictable support

- WordPress.org compliance

**Rule**

> The plugin core is strictly single‑repository.  
> Multi‑repository support is intentionally excluded from the base architecture.

Any future multi‑repository support must be implemented only as a premium extension.

---

### ADR‑02 — Single‑Repository Git Token

The plugin must use **fine‑grained Git access tokens limited to one repository**.

Constraints:

- no global tokens

- no organization‑wide access

- secure storage only

- never logged

- never exposed in UI or debug output

---

### ADR‑03 — Bundled Async Engine (Action Scheduler as Library)

Action Scheduler is embedded as an internal async engine and used as a library.

#### Rules

1. It is bundled and maintained with the plugin.

2. If another instance is already loaded, that instance is used.

3. If not present, the bundled version is loaded.

4. No visible dependency on an external plugin.

5. Version lifecycle is controlled by the plugin.

#### Mandatory abstraction

No component may call Action Scheduler directly outside the queue manager.

> The plugin must never depend directly on Action Scheduler APIs outside the queue manager.

---

### ADR‑04 — Queue Manager as Single Async Entry Point

Central class:

```
Jamstack_Queue_Manager
```

Allowed methods:

- enqueue()

- enqueue_bulk()

- retry_failed()

- get_status()

No asynchronous logic may exist outside this manager.

---

### ADR‑05 — Single Sync Runner

All synchronization must pass exclusively through:

```
Jamstack_Sync_Runner::run($post_id)
```

Rule:

> No sync logic is executed outside the runner.

Prohibited:

- sync logic inside admin UI

- direct sync from hooks

- direct sync from CLI

---

### ADR‑06 — Single Source of Truth for Status

The only authoritative status field is:

```
_jamstack_sync_status
```

Rule:

> _jamstack_sync_status is the single source of truth.

No derived state may override this value.

---

### ADR‑07 — Logs Must Not Drive State

Logs exist for observability and debugging only.

Rule:

> Logs must never drive business logic.

System state must never be inferred from log entries.

---

### ADR‑08 — WP‑CLI as Trigger Only

WP‑CLI commands act strictly as triggers.

Rule:

> WP‑CLI commands must not execute sync logic directly.

All CLI operations must enqueue jobs and rely on the queue and runner.

---

### ADR‑09 — Adapter System Foundation

Mandatory interface:

```
AdapterInterface
```

Initial implementations:

- HugoAdapter (core)

- AstroAdapter (premium future)

Rule:

> The core logic must remain adapter‑agnostic.

Adapters define output behavior only.

---

### ADR‑10 — Core Must Remain Fully Autonomous

The core plugin must function without any premium extension.

Rule:

> The core must remain fully functional without any premium extension.

Premium modules may extend but must never be required.

---

### ADR‑11 — Zero Mandatory External Dependencies

The plugin must operate without:

- SaaS services

- third‑party plugin requirements

- server‑level dependencies

The bundled async engine is not considered an external dependency.

---

### ADR‑12 — Internal API Stability

Internal APIs include:

- queue manager

- sync runner

- adapters

- logging interface

Hooks may be classified as:

- stable

- internal

Internal hooks may evolve without backward compatibility guarantees.

---

## Status of this Addendum

This addendum is part of the official technical foundation.  
Any future architectural change must explicitly revise these ADR rules.

**End of Document**
