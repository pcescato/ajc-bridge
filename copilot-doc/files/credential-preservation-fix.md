# Credential Preservation Fix + Adapter Selector

## Issue Reported

When saving Dev.to settings in the Credentials tab, the GitHub token was being lost, causing sync to fail with "Bad credentials" error.

**Log Evidence:**
```
[2026-02-09 17:09:25] [ERROR] GitHub authentication failed - 401 Bad credentials
```

**Root Cause:**
1. Dev.to API key field was being saved even when empty, overwriting existing value via array_merge
2. No adapter selector UI existed, so sync always defaulted to 'hugo' (GitHub flow)
3. Users couldn't choose between Dev.to and GitHub publishing

---

## Fixes Implemented

### 1. Dev.to API Key Preservation (admin/class-settings.php lines 331-351)

**Before:**
```php
// Sanitize Dev.to API key
if ( isset( $input['devto_api_key'] ) ) {
    $sanitized['devto_api_key'] = sanitize_text_field( trim( $input['devto_api_key'] ) );
}
```

**Problem:** Empty string overwrites existing API key via `array_merge()`

**After:**
```php
// Sanitize Dev.to API key
// CRITICAL: Only update if not empty, otherwise preserve existing
if ( isset( $input['devto_api_key'] ) ) {
    $api_key = sanitize_text_field( trim( $input['devto_api_key'] ) );
    
    // Only update if not empty
    if ( ! empty( $api_key ) ) {
        $sanitized['devto_api_key'] = $api_key;
    } else {
        // Preserve existing API key if input is empty
        if ( ! empty( $existing_settings['devto_api_key'] ) ) {
            $sanitized['devto_api_key'] = $existing_settings['devto_api_key'];
        }
    }
} else {
    // Field not in POST (saving from different tab)
    // Explicitly preserve existing API key
    if ( ! empty( $existing_settings['devto_api_key'] ) ) {
        $sanitized['devto_api_key'] = $existing_settings['devto_api_key'];
    }
}
```

**Pattern:** Same three-layer protection as GitHub token:
1. If field in POST but empty → preserve existing
2. If field in POST and not empty → update
3. If field not in POST at all → preserve existing

---

### 2. Added Adapter Type Selector

**New Field:** `adapter_type` (radio buttons)

**Location:** Settings > General > Content Types section

**Options:**
- **Static Site (Hugo/Jekyll)** - Publish to Hugo/Jekyll via GitHub
- **Dev.to Platform** - Publish directly to Dev.to via API

**Settings Registration (line 125):**
```php
add_settings_field(
    'adapter_type',
    __( 'Publishing Destination', 'atomic-jamstack-connector' ),
    array( __CLASS__, 'render_adapter_type_field' ),
    self::PAGE_SLUG,
    'atomic_jamstack_posttypes_section'
);
```

**Sanitization (lines 320-327):**
```php
// Sanitize adapter type (publishing destination)
if ( isset( $input['adapter_type'] ) ) {
    $adapter = $input['adapter_type'];
    // Whitelist validation
    $sanitized['adapter_type'] = in_array( $adapter, array( 'hugo', 'devto' ), true ) 
        ? $adapter 
        : 'hugo';
}
```

**Render Method (lines 666-713):**
```php
public static function render_adapter_type_field(): void {
    $settings = get_option( self::OPTION_NAME, array() );
    $adapter  = $settings['adapter_type'] ?? 'hugo';

    $adapters = array(
        'hugo'  => array(
            'label'       => __( 'Static Site (Hugo/Jekyll)', ... ),
            'description' => __( 'Publish to Hugo/Jekyll static site via GitHub. Requires GitHub credentials.', ... ),
        ),
        'devto' => array(
            'label'       => __( 'Dev.to Platform', ... ),
            'description' => __( 'Publish directly to Dev.to via API. Requires Dev.to API key.', ... ),
        ),
    );

    // Radio buttons for each adapter
    // Helper text: "Configure credentials for your chosen destination in the Credentials tab."
}
```

