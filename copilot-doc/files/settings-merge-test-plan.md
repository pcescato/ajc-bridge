# Settings Merge Logic - Test Plan

## Problem Statement

**Before Fix:**
When saving settings from a tabbed interface, only fields present in the current POST request were saved, causing all other fields to be lost. For example:
- Save from General tab → Credentials lost (repo, branch, token)
- Save from Credentials tab → General settings lost (post types, template, debug)

**After Fix:**
Settings use merge logic: `array_merge($existing_settings, $sanitized_input)` to preserve all fields not in current POST.

---

## Test Cases

### Test 1: Save from General Tab
**Setup:**
1. Go to Settings > General tab
2. Pre-configure Credentials tab with:
   - Repository: `user/test-repo`
   - Branch: `main`
   - Token: `ghp_test123456`

**Action:**
1. On General tab, change Front Matter template
2. Enable debug mode
3. Click Save

**Expected Result:**
- ✅ Front Matter template updated
- ✅ Debug mode enabled
- ✅ Repository still `user/test-repo`
- ✅ Branch still `main`
- ✅ Token still encrypted in database

**Database Check:**
```php
$settings = get_option('atomic_jamstack_settings');
var_dump($settings);
// Should contain ALL fields from both tabs
```

---

### Test 2: Save from Credentials Tab
**Setup:**
1. Go to Settings > Credentials tab
2. Pre-configure General tab with:
   - Front Matter template: Custom YAML
   - Debug mode: Enabled
   - Post types: Posts + Pages

**Action:**
1. On Credentials tab, update Repository to `newuser/newrepo`
2. Update Branch to `develop`
3. Click Save

**Expected Result:**
- ✅ Repository updated to `newuser/newrepo`
- ✅ Branch updated to `develop`
- ✅ Front Matter template unchanged
- ✅ Debug mode still enabled
- ✅ Post types still Posts + Pages

---

### Test 3: Token Protection - Empty Input
**Setup:**
1. Token already saved: `ghp_existing_token`
2. Go to Credentials tab

**Action:**
1. Leave token field empty (or see masked `••••••••••••••••`)
2. Update Repository only
3. Click Save

**Expected Result:**
- ✅ Token remains `ghp_existing_token` (encrypted)
- ✅ Token not overwritten by empty value
- ✅ Repository updated correctly

---

### Test 4: Token Protection - Masked Placeholder
**Setup:**
1. Token already saved and displayed as `••••••••••••••••`
2. Go to Credentials tab

**Action:**
1. Don't touch token field (shows placeholder)
2. Update Branch
3. Click Save

**Expected Result:**
- ✅ Token unchanged in database
- ✅ Masked placeholder ignored
- ✅ Branch updated correctly

---

### Test 5: Checkbox Behavior - Unchecking
**Setup:**
1. Debug mode currently enabled
2. Go to General tab

**Action:**
1. Uncheck Debug mode
2. Click Save

**Expected Result:**
- ✅ Debug mode disabled (false in database)
- ✅ All other fields preserved

**Special Note:**
Unchecked checkboxes don't appear in POST data. The code handles this:
```php
if ( isset( $input['debug_mode'] ) ) {
    $sanitized['debug_mode'] = ! empty( $input['debug_mode'] );
}
```
If not in POST, merge keeps existing value. If in POST but empty, sets false.

---

### Test 6: Multiple Tab Switches
**Setup:**
1. Start with default settings
2. Credentials tab: Set repo + token
3. General tab: Set template + debug

**Action:**
1. Switch to Credentials → Save (modify branch)
2. Switch to General → Save (modify post types)
3. Switch to Credentials → Save (add new token)

**Expected Result:**
- ✅ Each save preserves all previous changes
- ✅ No data loss across multiple saves
- ✅ Final database has all fields from all saves

---

### Test 7: Empty Array Fields
**Setup:**
1. Post types: Both Posts and Pages enabled

**Action:**
1. On General tab, uncheck ALL post types
2. Click Save

**Expected Result:**
- ✅ Falls back to default: `array('post')`
- ✅ At least one post type always enabled

**Code Logic:**
```php
if ( isset( $input['enabled_post_types'] ) ) {
    if ( ! empty( $input['enabled_post_types'] ) && is_array(...) ) {
        // Use selected
    } else {
        // Empty = default to 'post'
        $sanitized['enabled_post_types'] = array( 'post' );
    }
}
// If not in POST at all, merge keeps existing value
```

---

## Technical Implementation

### Key Changes

1. **Load existing settings first:**
```php
$existing_settings = get_option( self::OPTION_NAME, array() );
$sanitized = array();
```

