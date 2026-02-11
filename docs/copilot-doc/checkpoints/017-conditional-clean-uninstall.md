# Checkpoint 017: Conditional Clean Uninstall Feature

**Date:** 2024-02-08  
**Focus:** Implement user-controlled data deletion on plugin uninstall

## Summary

Implemented a conditional "Clean Uninstall" feature that gives users control over whether plugin data should be deleted when the plugin is uninstalled. Default behavior preserves data (safe default), with an opt-in checkbox in settings for users who want complete data removal. This improves user experience and complies with data privacy best practices.

## Changes Made

### 1. Settings Interface (admin/class-settings.php)

**Added checkbox field in General tab → Debug Settings section:**

```php
add_settings_field(
    'delete_data_on_uninstall',
    __( 'Delete data on uninstall', 'atomic-jamstack-connector' ),
    array( __CLASS__, 'render_uninstall_field' ),
    self::PAGE_SLUG,
    'atomic_jamstack_debug_section'
);
```

**Added sanitization logic:**
```php
if ( isset( $input['delete_data_on_uninstall'] ) ) {
    $sanitized['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] );
}
```

**Added render method (lines 481-508):**
- Checkbox with clear label
- Red warning text about permanent deletion
- Explicit language: "cannot be undone"
- Follows WordPress UI patterns

### 2. Uninstall Script (uninstall.php - NEW FILE)

**Created comprehensive uninstall script with:**

1. **Security check:**
   ```php
   if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
       exit;
   }
   ```

2. **Conditional execution:**
   ```php
   $settings = get_option( 'atomic_jamstack_settings', array() );
   if ( empty( $settings['delete_data_on_uninstall'] ) ) {
       return; // Keep data - safe default
   }
   ```

3. **Comprehensive cleanup:**
   - Plugin options
   - All post meta (_jamstack_* keys)
   - Transients (locks)
   - Action Scheduler tasks
   - Cache

**What gets deleted:**
- `atomic_jamstack_settings` option
- `_jamstack_sync_status` post meta
- `_jamstack_sync_last` post meta
- `_jamstack_file_path` post meta
- `_jamstack_last_commit_url` post meta
- `_jamstack_sync_start_time` post meta
- `jamstack_lock_*` transients
- Pending Action Scheduler tasks

**What does NOT get deleted:**
- WordPress posts/pages (core content)
- GitHub repository content (remote)
- Log files (filesystem, useful for debugging)

## Technical Implementation

### Checkbox Behavior

**Three states:**
1. **Unchecked (default):** Data preserved on uninstall
2. **Checked:** Data deleted on uninstall  
3. **Not set (fresh install):** Treated as unchecked (safe default)

**Code logic:**
```php
// In uninstall.php
empty($settings['delete_data_on_uninstall'])
// Returns true if: false, 0, '', null, or not set
// Safe: All non-truthy values preserve data
```

### Data Deletion Process

**Order of operations:**
1. Delete main options (settings)
2. Delete post meta (all posts at once)
3. Delete transients (pattern-based query)
4. Cancel Action Scheduler tasks (if available)
5. Clear cache

**Why this order:**
- Settings first (determines scope)
- Post meta bulk delete (efficient)
- Transients cleanup (edge case handling)
- Queue cleanup (prevent orphaned jobs)
- Cache last (may help other operations)

### Action Scheduler Cleanup

**Handles edge case:**
```php
if ( class_exists( 'ActionScheduler_DBStore' ) ) {
    // Delete actions
}
// If class not loaded, skip gracefully
```

**Groups cleaned:**
- `atomic_jamstack_sync` - Sync operations
- `atomic_jamstack_deletion` - Delete operations

**Statuses handled:**
- Pending actions cancelled
- In-progress actions cancelled
- Completed/failed actions left (historical)

### SQL Injection Prevention

**Transient deletion query:**
```php
$wpdb->prepare(
    "DELETE FROM {$wpdb->options} 
    WHERE option_name LIKE %s 
    OR option_name LIKE %s",
    $wpdb->esc_like( '_transient_jamstack_lock_' ) . '%',
    $wpdb->esc_like( '_transient_timeout_jamstack_lock_' ) . '%'
);
```

**Protection:**
- Uses `$wpdb->prepare()` (parameterized)
- Uses `$wpdb->esc_like()` (LIKE escaping)
- No user input in query
- Safe from SQL injection

## User Experience

### Settings Page

**Location:** Jamstack Sync → Settings → General → Debug Settings

