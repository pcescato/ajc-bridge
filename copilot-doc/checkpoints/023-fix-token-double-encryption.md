# Fix Token Double-Encryption Bug

**Date:** 2026-02-09
**Status:** ✅ Complete

---

## Problem

GitHub credentials were "unstable" - working initially but failing after subsequent saves or Dev.to test connections.

**Symptoms:**
- GitHub connection test works initially ✅
- After saving settings or testing Dev.to, GitHub fails ❌
- Error: "401 Bad credentials"
- Log shows: `token_length: 172` (double-encrypted)

---

## Root Cause

**Double-Encryption Bug:**

The `ajax_test_devto_connection()` method was calling `update_option()` directly to temporarily save the Dev.to API key for testing. This triggered WordPress's automatic sanitization callback, which:

1. Loaded the full settings array (including already-encrypted GitHub token)
2. Called `sanitize_settings()` on the entire array
3. Saw the 88-char encrypted GitHub token (base64 string)
4. Didn't recognize it as encrypted (no detection logic)
5. Re-encrypted it: 88 chars → 172 chars ❌

**Result:**
- Token stored as 172 chars (double-encrypted)
- `Git_API` decrypts once: 172 → 88 chars
- Tries to use 88-char encrypted string as GitHub token
- GitHub API rejects: **401 Bad Credentials**

---

## Solution

### Fix #1: Remove update_option() from Dev.to Test Handler

**File:** `admin/class-settings.php`
**Method:** `ajax_test_devto_connection()`

**Before:**
```php
// Temporarily save API key for test (TRIGGERS SANITIZATION!)
$settings['devto_api_key'] = $api_key;
update_option(self::OPTION_NAME, $settings); // ❌

$devto_api = new \AtomicJamstack\Core\DevTo_API();
$result = $devto_api->test_connection();

// Restore original (TRIGGERS AGAIN!)
$settings['devto_api_key'] = $original_key;
update_option(self::OPTION_NAME, $settings); // ❌
```

**After:**
```php
// Use WordPress filter to temporarily override (NO DB WRITE!)
add_filter(
    'option_' . self::OPTION_NAME,
    function($value) use ($api_key) {
        if (is_array($value)) {
            $value['devto_api_key'] = $api_key;
        }
        return $value;
    },
    999
);

$devto_api = new \AtomicJamstack\Core\DevTo_API();
$result = $devto_api->test_connection(); // ✅
// Filter auto-removed after request
```

**Benefits:**
- No `update_option()` = No sanitization triggered
- GitHub token never touched
- Cleaner code (no save/restore dance)

---

### Fix #2: Detect Already-Encrypted Tokens

**File:** `admin/class-settings.php`
**Method:** `sanitize_settings()`
**Lines:** 284-300

**Added Protection:**
```php
// All GitHub tokens start with 'github_pat_' (new) or 'ghp_' (classic)
$is_plain_text_token = (
    str_starts_with($token, 'github_pat_') ||
    str_starts_with($token, 'ghp_')
);

if ($is_plain_text_token) {
    // Plain text - encrypt it
    $sanitized['github_token'] = self::encrypt_token($token);
} else {
    // Already encrypted - preserve as-is
    Logger::warning(
        'Detected already-encrypted token, preserving',
        array('token_length' => strlen($token))
    );
    $sanitized['github_token'] = $token;
}
```

**Benefits:**
- Simple, reliable detection (user suggestion!)
- Prevents double-encryption defensively
- Handles both token formats
- Backward compatible

---

### Fix #3: Enhanced Debug Logging

**File:** `core/class-git-api.php`
**Method:** `__construct()`

Added detailed logging of token decryption:
```php
Logger::info(
    'Token decryption attempt',
    array(
        'encrypted_length' => strlen($encrypted_token),
        'decrypted_length' => strlen($decrypted),
        'decryption_success' => true/false,
        'encrypted_preview' => substr($encrypted_token, 0, 20),
        'decrypted_preview' => substr($decrypted, 0, 10),
    )
);
```

---

### Fix #4: Routing Decision Logging

**File:** `core/class-sync-runner.php`
**Method:** `run()`

Added logging to diagnose why Dev.to wasn't being called:
```php
Logger::info(
    'Sync routing decision',
    array(
        'adapter_type' => $adapter_type,
        'devto_mode' => $devto_mode,
    )
);
```

**Revealed the issue:**
- User had `adapter_type = 'hugo'` (not `'devto'`)
- Needed to change in Settings > General > Publishing Destination

---

## Testing Results

### Test 1: GitHub Token Decryption ✅
```
[INFO] Token decryption attempt {
    "encrypted_length": 172,
    "decrypted_length": 93,
    "decryption_success": true,
    "decrypted_preview": "github_pat..."
}
[SUCCESS] Token decrypted successfully
```
**Result:** Token decrypts correctly (93 chars = valid `github_pat_` token)

---

### Test 2: GitHub Connection ✅
```
[SUCCESS] GitHub connection test successful {
    "repo": "pcescato/hugodemo",
    "permissions": {"push": true}
}
```
**Result:** Authentication works!

