# Troubleshooting Guide

## Common Issues

### Installation & Activation

#### Plugin doesn't appear in list after upload
**Problem:** Uploaded ZIP but plugin not visible in WordPress Plugins page.

**Solution:**
1. Check that ZIP was extracted to: `/wp-content/plugins/ajc-bridge/`
2. Verify folder name is exactly `ajc-bridge` (not `ajc-bridge-main`)
3. Check `/wp-content/plugins/ajc-bridge/wp-jamstack-sync.php` exists
4. Try deactivating all plugins, then reactivate
5. Clear browser cache (CTRL+F5 or CMD+SHIFT+R)

---

#### "Plugin could not be activated" error
**Problem:** Error message when trying to activate.

**Causes & solutions:**
- **Missing PHP extensions:** Check `Settings` → `System Status`
  - Need: `json`, `curl`, `openssl`
  - Optional: `imagick` (or `gd` fallback)
  
- **WordPress version too old:** Requires 6.9+
  - Check: WordPress admin → `About WordPress`
  - Update if needed
  
- **PHP version too old:** Requires 8.1+
  - Check: `Settings` → `General` → `PHP Version`
  - Contact hosting provider to upgrade

- **Fatal PHP error:** Check `/wp-content/debug.log`
  - Enable debug in `wp-config.php`:
  ```php
  define('WP_DEBUG', true);
  define('WP_DEBUG_DISPLAY', false);
  define('WP_DEBUG_LOG', true);
  ```
  - View logs at `/wp-content/debug.log`

---

### Credentials & Connection

#### "Cannot connect to GitHub" error
**Problem:** Test GitHub connection fails with error.

**Causes & solutions:**
- **Invalid token:** Token may be expired or revoked
  - Go to GitHub → Settings → Developer settings → Tokens
  - Check token hasn't been revoked
  - Generate new token if needed
  
- **Token doesn't have `repo` scope:**
  - Regenerate token with `repo` scope selected
  - Paste new token to WordPress
  
- **Wrong repository:**
  - Check **Settings** → **General** → **Repository URL**
  - Format: `username/repository` (not full GitHub URL)
  - Example: `pcescato/my-blog` (not `https://github.com/pcescato/my-blog`)
  
- **Network blocked:** Contact hosting provider
  - Some hosts block outbound GitHub API calls
  - May need to whitelist `api.github.com`

---

#### "Cannot connect to dev.to" error
**Problem:** Test dev.to connection fails.

**Causes & solutions:**
- **Invalid API key:** Key may be revoked
  - Go to dev.to → Settings → Account
  - Find API Keys section
  - Generate new key if needed
  - Paste new key to WordPress
  
- **Using wrong account:** Multiple dev.to accounts?
  - Verify you're logged into correct dev.to account
  - Each account has different API key
  
- **Network blocked:** Contact hosting provider
  - Some hosts block dev.to API
  - May need whitelist: `dev.to/api`

---

### Sync Issues

#### "Sync never runs" or "Status stays processing"
**Problem:** Posted a draft but sync doesn't happen.

**Causes & solutions:**
- **Check strategy selected:**
  - `wordpress_only` → No sync (intentional)
  - Others → Should sync automatically
  
- **Check post is published:**
  - Draft posts don't sync
  - Must click "Publish" (not "Save draft")
  - Scheduled posts sync when date arrives
  
- **Check dev.to checkbox (if applicable):**
  - Strategy `wordpress_devto`: Must check "Publish to dev.to"
  - Strategy `dual_github_devto`: Must check "Publish to dev.to"
  
- **Action Scheduler not running:**
  - WordPress background jobs may be disabled
  - Check: WordPress admin → **Action Scheduler** → **Failed Actions**
  - Contact hosting provider if loopback requests blocked

**Debug steps:**
1. Go to **Settings** → **General** → Check "Enable Debug Mode"
2. Open **AJC Bridge** → **Logs** (if available)
3. Check `/wp-content/debug.log` for detailed errors
4. Share relevant logs with support

---

#### "Sync failed: API error"
**Problem:** Sync starts but fails with API error.

**Common errors:**
- **HTTP 401 (Unauthorized):**
  - Token expired or invalid
  - Generate new token, update settings
  
- **HTTP 403 (Forbidden):**
  - Token doesn't have write access
  - Regenerate with `repo` scope
  
- **HTTP 404 (Not Found):**
  - Repository doesn't exist or is private
  - Check repository URL
  - Verify GitHub token can access repo
  
- **HTTP 422 (Validation Error):**
  - Content validation failed
  - Common: Filename conflict in GitHub
  - Check branch for duplicate file
  
- **HTTP 429 (Rate Limited):**
  - Made too many API requests
  - Wait 1 hour, retry
  - Reduce bulk sync frequency

**Solution steps:**
1. Note the HTTP code
2. Check logs for full error message
3. Verify credentials in **Settings** → **Credentials**
4. Test connection again
5. Retry sync

---

#### "Image optimization failed"
**Problem:** Post syncs but images don't optimize.

**Causes & solutions:**
- **Missing Imagick extension:**
  - Install Imagick (ImageMagick 7.0.8+)
  - Contact hosting provider
  - GD is fallback but limited
  
- **Image format not supported:**
  - Supported: JPEG, PNG, WebP, GIF
  - Some formats may fail
  - Try converting image in WordPress first
  
- **File size too large:**
  - Image > 100MB may timeout
  - Compress before uploading
  
- **Permission denied:**
  - WordPress can't write to `/uploads/`
  - Check directory permissions (755)
  - Contact hosting provider

**Debug:**
- Enable Debug Mode
- Check logs for Intervention Image errors
- Test with simple PNG image first

---

