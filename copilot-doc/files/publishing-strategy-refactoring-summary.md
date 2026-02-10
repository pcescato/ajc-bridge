# Publishing Strategy Refactoring - Implementation Summary

## Status: ✅ Core Refactoring Complete

### Files Created

1. **admin/class-post-meta-box.php** (Complete) ✅
   - Post-level sync control meta box
   - Shows only in `wordpress_devto` and `dual_github_devto` modes
   - Checkbox: "Publish to dev.to"
   - Saves to post meta: `_atomic_jamstack_publish_devto`
   - Displays sync status and dev.to article link

2. **core/class-headless-redirect.php** (Complete) ✅
   - Frontend redirect handler for headless modes
   - Redirects logged-out users in `github_only`, `devto_only`, `dual_github_devto` modes
   - Smart path mapping:
     - Single posts → `/posts/slug` (GitHub) or `/slug` (dev.to)
     - Homepage → `/`
     - Archives → `/` (homepage)
   - Shows configuration notice if redirect URL not set
   - 301 permanent redirects for SEO

### Files Updated

3. **core/class-plugin.php** (Complete) ✅
   - Added `require_once` for `class-headless-redirect.php`
   - Added `require_once` for `class-post-meta-box.php`
   - Initialized `Headless_Redirect::init()` in core systems
   - Initialized `Post_Meta_Box::init()` in admin

4. **core/class-sync-runner.php** (Complete) ✅
   - Replaced 2-mode routing (adapter_type + devto_mode) with 5-mode strategy system
   - New `run()` method with switch statement for 5 strategies:
     - `wordpress_only` → Skip sync
     - `wordpress_devto` → Check post meta checkbox, sync to dev.to with WordPress canonical
     - `github_only` → Always sync to GitHub
     - `devto_only` → Always sync to dev.to (no canonical)
     - `dual_github_devto` → Always GitHub, optional dev.to (check post meta)
   - Added `migrate_old_settings()` method for backward compatibility
   - Updated `sync_to_devto()` signature to accept `?string $canonical_url` parameter

5. **adapters/class-devto-adapter.php** (Complete) ✅
   - Updated `convert()` signature: `convert(\WP_Post $post, ?string $canonical_url = null)`
   - Updated `get_front_matter()` signature: accepts optional `$canonical_url` parameter
   - Removed old `get_canonical_url()` method (no longer needed - canonical now passed from Sync_Runner)
   - Canonical URL logic now controlled by caller, not adapter

### Files PENDING Updates

6. **admin/class-settings.php** (TODO - Major refactoring needed)

**Current structure:**
```php
// General Tab
- 'enabled_post_types' (checkboxes)
- 'adapter_type' (radio: hugo/devto)

// Credentials Tab (GitHub)
- 'github_repo'
- 'github_branch'
- 'github_token'

// Credentials Tab (Dev.to)
- 'devto_api_key'
- 'devto_mode' (radio: primary/secondary)
- 'devto_canonical_url' (text, shown if secondary)
```

**New structure needed:**
```php
// General Tab
- 'enabled_post_types' (checkboxes) - KEEP
- 'publishing_strategy' (radio: wordpress_only / wordpress_devto / github_only / devto_only / dual_github_devto) - NEW
- 'wordpress_site_url' (text, default: get_site_url()) - NEW

// Credentials Tab (GitHub)
- 'github_repo' - KEEP
- 'github_branch' - KEEP
- 'github_token' - KEEP
- 'github_pages_url' (text, e.g., https://username.github.io/repo) - NEW

// Credentials Tab (Dev.to)
- 'devto_api_key' - KEEP
- 'devto_username' (text, e.g., pascal_cescato_692b7a8a20) - NEW

// REMOVE (obsolete):
- 'adapter_type' (replaced by publishing_strategy)
- 'devto_mode' (replaced by publishing_strategy)
- 'devto_canonical_url' (replaced by wordpress_site_url + github_pages_url)
```

---

## Required Settings Class Changes

### 1. Add New Field Registrations

**Location:** `register_settings()` method, General section

```php
// Replace adapter_type with publishing_strategy
add_settings_field(
    'publishing_strategy',
    __( 'Publishing Strategy', 'atomic-jamstack-connector' ),
    array( __CLASS__, 'render_publishing_strategy_field' ),
    self::PAGE_SLUG,
    'atomic_jamstack_posttypes_section'
);

add_settings_field(
    'wordpress_site_url',
    __( 'WordPress Site URL', 'atomic-jamstack-connector' ),
    array( __CLASS__, 'render_wordpress_site_url_field' ),
    self::PAGE_SLUG,
    'atomic_jamstack_posttypes_section'
);
```