---

### Test 3: Dev.to Connection ✅
```
[SUCCESS] Dev.to connection test successful
```
**Result:** No GitHub token corruption after Dev.to test

---

### Test 4: Dual Publishing ✅

After user changed settings:
- Settings > General: Adapter = "Dev.to Publishing"
- Settings > Credentials: Mode = "Secondary (Dual Publishing)"

```
[INFO] Sync routing decision {
    "adapter_type": "devto",
    "devto_mode": "secondary"
}
[INFO] Dual publishing mode: GitHub + Dev.to
[INFO] Step 1: Publishing to GitHub (canonical source)
[SUCCESS] GitHub sync complete
[INFO] Step 2: Syndicating to Dev.to (with canonical_url)
[SUCCESS] Dev.to sync complete
[SUCCESS] Dual publish complete: GitHub + Dev.to
```

**Result:** Both GitHub and Dev.to receive the post! 🎉

---

## Files Changed

### Modified

1. **admin/class-settings.php**
   - Lines 284-300: Added plain text token detection
   - Lines 1466-1500: Refactored Dev.to test handler (no update_option)

2. **core/class-git-api.php**
   - Lines 62-102: Enhanced token decryption logging

3. **core/class-sync-runner.php**
   - Lines 66-91: Added routing decision logging

### Created

1. **token-recovery.php** - Recovery tool for double-encrypted tokens
2. **token-diagnostic.php** - Encryption/decryption testing tool
3. **double-encryption-bug-fix.md** - Comprehensive documentation

---

## Key Learnings

### 1. GitHub Token Formats
All valid GitHub tokens start with:
- `github_pat_` - New fine-grained PAT format
- `ghp_` - Classic PAT format

**Detection:** Simple prefix check is more reliable than length/base64 validation.

### 2. WordPress update_option() Gotcha
Calling `update_option()` directly **still triggers** the registered sanitize callback, even outside form submissions. This can cause unintended double-processing.

**Solution:** Use WordPress filters to temporarily override options without persisting.

### 3. Token Length Reference
| State | Length | Description |
|-------|--------|-------------|
| Plain GitHub token | 40-95 | `github_pat_...` or `ghp_...` |
| Encrypted once ✅ | 80-172 | Correct state |
| Double-encrypted ❌ | 160+ | Bug detected |

### 4. Settings Tab Isolation
When saving one tab, fields from other tabs aren't in POST. The sanitization callback must explicitly preserve them using `get_option()` + `array_merge()`.

**Critical:** Token preservation requires three-layer protection:
1. Field in POST but empty → preserve existing
2. Field in POST and not empty → update
3. Field not in POST → preserve existing

---

## Recovery Tool

**File:** `atomic-jamstack-connector/token-recovery.php`

If a token is already double-encrypted:

```bash
cd /path/to/wordpress
php wp-content/plugins/atomic-jamstack-connector/token-recovery.php
```

**Output:**
```
=== GitHub Token Recovery Tool ===

Current stored token length: 172 characters

After first decryption: 93 characters
Preview: github_pat...
✅ Token appears to be correctly encrypted (single encryption).
```

---

## Configuration Checklist

For **Dual Publishing** (GitHub + Dev.to):

### General Tab
- [x] Publishing Destination: **"Dev.to Publishing"**

### Credentials Tab
- [x] GitHub Repository: `owner/repo`
- [x] GitHub Token: `github_pat_...`
- [x] Test GitHub: ✅ Success
- [x] Dev.to API Key: `(your key)`
- [x] Dev.to Mode: **"Secondary (Dual Publishing)"**
- [x] Dev.to Canonical URL: `https://yourblog.com`
- [x] Test Dev.to: ✅ Success

### Test Sync
- [x] Sync a post
- [x] Check logs: "Dual publishing mode: GitHub + Dev.to"
- [x] Verify GitHub: Post appears in repo
- [x] Verify Dev.to: Post appears with canonical_url

---

## Status

✅ **All Issues Resolved**

- [x] GitHub token no longer double-encrypted
- [x] Dev.to test connection doesn't corrupt GitHub token
- [x] Token detection simplified (prefix check)
- [x] Enhanced logging for diagnostics
- [x] Dual publishing works correctly
- [x] User confirmed: "works fine!"

---

## Next Steps

**Optional Enhancements:**

1. **API Key Encryption**
   - Dev.to API key currently stored in plain text
   - Could apply same encryption as GitHub token

2. **Per-Post Adapter Override**
   - Meta box to choose destination per post
   - Override global adapter setting

3. **Bulk Dual Publishing**
   - Sync multiple existing posts to both platforms
   - Background process for large batches

4. **Triple Publishing**
   - Add Medium, Hashnode, etc.
   - Unified syndication dashboard

---

## Summary

**Problem:** GitHub token double-encryption caused authentication failures after Dev.to operations.

**Solution:** 
1. Removed `update_option()` from AJAX test handlers
2. Added prefix-based plain text token detection
3. Enhanced logging for debugging
4. Verified dual publishing workflow

**Outcome:** Stable credentials, dual publishing working! 🎉
