<overview>
The user requested implementation of a Dev.to (Forem API) adapter for the Atomic Jamstack Connector WordPress plugin, which currently syncs posts to static site generators (Hugo/Jekyll) via GitHub. The goal was to enable direct publishing to Dev.to as an alternative destination, with support for two modes: Primary (Dev.to only) and Secondary (dual publishing to GitHub first, then Dev.to with canonical URL for SEO). The implementation required adapter pattern compliance, settings preservation across tabs (known bug in existing code), absolute image URLs (Dev.to requirement), and WordPress coding standards. A critical requirement emerged: Secondary mode must do DUAL publishing (GitHub + Dev.to), not just Dev.to alone.
</overview>

<history>
1. User requested Dev.to adapter implementation with specific architecture requirements
   - Created `adapters/class-devto-adapter.php` (458 lines) implementing Adapter_Interface
   - Converts WordPress posts to Dev.to markdown with YAML front matter
   - Handles tags (max 4, lowercase), description (max 160 chars), cover images (absolute URLs required)
   - Implements primary/secondary modes with canonical URL support
   - Created `core/class-devto-api.php` (315 lines) as REST API client
   - Implements publish_article(), test_connection(), get_articles() methods
   - Added comprehensive error handling with WP_Error returns
   - Modified `admin/class-settings.php` to add Dev.to section in Credentials tab
   - Added fields: API key (password), mode (radio), canonical URL (conditional)
   - Added AJAX handler ajax_test_devto_connection() for test button
   - Modified `core/class-sync-runner.php` to add sync_to_devto() method
   - Added adapter type detection and routing logic
   - Modified `assets/js/admin.js` for mode toggle and test connection AJAX
   - All syntax validated, WordPress Coding Standards compliant

2. User reported credential preservation bug after testing
   - Log showed "GitHub authentication failed - 401 Bad credentials"
   - Root cause: Dev.to API key sanitization didn't preserve existing value when empty
   - Empty field in POST was adding empty string to $sanitized array
   - array_merge() then overwrote existing credential with empty string
   - Fixed by implementing three-layer protection pattern (same as GitHub token):
     * If field in POST but empty → preserve existing
     * If field in POST and not empty → update
     * If field not in POST → preserve existing
   - Updated sanitize_settings() lines 331-351 with explicit preservation logic

3. User needed adapter selector UI (no way to choose between Hugo and Dev.to)
   - Added adapter_type field to Settings > General > Content Types section
   - Radio buttons: "GitHub Only (Hugo/Jekyll)" vs "Dev.to Publishing"
   - Added sanitization with whitelist validation (lines 320-327)
   - Added render_adapter_type_field() method (lines 666-713)
   - Default: 'hugo' for backward compatibility
   - Sync runner already had routing logic checking adapter_type

4. CRITICAL: User clarified Secondary mode requirement
   - User stated: "when dev.to is 'secondary', you have to use Github adapter in a first time, and then publish on dev.to - not only on dev.to!"
   - This means Secondary mode = DUAL PUBLISHING workflow
   - Step 1: Sync to GitHub (Hugo) - this is the canonical source
   - Step 2: Syndicate to Dev.to with canonical_url pointing to Hugo site
   - Updated sync runner routing logic to detect Secondary mode
   - Created sync_dual_publish() method (lines 79-162)
   - If GitHub fails, stops (canonical must exist first)
   - If GitHub succeeds but Dev.to fails, returns partial success
   - If both succeed, returns combined success
   - Updated adapter selector description to clarify dual publishing
   - Updated Dev.to mode descriptions: "Primary (Dev.to only)" vs "Secondary (Dual Publishing)"
</history>

<work_done>
Files created:
- `adapters/class-devto-adapter.php` (458 lines) - Converts WP posts to Dev.to markdown format
- `core/class-devto-api.php` (315 lines) - REST API client for Dev.to

Files modified:
- `admin/class-settings.php` (+213 lines total)
  - Lines 50: Added ajax_test_devto_connection action
  - Lines 125-131: Added adapter_type field registration
  - Lines 200-237: Added Dev.to settings section (API key, mode, canonical URL)
  - Lines 320-327: Added adapter_type sanitization
  - Lines 331-351: Enhanced devto_api_key sanitization with three-layer protection
  - Lines 353-370: Added devto_mode and devto_canonical_url sanitization
  - Lines 666-713: Added render_adapter_type_field() method
  - Lines 757-845: Added Dev.to render methods (section, API key, mode, canonical URL)
  - Lines 1382-1425: Added ajax_test_devto_connection() AJAX handler