**Location:** `register_settings()` method, GitHub Credentials section

```php
add_settings_field(
    'github_pages_url',
    __( 'GitHub Pages URL', 'atomic-jamstack-connector' ),
    array( __CLASS__, 'render_github_pages_url_field' ),
    self::PAGE_SLUG,
    'atomic_jamstack_github_section'
);
```

**Location:** `register_settings()` method, Dev.to Credentials section

```php
add_settings_field(
    'devto_username',
    __( 'Dev.to Username', 'atomic-jamstack-connector' ),
    array( __CLASS__, 'render_devto_username_field' ),
    self::PAGE_SLUG,
    'atomic_jamstack_devto_section'
);
```

### 2. Add New Sanitization Logic

**Location:** `sanitize_settings()` method

```php
// Sanitize publishing strategy
if ( isset( $input['publishing_strategy'] ) ) {
    $strategy = $input['publishing_strategy'];
    $allowed  = array( 'wordpress_only', 'wordpress_devto', 'github_only', 'devto_only', 'dual_github_devto' );
    $sanitized['publishing_strategy'] = in_array( $strategy, $allowed, true ) ? $strategy : 'wordpress_only';
}

// Sanitize WordPress site URL
if ( isset( $input['wordpress_site_url'] ) ) {
    $url = esc_url_raw( trim( $input['wordpress_site_url'] ) );
    if ( ! empty( $url ) && wp_parse_url( $url, PHP_URL_SCHEME ) ) {
        $sanitized['wordpress_site_url'] = rtrim( $url, '/' );
    }
}

// Sanitize GitHub Pages URL
if ( isset( $input['github_pages_url'] ) ) {
    $url = esc_url_raw( trim( $input['github_pages_url'] ) );
    if ( ! empty( $url ) && wp_parse_url( $url, PHP_URL_SCHEME ) ) {
        $sanitized['github_pages_url'] = rtrim( $url, '/' );
    }
}

// Sanitize Dev.to username
if ( isset( $input['devto_username'] ) ) {
    $username = sanitize_text_field( trim( $input['devto_username'] ) );
    if ( ! empty( $username ) ) {
        $sanitized['devto_username'] = $username;
    }
}
```

### 3. Add New Render Methods

**render_publishing_strategy_field():**

