# Menu Architecture Refactoring - Unified Sidebar Menu

## Task Overview

Refactor the plugin's admin menu architecture from scattered entries (Settings under WP Settings menu + separate top-level Sync History) to a unified, professional top-level sidebar menu with organized submenus.

## Problem Statement

**Before:**
- Settings page: Under WordPress Settings menu (Settings > Jamstack Sync)
- Sync History: Separate top-level menu with dashicons-update icon
- No Bulk Operations visibility in menu
- Inconsistent navigation experience
- Hard to discover all plugin features

**After:**
- Unified top-level "Jamstack Sync" menu with cloud-upload icon
- Three clear submenus: Settings, Bulk Operations, Sync History
- Better discoverability and organization
- Professional appearance
- Consistent navigation

## Menu Structure

### New Hierarchy

```
Jamstack Sync (dashicons-cloud-upload) [publish_posts]
├── Settings [manage_options]
│   ├── General (sub-tab)
│   └── GitHub Credentials (sub-tab)
├── Bulk Operations [manage_options]
└── Sync History [publish_posts]
```

### Access Control

**Capability Matrix:**

| Menu Item | Capability | Who Can Access |
|-----------|------------|----------------|
| Top-level Menu | `publish_posts` | Authors, Editors, Admins |
| Settings Submenu | `manage_options` | Admins only |
| Bulk Operations | `manage_options` | Admins only |
| Sync History | `publish_posts` | Authors, Editors, Admins |

**Logic:**
- Main menu visible to Authors+ (for Sync History access)
- Settings/Bulk hidden from non-admins (capability check)
- Role-based filtering in Sync History (authors see only their own records)

## Implementation Details

### 1. Admin Class Refactoring (admin/class-admin.php)

#### A. Removed Old Menu Registration

**Before:**
```php
// Settings under WP Settings menu
add_options_page(
    __( 'Jamstack Sync Settings', 'atomic-jamstack-connector' ),
    __( 'Jamstack Sync', 'atomic-jamstack-connector' ),
    'manage_options',
    Settings::PAGE_SLUG,
    array( Settings::class, 'render_page' )
);

// Separate top-level menu for history
add_menu_page(
    __( 'Sync History', 'atomic-jamstack-connector' ),
    __( 'Sync History', 'atomic-jamstack-connector' ),
    'publish_posts',
    'atomic-jamstack-history',
    array( Settings::class, 'render_history_page' ),
    'dashicons-update',
    26
);
```

#### B. New Unified Menu Structure

**After:**
```php
// Main top-level menu - Visible to authors and above
add_menu_page(
    __( 'Jamstack Sync', 'atomic-jamstack-connector' ),
    __( 'Jamstack Sync', 'atomic-jamstack-connector' ),
    'publish_posts',
    'jamstack-sync',
    array( Settings::class, 'render_settings_page' ),
    'dashicons-cloud-upload',
    26
);

// Submenu 1: Settings (default) - Admin only
add_submenu_page(
    'jamstack-sync',
    __( 'Settings', 'atomic-jamstack-connector' ),
    __( 'Settings', 'atomic-jamstack-connector' ),
    'manage_options',
    'jamstack-sync',
    array( Settings::class, 'render_settings_page' )
);

// Submenu 2: Bulk Operations - Admin only
add_submenu_page(
    'jamstack-sync',
    __( 'Bulk Operations', 'atomic-jamstack-connector' ),
    __( 'Bulk Operations', 'atomic-jamstack-connector' ),
    'manage_options',
    'jamstack-sync-bulk',
    array( Settings::class, 'render_bulk_page' )
);

// Submenu 3: Sync History - Authors and above
add_submenu_page(
    'jamstack-sync',
    __( 'Sync History', 'atomic-jamstack-connector' ),
    __( 'Sync History', 'atomic-jamstack-connector' ),
    'publish_posts',
    'jamstack-sync-history',
    array( Settings::class, 'render_history_page' )
);
```

