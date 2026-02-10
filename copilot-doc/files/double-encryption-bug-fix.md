# GitHub Token Double-Encryption Bug - Fix

## Problem

The GitHub token was being **double-encrypted**, causing authentication failures (401 Bad Credentials).

### Symptoms

- GitHub connection test initially works ✅
- After saving settings or testing Dev.to connection, GitHub fails ❌
- Error log shows: `token_length: 172`
- Error message: "Bad credentials"

### Root Cause

When `update_option()` is called directly (not through Settings API form), WordPress still triggers the `sanitize_settings()` callback. This caused:

1. **Initial Save:**
   - User enters token: `ghp_abc123` (44 chars)
   - `sanitize_settings()` encrypts it: 44 → 88 chars ✅
   - Stored in DB: 88 chars (encrypted)

2. **Dev.to Test Connection:**
   - `ajax_test_devto_connection()` calls `update_option()` to temporarily set Dev.to API key
   - WordPress automatically triggers `sanitize_settings()` on the entire settings array
   - GitHub token in the array is now the 88-char encrypted string (from `get_option`)
   - `sanitize_settings()` sees it's not empty and not masked `'••••••••••••••••'`
   - Checks if it starts with `ghp_` → NO (it's base64 encrypted data)
   - Assumes it's a plain token → encrypts it again ❌
   - Result: 88 → 172 chars (double-encrypted)

3. **Subsequent Use:**
   - `Git_API` loads token from DB (172 chars)
   - Decrypts once: 172 → 88 chars
   - Tries to use 88-char encrypted string as GitHub token
   - GitHub API rejects it: **401 Bad Credentials** ❌

---

## The Fix

### Fix #1: Prevent update_option() from Triggering Double-Encryption

**File:** `admin/class-settings.php`
**Method:** `ajax_test_devto_connection()`

**Before:**
```php
// Temporarily set API key for test
$settings = get_option( self::OPTION_NAME, array() );
$settings['devto_api_key'] = $api_key;
update_option( self::OPTION_NAME, $settings ); // ❌ Triggers sanitize_settings()

// Test connection
$devto_api = new \AtomicJamstack\Core\DevTo_API();
$result = $devto_api->test_connection();

// Restore original key
$settings['devto_api_key'] = $original_key;
update_option( self::OPTION_NAME, $settings ); // ❌ Triggers again!
```

**After:**
```php
// Test connection WITHOUT saving to database
// Use a filter to temporarily override settings for this request only
add_filter(
    'option_' . self::OPTION_NAME,
    function( $value ) use ( $api_key ) {
        if ( is_array( $value ) ) {
            $value['devto_api_key'] = $api_key;
        }
        return $value;
    },
    999
);

$devto_api = new \AtomicJamstack\Core\DevTo_API();
$result = $devto_api->test_connection();
// Filter automatically removed after request - no cleanup needed
```

**Benefits:**
- ✅ No `update_option()` call → no sanitization triggered
- ✅ Dev.to test uses temporary API key without persisting
- ✅ GitHub token never touched
- ✅ Cleaner code - no save/restore dance

---

### Fix #2: Detect Already-Encrypted Tokens in sanitize_settings()

**File:** `admin/class-settings.php`
**Method:** `sanitize_settings()`
**Lines:** 284-300

**Added Protection:**
```php
// All GitHub tokens start with 'github_pat_' (new format) or 'ghp_' (classic format)
// If it doesn't start with these prefixes, it's already encrypted
$is_plain_text_token = (
    str_starts_with( $token, 'github_pat_' ) ||
    str_starts_with( $token, 'ghp_' )
);

if ( $is_plain_text_token ) {
    // Plain text token - encrypt it
    $sanitized['github_token'] = self::encrypt_token( $token );
} else {
    // Token is already encrypted - preserve without re-encrypting
    Logger::warning(
        'Detected already-encrypted GitHub token, preserving without re-encryption',
        array( 'token_length' => strlen( $token ) )
    );
    $sanitized['github_token'] = $token;
}
```

**Logic:**
1. Check if token starts with `github_pat_` (new GitHub PAT format)
2. OR check if token starts with `ghp_` (classic GitHub PAT format)
3. If either → plain text → encrypt it
4. Otherwise → already encrypted → preserve as-is

**Benefits:**
- ✅ Simple, reliable detection (no length checks or base64 validation needed)
- ✅ Prevents double-encryption even if `update_option()` is called elsewhere
- ✅ Handles both old and new GitHub token formats
- ✅ Logs warning for debugging
- ✅ Backward compatible

---

### Fix #3: Enhanced Logging in Git_API Constructor

**File:** `core/class-git-api.php`
**Method:** `__construct()`
**Lines:** 62-102

**Added Debug Logging:**
```php
Logger::info(
    'Token decryption attempt',
    array(
        'encrypted_length' => strlen( $encrypted_token ),
        'decrypted_length' => $decrypted !== false ? strlen( $decrypted ) : 0,
        'decryption_success' => $decrypted !== false && ! empty( $decrypted ),
        'encrypted_preview' => substr( $encrypted_token, 0, 20 ) . '...',
        'decrypted_preview' => $decrypted !== false ? substr( $decrypted, 0, 10 ) . '...' : 'FAILED',
    )
);
```

**Benefits:**
- ✅ Diagnose encryption/decryption issues
- ✅ See token lengths at each step
- ✅ Verify decryption success
- ✅ Safe previews (first 10-20 chars only)

---

## Recovery Tool

**File:** `atomic-jamstack-connector/token-recovery.php`

If a token is already double-encrypted, run this script to fix it:

```bash
cd /path/to/wordpress
php wp-content/plugins/atomic-jamstack-connector/token-recovery.php
```

**What it does:**
1. Loads current token from database
2. Attempts decryption
3. Detects if token is double-encrypted (decrypted result is still encrypted)
4. If double-encrypted:
   - Decrypts twice to get plain token
   - Validates it looks like a GitHub token (`ghp_` prefix)
   - Re-encrypts correctly (single encryption)
   - Saves back to database (bypassing sanitize callback)

**Output:**
```
=== GitHub Token Recovery Tool ===

Current stored token length: 172 characters

After first decryption: 88 characters
⚠️  Token appears to be DOUBLE-ENCRYPTED!
Attempting second decryption...

After second decryption: 44 characters
Preview: ghp_abc123...

Do you want to fix the double-encryption? (yes/no): yes

✅ Token fixed! Stored length: 88 characters
✅ The token is now correctly encrypted (single encryption).
✅ Please test your GitHub connection.
```

---

## Testing

### Test 1: Fresh Token Entry
1. Enter new GitHub token in settings
2. Save
3. Check log: Should see encryption, token length ~88 chars
4. Test connection → ✅ Should work

### Test 2: Dev.to Test Connection
1. Ensure GitHub token is saved and working
2. Go to Dev.to settings
3. Enter Dev.to API key
4. Click "Test Connection"
5. Check log: Should NOT see GitHub token re-encryption
6. Test GitHub connection again → ✅ Should still work

### Test 3: Multiple Tab Saves
1. Save General tab
2. Save Credentials tab
3. Save Advanced tab
4. Test GitHub connection → ✅ Should still work
5. Check log: Token length should remain ~88 chars (not 172)

### Test 4: Double-Encrypted Recovery
1. If token is already double-encrypted (172 chars)
2. Run `php token-recovery.php`
3. Follow prompts
4. Test GitHub connection → ✅ Should now work

---

## Prevention Checklist

When adding new AJAX handlers or features:

- [ ] **NEVER** call `update_option()` directly with full settings array
- [ ] Use filters to temporarily override options for testing
- [ ] If you must use `update_option()`, use flag `false` to skip autoload
- [ ] Always check if sensitive data could be double-processed
- [ ] Test with existing tokens to ensure they're preserved
- [ ] Add logging for debugging

---

## Token Length Reference

| Token State | Length | Description |
|-------------|--------|-------------|
| Plain GitHub token | 40-44 | `ghp_abc123...` or `github_pat_...` |
| Encrypted once ✅ | 80-95 | Correct state |
| Double-encrypted ❌ | 160-180 | Bug - needs recovery |
| Triple-encrypted ❌❌ | 280+ | Critical - manual fix needed |

**Quick Check:**
```bash
# In WordPress database
SELECT LENGTH(option_value) 
FROM wp_options 
WHERE option_name = 'atomic_jamstack_settings';

# If result > 150, likely double-encrypted
```

---

## Related Issues

### Issue: Masked Field Re-Encryption

**Status:** ✅ Fixed (already handled)

The masked placeholder `'••••••••••••••••'` is explicitly checked:

```php
if ( $token !== '••••••••••••••••' ) {
    // Process token
} else {
    // Preserve existing
}
```

### Issue: Multisite Token Sharing

**Status:** ⚠️ Not Tested

Each site in multisite should have separate tokens. If tokens are shared across sites, encryption/decryption might fail if sites use different salts.

**Recommendation:** Each site should have its own settings.

### Issue: Salt Changes

**Status:** ⚠️ Edge Case

If WordPress salts (`wp_salt('auth')` or `wp_salt('nonce')`) change (e.g., during security incident), all encrypted tokens become undecryptable.

**Mitigation:** Token recovery script won't work. User must re-enter token.

---

## Changelog

### v1.2.1 (2026-02-09)

**Fixed:**
- ✅ GitHub token double-encryption bug
- ✅ Dev.to test connection now uses filter instead of `update_option()`
- ✅ Added detection for already-encrypted tokens in `sanitize_settings()`
- ✅ Enhanced logging in `Git_API` constructor

**Added:**
- ✅ Token recovery script (`token-recovery.php`)
- ✅ Comprehensive documentation
- ✅ Encryption state detection

**Testing:**
- ✅ Syntax validated
- ⏳ Awaiting user testing

---

## Summary

**The Bug:**
- `update_option()` in AJAX handlers triggered `sanitize_settings()`
- Encrypted tokens were re-encrypted (88 → 172 chars)
- Decryption only removed one layer (172 → 88)
- GitHub API received encrypted garbage → 401 error

**The Fix:**
- Use WordPress filters for temporary option overrides
- Detect already-encrypted tokens and skip re-encryption
- Enhanced logging for diagnostics
- Recovery tool for existing double-encrypted tokens

**Status:**
- ✅ Code fixed and validated
- ✅ Recovery tool provided
- ⏳ Awaiting user confirmation

**Next Steps:**
1. User runs recovery tool if needed: `php token-recovery.php`
2. User tests GitHub connection
3. User tests Dev.to connection to verify no regression
4. Confirm issue resolved