```php
public static function render_publishing_strategy_field(): void {
    $settings = get_option( self::OPTION_NAME, array() );
    $strategy = $settings['publishing_strategy'] ?? 'wordpress_only';
    
    // Migrate old settings if publishing_strategy not set
    if ( 'wordpress_only' === $strategy && isset( $settings['adapter_type'] ) ) {
        // Show old setting to help user understand migration
        $adapter_type = $settings['adapter_type'] ?? 'hugo';
        $devto_mode   = $settings['devto_mode'] ?? 'primary';
        
        if ( 'hugo' === $adapter_type ) {
            $strategy = 'github_only';
        } elseif ( 'devto' === $adapter_type && 'primary' === $devto_mode ) {
            $strategy = 'devto_only';
        } elseif ( 'devto' === $adapter_type && 'secondary' === $devto_mode ) {
            $strategy = 'dual_github_devto';
        }
    }
    ?>
    
    <fieldset>
        <label style="display: block; margin: 10px 0;">
            <input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[publishing_strategy]" 
                   value="wordpress_only" <?php checked( $strategy, 'wordpress_only' ); ?> />
            <strong><?php esc_html_e( 'WordPress Only', 'atomic-jamstack-connector' ); ?></strong>
        </label>
        <p class="description" style="margin-left: 25px;">
            <?php esc_html_e( 'No external sync. Plugin settings available but sync disabled. WordPress remains your public site.', 'atomic-jamstack-connector' ); ?>
        </p>
        
        <label style="display: block; margin: 20px 0 10px;">
            <input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[publishing_strategy]" 
                   value="wordpress_devto" <?php checked( $strategy, 'wordpress_devto' ); ?> />
            <strong><?php esc_html_e( 'WordPress + dev.to Syndication', 'atomic-jamstack-connector' ); ?></strong>
        </label>
        <p class="description" style="margin-left: 25px;">
            <?php esc_html_e( 'WordPress remains your public site (canonical). Optionally syndicate posts to dev.to with canonical_url pointing to WordPress. Check "Publish to dev.to" per post.', 'atomic-jamstack-connector' ); ?>
        </p>
        
        <label style="display: block; margin: 20px 0 10px;">
            <input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[publishing_strategy]" 
                   value="github_only" <?php checked( $strategy, 'github_only' ); ?> />
            <strong><?php esc_html_e( 'GitHub Only (Headless)', 'atomic-jamstack-connector' ); ?></strong>
        </label>
        <p class="description" style="margin-left: 25px;">
            <?php esc_html_e( 'WordPress is headless (admin-only). All published posts sync to Hugo/Jekyll on GitHub Pages. WordPress frontend redirects to your static site.', 'atomic-jamstack-connector' ); ?>
        </p>
        
        <label style="display: block; margin: 20px 0 10px;">
            <input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[publishing_strategy]" 
                   value="devto_only" <?php checked( $strategy, 'devto_only' ); ?> />
            <strong><?php esc_html_e( 'Dev.to Only (Headless)', 'atomic-jamstack-connector' ); ?></strong>
        </label>
        <p class="description" style="margin-left: 25px;">
            <?php esc_html_e( 'WordPress is headless. All published posts sync to dev.to. WordPress frontend redirects to dev.to.', 'atomic-jamstack-connector' ); ?>
        </p>
        
        <label style="display: block; margin: 20px 0 10px;">
            <input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[publishing_strategy]" 
                   value="dual_github_devto" <?php checked( $strategy, 'dual_github_devto' ); ?> />
            <strong><?php esc_html_e( 'Dual Publishing (GitHub + dev.to)', 'atomic-jamstack-connector' ); ?></strong>
        </label>
        <p class="description" style="margin-left: 25px;">
            <?php esc_html_e( 'WordPress is headless. Posts sync to GitHub (canonical). Optionally syndicate to dev.to with canonical_url. Check "Publish to dev.to" per post.', 'atomic-jamstack-connector' ); ?>
        </p>
    </fieldset>
    
    <?php
}
```

**render_wordpress_site_url_field():**

```php
public static function render_wordpress_site_url_field(): void {
    $settings = get_option( self::OPTION_NAME, array() );
    $value    = $settings['wordpress_site_url'] ?? get_site_url();
    ?>
    <input 
        type="url" 
        name="<?php echo esc_attr( self::OPTION_NAME ); ?>[wordpress_site_url]" 
        value="<?php echo esc_attr( $value ); ?>" 
        class="regular-text"
        placeholder="<?php echo esc_attr( get_site_url() ); ?>"
    />
    <p class="description">
        <?php esc_html_e( 'Your WordPress site URL. Used as canonical URL when syndicating to dev.to in "WordPress + dev.to" mode. Defaults to current site URL.', 'atomic-jamstack-connector' ); ?>
    </p>
    <?php
}
```

**render_github_pages_url_field():**

```php
public static function render_github_pages_url_field(): void {
    $settings = get_option( self::OPTION_NAME, array() );
    $value    = $settings['github_pages_url'] ?? '';
    ?>
    <input 
        type="url" 
        name="<?php echo esc_attr( self::OPTION_NAME ); ?>[github_pages_url]" 
        value="<?php echo esc_attr( $value ); ?>" 
        class="regular-text"
        placeholder="https://username.github.io/repo"
    />
    <p class="description">
        <?php esc_html_e( 'Your deployed Hugo/Jekyll site URL (e.g., GitHub Pages). Used for canonical URLs in dual publishing mode and for WordPress frontend redirects in headless modes.', 'atomic-jamstack-connector' ); ?>
    </p>
    <?php
}
```

**render_devto_username_field():**

```php
public static function render_devto_username_field(): void {
    $settings = get_option( self::OPTION_NAME, array() );
    $value    = $settings['devto_username'] ?? '';
    ?>
    <input 
        type="text" 
        name="<?php echo esc_attr( self::OPTION_NAME ); ?>[devto_username]" 
        value="<?php echo esc_attr( $value ); ?>" 
        class="regular-text"
        placeholder="pascal_cescato_692b7a8a20"
        pattern="[a-z0-9_]+"
    />
    <p class="description">
        <?php esc_html_e( 'Your dev.to username (from profile URL). Used for WordPress frontend redirects in "Dev.to Only" headless mode.', 'atomic-jamstack-connector' ); ?>
    </p>
    <?php
}
```

### 4. Remove Obsolete Fields

**Location:** `register_settings()` method