**Key Changes:**
- Menu position: 26 (below Settings, above Tools)
- Icon: `dashicons-cloud-upload` (better represents sync/upload)
- Parent slug: `jamstack-sync` (short, clean)
- All submenus under single parent

#### C. Updated Script Enqueuing

**Before:**
```php
$allowed_pages = array(
    'settings_page_' . Settings::PAGE_SLUG,
    'toplevel_page_' . Settings::HISTORY_PAGE_SLUG,
);
```

**After:**
```php
$allowed_pages = array(
    'toplevel_page_jamstack-sync',               // Settings page
    'jamstack-sync_page_jamstack-sync-bulk',     // Bulk Operations
    'jamstack-sync_page_jamstack-sync-history',  // Sync History
);
```

**WordPress Hook Naming:**
- Top-level menu: `toplevel_page_{$menu_slug}`
- Submenu: `{$parent_slug}_page_{$menu_slug}`

### 2. Settings Class Refactoring (admin/class-settings.php)

#### A. New Render Methods

**Created Three Separate Page Methods:**

**1. render_settings_page()** (replaces old tabbed render_page)
```php
public static function render_settings_page(): void {
    // Capability check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( ... );
    }
    
    // Get active sub-tab (General/Credentials)
    $settings_tab = isset( $_GET['settings_tab'] ) 
        ? sanitize_key( $_GET['settings_tab'] ) 
        : 'general';
    
    // Render with sub-tabs
    // - General
    // - GitHub Credentials
}
```

**Features:**
- Directly handles General/Credentials sub-tabs
- No intermediate "Settings" vs "Bulk" tab navigation
- Clean URL: `?page=jamstack-sync&settings_tab=general`
- Uses `atomic-jamstack-settings-wrap` class for styling

**2. render_bulk_page()** (new standalone page)
```php
public static function render_bulk_page(): void {
    // Capability check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( ... );
    }
    
    // Render bulk operations content
    self::render_bulk_tab();
}
```

**Features:**
- Dedicated page for bulk operations
- No tabs needed (single-purpose page)
- Clean URL: `?page=jamstack-sync-bulk`
- Calls existing `render_bulk_tab()` method

**3. render_history_page()** (updated)
```php
public static function render_history_page(): void {
    // Capability check (publish_posts for authors)
    if ( ! current_user_can( 'publish_posts' ) ) {
        wp_die( ... );
    }
    
    // Render sync history
    self::render_monitor_tab();
}
```

**Features:**
- Updated to use `atomic-jamstack-settings-wrap` class
- Maintains author access (publish_posts capability)
- Calls existing `render_monitor_tab()` method
- Clean URL: `?page=jamstack-sync-history`

#### B. Removed Old Methods

**Deleted: render_page()**
- Old tabbed interface (Settings/Bulk tabs)
- Replaced by separate render_settings_page() and render_bulk_page()

**Deleted: render_settings_tab()**
- Functionality moved into render_settings_page()
- No longer needed as intermediate method

#### C. Updated URL Structure

**Old URLs:**
```
Settings: /wp-admin/options-general.php?page=atomic-jamstack-settings&tab=settings&settings_tab=general
Bulk: /wp-admin/options-general.php?page=atomic-jamstack-settings&tab=bulk
History: /wp-admin/admin.php?page=atomic-jamstack-history
```

**New URLs:**
```
Settings: /wp-admin/admin.php?page=jamstack-sync&settings_tab=general
Bulk: /wp-admin/admin.php?page=jamstack-sync-bulk
History: /wp-admin/admin.php?page=jamstack-sync-history
```

**Benefits:**
- Shorter, cleaner URLs
- No intermediate "tab=settings" needed
- All under same base path (admin.php)
- Consistent naming convention

### 3. Sub-Tab Navigation Updates

**Settings Page Sub-Tabs:**

**Before:**
```html
<a href="?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>&tab=settings&settings_tab=general">
```

**After:**
```html
<a href="?page=jamstack-sync&settings_tab=general">
```

**Changes:**
- Removed `&tab=settings` (no longer needed)
- Updated page slug to `jamstack-sync`
- Simpler URL structure

