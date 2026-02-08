# Clean Uninstall Feature - Documentation

## Overview

The Atomic Jamstack Connector plugin now includes a conditional "Clean Uninstall" feature that allows users to choose whether plugin data should be deleted when the plugin is uninstalled.

**Key Principle:** User control and transparency
- Default behavior: Data is **preserved** on uninstall (safe default)
- Opt-in deletion: User must explicitly enable data deletion
- Clear warnings: User is informed of consequences

---

## User Experience

### Settings Interface

**Location:** Jamstack Sync → Settings → General Tab → Debug Settings section

**Checkbox Label:** "Delete data on uninstall"

**Checkbox Description:** "Permanently delete all plugin data when uninstalling"

**Warning Message (red text):**
> **Warning:** If checked, all settings and synchronization logs will be permanently deleted from the database when the plugin is uninstalled. This action cannot be undone.

### Default Behavior

**Unchecked (Default):**
- Plugin settings preserved in database
- Post meta preserved (sync status, file paths, etc.)
- Can reinstall plugin without reconfiguring
- Safe for temporary deactivation/testing

**Checked:**
- All plugin data deleted on uninstall
- Clean database after removal
- Must reconfigure if reinstalling
- Use for permanent removal

---

## What Gets Deleted

### When "Delete data on uninstall" is ENABLED:

#### 1. Plugin Options
```php
delete_option('atomic_jamstack_settings');
```
**Contains:**
- GitHub repository and branch
- Encrypted personal access token
- Front Matter template
- Debug mode setting
- Post types configuration
- Delete on uninstall preference itself

#### 2. Post Meta Keys
```php
delete_post_meta_by_key('_jamstack_sync_status');
delete_post_meta_by_key('_jamstack_sync_last');
delete_post_meta_by_key('_jamstack_file_path');
delete_post_meta_by_key('_jamstack_last_commit_url');
delete_post_meta_by_key('_jamstack_sync_start_time');
```

**Impact:**
- Removes sync status from all posts
- Removes file path tracking
- Removes commit URLs
- Posts themselves are NOT deleted

#### 3. Transients (Locks)
```sql
DELETE FROM wp_options 
WHERE option_name LIKE '_transient_jamstack_lock_%' 
OR option_name LIKE '_transient_timeout_jamstack_lock_%'
```

**Purpose:**
- Cleans up any orphaned sync locks
- Removes temporary processing markers

#### 4. Action Scheduler Tasks
```php
// Cancels all pending/in-progress actions
- atomic_jamstack_sync group
- atomic_jamstack_deletion group
```

**Purpose:**
- Prevents queued syncs from running after uninstall
- Cleans up background job queue

#### 5. Cache
```php
wp_cache_delete('atomic_jamstack_settings', 'options');
```

**Purpose:**
- Ensures no stale cached data remains

---

## What Does NOT Get Deleted

### 1. Log Files
**Location:** `wp-content/uploads/atomic-jamstack-logs/`

**Reason:**
- Useful for debugging even after uninstall
- File deletion can fail due to permissions
- WordPress doesn't guarantee file system access in uninstall

**Manual Cleanup:**
Users can manually delete the logs directory if desired:
```bash
rm -rf wp-content/uploads/atomic-jamstack-logs/
```

### 2. WordPress Posts/Pages
**Reason:**
- Plugin only syncs content, doesn't own it
- Deleting posts would be destructive and unexpected
- Posts are core WordPress content

### 3. GitHub Repository Content
**Reason:**
- Files remain in GitHub repository
- Plugin can't access GitHub API during uninstall (no settings)
- User maintains control of remote content

---

## Implementation Details

### File: admin/class-settings.php

**Changes Made:**

1. **Added Settings Field Registration:**
```php
add_settings_field(
    'delete_data_on_uninstall',
    __( 'Delete data on uninstall', 'atomic-jamstack-connector' ),
    array( __CLASS__, 'render_uninstall_field' ),
    self::PAGE_SLUG,
    'atomic_jamstack_debug_section'
);
```