- `core/class-sync-runner.php` (+250 lines)
  - Lines 66-77: Added adapter type and mode detection with routing logic
  - Lines 79-162: Added sync_dual_publish() method for Secondary mode dual publishing
  - Lines 164-258: Added sync_to_devto() method for API-based publishing
  - Lines 260+: Refactored existing GitHub sync into sync_to_github() method (no logic changes)

- `assets/js/admin.js` (+43 lines)
  - Lines 46-50: Added mode toggle handler (show/hide canonical URL field)
  - Lines 52-88: Added Dev.to test connection AJAX handler

Documentation created:
- `devto-adapter-implementation.md` (18KB) - Complete technical documentation
- `devto-quick-reference.md` (9KB) - Quick reference for developers
- `credential-preservation-fix.md` (11KB) - Detailed fix documentation

Work completed:
- ✅ Dev.to adapter implementing Adapter_Interface
- ✅ Dev.to API client with error handling
- ✅ Settings UI with credentials and mode selection
- ✅ AJAX test connection functionality
- ✅ Credential preservation fix (three-layer protection)
- ✅ Adapter selector UI
- ✅ Dual publishing workflow for Secondary mode
- ✅ Updated UI descriptions for clarity
- ✅ All syntax validated

Current state:
- All code syntax valid (php -l passed)
- Backward compatible (defaults to 'hugo' adapter)
- Ready for testing

Untested:
- Actual Dev.to API publishing (requires valid API key)
- Dual publishing workflow end-to-end
- Credential preservation across multiple tab switches
</work_done>

<technical_details>
**Critical Settings Preservation Pattern:**
The plugin has a known bug where saving one settings tab can erase another tab's data if not properly handled. The fix requires three-layer protection:

```php
if ( isset( $input['field'] ) ) {
    $value = sanitize_text_field( trim( $input['field'] ) );
    if ( ! empty( $value ) ) {
        $sanitized['field'] = $value; // Update
    } else {
        // Preserve existing if input is empty
        if ( ! empty( $existing_settings['field'] ) ) {
            $sanitized['field'] = $existing_settings['field'];
        }
    }
} else {
    // Field not in POST (different tab) - preserve
    if ( ! empty( $existing_settings['field'] ) ) {
        $sanitized['field'] = $existing_settings['field'];
    }
}
```

This prevents `array_merge()` from overwriting with empty strings.

**Image URL Requirements (CRITICAL for Dev.to):**
- Dev.to requires **absolute URLs** for all images (cover_image, content images)
- WordPress typically stores relative paths or file system paths
- Solution: Always use `wp_get_attachment_url()` for cover images, then validate with `parse_url($url, PHP_URL_SCHEME)`
- Content images: Parse markdown, find relative URLs, convert with `home_url($relative_path)`
- NEVER use file paths like `/var/www/uploads/image.jpg`
- ALWAYS use URLs like `https://example.com/wp-content/uploads/image.jpg`

