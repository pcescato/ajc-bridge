# AJC Bridge

[![WordPress](https://img.shields.io/badge/WordPress-6.9%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPLv3-green.svg)](https://www.gnu.org/licenses/gpl-3.0.html)
[![Version](https://img.shields.io/badge/Version-1.2.0-orange.svg)](https://github.com/pcescato/ajc-bridge/releases)

**A production-grade WordPress plugin that bridges your content to modern Jamstack platforms.** Take complete control over where and how your content is published with 5 distinct publishing strategies—from traditional WordPress to fully headless architecture.

---

## 📋 Table of Contents

- [Overview](#-overview)
- [Features](#-features)
- [Publishing Strategies](#-publishing-strategies)
- [Installation](#-installation)
  - [End Users](#for-end-users)
  - [Developers](#for-developers)
- [Configuration](#-configuration)
- [Usage](#-usage)
- [Architecture](#-architecture)
- [Development](#-development)
- [Troubleshooting](#-troubleshooting)
- [FAQ](#-faq)
- [Contributing](#-contributing)
- [License](#-license)

---

## 🌟 Overview

**AJC Bridge** is a robust WordPress plugin designed for developers and content creators who want flexibility in their publishing workflow. Whether you're running a traditional WordPress site, syndicating to dev.to, going fully headless with Hugo/Jekyll, or managing a dual-platform strategy, AJC Bridge handles it seamlessly.

### Why AJC Bridge?

- **🎯 5 Publishing Strategies**: One plugin, five workflows—choose what fits your needs
- **🔒 Production-Ready**: Encrypted credentials, atomic commits, comprehensive error handling
- **⚡ Performance**: Async background processing with Action Scheduler, WebP/AVIF image optimization
- **🎨 Customizable**: Define your own Front Matter templates, control sync on a per-post basis
- **🛡️ SEO-Friendly**: Smart canonical URL management, 301 redirects for headless mode
- **📊 Monitoring**: Real-time sync status, detailed logs, one-click retry

---

## ✨ Features

### Publishing & Content Management

- **5 Publishing Strategies** with automatic migration from legacy configurations
- **Per-Post Control**: Checkbox to enable/disable dev.to syndication on individual posts
- **Atomic Commits**: All content and images uploaded in a single GitHub commit
- **Bulk Sync**: Synchronize all published posts with one click
- **Page Support**: Sync both posts and pages to your Jamstack site
- **Clean Markdown**: WordPress HTML converted to platform-compatible Markdown
- **Deletion Management**: Automatic cleanup when posts are trashed/deleted

### Headless WordPress

- **Automatic Frontend Redirects**: 301 permanent redirects to your external site
- **Admin-Only Mode**: WordPress backend remains fully functional
- **Canonical URL Management**: Smart canonical URL handling for SEO
- **Dual Platform Support**: Publish to GitHub and dev.to simultaneously

### Dev.to Integration

- **Native API Support**: Direct integration with dev.to (Forem) API
- **Markdown Optimization**: Content conversion optimized for dev.to rendering
- **Cover Image Support**: Automatic featured image handling
- **Draft Management**: Posts created as drafts by default for review
- **Update Detection**: Smart updates to existing articles

### GitHub Integration

- **Trees API**: Atomic commits using GitHub's advanced Trees API (~70% fewer API calls)
- **Smart Duplicate Prevention**: Avoids redundant commits with SHA comparison
- **Branch Support**: Deploy to any branch (main, gh-pages, custom)
- **Commit History**: Clean git history with descriptive commit messages
- **Hugo/Jekyll Compatible**: Works with any static site generator

### Image Processing

- **Automatic Optimization**: WebP and AVIF generation with Intervention Image v3
- **Featured Image Support**: Dedicated featured image processing
- **Content Images**: Extract and process all images in post content
- **Multiple Formats**: Original, WebP, and AVIF uploaded simultaneously
- **Driver Support**: Imagick (recommended) or GD fallback

### Front Matter Customization

- **Template System**: Define custom Front Matter with 7+ placeholders
- **YAML & TOML Support**: Compatible with both formats (`---` or `+++`)
- **Theme Compatibility**: Works with any Hugo theme (PaperMod, Minimal, etc.)
- **Placeholders**: `{{title}}`, `{{date}}`, `{{author}}`, `{{slug}}`, `{{id}}`, `{{image_avif}}`, `{{image_webp}}`, `{{image_original}}`

### Developer Features

- **WordPress Coding Standards**: 100% compliant PHP code
- **Type Safety**: PHP 8.1+ with strict types and type declarations
- **Modern Architecture**: Strategy pattern, adapters, dependency injection
- **Async Processing**: Background sync using Action Scheduler (no blocking requests)
- **Lock Mechanism**: Prevents concurrent sync conflicts
- **Retry Logic**: Automatic retry with exponential backoff on failures
- **Advanced Logging**: Detailed debug logs with real-time admin UI feedback
- **Role-Based Access**: Authors can sync their own posts, view their sync history
- **WordPress Native**: No shell commands, only WordPress and PHP native APIs

### Security

- **Encrypted Token Storage**: GitHub and dev.to credentials encrypted at rest
- **Masked UI Display**: Tokens never exposed in admin interface
- **Nonce Verification**: All AJAX requests protected
- **Sanitization**: Comprehensive input sanitization and validation
- **No Tracking**: Zero external tracking or analytics

### User Experience

- **Tabbed Settings Interface**: Organized settings with General and Credentials tabs
- **Monitoring Dashboard**: Track sync status, view GitHub commits, one-click resync
- **Connection Testing**: Test GitHub and dev.to API connections before saving
- **Real-Time Feedback**: Success/error messages in admin UI
- **Clean Uninstall Option**: Choose to preserve or permanently delete plugin data

---

## 🚀 Publishing Strategies

AJC Bridge offers **5 distinct publishing strategies** to match your workflow:

### 1. 📝 WordPress Only

**Status**: WordPress site fully public and functional  
**Sync**: Disabled (plugin configured but inactive)  
**Use Case**: Traditional WordPress site, plugin ready for future activation

```
WordPress → Public Website
```

---

### 2. 📰 WordPress + dev.to Syndication

**Status**: WordPress is your canonical publication  
**Sync**: Optional per-post syndication to dev.to  
**Canonical**: dev.to articles point back to WordPress  
**Use Case**: WordPress as primary, reach dev.to audience with proper SEO

```
WordPress → Public Website (canonical)
         └→ dev.to (optional syndication with canonical_url)
```

**Per-Post Control**: Each post has a "Publish to dev.to" checkbox in the editor sidebar.

---

### 3. 🌐 GitHub Only (Headless)

**Status**: WordPress is headless (admin-only)  
**Sync**: All posts automatically sync to Hugo/Jekyll  
**Redirect**: Frontend visitors redirected to GitHub Pages (301)  
**Use Case**: Fully headless WordPress, GitHub Pages as public site

```
WordPress (admin-only) → GitHub → Hugo/Jekyll (public)
                      ↓
                  Frontend redirects to Hugo site
```

---

### 4. 🔗 dev.to Only (Headless)

**Status**: WordPress is headless (admin-only)  
**Sync**: All posts automatically sync to dev.to  
**Redirect**: Frontend visitors redirected to dev.to profile (301)  
**Use Case**: WordPress as CMS, dev.to as public platform

```
WordPress (admin-only) → dev.to (public)
                      ↓
                  Frontend redirects to dev.to
```

---

### 5. 🌍 Dual Publishing (GitHub + dev.to)

**Status**: WordPress is headless (admin-only)  
**Sync**: All posts to GitHub, optional per-post to dev.to  
**Canonical**: GitHub site is canonical, dev.to points to it  
**Redirect**: Frontend visitors redirected to GitHub Pages (301)  
**Use Case**: Maximum reach—Hugo site as primary, dev.to for community

```
WordPress (admin-only) → GitHub → Hugo/Jekyll (canonical, public)
                      └→ dev.to (optional syndication with canonical_url to Hugo)
                      ↓
                  Frontend redirects to Hugo site
```

**Per-Post Control**: Each post has a "Publish to dev.to" checkbox in the editor sidebar.

---

## 📦 Installation

### For End Users

#### Method 1: WordPress Admin (Recommended)

1. Download the latest `ajc-bridge.zip` from [Releases](https://github.com/pcescato/ajc-bridge/releases)
2. Go to WordPress Admin → **Plugins** → **Add New** → **Upload Plugin**
3. Choose `ajc-bridge.zip` and click **Install Now**
4. Click **Activate**
5. Navigate to **AJC Bridge** → **Settings** to configure

#### Method 2: Manual Installation

1. Download and extract `ajc-bridge.zip`
2. Upload the `ajc-bridge` folder to `/wp-content/plugins/`
3. Activate the plugin through the **Plugins** menu in WordPress
4. Navigate to **AJC Bridge** → **Settings** to configure

---

### For Developers

#### Clone and Install

```bash
# Clone the repository
git clone https://github.com/pcescato/ajc-bridge.git
cd ajc-bridge

# Install Composer dependencies
composer install --no-dev --prefer-dist --optimize-autoloader

# Symlink to WordPress plugins directory
ln -s $(pwd) /path/to/wordpress/wp-content/plugins/ajc-bridge

# Or copy to plugins directory
cp -r . /path/to/wordpress/wp-content/plugins/ajc-bridge
```

#### Requirements

- **WordPress**: 6.9 or higher
- **PHP**: 8.1 or higher
- **PHP Extensions**:
  - `json` (required)
  - `curl` (required)
  - `openssl` (required for encryption)
  - `imagick` (recommended for image processing) or `gd` (fallback)
- **Composer**: For dependency management (development)

#### Dependencies

The plugin uses these Composer packages:

```json
{
  "intervention/image": "^3.11",
  "league/html-to-markdown": "^5.1",
  "woocommerce/action-scheduler": "^3.9"
}
```

**Note**: `intervention/image` v3 requires Imagick extension with AVIF support (ImageMagick 7.0.8+) for full functionality.

---

## ⚙️ Configuration

### Step 1: Choose Publishing Strategy

Navigate to **AJC Bridge** → **Settings** → **General** tab.

1. **Publishing Strategy**: Select one of the 5 strategies
2. **GitHub Site URL**: Your deployed Hugo/Jekyll site URL (e.g., `https://username.github.io/repo`)
3. **Dev.to Site URL**: Your dev.to profile URL or WordPress URL for canonical links

### Step 2: Configure Credentials

Navigate to **AJC Bridge** → **Settings** → **Credentials** tab.

#### GitHub Configuration

1. **Personal Access Token**: 
   - Go to GitHub → **Settings** → **Developer settings** → **Personal access tokens** → **Tokens (classic)**
   - Click **Generate new token (classic)**
   - Name: `AJC Bridge WordPress Sync`
   - Scopes: Select `repo` (Full control of private repositories)
   - Generate and copy the token
   - Paste into AJC Bridge settings

2. **Repository**: Format `owner/repo` (e.g., `pcescato/my-hugo-site`)

3. **Branch**: Branch to deploy to (e.g., `main`, `gh-pages`)

4. **Test Connection**: Click button to verify setup

#### Dev.to Configuration

1. **API Key**:
   - Go to dev.to → **Settings** → **Extensions** → **DEV Community API Keys**
   - Generate a new key
   - Copy and paste into AJC Bridge settings

2. **Test Connection**: Click button to verify API key

### Step 3: Optional Configuration

#### Hugo Configuration (General tab)

**Front Matter Template**: Customize the YAML/TOML header for your Hugo theme.

**Default YAML Template**:
```yaml
---
title: "{{title}}"
date: {{date}}
draft: false
author: "{{author}}"
slug: "{{slug}}"
id: {{id}}
images: ["{{image_avif}}", "{{image_webp}}", "{{image_original}}"]
---
```

**TOML Alternative**:
```toml
+++
title = "{{title}}"
date = {{date}}
draft = false
author = "{{author}}"
slug = "{{slug}}"
id = {{id}}
images = ["{{image_avif}}", "{{image_webp}}", "{{image_original}}"]
+++
```

**Available Placeholders**:
- `{{title}}` - Post title
- `{{date}}` - Publication date (ISO 8601 format)
- `{{author}}` - Author name
- `{{slug}}` - Post slug
- `{{id}}` - WordPress post ID
- `{{image_avif}}` - Featured image AVIF path
- `{{image_webp}}` - Featured image WebP path
- `{{image_original}}` - Featured image original path

#### Other Options

- **Content Types**: Select which content types to sync (posts, pages)
- **Debug Mode**: Enable detailed logging for troubleshooting
- **Clean Uninstall**: Remove all plugin data when uninstalling

### Step 4: Save and Test

1. Click **Save Changes**
2. Verify success messages for both tabs
3. Test GitHub/dev.to connections using test buttons

---

## 📖 Usage

### Publishing Your First Post

#### For All Strategies

1. Create a new post in WordPress
2. Add content and a featured image
3. Click **Publish**

#### For wordpress_devto and dual_github_devto Strategies

1. In the post editor sidebar, find the **Jamstack Publishing** meta box
2. Check **"Publish to dev.to"** to syndicate this post
3. Click **Publish** or **Update**

**Note**: The dev.to checkbox only appears in strategies that support dev.to syndication.

### Monitoring Sync Status

#### Posts List View

Navigate to **Posts** → **All Posts**. You'll see additional columns:

- **Sync Status**: Current sync state (✓ Synced, ⏳ Pending, ✗ Failed, etc.)
- **Last Sync**: Timestamp of last successful sync
- **Actions**: Quick actions (Sync Now, View on GitHub, etc.)

#### Individual Post

In the post editor, the **Jamstack Publishing** meta box shows:

- Current sync status
- Last sync timestamp
- GitHub commit URL (if applicable)
- Dev.to article URL (if applicable)
- **Sync Now** button for manual sync

### Bulk Sync

Navigate to **AJC Bridge** → **Settings** → **General** tab.

1. Scroll to **Bulk Actions**
2. Click **Sync All Published Posts**
3. Action Scheduler will process all posts in the background
4. Monitor progress in **Tools** → **Scheduled Actions**

### Manual Sync

#### Single Post

1. Edit the post or view **All Posts**
2. Click **Sync Now** in the meta box or quick actions
3. Wait for the success/error message

#### Retry Failed Syncs

1. Navigate to **AJC Bridge** → **Logs**
2. Find failed syncs
3. Click **Retry** on individual posts
4. Or use **Retry All Failed** for bulk retry

### Viewing Logs

Navigate to **AJC Bridge** → **Logs** (or enable Debug Mode in settings).

**Log Levels**:
- 🟢 **Info**: General operations
- ✅ **Success**: Successful syncs
- ⚠️ **Warning**: Non-critical issues
- 🔴 **Error**: Critical failures

**Log Location**: `/wp-content/uploads/ajc-bridge-logs/ajc-bridge-YYYY-MM-DD.log`

---

## 🏗️ Architecture

### Directory Structure

```
ajc-bridge/
├── ajc-bridge.php          # Main plugin file (bootstrap)
├── composer.json           # Composer dependencies
├── readme.txt              # WordPress.org readme
├── README.md               # GitHub readme (this file)
├── uninstall.php           # Clean uninstall handler
├── LICENSE                 # GPL v3 license
│
├── admin/                  # Admin UI classes
│   ├── class-admin.php             # Menu registration
│   ├── class-settings.php          # Settings page with tabs
│   ├── class-columns.php           # Custom post list columns
│   ├── class-post-meta-box.php     # Post editor meta box
│   └── assets/                     # Admin CSS/JS
│       ├── css/
│       │   ├── columns.css
│       │   └── settings.css
│       └── js/
│           ├── columns.js
│           └── settings.js
│
├── core/                   # Core business logic
│   ├── class-plugin.php            # Singleton bootstrap
│   ├── class-sync-runner.php       # Central sync orchestrator
│   ├── class-queue-manager.php     # Async queue abstraction
│   ├── class-logger.php            # Logging system
│   ├── class-git-api.php           # GitHub API client
│   ├── class-devto-api.php         # Dev.to API client
│   ├── class-media-processor.php   # Image processing (WebP/AVIF)
│   └── class-headless-redirect.php # Frontend redirect handler
│
├── adapters/               # Strategy pattern adapters
│   ├── interface-adapter.php       # Adapter interface
│   ├── class-hugo-adapter.php      # Hugo/Jekyll adapter
│   └── class-devto-adapter.php     # Dev.to adapter
│
├── cli/                    # WP-CLI commands (future)
│
├── includes/               # Helper functions
│
├── languages/              # i18n translation files
│
├── docs/                   # Documentation
│
└── vendor/                 # Composer dependencies
    ├── intervention/image
    ├── league/html-to-markdown
    └── woocommerce/action-scheduler
```

### Key Classes

#### Core Classes

| Class | Responsibility |
|-------|----------------|
| `Plugin` | Singleton bootstrap, hooks registration, WordPress integration |
| `Sync_Runner` | Central sync orchestrator, strategy selection, workflow coordination |
| `Queue_Manager` | Async queue abstraction layer for Action Scheduler |
| `Logger` | Centralized logging to files and admin UI |
| `Git_API` | GitHub REST API client (Trees API for atomic commits) |
| `DevTo_API` | Dev.to (Forem) API client |
| `Media_Processor` | Image processing with Intervention Image v3 (WebP/AVIF) |
| `Headless_Redirect` | Frontend redirect handler for headless modes |

#### Admin Classes

| Class | Responsibility |
|-------|----------------|
| `Admin` | Menu registration and coordination |
| `Settings` | Tabbed settings page, AJAX handlers, connection testing |
| `Columns` | Custom columns in post list view |
| `Post_Meta_Box` | Sidebar meta box in post editor |

#### Adapters

| Adapter | Strategy | Output |
|---------|----------|--------|
| `Hugo_Adapter` | GitHub-based | Markdown with YAML/TOML Front Matter |
| `DevTo_Adapter` | Dev.to-based | Markdown optimized for dev.to |

### Design Patterns

- **Singleton**: `Plugin` class for single instance
- **Strategy Pattern**: Adapters for different publishing platforms
- **Dependency Injection**: Loose coupling between components
- **Facade Pattern**: Simplified API for complex subsystems (Git_API, DevTo_API)
- **Observer Pattern**: WordPress hooks for event-driven architecture

### Data Flow

#### Publishing Workflow (GitHub Strategy)

```
User clicks "Publish"
    ↓
WordPress save_post hook
    ↓
Queue_Manager::enqueue($post_id)
    ↓
Action Scheduler (background job)
    ↓
Sync_Runner::run($post_id)
    ↓
Strategy selection (github_only)
    ↓
Hugo_Adapter::sync($post_id, $api)
    ├─→ Convert HTML to Markdown
    ├─→ Generate Front Matter
    ├─→ Process images (WebP/AVIF)
    └─→ Git_API::atomic_commit()
        ├─→ Create tree with all files
        └─→ Create commit
    ↓
Update post meta (_ajc_sync_status, _ajc_sync_last)
    ↓
Logger::success("Post synced")
```

#### Publishing Workflow (Dev.to Strategy)

```
User clicks "Publish" (with "Publish to dev.to" checked)
    ↓
WordPress save_post hook
    ↓
Queue_Manager::enqueue($post_id)
    ↓
Action Scheduler (background job)
    ↓
Sync_Runner::run($post_id)
    ↓
Strategy selection (wordpress_devto or dual_github_devto)
    ↓
Check post meta (_ajc_bridge_publish_devto)
    ↓
DevTo_Adapter::sync($post_id, $api)
    ├─→ Convert HTML to Markdown
    ├─→ Prepare article payload
    ├─→ Set canonical_url (if applicable)
    └─→ DevTo_API::create_or_update_article()
    ↓
Update post meta (_ajc_bridge_devto_id, _ajc_bridge_devto_url)
    ↓
Logger::success("Post synced to dev.to")
```

### Database Schema

#### Post Meta Keys

| Meta Key | Type | Description |
|----------|------|-------------|
| `_ajc_sync_status` | string | Current sync status (synced, pending, failed, etc.) |
| `_ajc_sync_last` | timestamp | Unix timestamp of last successful sync |
| `_ajc_file_path` | string | GitHub file path (e.g., `content/posts/hello-world.md`) |
| `_ajc_last_commit_url` | string | URL to GitHub commit |
| `_ajc_sync_start_time` | timestamp | Sync operation start time (for lock) |
| `_ajc_sync_timestamp` | timestamp | Queue enqueue timestamp |
| `_ajc_retry_count` | int | Number of retry attempts |
| `_ajc_bridge_publish_devto` | bool | Per-post dev.to sync flag |
| `_ajc_bridge_devto_id` | int | Dev.to article ID |
| `_ajc_bridge_devto_url` | string | Dev.to article URL |
| `_ajc_bridge_devto_sync_time` | timestamp | Last dev.to sync timestamp |
| `_wpjamstack_lock` | bool | Sync lock to prevent concurrent operations |

#### Options

| Option Key | Type | Description |
|------------|------|-------------|
| `ajc_bridge_settings` | array | Plugin settings (encrypted credentials, configuration) |
| `ajc_bridge_logs` | array | Recent log entries for admin UI |

---

## 🛠️ Development

### Local Development Setup

#### 1. Prerequisites

```bash
# Required
- PHP 8.1+
- Composer
- WordPress 6.9+ (local development environment)
- Git

# Recommended
- Local WordPress environment (Local by Flywheel, Laravel Valet, Docker)
- IDE with PHP support (VS Code, PHPStorm)
- GitHub account with a test repository
- Dev.to account with API key
```

#### 2. Install Dependencies

```bash
cd ajc-bridge
composer install
```

#### 3. Development Environment Variables

Create a `.env` file in the plugin root (development only):

```env
# GitHub Configuration
GITHUB_TOKEN=ghp_your_personal_access_token
GITHUB_REPO=username/repo
GITHUB_BRANCH=main

# Dev.to Configuration
DEVTO_API_KEY=your_devto_api_key

# WordPress Configuration (if needed)
WP_HOME=http://localhost:8080
WP_SITEURL=http://localhost:8080
```

**Note**: The plugin automatically loads `.env` when `WP_ENVIRONMENT_TYPE === 'development'`.

#### 4. Enable Debug Mode

In WordPress Admin:
- Navigate to **AJC Bridge** → **Settings** → **General**
- Enable **Debug Mode**
- Check logs at `/wp-content/uploads/ajc-bridge-logs/`

### Coding Standards

This plugin follows **WordPress Coding Standards**.

#### Run PHP_CodeSniffer

```bash
# Install PHPCS (if not installed)
composer global require "squizlabs/php_codesniffer=*"
composer global require "wp-coding-standards/wpcs=*"

# Configure PHPCS
phpcs --config-set installed_paths /path/to/wpcs

# Run standards check
phpcs --standard=WordPress ajc-bridge.php core/ admin/ adapters/
```

#### Key Standards

- **Indentation**: Tabs (not spaces)
- **Line Length**: 100 characters max (soft limit)
- **Naming**: `snake_case` for functions, `Class_Name` for classes
- **Documentation**: PHPDoc for all classes, methods, and functions
- **Type Hints**: Required for all method parameters and return types
- **Strict Types**: `declare(strict_types=1);` at the top of all files

### Testing

#### Manual Testing Checklist

**Strategy: GitHub Only**
- [ ] Publish new post → Verify commit on GitHub
- [ ] Update post → Verify update commit
- [ ] Delete post → Verify deletion commit
- [ ] Add featured image → Verify WebP/AVIF in GitHub
- [ ] Add content images → Verify all images uploaded
- [ ] Test bulk sync → Verify all posts synced
- [ ] Test frontend redirect → Verify 301 to GitHub Pages

**Strategy: Dev.to Only**
- [ ] Publish post → Verify article on dev.to
- [ ] Update post → Verify update on dev.to
- [ ] Check canonical URL handling
- [ ] Test frontend redirect → Verify 301 to dev.to

**Strategy: Dual Publishing**
- [ ] Publish with dev.to checkbox → Verify both platforms
- [ ] Publish without checkbox → Verify GitHub only
- [ ] Update post → Verify both platforms update
- [ ] Check canonical URLs (dev.to → GitHub)

**Strategy: WordPress + dev.to**
- [ ] Publish with checkbox → Verify dev.to syndication
- [ ] Publish without checkbox → Verify WordPress only
- [ ] Check canonical URL (dev.to → WordPress)
- [ ] Verify WordPress site remains public

#### Unit Testing (Future)

The plugin architecture supports unit testing with PHPUnit. This is planned for future development.

```bash
# Future command
composer test
```

### Building for Production

#### Create Release Zip

```bash
# Manual method
composer install --no-dev --prefer-dist --optimize-autoloader
zip -r ajc-bridge.zip . \
  -x "*.git*" \
  -x "*.github*" \
  -x "composer.json" \
  -x "composer.lock" \
  -x "node_modules/*" \
  -x "tests/*" \
  -x "docs/*"
```

#### Automated Release (GitHub Actions)

The plugin includes a GitHub Actions workflow for automated releases:

```bash
# Create and push a tag
git tag v1.3.0
git push origin v1.3.0

# GitHub Actions will:
# 1. Install PHP 8.1
# 2. Run composer install --no-dev
# 3. Create ajc-bridge.zip
# 4. Create GitHub Release with zip attached
```

See `.github/workflows/release.yml` for details.

### Contributing Workflow

1. **Fork the repository**
2. **Create a feature branch**: `git checkout -b feature/my-feature`
3. **Make changes** following WordPress Coding Standards
4. **Test thoroughly** using the manual testing checklist
5. **Commit with descriptive messages**: `git commit -m "Add: Feature description"`
6. **Push to your fork**: `git push origin feature/my-feature`
7. **Open a Pull Request** with detailed description

#### Commit Message Format

```
Type: Short description (50 chars max)

Detailed explanation if needed (wrap at 72 chars).

- Change 1
- Change 2
- Change 3

Fixes #123
```

**Types**: `Add`, `Fix`, `Update`, `Remove`, `Refactor`, `Docs`, `Security`

---

## 🔧 Troubleshooting

### Common Issues

#### 1. "Connection failed: Network error" (GitHub/Dev.to)

**Cause**: Invalid API credentials or network issues.

**Solution**:
- Verify GitHub Personal Access Token has `repo` scope
- Verify dev.to API key is correct and active
- Check repository format is `owner/repo` (not full URL)
- Test connection using built-in test buttons
- Check server can reach GitHub/Dev.to APIs (no firewall blocking)

```bash
# Test from server
curl -H "Authorization: token YOUR_TOKEN" https://api.github.com/user
curl -H "api-key: YOUR_KEY" https://dev.to/api/articles/me
```

#### 2. Images Not Syncing to GitHub

**Cause**: Imagick extension missing or AVIF support disabled.

**Solution**:
- Install Imagick extension: `apt-get install php-imagick` or `yum install php-imagick`
- Verify Imagick version: `php -r "echo Imagick::getVersion()['versionString'];"`
- Requires ImageMagick 7.0.8+ for AVIF support
- Fallback to GD if Imagick unavailable (WebP only, no AVIF)

```bash
# Check PHP extensions
php -m | grep -E "(imagick|gd)"

# Restart PHP-FPM after installing
systemctl restart php-fpm
```

#### 3. Sync Stuck in "Pending" Status

**Cause**: Action Scheduler not running or PHP errors.

**Solution**:
- Check Action Scheduler: **Tools** → **Scheduled Actions**
- Look for failed actions and error messages
- Enable **Debug Mode** in plugin settings
- Check logs: `/wp-content/uploads/ajc-bridge-logs/`
- Verify cron is working: **Tools** → **Site Health** → **Info** → **WordPress Constants** → `DISABLE_WP_CRON`

```php
// Add to wp-config.php to force cron (if needed)
define('DISABLE_WP_CRON', false);
```

#### 4. Frontend Redirects Not Working (Headless Mode)

**Cause**: Permalink structure or template hierarchy issues.

**Solution**:
- Flush permalinks: **Settings** → **Permalinks** → **Save Changes**
- Verify publishing strategy is set to headless mode (github_only, devto_only, dual_github_devto)
- Check redirect URL is correct in **General** tab
- Test as logged-out user (redirects don't apply to admins)
- Check for theme conflicts (temporarily switch to Twenty Twenty-Four)

#### 5. "Failed to read AVIF file contents"

**Cause**: File created but unreadable, or Imagick AVIF encoding failed.

**Solution**:
- Check `/tmp/ajc-bridge-images/` directory permissions: `chmod 755 /tmp/ajc-bridge-images`
- Verify Imagick AVIF support: `php -r "echo in_array('AVIF', Imagick::queryFormats()) ? 'YES' : 'NO';"`
- Update ImageMagick to 7.0.8+ if AVIF not supported
- AVIF is optional; WebP and original formats will still upload

#### 6. Bulk Sync Times Out

**Cause**: Too many posts processed at once, PHP timeout.

**Solution**:
- Bulk sync uses Action Scheduler (should not timeout)
- Check Action Scheduler status: **Tools** → **Scheduled Actions**
- Increase PHP `max_execution_time` if needed (default 30s)
- Monitor progress in Action Scheduler dashboard
- Failed posts can be retried individually

#### 7. Dev.to Sync Creates Duplicate Articles

**Cause**: Plugin lost track of dev.to article ID.

**Solution**:
- Check post meta for `_ajc_bridge_devto_id`
- If missing, manually add meta key with dev.to article ID
- Future syncs will update existing article instead of creating new one
- Delete duplicate articles manually on dev.to

### Debug Mode

Enable **Debug Mode** in **AJC Bridge** → **Settings** → **General** to capture detailed logs.

**Logs Include**:
- API requests/responses
- Image processing steps
- Markdown conversion
- Error stack traces

**Log Location**: `/wp-content/uploads/ajc-bridge-logs/ajc-bridge-YYYY-MM-DD.log`

### Getting Help

1. **Check Logs**: Enable Debug Mode and review logs
2. **Search Issues**: Check [GitHub Issues](https://github.com/pcescato/ajc-bridge/issues)
3. **WordPress Support**: Visit [WordPress.org plugin support](https://wordpress.org/support/plugin/ajc-bridge/)
4. **Open an Issue**: Provide WordPress version, PHP version, error logs, and steps to reproduce

---

## ❓ FAQ

### General Questions

#### Can I switch between publishing strategies?

Yes! You can change strategies at any time in **Settings** → **General** → **Publishing Strategy**. Existing synced content remains on external platforms.

#### Will my existing WordPress content be affected?

No. The plugin never modifies your WordPress database posts. It only adds custom post meta for tracking sync status.

#### Can I use this with existing Hugo sites?

Absolutely. The plugin creates Markdown files compatible with any static site generator. Customize the Front Matter template to match your theme's requirements.

#### Does this work with Gutenberg blocks?

Yes. The plugin converts Gutenberg block HTML to clean Markdown. Most common blocks are supported (paragraphs, headings, lists, images, code, quotes).

#### Can I sync custom post types?

Currently, the plugin supports posts and pages. Custom post type support can be added by filtering `ajc_bridge_supported_post_types` (developer feature).

### Publishing Strategy Questions

#### What happens if I disable sync in "WordPress Only" mode?

Your WordPress site remains fully functional and public. The plugin does nothing until you switch to an active strategy.

#### In wordpress_devto mode, is WordPress still public?

Yes! WordPress remains your public-facing site. The dev.to checkbox allows optional syndication on a per-post basis with canonical URLs pointing back to WordPress.

#### Can I unpublish posts from dev.to?

Yes. Uncheck "Publish to dev.to" and update the post. The plugin will unpublish the article on dev.to (set to draft or delete, depending on configuration).

#### What's the difference between github_only and dual_github_devto?

- **github_only**: Posts sync to GitHub only. No dev.to integration.
- **dual_github_devto**: Posts sync to GitHub (canonical) with optional per-post syndication to dev.to. Dev.to articles include canonical_url pointing to your Hugo site.

### Technical Questions

#### Does this require WP-CLI?

No. The plugin works entirely through the WordPress admin interface. WP-CLI support is planned for future versions.

#### Can I customize the Markdown output?

Yes. The plugin includes filters for customizing Markdown conversion:
- `ajc_bridge_markdown_content`: Modify converted Markdown
- `ajc_bridge_front_matter`: Modify Front Matter before save

#### How are images handled?

- Featured images are processed separately with WebP and AVIF variants
- Content images are extracted from HTML and processed
- Original, WebP, and AVIF formats uploaded to GitHub
- Images stored in `static/images/{post_id}/` on GitHub

#### What's the performance impact?

Minimal. All sync operations run in the background using Action Scheduler. Visitors experience no delays. The plugin uses transients for caching and optimized API calls (GitHub Trees API reduces calls by ~70%).

#### Is this compatible with multisite?

Not currently tested or supported. Single-site WordPress installations only.

#### Can I run this on shared hosting?

Yes, if your host meets the requirements (PHP 8.1+, Imagick/GD, no exec() restrictions). However, Action Scheduler requires WordPress cron to work properly. Some shared hosts disable `wp-cron.php`.

### Security Questions

#### Are my API keys secure?

Yes. GitHub and dev.to credentials are encrypted using WordPress's `wp_salt()` and stored in the database. Tokens are never exposed in the admin UI (masked as `ghp_***...***`).

#### Where are logs stored?

Logs are stored in `/wp-content/uploads/ajc-bridge-logs/` with an `.htaccess` file to prevent direct access. Logs contain no sensitive data (tokens are never logged).

#### Can authors access plugin settings?

No. Only Administrators can access plugin settings and credentials. Authors can sync their own posts and view their sync status.

#### What data is sent to external services?

**GitHub**: Repository name, branch, Markdown files, images, commit messages, your Personal Access Token (for auth).  
**Dev.to**: Article title, content, tags, cover image, canonical URL, your API key (for auth).

No tracking or analytics data is collected by the plugin itself.

---

## 🤝 Contributing

Contributions are welcome! Whether it's bug reports, feature requests, documentation improvements, or code contributions, your help is appreciated.

### How to Contribute

1. **Report Bugs**: Open an issue on [GitHub Issues](https://github.com/pcescato/ajc-bridge/issues) with detailed reproduction steps
2. **Request Features**: Describe the feature, use case, and why it would benefit users
3. **Submit Pull Requests**: Follow the [Contributing Workflow](#contributing-workflow) above
4. **Improve Documentation**: Fix typos, add examples, clarify explanations
5. **Translate**: Help translate the plugin into other languages (i18n)

### Development Priorities

Current priorities for future versions:

- **WP-CLI Support**: Command-line interface for bulk operations
- **Custom Post Type Support**: Sync custom post types beyond posts/pages
- **Multisite Compatibility**: Support for WordPress multisite networks
- **GitLab Support**: Extend beyond GitHub to GitLab, Bitbucket
- **Unit Tests**: PHPUnit test suite for core functionality
- **Scheduled Sync**: Automatic periodic sync for updated posts
- **Media Library Sync**: Sync entire media library to GitHub
- **Translation Ready**: Complete i18n coverage for all strings

### Code of Conduct

Be respectful, inclusive, and professional. Harassment or discriminatory behavior will not be tolerated.

---

## 📄 License

This plugin is licensed under the **GNU General Public License v3.0 or later**.

```
AJC Bridge - WordPress to Jamstack publishing plugin
Copyright (C) 2024-2026 Pascal CESCATO

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <https://www.gnu.org/licenses/>.
```

**Full License**: See [LICENSE](LICENSE) file.

---

## 🙏 Acknowledgments

- **WordPress Community**: For the amazing platform and coding standards
- **Action Scheduler**: For reliable background job processing
- **Intervention Image**: For powerful image manipulation capabilities
- **League HTML-to-Markdown**: For clean Markdown conversion
- **Contributors**: Everyone who has reported bugs, suggested features, or contributed code

---

## 📞 Support & Links

- **Plugin Homepage**: [https://github.com/pcescato/ajc-bridge](https://github.com/pcescato/ajc-bridge)
- **Documentation**: This README and [readme.txt](readme.txt)
- **Issues**: [GitHub Issues](https://github.com/pcescato/ajc-bridge/issues)
- **WordPress.org**: (Coming soon after approval)
- **Author**: Pascal CESCATO - [GitHub Profile](https://github.com/pcescato)

---

## 🗺️ Roadmap

### Version 1.5.0 (Planned)

- [ ] WP-CLI commands (`wp ajc-bridge sync`, `wp ajc-bridge status`)
- [ ] Improved error recovery with automatic retry
- [ ] Media library bulk sync
- [ ] Custom post type support via filter
- [ ] Performance optimizations for large sites (5000+ posts)
- [ ] GitLab adapter (in addition to GitHub)
- [ ] Enhanced dev.to features (series support, cover images)

### Version 2.0.0 (Future)

- [ ] Full i18n coverage with translations (French, Spanish, German)
- [ ] Multisite compatibility
- [ ] PHPUnit test suite
- [ ] Scheduled periodic sync (background updates)
- [ ] Visual sync history dashboard
- [ ] Webhook support for external triggers
- [ ] REST API endpoints for external integrations

---

## 📊 Stats & Info

- **Current Version**: 1.3.0
- **WordPress Version**: 6.9+
- **PHP Version**: 8.1+
- **Lines of Code**: ~5,000+ PHP
- **Active Installs**: (Pending WordPress.org approval)
- **Last Updated**: February 2026

---

**Thank you for using AJC Bridge!** 🚀

If you find this plugin helpful, please consider:
- ⭐ Starring the [GitHub repository](https://github.com/pcescato/ajc-bridge)
- 📝 Writing a review on WordPress.org (after approval)
- 🐛 Reporting bugs and suggesting features
- 🤝 Contributing code or documentation

Happy publishing! 📚✨