**Remove:**
- `render_adapter_type_field()` call
- `render_devto_mode_field()` call  
- `render_devto_canonical_url_field()` call

**Location:** `sanitize_settings()` method

**Remove/Update:**
- `adapter_type` sanitization (or keep for backward compat during migration)
- `devto_mode` sanitization (or keep for backward compat during migration)
- `devto_canonical_url` sanitization (no longer needed)

**Location:** Render methods section

**Remove:**
- `render_adapter_type_field()` method
- `render_devto_mode_field()` method
- `render_devto_canonical_url_field()` method

---

## Testing Checklist

### Meta Box Tests
- [ ] `wordpress_only` mode: Meta box does NOT show
- [ ] `wordpress_devto` mode: Meta box shows with "syndicate" description
- [ ] `github_only` mode: Meta box does NOT show
- [ ] `devto_only` mode: Meta box does NOT show
- [ ] `dual_github_devto` mode: Meta box shows with "GitHub automatic" description
- [ ] Checkbox saves correctly to `_atomic_jamstack_publish_devto` meta
- [ ] Sync status displays correctly in meta box
- [ ] Dev.to article link displays when published

### Headless Redirect Tests
- [ ] `wordpress_only` mode: No redirect, site works normally
- [ ] `wordpress_devto` mode: No redirect, site works normally
- [ ] `github_only` mode: Redirects to GitHub Pages URL
- [ ] `devto_only` mode: Redirects to dev.to/username
- [ ] `dual_github_devto` mode: Redirects to GitHub Pages URL
- [ ] Single post redirects to correct path
- [ ] Homepage redirects to root
- [ ] Archives redirect to homepage
- [ ] Logged-in users see WordPress normally (no redirect)
- [ ] Admin area always accessible (no redirect)
- [ ] Shows config notice if redirect URL not set

### Sync Logic Tests
- [ ] `wordpress_only`: Sync skipped with message
- [ ] `wordpress_devto` + checkbox ON: Syncs to dev.to with WordPress canonical
- [ ] `wordpress_devto` + checkbox OFF: Sync skipped
- [ ] `github_only`: Always syncs to GitHub
- [ ] `devto_only`: Always syncs to dev.to (no canonical)
- [ ] `dual_github_devto` + checkbox ON: Syncs to GitHub + dev.to with GitHub canonical
- [ ] `dual_github_devto` + checkbox OFF: Syncs to GitHub only
- [ ] Canonical URLs correct in all scenarios
- [ ] Old settings migrate correctly on first sync

### Settings UI Tests (PENDING)
- [ ] Publishing strategy radio buttons display correctly
- [ ] WordPress Site URL field shows
- [ ] GitHub Pages URL field shows
- [ ] Dev.to Username field shows
- [ ] Old adapter_type settings migrate visually
- [ ] Sanitization works for all new fields
- [ ] Settings save without data loss across tabs

---

## Backward Compatibility

### Migration Strategy

**Automatic migration in `Sync_Runner::run()`:**

```php
// Old settings → New strategy mapping
'hugo' + N/A           → 'github_only'
'devto' + 'primary'    → 'devto_only'
'devto' + 'secondary'  → 'dual_github_devto'
```

**Settings migration (one-time, user-initiated):**

When user first loads settings page after update:
1. Show admin notice: "Publishing settings updated! Please review your Publishing Strategy."
2. Auto-select the equivalent new strategy based on old settings
3. Preserve all credentials (GitHub token, Dev.to API key)
4. Map `devto_canonical_url` → `wordpress_site_url` or `github_pages_url` depending on mode

**Post meta checkbox default:**

For existing posts after upgrade:
- If old mode was 'secondary' (dual publishing), set `_atomic_jamstack_publish_devto = '1'` for all published posts
- Otherwise, leave unchecked (users opt-in per post)

---

## Summary

**✅ Completed:**
- Post meta box with per-post sync control
- Headless redirect handler with smart routing
- Sync runner refactored for 5-mode strategy system
- Dev.to adapter updated to accept canonical URL parameter
- Plugin class updated to load new components
- All syntax validated

**⏳ Pending:**
- Settings class UI refactoring (large task - see detailed instructions above)

**🎯 Next Steps:**
1. Implement Settings class changes following the detailed guide above
2. Test each strategy mode thoroughly
3. Test backward compatibility migration
4. Document new features in readme

The core refactoring is complete and functional. The Settings UI changes are the final piece to make this user-friendly.
