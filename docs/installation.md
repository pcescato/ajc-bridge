# Installation Guide

## System Requirements

- **WordPress**: 6.9 or higher
- **PHP**: 8.1 or higher
- **PHP Extensions**:
  - `json` (required)
  - `curl` (required)
  - `openssl` (required for encryption)
  - `imagick` (recommended for image processing) or `gd` (fallback)

### Note on Image Processing

The plugin uses `intervention/image` v3 which requires:
- **Imagick** extension with AVIF support (ImageMagick 7.0.8+) for full quality
- **GD** fallback available but with reduced AVIF compatibility

## Installation Methods

### Method 1: WordPress Admin (Recommended)

1. Download the latest `ajc-bridge.zip` from [Releases](https://github.com/pcescato/ajc-bridge/releases)
2. Go to WordPress Admin → **Plugins** → **Add New** → **Upload Plugin**
3. Choose `ajc-bridge.zip` and click **Install Now**
4. Click **Activate**
5. Navigate to **AJC Bridge** → **Settings** to configure

### Method 2: Manual Installation

1. Download and extract `ajc-bridge.zip`
2. Upload the `ajc-bridge` folder to `/wp-content/plugins/`
3. Activate the plugin through the **Plugins** menu in WordPress
4. Navigate to **AJC Bridge** → **Settings** to configure

### Method 3: Git Clone (for Developers)

```bash
# Clone the repository
git clone https://github.com/pcescato/ajc-bridge.git
cd ajc-bridge

# Install Composer dependencies
composer install --no-dev --prefer-dist --optimize-autoloader

# Symlink to WordPress plugins directory
ln -s $(pwd) /path/to/wordpress/wp-content/plugins/ajc-bridge
```

## Automatic Updates

AJC Bridge integrates with GitHub releases. Once activated:

1. Go to WordPress → **Plugins**
2. New versions appear automatically with update notifications
3. Click **Update** to install latest version
4. No breaking changes between minor versions

## First-Time Setup

### 1. Access Settings

Navigate to **AJC Bridge** → **Settings** in the WordPress admin menu.

You'll see two tabs:
- **General** — Publishing strategy and SSG configuration
- **Credentials** — GitHub and dev.to API keys

### 2. Choose Your Publishing Strategy

See [Workflows](workflows.md) for detailed strategy explanations.

Options:
- `wordpress_only` — Traditional WordPress site
- `wordpress_devto` — WordPress + dev.to syndication
- `github_only` — Headless with GitHub Pages
- `devto_only` — WordPress + dev.to
- `dual_github_devto` — GitHub + dev.to with canonical URLs

### 3. Add Credentials

#### GitHub Setup

1. Go to GitHub → **Settings** → **Developer settings** → **Personal access tokens** → **Tokens (classic)**
2. Click **Generate new token (classic)**
3. Set:
   - **Token name**: `AJC Bridge WordPress Sync`
   - **Scopes**: Select `repo` (Full control)
4. Generate and copy the token
5. Paste into AJC Bridge **Credentials** tab → **GitHub Token**

#### Dev.to Setup (if using dev.to strategy)

1. Go to dev.to → **Settings** → **Account** → **Dev Community**
2. Scroll to **API Keys** section
3. Copy your API key
4. Paste into AJC Bridge **Credentials** tab → **Dev.to API Key**

### 4. Test Connection

In the **General** tab, click:
- **Test GitHub Connection** — Verifies GitHub token
- **Test Dev.to Connection** — Verifies dev.to API key

Both should show green ✓ checkmarks.

### 5. Configure Your Static Site

For Hugo/Jekyll/Astro, see [Adapters](adapters.md) for SSG-specific configuration.

Key settings:
- **Repository URL** — Your GitHub repo (`username/repo-name`)
- **Branch** — Where to deploy (`main`, `gh-pages`, etc.)
- **SSG Type** — Select Hugo, Astro, or Jekyll
- **Site URL** — Your deployed site URL

### 6. Save Settings

Click **Save Settings** at the bottom. You should see a green success message.

## Troubleshooting Installation

### Plugin doesn't appear in list
- Ensure you extracted the ZIP file completely
- Check that folder is named `ajc-bridge` (not `ajc-bridge-main`)
- Verify `wp-content/plugins/ajc-bridge/` exists

### "Cannot connect to GitHub"
- Go to **Settings** → **Credentials**
- Verify GitHub token is valid and hasn't been revoked
- Token should have `repo` scope

### "Missing required PHP extensions"
- Contact your hosting provider
- Request Imagick or GD extension installation
- Cannot proceed without at least `curl` and `openssl`

### "Activation fails silently"
- Check `wp-content/debug.log` for errors
- Verify PHP version is 8.1+
- Check WordPress version is 6.9+

---

**Next:** [Configure your settings →](configuration.md)