#### "Front matter not generated correctly"
**Problem:** GitHub/dev.to has wrong metadata.

**Causes & solutions:**
- **Custom template overrode defaults:**
  - Go to **Settings** → **General** → **Front Matter Template**
  - Check template syntax
  - Use placeholders: `{{title}}`, `{{date}}`, `{{slug}}`
  
- **Wrong SSG type selected:**
  - Hugo uses different format than Astro
  - Verify **Settings** → **General** → **SSG Type**
  
- **Special characters in title:**
  - YAML requires quotes for special chars
  - If title has `:` or `"`, wrap in quotes
  - Example: `title: "My Title: A Story"`

---

### Dev.to Specific

#### "Article created as draft but not publishing"
**Problem:** Article stuck as draft on dev.to.

**Solution:**
- Manually publish on dev.to
- On next WordPress update, status preserved
- OR edit `published: true` in dev.to markdown front matter

---

#### "Published status resets to draft"
**Problem:** Published article reverts to draft after WordPress update.

**This was a known bug, now fixed:**
1. Update plugin to latest version
2. Next sync will preserve dev.to publish status
3. Check logs: "Fetched current Dev.to published status"

---

### GitHub / Static Site Issues

#### "Commits have wrong branch"
**Problem:** Code pushes to wrong branch (not `main` or `gh-pages`).

**Solution:**
- Go to **Settings** → **General** → **Branch**
- Verify you selected correct branch
- Ensure GitHub Pages points to that branch
- In GitHub: **Settings** → **Pages** → **Source** → Select same branch

---

#### "Images not showing on site"
**Problem:** Site built but images missing.

**Causes:**
- **Wrong image path in front matter:**
  - Hugo: `/images/1/featured.webp` or `images/1/featured.webp`
  - Astro: `/image/featured.avif`
  - Jekyll: `/assets/images/featured.webp`
  
- **Images not pushed to GitHub:**
  - Check repo on GitHub.com
  - Verify `static/images/` or `public/image/` folder exists
  
- **Wrong file extension:**
  - Check front matter for `.webp` but AVIF was generated
  - Markdown should reference `.webp` for broad browser support

---

#### "Site doesn't deploy after commit"
**Problem:** Commit pushed but GitHub Pages doesn't update.

**Solution:**
- Check GitHub Actions: **Actions** tab → **Workflows** → **Pages**
- Look for failed builds
- Common issues:
  - Hugo build failed (syntax error in content)
  - Jekyll build failed (invalid front matter)
  - Workflow disabled
  
- If disabled, re-enable in **Settings** → **Pages**

---

## Debug Mode

### Enable Debug Logging

1. Go to **AJC Bridge** → **Settings** → **General**
2. Check **Enable Debug Mode**
3. Click **Save Settings**
4. All sync operations log to `/wp-content/debug.log`

### Check Logs

**Method 1: WordPress Admin** (if plugin provides UI)
- Go to **AJC Bridge** → **Logs**
- View real-time sync status

**Method 2: SSH Access**
```bash
# SSH into server
tail -f /path/to/wp-content/debug.log

# Watch live:
tail -100 debug.log | grep "AJC Bridge"

# Search for errors:
grep "ERROR" debug.log | tail -20
```

### Log Output Example

```
[2026-02-17 13:08:36] [INFO] Starting sync process {"post_id":8,"title":"My Post"}
[2026-02-17 13:08:36] [INFO] Publishing strategy determined {"strategy":"github_only"}
[2026-02-17 13:08:37] [INFO] Fetched current Dev.to published status {"published":true}
[2026-02-17 13:08:38] [INFO] Converting post to Dev.to markdown {"post_id":8}
[2026-02-17 13:08:39] [INFO] Markdown conversion complete {"length":2847}
[2026-02-17 13:08:40] [INFO] Dev.to article updated {"article_id":258}
[2026-02-17 13:08:41] [INFO] Sync complete - success
```

---

## Getting Help

If you've tried the above:

1. **Enable Debug Mode** and run sync
2. **Collect logs** from `/wp-content/debug.log`
3. **Note error details:**
   - HTTP status code
   - Error message
   - Which step failed (credentials, markdown, API)
4. **Check:** Do you have latest version?
   - WordPress admin → Plugins → Check AJC Bridge version
   - Compare with [Releases](https://github.com/pcescato/ajc-bridge/releases)
5. **Report issue:**
   - Include plugin version
   - Include relevant log excerpts (redact tokens!)
   - Describe steps to reproduce
   - Go to [GitHub Issues](https://github.com/pcescato/ajc-bridge/issues)

---

## FAQ

### Q: Can I use AJC Bridge with a private GitHub repo?
**A:** Yes. Ensure your token has `repo` scope (includes private repo access).

### Q: What happens if I delete a post?
**A:** Posts deleted from WordPress are automatically removed from GitHub/dev.to (files deleted, articles archived).

### Q: Can I edit posts on GitHub instead of WordPress?
**A:** Not automatically, but you can:
1. Edit markdown on GitHub
2. WordPress continues to work (separate CMS)
3. Next WordPress update will override GitHub changes
**Recommendation:** Edit in WordPress, GitHub as read-only backup.

### Q: How often does sync run?
**A:** Immediately when you publish. Action Scheduler runs in background.

### Q: Can I bulk-update old posts?
**A:** Yes. Go to **AJC Bridge** → **Monitoring** → **Bulk Sync** (if available).

### Q: What if my API token expires?
**A:** Sync fails. Generate new token, update WordPress settings, retry.

### Q: Is my token secure?
**A:** Yes. Encrypted in WordPress database using native encryption. Never logged or transmitted unencrypted.

---

**Still stuck?** [Open an issue →](https://github.com/pcescato/ajc-bridge/issues)
