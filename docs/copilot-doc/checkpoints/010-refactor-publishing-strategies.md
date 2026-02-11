<overview>
The user is refactoring the WP Jamstack Sync plugin to support 5 distinct publishing strategies with post-level sync control. The goal is to accommodate multiple use cases: traditional WordPress sites that syndicate to dev.to, headless WordPress for JAMstack workflows, and hybrid approaches. The plugin previously had a simpler 2-mode system (GitHub or Dev.to) but now needs to handle wordpress_only, wordpress_devto, github_only, devto_only, and dual_github_devto strategies, with per-post checkboxes for optional dev.to syndication in certain modes. I'm implementing new classes (Post_Meta_Box, Headless_Redirect), refactoring the sync runner routing logic, and preparing to overhaul the settings UI to expose these options.
</overview>

<history>
1. **User reported Dev.to posts not appearing, only GitHub sync happening**
   - Diagnosed: adapter_type was set to 'hugo' instead of 'devto'
   - Added routing decision logging to Sync_Runner
   - Confirmed fix worked after user changed settings

2. **User requested GitHub token double-encryption bug fix**
   - Root cause: ajax_test_devto_connection() called update_option() which triggered sanitize_settings(), causing encrypted tokens to be re-encrypted (88 → 172 chars)
   - Fixed by replacing update_option() with WordPress filter to temporarily override option
   - Added token detection logic: check if token starts with 'github_pat_' or 'ghp_' (user's suggestion - much simpler than length/base64 checks)
   - Added enhanced logging in Git_API constructor
   - User confirmed: "works fine!"

3. **User requested Dev.to posts always sync as drafts**
   - Modified adapters/class-devto-adapter.php line 66
   - Changed `'published' => 'publish' === $post->post_status` to `'published' => false`
   - Rationale: Allows manual review on dev.to before publishing

4. **User requested tab name change**
   - Changed admin/class-settings.php line 921
   - "GitHub Credentials" → "Credentials"
   - Makes sense since tab now contains both GitHub and Dev.to credentials

5. **User provided Plugin Check report with errors**
   - Fixed ABSPATH protection in token-diagnostic.php and token-recovery.php
   - Replaced all parse_url() with wp_parse_url() in admin/class-settings.php (1 instance) and adapters/class-devto-adapter.php (3 instances)
   - WordPress standard for cross-PHP-version compatibility

6. **User requested removal of diagnostic tool files**
   - Deleted token-diagnostic.php and token-recovery.php
   - These were temporary debugging tools for the double-encryption bug, no longer needed

7. **MAJOR REFACTOR: User requested 5 publishing strategies with post-level control**
   - Created admin/class-post-meta-box.php (complete) - adds "Publish to dev.to" checkbox in post sidebar for wordpress_devto and dual_github_devto modes
   - Created core/class-headless-redirect.php (complete) - redirects WordPress frontend to GitHub Pages or dev.to in headless modes
   - Updated core/class-plugin.php to load new classes
   - Refactored core/class-sync-runner.php run() method with switch statement for 5 strategies
   - Added migrate_old_settings() method for backward compatibility
   - Updated adapters/class-devto-adapter.php convert() and get_front_matter() to accept ?string $canonical_url parameter
   - **IN PROGRESS: Need to refactor admin/class-settings.php** to add publishing_strategy field with 5 radio buttons, plus wordpress_site_url, github_pages_url, devto_username fields
</history>

<work_done>
Files created:
- admin/class-post-meta-box.php (complete, 5478 bytes)
  - Meta box with "Publish to dev.to" checkbox
  - Only shows in wordpress_devto and dual_github_devto modes
  - Saves to _atomic_jamstack_publish_devto post meta
  - Shows sync status and dev.to article link

- core/class-headless-redirect.php (complete, 6595 bytes)
  - Redirects frontend in headless modes (github_only, devto_only, dual_github_devto)
  - Doesn't redirect admin, logged-in users, or AJAX
  - Shows configuration notice if redirect URLs not set
  - Routes to GitHub Pages or dev.to based on strategy

Files modified:
- core/class-plugin.php
  - Added require for class-headless-redirect.php (line 91)
  - Added Headless_Redirect::init() (line 105)
  - Added require for class-post-meta-box.php (line 119)
  - Added Post_Meta_Box::init() (line 123)

- core/class-sync-runner.php
  - Refactored run() method (lines 32-168) with 5-strategy switch statement
  - Added migrate_old_settings() method (lines 170-194) for backward compatibility
  - Updated sync_to_devto() signature to accept ?string $canonical_url parameter (line 281)
  - Canonical URL now passed from caller, not retrieved inside method

- adapters/class-devto-adapter.php
  - Updated convert() method signature to accept ?string $canonical_url parameter (line 34)
  - Updated get_front_matter() signature to accept ?string $canonical_url parameter (line 63)
  - Removed get_canonical_url() method call - canonical now passed as parameter (line 78)
  - Changed from retrieving canonical internally to receiving it as parameter

Work completed:
- ✅ Post meta box for per-post dev.to sync control
- ✅ Headless redirect handler
- ✅ Sync runner refactored for 5 strategies
- ✅ Dev.to adapter accepts canonical URL parameter
- ✅ Backward compatibility migration
- ⏳ **IN PROGRESS:** Settings UI refactor - need to add publishing_strategy field and new URL fields

Current state:
- New architecture implemented in core logic
- Settings UI still has old adapter_type/devto_mode fields
- Need to add publishing_strategy radio buttons
- Need to add wordpress_site_url, github_pages_url, devto_username fields
- All syntax validated, no errors
</work_done>

<technical_details>
**5 Publishing Strategies:**

| Strategy | WP Frontend | GitHub | Dev.to | Canonical | Dev.to Checkbox |
|----------|-------------|--------|--------|-----------|-----------------|
| wordpress_only | Public | Never | Never | WordPress | N/A |
| wordpress_devto | Public | Never | Optional | WordPress | Yes (per-post) |
| github_only | Headless | Always | Never | GitHub Pages | No |
| devto_only | Headless | Never | Always | dev.to | No |
| dual_github_devto | Headless | Always | Optional | GitHub Pages | Yes (per-post) |

**Key Insight:** In wordpress_devto and dual_github_devto modes, dev.to sync is **opt-in per post** via checkbox in meta box. Not automatic!

**Backward Compatibility Migration:**
- Old: adapter_type='hugo' → New: publishing_strategy='github_only'
- Old: adapter_type='devto', devto_mode='primary' → New: 'devto_only'
- Old: adapter_type='devto', devto_mode='secondary' → New: 'dual_github_devto'
- Automatic migration in migrate_old_settings() method

**Canonical URL Patterns:**
- wordpress_devto: `{wordpress_site_url}/{post_name}`
- github_only/dual: `{github_pages_url}/posts/{post_name}`
- devto_only: No canonical (dev.to is primary)

**Post Meta Keys:**
- `_atomic_jamstack_publish_devto` - '1' or '0' - controls per-post dev.to sync
- `_devto_article_id` - Stored dev.to article ID
- `_devto_article_url` - Stored dev.to article URL
- `_jamstack_sync_status` - 'success', 'error', 'processing'
- `_jamstack_sync_last` - Unix timestamp

**Headless Redirect Behavior:**
- Only redirects if strategy in [github_only, devto_only, dual_github_devto]
- Never redirects admin, logged-in users, AJAX, or REST API
- 301 permanent redirect
- Shows configuration notice if redirect URLs not configured

**Dev.to Adapter Changes:**
- convert() now accepts ?string $canonical_url parameter
- If null, no canonical_url added to front matter
- If provided, adds canonical_url to front matter
- Always sets published=false (manual review on dev.to)

**Settings Structure (Planned):**
```php
array(
    'publishing_strategy' => 'wordpress_devto|github_only|devto_only|dual_github_devto|wordpress_only',
    'wordpress_site_url' => 'https://mysite.com', // For wordpress_devto canonical
    'github_pages_url' => 'https://username.github.io/repo', // For github modes
    'devto_username' => 'username', // For devto_only redirects
    // ... existing fields ...
)
```

**Critical Settings Merge Pattern:**
Always use this pattern to prevent data loss across tabs:
```php
$existing_settings = get_option('atomic_jamstack_settings', array());
$sanitized = array();
// ... sanitize input fields ...
return array_merge($existing_settings, $sanitized);
```

**Unresolved Questions:**
- Should wordpress_devto mode disable GitHub sync entirely, or allow optional GitHub backup?
- Should there be a way to bulk-enable dev.to checkbox for existing posts?
- What happens to posts that were synced under old settings after migration?
</technical_details>

<important_files>
- **admin/class-post-meta-box.php** (NEW, complete)
  - Adds "Jamstack Publishing" meta box to post editor sidebar
  - Shows only in wordpress_devto and dual_github_devto modes
  - Checkbox controls _atomic_jamstack_publish_devto post meta
  - Displays last sync status and dev.to article link
  - Lines 36-52: Conditional meta box registration
  - Lines 64-165: Meta box rendering with nonce security
  - Lines 176-193: Save handler with security checks

- **core/class-headless-redirect.php** (NEW, complete)
  - Handles frontend redirects for headless WordPress modes
  - Lines 31-89: maybe_redirect() - main redirect logic
  - Lines 99-115: get_redirect_base() - determines destination URL
  - Lines 124-152: get_redirect_path() - builds path for post/archive
  - Lines 163-225: show_headless_notice() - configuration required page

- **core/class-sync-runner.php** (modified)
  - Lines 42-168: Refactored run() method with 5-strategy switch
  - Lines 60-78: wordpress_only case (sync disabled)
  - Lines 80-111: wordpress_devto case (check meta, use WP canonical)
  - Lines 113-118: github_only case (always sync to GitHub)
  - Lines 120-125: devto_only case (sync to dev.to, no canonical)
  - Lines 127-151: dual_github_devto case (GitHub always, dev.to optional)
  - Lines 170-194: migrate_old_settings() - backward compatibility
  - Line 281: sync_to_devto() now accepts ?string $canonical_url

- **adapters/class-devto-adapter.php** (modified)
  - Line 34: convert() signature updated with ?string $canonical_url
  - Line 36: Passes canonical_url to get_front_matter()
  - Line 63: get_front_matter() signature updated with ?string $canonical_url
  - Lines 77-79: Conditional canonical_url addition (only if provided)
  - Removed internal get_canonical_url() call - now uses parameter

- **core/class-plugin.php** (modified)
  - Line 91: Added require for class-headless-redirect.php
  - Line 105: Added Headless_Redirect::init()
  - Line 119: Added require for class-post-meta-box.php
  - Line 123: Added Post_Meta_Box::init()

- **admin/class-settings.php** (needs major refactoring - NOT DONE YET)
  - Currently has adapter_type field (lines 126-131, render at 690-713)
  - Currently has devto_mode field (lines 225-237, render at 831-875)
  - NEEDS: publishing_strategy field with 5 radio buttons
  - NEEDS: wordpress_site_url field
  - NEEDS: github_pages_url field  
  - NEEDS: devto_username field
  - NEEDS: Updated sanitization for new fields
  - NEEDS: Preserve existing fields in array_merge pattern
</important_files>

<next_steps>
**Immediate next step:**
Complete the settings UI refactor in admin/class-settings.php. This is the final major piece needed to make the 5-strategy system functional.

**Required changes to admin/class-settings.php:**

1. **Replace adapter_type field (lines 126-131, 690-713) with publishing_strategy:**
   - Add 5 radio buttons: wordpress_only, wordpress_devto, github_only, devto_only, dual_github_devto
   - Include descriptions for each mode
   - Default to 'wordpress_only' for safety

2. **Add new settings fields in General section:**
   - wordpress_site_url (defaults to get_site_url())
   - Helper text: "Your WordPress site URL for canonical URLs when syndicating to dev.to"

3. **Add new fields in GitHub section:**
   - github_pages_url (text input, placeholder: "https://username.github.io/repo")
   - Helper text: "Your deployed Hugo/Jekyll site URL for canonical URLs and redirects"

4. **Add new field in Dev.to section:**
   - devto_username (text input)
   - Helper text: "Your dev.to username for frontend redirects in dev.to-only mode"

5. **Update sanitization logic:**
   - Sanitize publishing_strategy with whitelist of 5 values
   - Sanitize wordpress_site_url with esc_url_raw()
   - Sanitize github_pages_url with esc_url_raw()
   - Sanitize devto_username with sanitize_text_field()
   - Ensure array_merge pattern preserves all existing fields

6. **Remove or deprecate old fields:**
   - Can keep adapter_type and devto_mode for backward compatibility
   - Or remove them entirely and rely on migrate_old_settings()

**After settings UI complete:**
- Test all 5 strategies
- Test post-level dev.to checkbox
- Test headless redirects
- Verify backward compatibility for existing users
- Update documentation

**Testing checklist from requirements:**
- [ ] WordPress Only mode: No sync, no meta box
- [ ] WordPress + dev.to: Meta box appears, checkbox controls sync
- [ ] GitHub Only: Always syncs, frontend redirects, no meta box
- [ ] Dev.to Only: Always syncs, frontend redirects, no meta box
- [ ] Dual: GitHub always syncs, checkbox controls dev.to, redirects
- [ ] Canonical URLs correct in all modes
- [ ] Settings save without erasing other tabs
</next_steps>