### 4. Redirect Handling

The existing redirect logic continues to work correctly:

```php
public static function handle_settings_redirect(): void {
    if ( isset( $_POST['settings_tab'] ) ) {
        $settings_tab = sanitize_key( $_POST['settings_tab'] );
        add_filter( 'wp_redirect', function( $location ) use ( $settings_tab ) {
            return add_query_arg( 'settings_tab', $settings_tab, $location );
        });
    }
}
```

**Preserves:**
- Active sub-tab (General/Credentials) after save
- Works with new URL structure automatically
- No changes needed

## User Experience Improvements

### Navigation Flow

**Old Flow:**
```
WordPress Admin
├── Settings
│   └── Jamstack Sync (scattered)
└── Sync History (separate, isolated)
```

**New Flow:**
```
WordPress Admin
└── Jamstack Sync (unified)
    ├── Settings
    │   ├── General
    │   └── GitHub Credentials
    ├── Bulk Operations
    └── Sync History
```

### For Administrators

**Before:**
1. Go to Settings > Jamstack Sync
2. Navigate tabs within settings
3. Return to dashboard, then find Sync History separately

**After:**
1. Go to Jamstack Sync menu (sidebar)
2. See all options in one place
3. One-click access to any feature

### For Authors

**Before:**
- Could see Sync History (top-level menu)
- No visibility of other features
- Unclear what else plugin offers

**After:**
- See Jamstack Sync menu (indicates plugin presence)
- Access Sync History submenu
- Settings/Bulk hidden but menu visible (discoverability)

## Visual Design

### Menu Icon

**Old:** `dashicons-update` (spinning arrows, suggests refresh)  
**New:** `dashicons-cloud-upload` (cloud with up arrow, represents sync to cloud/GitHub)

**Rationale:**
- More accurately represents plugin function
- Visually distinct from Update/Cache icons
- Professional appearance
- Matches industry standard (cloud sync icons)

### Menu Position

**Position: 26**

WordPress menu positions:
- 25: Comments
- 26: **Jamstack Sync** ← Our plugin
- 59: Separator
- 60: Themes

**Rationale:**
- After content-related items
- Before appearance/customization
- Logical position for content publishing tool

## Capability Management

### Role-Based Access

**Admins (manage_options):**
- ✅ Settings (General + Credentials)
- ✅ Bulk Operations
- ✅ Sync History (all records)

**Editors (edit_others_posts):**
- ❌ Settings
- ❌ Bulk Operations
- ✅ Sync History (all records)

**Authors (publish_posts):**
- ❌ Settings
- ❌ Bulk Operations
- ✅ Sync History (own records only)

**Contributors (edit_posts):**
- ❌ All plugin features (no publish_posts capability)

### Security Checks

Each page has proper capability checks:

```php
// Settings page
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'You do not have sufficient permissions...', '...' ) );
}

// Sync History page
if ( ! current_user_can( 'publish_posts' ) ) {
    wp_die( __( 'You do not have sufficient permissions...', '...' ) );
}
```

**Additional Filtering:**
- Sync History query filters by user ID for non-admins
- Database::get_sync_logs() has author_id parameter
- Authors only see their own sync records

## Migration Impact

### Database

**No changes required:**
- Settings option key unchanged: `atomic_jamstack_settings`
- Post meta keys unchanged
- Logs table unchanged

### Existing Installations

**User Experience:**
- Menu moves from Settings to sidebar
- Bookmarks to old URLs will 404 (acceptable)
- No data loss
- Settings preserved

**Recommended Actions:**
- Update documentation screenshots
- Announce menu restructure in changelog
- No migration script needed

## Testing Checklist

- [x] Admin can access all three submenus
- [x] Author can only access Sync History
- [x] Settings sub-tabs work (General/Credentials)
- [x] Active sub-tab preserved after save
- [x] CSS/JS enqueued correctly on all pages
- [x] Menu icon displays correctly
- [x] Menu position is correct (26)
- [x] Capability checks work
- [x] Sync History filters by author for non-admins
- [x] Bulk operations page displays correctly
- [x] All AJAX calls work (connection test)
- [x] Form submissions work
- [x] Redirects preserve tab state