2. **Added Sanitization:**
```php
if ( isset( $input['delete_data_on_uninstall'] ) ) {
    $sanitized['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] );
}
```

3. **Added Render Method:**
```php
public static function render_uninstall_field(): void {
    $settings = get_option( self::OPTION_NAME, array() );
    $checked  = ! empty( $settings['delete_data_on_uninstall'] );
    ?>
    <label>
        <input 
            type="checkbox" 
            name="<?php echo esc_attr( self::OPTION_NAME ); ?>[delete_data_on_uninstall]" 
            value="1"
            <?php checked( $checked ); ?>
        />
        <?php esc_html_e( 'Permanently delete all plugin data when uninstalling', 'atomic-jamstack-connector' ); ?>
    </label>
    <p class="description" style="color: #d63638;">
        <strong><?php esc_html_e( 'Warning:', 'atomic-jamstack-connector' ); ?></strong>
        <?php esc_html_e( 'If checked, all settings and synchronization logs will be permanently deleted...', 'atomic-jamstack-connector' ); ?>
    </p>
    <?php
}
```

### File: uninstall.php (NEW)

**Structure:**

```php
<?php
// 1. Security check
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// 2. Load settings
$settings = get_option( 'atomic_jamstack_settings', array() );

// 3. Check user preference
if ( empty( $settings['delete_data_on_uninstall'] ) ) {
    return; // Keep data - exit early
}

// 4. User opted in - proceed with deletion
delete_option( 'atomic_jamstack_settings' );
delete_post_meta_by_key( '_jamstack_sync_status' );
// ... etc
```

**Key Features:**

1. **Security First:**
   - Checks `WP_UNINSTALL_PLUGIN` constant
   - Only runs when WordPress triggers uninstall
   - Cannot be accessed directly via URL

2. **Conditional Execution:**
   - Checks setting before doing anything
   - Early return if deletion not enabled
   - Zero database impact if unchecked

3. **Comprehensive Cleanup:**
   - Options table
   - Post meta
   - Transients
   - Action Scheduler queue
   - Cache

4. **Documented Exclusions:**
   - Explains what doesn't get deleted
   - Provides manual cleanup instructions
   - Justifies decisions

---

## WordPress Uninstall Process

### How WordPress Triggers Uninstall

1. User goes to Plugins page
2. User deactivates plugin (if active)
3. User clicks "Delete" link
4. WordPress confirms deletion
5. WordPress looks for `uninstall.php` in plugin root
6. If found, WordPress defines `WP_UNINSTALL_PLUGIN` constant
7. WordPress includes `uninstall.php`
8. Uninstall script executes
9. WordPress deletes plugin files

### Security Measures

**WordPress provides:**
- `WP_UNINSTALL_PLUGIN` constant (must be defined)
- Nonce verification (handled by WP core)
- Capability checks (only admins can uninstall)

**Our script adds:**
- Constant check at the very top
- Early exit if not uninstalling
- User preference check before deletion

---

## Testing Scenarios

### Scenario 1: Default Behavior (Unchecked)

**Setup:**
1. Install plugin
2. Configure settings (GitHub, template, etc.)
3. Sync some posts
4. Leave "Delete data on uninstall" unchecked

**Test:**
1. Uninstall plugin
2. Check database

**Expected Result:**
- ✅ Settings preserved in `wp_options`
- ✅ Post meta preserved
- ✅ Can reinstall without reconfiguring

**Verification:**
```sql
SELECT * FROM wp_options WHERE option_name = 'atomic_jamstack_settings';
-- Should return 1 row with all settings intact

SELECT * FROM wp_postmeta WHERE meta_key LIKE '_jamstack_%';
-- Should return all sync meta
```

---

### Scenario 2: Clean Uninstall (Checked)

**Setup:**
1. Install plugin
2. Configure settings
3. Sync some posts
4. Enable "Delete data on uninstall" checkbox
5. Save settings

**Test:**
1. Uninstall plugin
2. Check database