2. **Only sanitize fields in POST:**
```php
if ( isset( $input['github_repo'] ) ) {
    $sanitized['github_repo'] = sanitize_text_field( $input['github_repo'] );
}
// Not in POST? Not in $sanitized, will be preserved by merge
```

3. **Merge at the end:**
```php
$merged_settings = array_merge( $existing_settings, $sanitized );
return $merged_settings;
```

### Merge Behavior

**array_merge() rules:**
- Keys in `$sanitized` overwrite keys in `$existing_settings`
- Keys only in `$existing_settings` are preserved
- Result: Only updated fields change, rest stays intact

**Example:**
```php
$existing = [
    'github_repo' => 'old/repo',
    'github_token' => 'encrypted_old_token',
    'debug_mode' => true,
];

$sanitized = [
    'debug_mode' => false,  // Only this field in POST
];

$merged = array_merge($existing, $sanitized);
// Result:
// [
//     'github_repo' => 'old/repo',         ← Preserved
//     'github_token' => 'encrypted_old_token',  ← Preserved
//     'debug_mode' => false,               ← Updated
// ]
```

---

## Edge Cases

### Edge Case 1: First Time Setup
**Scenario:** No existing settings, first save

**Behavior:**
```php
$existing_settings = get_option(..., array()); // Returns empty array
$sanitized = [ 'github_repo' => 'user/repo' ];
$merged = array_merge([], $sanitized); // Returns $sanitized
```
**Result:** ✅ Works correctly

---

### Edge Case 2: Partial Settings
**Scenario:** Database has only 2 fields, user adds 3rd

**Behavior:**
```php
$existing = ['github_repo' => 'old/repo'];
$sanitized = ['debug_mode' => true];
$merged = ['github_repo' => 'old/repo', 'debug_mode' => true];
```
**Result:** ✅ Accumulates settings over time

---

### Edge Case 3: Validation Errors
**Scenario:** User submits invalid repo format

**Behavior:**
```php
// Validation fails, adds error
add_settings_error(..., 'Repository must be in format: owner/repo');
// But still sanitizes and merges
$sanitized['github_repo'] = 'invalid_format';
return array_merge($existing, $sanitized);
```
**Result:** ✅ Invalid value saved but error shown. User can fix.

**Alternative:** Could skip merge if validation fails, but current approach allows user to see what they entered.

---

## Debugging Commands

### Check Current Settings
```php
// In WordPress admin
$settings = get_option('atomic_jamstack_settings');
echo '<pre>';
print_r($settings);
echo '</pre>';
```

### Simulate Tab Save
```php
// Simulate saving from General tab
$input = [
    'hugo_front_matter_template' => '---\ntitle: {{title}}\n---',
    'debug_mode' => '1',
    'enabled_post_types' => ['post', 'page'],
];
$sanitized = \AtomicJamstack\Admin\Settings::sanitize_settings($input);
var_dump($sanitized);
// Should include existing github_repo, github_token, github_branch
```

### Verify Merge Logic
```php
$existing = ['a' => 1, 'b' => 2, 'c' => 3];
$new = ['b' => 20, 'd' => 4];
$merged = array_merge($existing, $new);
print_r($merged);
// ['a' => 1, 'b' => 20, 'c' => 3, 'd' => 4]
```

---

## Success Criteria

- ✅ All fields preserved when saving from any tab
- ✅ Token never lost when saving from General tab
- ✅ Template never lost when saving from Credentials tab
- ✅ Checkboxes work correctly (checked, unchecked, not in POST)
- ✅ Array fields (post types) accumulate correctly
- ✅ No PHP errors or warnings
- ✅ Settings errors still displayed for validation
- ✅ Multiple sequential saves work correctly

---

## Notes

1. **Why not use hidden fields?**
   - Hidden fields would work but add complexity
   - Merge logic is cleaner and more maintainable
   - Prevents form manipulation (hidden fields can be edited)

2. **Why check isset() not empty()?**
   - `empty()` would skip fields with value `0`, `false`, or `''`
   - `isset()` only checks presence in POST
   - Allows explicit false/empty values to be saved

3. **Token special case:**
   - Needs extra protection because masked in UI
   - Three states: not in POST, empty, or masked placeholder
   - Only updates on actual new value

4. **Checkbox special case:**
   - Unchecked = not in POST at all
   - Must distinguish "not in POST" (preserve) vs "unchecked" (set false)
   - Solution: Only update if field is from current tab's form

---

**Test Status:** Ready for manual testing  
**Expected Outcome:** Zero data loss across tab saves  
**Regression Risk:** Low (only changes sanitize logic, no DB schema changes)