## Files Modified

### 1. admin/class-admin.php

**Lines Changed:** ~45 lines modified
- Replaced `add_options_page()` with `add_menu_page()`
- Removed separate `add_menu_page()` for history
- Added three `add_submenu_page()` calls
- Updated `enqueue_scripts()` hook detection

**Key Methods:**
- `add_menu_pages()`: Complete refactor
- `enqueue_scripts()`: Updated $allowed_pages array

### 2. admin/class-settings.php

**Lines Changed:** ~60 lines modified
- Added `render_settings_page()` method
- Added `render_bulk_page()` method
- Updated `render_history_page()` (added wrap class)
- Removed `render_page()` method
- Removed `render_settings_tab()` method
- Updated sub-tab URLs (removed tab parameter)

**Key Methods:**
- `render_settings_page()`: New method (50 lines)
- `render_bulk_page()`: New method (15 lines)
- `render_history_page()`: Updated (1 line change)

## WordPress Coding Standards

✅ **Capability Checks:**
- All pages check capabilities
- Use `wp_die()` for unauthorized access
- Proper error messages

✅ **Escaping:**
- `esc_attr()` for attributes
- `esc_html_e()` for translatable text
- `sanitize_key()` for user input

✅ **Translations:**
- All strings wrapped in `__()`/`esc_html_e()`
- Consistent text domain: 'atomic-jamstack-connector'
- Translation ready

✅ **Nonce Verification:**
- Form submissions have nonce checks (existing)
- Settings API handles nonces automatically

✅ **Documentation:**
- All methods have docblocks
- Parameter types specified
- Return types specified

## Performance Impact

**Negligible:**
- Menu registration: No additional overhead
- Page rendering: Same number of pages, just reorganized
- Database queries: Unchanged
- HTTP requests: Same CSS/JS files

**Improvements:**
- Cleaner URLs (less parsing)
- Fewer intermediate checks (no tab switching)
- Better code organization

## Backward Compatibility

### Breaking Changes

**URLs Changed:**
- Old bookmark URLs will 404
- External links to settings page need updating

**Non-Breaking:**
- Settings data preserved
- Database unchanged
- Plugin functionality unchanged
- Internal links work (they use relative URLs)

### Upgrade Path

**For Users:**
1. Plugin updates automatically
2. Navigate to new menu location
3. All settings preserved
4. No manual action needed

**For Developers:**
- Update any hardcoded URLs in custom code
- Check external documentation links
- Test capability-based access

## Documentation Updates

### Required Updates

- [ ] README.md: Update menu navigation screenshots
- [ ] User guide: Update "Settings" section paths
- [ ] Screenshots: Capture new sidebar menu
- [ ] Changelog: Document menu restructure

### Updated Paths in Docs

**Before:**
```
Go to Settings > Jamstack Sync
```

**After:**
```
Go to Jamstack Sync in the sidebar menu
```

## Future Enhancements

Potential additions:
1. Dashboard widget with sync statistics
2. Quick sync button in admin bar
3. Contextual help tabs (WordPress native)
4. Welcome screen on first activation
5. Settings import/export submenu
6. Diagnostics submenu for troubleshooting

## Conclusion

The menu architecture has been successfully refactored from scattered entries to a professional, unified sidebar menu with clear organization:

✅ **Unified Navigation** - Single entry point for all plugin features  
✅ **Better Discoverability** - All options visible in submenu  
✅ **Professional Appearance** - Cloud upload icon, proper positioning  
✅ **Role-Based Access** - Authors see history, admins see everything  
✅ **Clean URLs** - Shorter, more intuitive structure  
✅ **WordPress Standards** - Follows best practices for menu registration  

**Status:** ✅ COMPLETE - Ready for production

The plugin now provides a cohesive, professional admin experience that aligns with WordPress conventions and user expectations.