---

## How It Works Now

### Credential Preservation

**Scenario 1: User saves Dev.to API key**
```
1. Credentials tab open, both GitHub and Dev.to fields visible
2. User enters Dev.to API key, clicks Save
3. Sanitize logic:
   - github_token: In POST (masked "••••••••••••••••") → Preserve existing ✅
   - devto_api_key: In POST (new value) → Update ✅
4. array_merge: Combines preserved + updated values
5. Result: Both credentials intact ✅
```

**Scenario 2: User saves GitHub token**
```
1. Credentials tab open
2. User enters new GitHub token, clicks Save
3. Sanitize logic:
   - github_token: In POST (new value) → Encrypt and update ✅
   - devto_api_key: In POST (empty or not changed) → Preserve existing ✅
4. array_merge: Combines values
5. Result: Both credentials intact ✅
```

**Scenario 3: User saves General tab settings**
```
1. General tab open (GitHub and Dev.to fields NOT in form)
2. User changes post types, clicks Save
3. Sanitize logic:
   - github_token: NOT in POST → Explicitly preserved ✅
   - devto_api_key: NOT in POST → Explicitly preserved ✅
4. array_merge: Combines preserved credentials with new settings
5. Result: All credentials intact ✅
```

---

### Adapter Routing

**Before Fix:**
```php
// core/class-sync-runner.php
$adapter_type = $settings['adapter_type'] ?? 'hugo'; // Always 'hugo' (no UI to set it)

if ( 'devto' === $adapter_type ) {
    return self::sync_to_devto( $post ); // Never executed
}

return self::sync_to_github( $post ); // Always executed
```

**After Fix:**
```
Settings > General > Publishing Destination:
  ○ Static Site (Hugo/Jekyll)  [Default, uses GitHub]
  ● Dev.to Platform           [User can select this]

When user saves: $settings['adapter_type'] = 'devto'

Sync Runner:
  1. Loads settings
  2. Checks adapter_type
  3. If 'devto' → sync_to_devto() (API flow)
  4. If 'hugo' → sync_to_github() (Git flow)
```

---

## Testing Steps

### Test Credential Preservation

1. **Setup:**
   - Add GitHub token in Credentials tab
   - Add Dev.to API key in Credentials tab
   - Save

2. **Test 1: Save Dev.to settings**
   - Credentials tab > Enter new Dev.to API key
   - Click Save
   - Test GitHub connection → Should still work ✅
   - Test Dev.to connection → Should work ✅

3. **Test 2: Save GitHub settings**
   - Credentials tab > Enter new GitHub token
   - Click Save
   - Test GitHub connection → Should work ✅
   - Test Dev.to connection → Should still work ✅

4. **Test 3: Save General tab**
   - General tab > Change post types
   - Click Save
   - Credentials tab > Test both connections → Both should work ✅

### Test Adapter Selector

1. **Default State:**
   - General tab > Publishing Destination should default to "Static Site (Hugo/Jekyll)"
   - Sync post → Should use GitHub

2. **Switch to Dev.to:**
   - General tab > Select "Dev.to Platform"
   - Save
   - Sync post → Should use Dev.to API
   - Check logs: Should see "Starting Dev.to sync"

3. **Switch back to Hugo:**
   - General tab > Select "Static Site (Hugo/Jekyll)"
   - Save
   - Sync post → Should use GitHub
   - Check logs: Should see GitHub API calls

---

## Settings Structure

```php
$settings = get_option( 'atomic_jamstack_settings', array() );

array(
    // Publishing configuration (NEW)
    'adapter_type' => 'devto', // or 'hugo'
    
    // GitHub credentials (existing)
    'github_repo' => 'owner/repo',
    'github_branch' => 'main',
    'github_token' => 'encrypted_token',
    
    // Dev.to credentials (new)
    'devto_api_key' => 'api_key_here',
    'devto_mode' => 'primary', // or 'secondary'
    'devto_canonical_url' => 'https://yourblog.com',
    
    // Other settings (existing)
    'enabled_post_types' => array( 'post', 'page' ),
    'hugo_front_matter_template' => '...',
    'debug_mode' => true,
    'delete_data_on_uninstall' => false,
);
```