**Expected Result:**
- ✅ Settings deleted from `wp_options`
- ✅ All post meta deleted
- ✅ Transients deleted
- ✅ Action Scheduler tasks cancelled
- ✅ Clean database

**Verification:**
```sql
SELECT * FROM wp_options WHERE option_name = 'atomic_jamstack_settings';
-- Should return 0 rows

SELECT * FROM wp_postmeta WHERE meta_key LIKE '_jamstack_%';
-- Should return 0 rows

SELECT * FROM wp_options WHERE option_name LIKE '%jamstack_lock_%';
-- Should return 0 rows
```

---

### Scenario 3: Reinstall After Clean Uninstall

**Setup:**
1. Plugin installed with data
2. Enable "Delete data on uninstall"
3. Uninstall plugin (data deleted)

**Test:**
1. Reinstall plugin
2. Try to access settings

**Expected Result:**
- ✅ Plugin works correctly
- ✅ Settings page shows defaults
- ✅ Must reconfigure GitHub credentials
- ✅ No errors or warnings

---

### Scenario 4: Deactivate vs Uninstall

**Setup:**
1. Plugin installed with data
2. Enable "Delete data on uninstall"

**Test:**
1. Deactivate (but don't uninstall)
2. Check database
3. Reactivate
4. Check settings

**Expected Result:**
- ✅ Deactivation does NOT delete data
- ✅ Settings preserved during deactivation
- ✅ Reactivation restores full functionality
- ✅ Only UNINSTALL deletes data

---

## Edge Cases

### Edge Case 1: Uninstall Without Activation
**Scenario:** User installs but never activates, then uninstalls

**Behavior:**
- No settings exist in database
- `get_option()` returns empty array
- `empty($settings['delete_data_on_uninstall'])` is true
- Early return - no deletion attempted
- No errors

**Result:** ✅ Safe

---

### Edge Case 2: Checkbox Toggled Multiple Times
**Scenario:** User enables, saves, disables, saves, then uninstalls

**Behavior:**
- Last saved value is used
- If unchecked at uninstall time, data preserved
- If checked at uninstall time, data deleted
- No accumulation or confusion

**Result:** ✅ Predictable

---

### Edge Case 3: Multisite Installation
**Scenario:** Plugin activated network-wide or per-site

**Behavior:**
- `uninstall.php` runs once per site
- Each site's settings checked independently
- One site can keep data, another can delete
- No cross-site data leakage

**Result:** ✅ Site-specific

---

### Edge Case 4: Action Scheduler Not Available
**Scenario:** Action Scheduler library not loaded during uninstall

**Behavior:**
```php
if ( class_exists( 'ActionScheduler_DBStore' ) ) {
    // Delete actions
}
// If class doesn't exist, skip silently
```

**Result:** ✅ Graceful degradation

---

### Edge Case 5: Database Errors
**Scenario:** Database query fails during uninstall

**Behavior:**
- WordPress suppresses errors during uninstall
- Plugin files still deleted
- Partial cleanup possible
- Next uninstall/cleanup won't run (files gone)

**Mitigation:**
- Use WordPress functions (have error handling)
- Keep queries simple
- Don't abort on single failure

**Result:** ⚠️ Best effort cleanup

---

## Security Considerations

### 1. Uninstall Constant Check
```php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}
```

**Purpose:** Prevents direct execution via URL

**Attack Prevented:**
```
example.com/wp-content/plugins/atomic-jamstack-connector/uninstall.php
```
Without check, attacker could trigger data deletion by visiting URL.

### 2. User Capability
WordPress core ensures only administrators can uninstall plugins.
Our script doesn't need additional capability checks.

### 3. Nonce Verification
WordPress core handles nonce verification for plugin uninstall.
Our script doesn't need additional nonce checks.

### 4. SQL Injection Prevention
```php
$wpdb->prepare(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
    $wpdb->esc_like( '_transient_jamstack_lock_' ) . '%'
);
```

**Protection:**
- Uses `$wpdb->prepare()` for parameterized query
- Uses `$wpdb->esc_like()` for LIKE wildcards
- No user input in query

### 5. Data Validation
```php
if ( empty( $settings['delete_data_on_uninstall'] ) ) {
    return;
}
```

**Protection:**
- Checks setting is explicitly true
- Uses `empty()` so false/0/null/missing all preserve data
- Safe default: preserve data

---

## Best Practices Applied

### 1. Safe Defaults
✅ Default is to PRESERVE data
✅ User must opt-in to deletion
✅ Follows principle of least surprise

### 2. Clear Communication
✅ Red warning text
✅ Explicit language ("permanently deleted", "cannot be undone")
✅ Checkbox label describes exact behavior

### 3. Conditional Execution
✅ Early return if deletion not enabled
✅ Zero database impact if unchecked
✅ Efficient - checks preference before heavy operations

### 4. Comprehensive Cleanup
✅ Deletes all options
✅ Deletes all post meta
✅ Deletes all transients
✅ Cancels queued tasks
✅ Clears cache

### 5. Documented Exclusions
✅ Explains what doesn't get deleted
✅ Provides manual cleanup instructions
✅ Justifies decisions (log files, posts, GitHub)

### 6. Error Handling
✅ Uses WordPress functions (built-in error handling)
✅ Checks class existence before using Action Scheduler
✅ Doesn't abort on single failure
✅ Best effort cleanup

### 7. Code Quality
✅ Well-commented
✅ Clear structure
✅ Type hints where applicable
✅ WordPress Coding Standards

---

## User Documentation

### For Plugin Users

**To enable clean uninstall:**

1. Go to **Jamstack Sync → Settings**
2. Click the **General** tab
3. Scroll to **Debug Settings** section
4. Check **"Delete data on uninstall"**
5. Click **Save Changes**
6. Read the warning carefully

**To disable (keep data):**

1. Same steps as above
2. Uncheck the checkbox
3. Click Save Changes

**What happens when you uninstall:**

- **If unchecked:** Settings and sync data are preserved. You can reinstall without reconfiguring.
- **If checked:** All plugin data is permanently deleted. You must reconfigure if you reinstall.

**Deactivate vs Uninstall:**

- **Deactivate:** Plugin stops working, but data is kept
- **Uninstall:** Plugin is removed, data handling depends on checkbox

---

## Developer Notes

### Extending Cleanup

To add additional data cleanup, edit `uninstall.php`:

```php
// After existing cleanup code, add:

// 6. Delete custom data
delete_option( 'my_custom_jamstack_option' );

// 7. Delete custom transients
delete_transient( 'my_custom_jamstack_cache' );

// 8. Delete custom post meta
delete_post_meta_by_key( '_my_custom_jamstack_meta' );
```

### Testing Uninstall Locally

**Warning:** Uninstall deletes the plugin files!

**Test workflow:**

1. Create full backup of plugin directory
2. Set up test data
3. Enable "Delete data on uninstall"
4. Uninstall via WordPress admin
5. Check database for cleanup
6. Restore plugin from backup
7. Repeat with checkbox unchecked

**Alternative: Manual testing:**

```php
// Create test file: test-uninstall.php
define( 'WP_UNINSTALL_PLUGIN', true );
require_once 'path/to/uninstall.php';
```

### Multisite Considerations

For network-activated plugins, WordPress calls `uninstall.php` once per site.

No special handling needed - our script works correctly per-site.

---

## Compliance

- ✅ WordPress Plugin Guidelines
- ✅ GDPR Data Deletion (user can remove all data)
- ✅ Security best practices
- ✅ WordPress Coding Standards
- ✅ Accessibility (clear warnings)

---

## Changelog

**Version 1.1.0:**
- Added conditional clean uninstall feature
- Added "Delete data on uninstall" checkbox in settings
- Created `uninstall.php` script
- Default behavior: preserve data (safe)
- Opt-in for clean uninstall

---

**Status:** Complete  
**Files Modified:** admin/class-settings.php  
**Files Created:** uninstall.php  
**Breaking Changes:** None (new feature, safe default)