**Dual Publishing Workflow (Secondary Mode):**
When `adapter_type = 'devto'` AND `devto_mode = 'secondary'`:
1. Call `sync_to_github($post)` first (Hugo/Jekyll via Git)
2. If GitHub fails → return error, stop (canonical source must exist)
3. If GitHub succeeds → call `sync_to_devto($post)` with canonical_url
4. If Dev.to fails → return partial success (GitHub worked, Dev.to didn't)
5. If both succeed → return combined success

This ensures the Hugo site is the canonical source for SEO, with Dev.to as syndication.

**Adapter Routing Logic:**
```php
$adapter_type = $settings['adapter_type'] ?? 'hugo';
$devto_mode = $settings['devto_mode'] ?? 'primary';

if ( 'devto' === $adapter_type ) {
    if ( 'secondary' === $devto_mode ) {
        return sync_dual_publish($post); // GitHub + Dev.to
    }
    return sync_to_devto($post); // Dev.to only
}
return sync_to_github($post); // GitHub only
```

**Dev.to Front Matter Requirements:**
- `title`: Required, from post_title
- `published`: Boolean, true if post_status === 'publish'
- `description`: Max 160 chars, from excerpt or truncated content
- `tags`: Max 4, lowercase, spaces converted to hyphens
- `cover_image`: Absolute URL with http/https scheme
- `canonical_url`: Only in secondary mode, format: base_url + '/' + post_slug
- `series`: Optional, from primary category name

**WordPress API Standards:**
- Use `wp_remote_request()` not curl
- Return `WP_Error` not exceptions (except in try/catch)
- Use `get_option()`, `update_option()` for settings
- Use `get_post_meta()`, `update_post_meta()` for post data
- All input: `sanitize_text_field()`, `esc_url_raw()`
- All output: `esc_html()`, `esc_attr()`, `esc_textarea()`
- AJAX: `check_ajax_referer()`, `current_user_can()`
- POST data: Always use `wp_unslash()` before sanitization

**Post Meta Storage:**
- `_devto_article_id` (int) - Dev.to article ID for updates (POST vs PUT)
- `_devto_article_url` (string) - Public Dev.to article URL
- Reuses existing: `_jamstack_sync_status`, `_jamstack_sync_last`, `_jamstack_sync_start_time`

**Settings Structure:**
All in single option `atomic_jamstack_settings`:
```php
array(
    'adapter_type' => 'devto', // 'hugo' or 'devto'
    'github_repo' => 'owner/repo',
    'github_branch' => 'main',
    'github_token' => 'encrypted_token',
    'devto_api_key' => 'api_key',
    'devto_mode' => 'secondary', // 'primary' or 'secondary'
    'devto_canonical_url' => 'https://yourblog.com',
    // ... other settings
)
```

**Known Issues:**
- Encrypted GitHub token length is 172 chars (includes encryption overhead)
- Token field shows masked "••••••••••••••••" when already set
- Empty masked field must preserve existing token, not overwrite
</technical_details>

<important_files>
- `adapters/class-devto-adapter.php` (458 lines)
  - Implements Adapter_Interface for Dev.to
  - Critical: All image URLs must be absolute (lines 192-219 for cover_image, lines 388-404 for content images)
  - Front matter generation in get_front_matter() (lines 72-99)
  - Markdown conversion with HTML parsing (lines 269-375)
  - Key methods: convert(), get_front_matter(), get_file_path() returns empty string

- `core/class-devto-api.php` (315 lines)
  - REST API client for Dev.to (Forem API)
  - publish_article() lines 49-146: POST/PUT with body_markdown
  - test_connection() lines 155-210: Validates API key
  - Error extraction logic lines 220-263: Parses JSON error responses
  - API base: https://dev.to/api
  - Timeouts: 30s for publish, 15s for test

- `core/class-sync-runner.php` (523 lines)
  - Central sync orchestrator, single entry point
  - Lines 66-77: Adapter routing logic with mode detection
  - Lines 79-162: sync_dual_publish() - NEW dual publishing workflow
  - Lines 164-258: sync_to_devto() - Dev.to API flow
  - Lines 260+: sync_to_github() - Refactored GitHub flow (unchanged logic)
  - Critical: Secondary mode must call GitHub first, then Dev.to

- `admin/class-settings.php` (1450+ lines)
  - Settings registration and UI rendering
  - Lines 50: AJAX handler registration for Dev.to test
  - Lines 125-131: adapter_type field registration
  - Lines 200-237: Dev.to settings section (API key, mode, canonical URL)
  - Lines 320-327: adapter_type sanitization (whitelist: hugo/devto)
  - Lines 331-351: devto_api_key sanitization with THREE-LAYER PROTECTION (critical fix)
  - Lines 666-713: render_adapter_type_field() - adapter selector UI
  - Lines 757-845: Dev.to render methods
  - Lines 1382-1425: ajax_test_devto_connection() - AJAX test handler

- `assets/js/admin.js` (88 lines)
  - Admin JavaScript for settings UI
  - Lines 46-50: Mode toggle (show/hide canonical URL field based on primary/secondary)
  - Lines 52-88: Dev.to test connection AJAX with success/error display
  - Nonce: atomicJamstackAdmin.testConnectionNonce

- `adapters/interface-adapter.php` (60 lines)
  - Adapter contract definition
  - Required methods: convert(), get_file_path(), get_front_matter()
  - Dev.to adapter must return empty string for get_file_path() (API-based, no files)
</important_files>

<next_steps>
Immediate testing needed:
1. Verify adapter selector appears in Settings > General
2. Test credential preservation:
   - Add both GitHub and Dev.to credentials
   - Save General tab settings
   - Verify both credentials still present in Credentials tab
3. Test Primary mode (Dev.to only):
   - Set adapter_type = 'devto'
   - Set devto_mode = 'primary'
   - Sync a post
   - Should see "Starting Dev.to sync" in logs (no GitHub calls)
4. Test Secondary mode (dual publishing):
   - Set adapter_type = 'devto'
   - Set devto_mode = 'secondary'
   - Configure both GitHub and Dev.to credentials
   - Sync a post
   - Should see: "Dual publishing mode", "Step 1: Publishing to GitHub", "Step 2: Syndicating to Dev.to"
   - Verify Dev.to article has canonical_url field pointing to Hugo site

Future enhancements:
- Per-post adapter override (meta box)
- Adapter auto-detection based on available credentials
- Bulk operations with adapter selection
- Publish to both simultaneously (not just secondary mode)
- API key encryption (currently plain text in DB)
- Cover image upload to Dev.to (if API supports)

No blockers - all code implemented and syntax validated.
</next_steps>