---

## Code Changes Summary

### admin/class-settings.php

| Lines | Change | Purpose |
|-------|--------|---------|
| 125-131 | Added adapter_type field registration | Allow user to select publishing destination |
| 320-327 | Added adapter_type sanitization | Whitelist validation (hugo/devto only) |
| 331-351 | Enhanced devto_api_key sanitization | Three-layer preservation like GitHub token |
| 666-713 | Added render_adapter_type_field() | UI for selecting adapter |

**Total:** +68 lines

---

## User Experience Improvements

### Before
- ❌ Saving Dev.to settings erased GitHub token
- ❌ No way to choose between Dev.to and GitHub
- ❌ Sync always tried GitHub (even with Dev.to configured)
- ❌ Confusing error messages

### After
- ✅ All credentials preserved across saves
- ✅ Clear adapter selector with descriptions
- ✅ Sync uses correct destination based on selection
- ✅ Clear indication of requirements for each adapter

---

## Migration Path

**For existing users (GitHub only):**
1. Update plugin
2. No action needed - defaults to 'hugo' adapter
3. GitHub sync continues working unchanged

**For new users (want Dev.to):**
1. Install plugin
2. General tab > Select "Dev.to Platform"
3. Credentials tab > Add Dev.to API key
4. Test connection
5. Sync posts

**For hybrid users (want both):**
1. Configure both GitHub and Dev.to credentials
2. Switch adapter via General tab radio buttons
3. Can publish some posts to GitHub, others to Dev.to
4. Future enhancement: Per-post adapter selection

---

## Validation

### Syntax Check
```bash
php -l admin/class-settings.php
# No syntax errors detected ✅
```

### Logic Check
- ✅ API key preservation matches GitHub token pattern
- ✅ array_merge() receives preserved values
- ✅ Whitelist validation for adapter_type
- ✅ Default value 'hugo' maintains backward compatibility
- ✅ Radio buttons prevent invalid selections

### Security Check
- ✅ Input sanitization: `sanitize_text_field()`, `in_array()`
- ✅ Output escaping: `esc_attr()`, `esc_html()`
- ✅ Whitelist validation for adapter type
- ✅ No new attack vectors introduced

---

## Log Output After Fix

**Expected after selecting Dev.to adapter:**
```
[INFO] Sync runner started {"post_id":1492}
[INFO] Starting Dev.to sync {"post_id":1492}
[INFO] Dev.to API response {"http_code":201,"success":true}
[SUCCESS] Dev.to sync complete {"article_id":123456}
```

**vs. Before (always tried GitHub):**
```
[INFO] Sync runner started {"post_id":1492}
[INFO] Testing GitHub connection {"repo":"..."}
[ERROR] GitHub authentication failed - 401 Bad credentials
```

---

## Next Steps

1. **Test the fix:**
   - Clear any cached settings
   - Add both GitHub and Dev.to credentials
   - Test each adapter separately
   - Verify no credential loss

2. **Monitor logs:**
   - Check which adapter is being used
   - Verify API calls go to correct destination
   - Confirm no 401 errors

3. **Future enhancements:**
   - Per-post adapter override
   - Adapter auto-detection (if only one configured)
   - Bulk operations with adapter selection
   - Dual publishing (both GitHub and Dev.to)

---

## Status

✅ **Credential preservation:** Fixed with three-layer protection
✅ **Adapter selector:** Added to General tab
✅ **Routing logic:** Already implemented in sync runner
✅ **Backward compatibility:** Hugo remains default
✅ **Syntax validation:** No errors
✅ **Security:** Maintained

**Ready for testing!**