**Visual Design:**
- Checkbox with clear label
- Description text in red (#d63638)
- Bold "Warning:" prefix
- Explicit consequences explained

**Warning Message:**
> **Warning:** If checked, all settings and synchronization logs will be permanently deleted from the database when the plugin is uninstalled. This action cannot be undone.

**User Flow:**
1. User reads warning
2. User consciously checks box
3. User clicks Save
4. Setting persisted to database
5. On uninstall, data is deleted

### Uninstall Behavior

**Scenario 1: Default (unchecked)**
- User installs plugin
- User configures settings
- User uninstalls plugin
- **Result:** All data preserved, can reinstall without reconfiguring

**Scenario 2: Clean uninstall (checked)**
- User installs plugin
- User enables "Delete data on uninstall"
- User uninstalls plugin
- **Result:** All data deleted, clean database

**Scenario 3: Deactivate (not uninstall)**
- User deactivates plugin
- **Result:** No data deleted (deactivate ≠ uninstall)
- User can reactivate and continue

## Security Considerations

### 1. Direct Access Prevention
```php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}
```

**Prevents:**
- Direct URL access: `/wp-content/plugins/.../uninstall.php`
- Execution outside WordPress uninstall context
- Unauthorized data deletion

### 2. Capability Enforcement
- WordPress core ensures only administrators can uninstall
- Our script inherits this protection
- No additional capability checks needed

### 3. Safe Defaults
- Unchecked by default
- Must explicitly enable
- Uses `empty()` check (safe for all falsy values)

### 4. Data Validation
- Setting checked with `empty()` (not `isset()`)
- Missing key treated as false
- Invalid values treated as false
- No type coercion issues

### 5. Query Security
- All queries use `$wpdb->prepare()`
- LIKE patterns use `$wpdb->esc_like()`
- No raw SQL with variables
- No user input in queries

## Edge Cases Handled

### 1. Fresh Install (No Settings)
**Scenario:** Plugin never configured, then uninstalled

**Behavior:**
```php
$settings = get_option(..., array()); // Returns empty array
empty($settings['delete_data_on_uninstall']) // True
return; // Exit, no deletion
```

**Result:** ✅ Safe, no errors

### 2. Partial Settings
**Scenario:** Settings exist but checkbox never set

**Behavior:**
- Key doesn't exist in array
- `empty()` returns true
- Data preserved

**Result:** ✅ Safe default

### 3. Action Scheduler Not Available
**Scenario:** Library not loaded during uninstall

**Behavior:**
```php
if ( class_exists( 'ActionScheduler_DBStore' ) ) {
    // Only runs if available
}
```

**Result:** ✅ Graceful skip

### 4. Database Query Failure
**Scenario:** DELETE query fails

**Behavior:**
- WordPress suppresses errors during uninstall
- Plugin files still deleted by WordPress
- Partial cleanup occurs
- No fatal errors

**Result:** ⚠️ Best effort cleanup

### 5. Multisite Installation
**Scenario:** Plugin installed network-wide

**Behavior:**
- WordPress calls uninstall.php once per site
- Each site checked independently
- Site A can keep data, Site B can delete
- No cross-site impact

**Result:** ✅ Site-specific behavior

## Files Modified/Created

### admin/class-settings.php (~35 lines added)

**Section 1: Field Registration (lines 144-150)**
```php
add_settings_field(
    'delete_data_on_uninstall',
    __( 'Delete data on uninstall', 'atomic-jamstack-connector' ),
    array( __CLASS__, 'render_uninstall_field' ),
    self::PAGE_SLUG,
    'atomic_jamstack_debug_section'
);
```

**Section 2: Sanitization (lines 250-253)**
```php
if ( isset( $input['delete_data_on_uninstall'] ) ) {
    $sanitized['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] );
}
```

**Section 3: Render Method (lines 481-508)**
- Full checkbox field implementation
- Warning message with styling
- Translations ready

### uninstall.php (NEW FILE, 100+ lines)

**Structure:**
1. File header and security check (lines 1-12)
2. Load settings and conditional check (lines 14-23)
3. Comment block explaining deletion (lines 25-27)
4. Delete options (lines 29-30)
5. Delete post meta (lines 32-44)
6. Delete transients (lines 46-60)
7. Delete Action Scheduler tasks (lines 62-85)
8. Clear cache (lines 87-88)
9. Documentation comments (lines 90-101)

**Key Features:**
- Well-commented
- Security-first
- Conditional execution
- Comprehensive cleanup
- Graceful error handling

## Testing Recommendations

### Test Case 1: Default Behavior
1. Install plugin
2. Configure settings (repo, token, etc.)
3. Sync some posts
4. Uninstall plugin (checkbox unchecked)
5. Check database

**Expected:**
- ✅ Settings exist in `wp_options`
- ✅ Post meta exists
- ✅ Can reinstall without reconfiguring

**SQL Check:**
```sql
SELECT * FROM wp_options WHERE option_name = 'atomic_jamstack_settings';
-- Should return 1 row

SELECT * FROM wp_postmeta WHERE meta_key LIKE '_jamstack_%';
-- Should return multiple rows
```

### Test Case 2: Clean Uninstall
1. Install plugin
2. Configure settings
3. Check "Delete data on uninstall"
4. Save settings
5. Sync posts
6. Uninstall plugin

**Expected:**
- ✅ No settings in `wp_options`
- ✅ No post meta
- ✅ No transients
- ✅ Clean database

**SQL Check:**
```sql
SELECT * FROM wp_options WHERE option_name = 'atomic_jamstack_settings';
-- Should return 0 rows

SELECT * FROM wp_postmeta WHERE meta_key LIKE '_jamstack_%';
-- Should return 0 rows
```

### Test Case 3: Deactivate vs Uninstall
1. Install and configure
2. Enable "Delete data on uninstall"
3. Deactivate (don't uninstall)
4. Check database

**Expected:**
- ✅ Data still exists (deactivate doesn't delete)
- ✅ Can reactivate normally

4. Now uninstall
5. Check database

**Expected:**
- ✅ Data deleted (uninstall with checkbox enabled)

### Test Case 4: Reinstall After Clean
1. Clean uninstall (data deleted)
2. Reinstall plugin
3. Access settings page

**Expected:**
- ✅ Plugin works correctly
- ✅ Settings show defaults
- ✅ Must reconfigure GitHub
- ✅ No errors

### Test Case 5: Toggle Checkbox
1. Enable checkbox, save
2. Disable checkbox, save
3. Uninstall

**Expected:**
- ✅ Data preserved (last saved value used)

### Manual Testing Script

```php
// test-uninstall.php (DO NOT commit to repo)
// WARNING: This deletes data!

define( 'WP_UNINSTALL_PLUGIN', true );
require_once __DIR__ . '/wp-load.php'; // Adjust path
require_once __DIR__ . '/wp-content/plugins/atomic-jamstack-connector/uninstall.php';

echo "Uninstall complete. Check database.\n";
```

## Benefits

### For Users
1. **Control** - Choose whether to keep or delete data
2. **Safety** - Default preserves data (can reinstall)
3. **Privacy** - Can completely remove all traces
4. **Transparency** - Clear warning explains consequences

### For Developers
1. **Maintainable** - Single uninstall file
2. **Secure** - Proper checks and validation
3. **Comprehensive** - Cleans all plugin data
4. **Documented** - Clear comments and docs

### For Compliance
1. **GDPR** - Users can delete all their data
2. **WordPress Guidelines** - Follows best practices
3. **Security** - Proper access controls
4. **Standards** - WordPress Coding Standards

## Known Limitations

### 1. Log Files Not Deleted
**Reason:**
- File operations can fail (permissions)
- Logs useful for debugging after uninstall
- WordPress doesn't guarantee file system access

**Workaround:**
- Document manual deletion: `wp-content/uploads/atomic-jamstack-logs/`
- Consider adding to uninstall docs

### 2. GitHub Content Not Deleted
**Reason:**
- Can't access GitHub API without settings
- Settings deleted before we could use them
- User should control remote content

**Acceptable:** Remote content is user's responsibility

### 3. Partial Cleanup on Error
**Reason:**
- Database queries can fail
- Plugin files deleted even if cleanup fails
- Can't retry (files gone)

**Mitigation:** Use WordPress functions (have error handling)

### 4. Action Scheduler Dependency
**Issue:** Cleanup only works if Action Scheduler loaded

**Mitigation:** Check class existence, skip gracefully

## Future Enhancements

1. **Admin notice on uninstall:** Show what will be deleted
2. **Export settings:** Before uninstall, download JSON backup
3. **Log file cleanup:** Optional file deletion
4. **GitHub cleanup:** Optionally delete remote files (complex)
5. **Uninstall summary:** Show what was deleted (where to display?)

## Related Checkpoints

- **016**: Settings merge logic (preserves checkbox value correctly)
- **015**: PHP 8 type safety (used in setting handling)
- **013**: Menu architecture (where checkbox appears)

## Compliance

- [x] WordPress Plugin Guidelines
- [x] WordPress Coding Standards
- [x] Security best practices
- [x] GDPR compliance (data deletion)
- [x] Safe defaults (preserve data)
- [x] Clear user communication
- [x] Proper error handling

---

**Status:** Complete  
**Breaking Changes:** None (new feature, opt-in)  
**User Impact:** Positive (more control, privacy compliance)  
**Testing:** Manual testing recommended (destructive operation)
