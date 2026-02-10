# Dual Publishing Workflow - Secondary Mode

## Feature Request

When Dev.to mode is set to **'secondary'**, publish to **both** destinations:
1. **GitHub first** (Hugo static site) - the canonical/primary source
2. **Then Dev.to** - syndication with canonical_url pointing to Hugo site

This enables proper SEO with the Hugo site as the authoritative source, while still getting Dev.to's audience reach.

---

## Implementation

### Sync Flow Logic (core/class-sync-runner.php)

**Three publishing modes now supported:**

```php
// 1. GitHub Only (adapter_type = 'hugo')
if ( 'hugo' === $adapter_type ) {
    return self::sync_to_github( $post );
}

// 2. Dev.to Primary (adapter_type = 'devto', devto_mode = 'primary')
if ( 'devto' === $adapter_type && 'primary' === $devto_mode ) {
    return self::sync_to_devto( $post ); // Dev.to only
}

// 3. Dual Publishing (adapter_type = 'devto', devto_mode = 'secondary')
if ( 'devto' === $adapter_type && 'secondary' === $devto_mode ) {
    return self::sync_dual_publish( $post ); // GitHub + Dev.to
}
```

### New Method: sync_dual_publish()

**Workflow:**

```
Step 1: Publish to GitHub (canonical source)
    ↓
  Success?
    ↓
  YES → Continue to Step 2
  NO  → Return error, don't publish to Dev.to
    ↓
Step 2: Syndicate to Dev.to (with canonical_url)
    ↓
  Success?
    ↓
  YES → Return combined success
  NO  → Return partial success (GitHub OK, Dev.to failed)
```

**Code:**

```php
private static function sync_dual_publish( \WP_Post $post ): array|\WP_Error {
    $post_id = $post->ID;
    Logger::info( 'Starting dual publish workflow', array( 'post_id' => $post_id ) );

    // STEP 1: Sync to GitHub (Hugo) - canonical source
    Logger::info( 'Step 1: Publishing to GitHub (canonical source)', ... );
    $github_result = self::sync_to_github( $post );

    if ( is_wp_error( $github_result ) ) {
        // GitHub failed - don't proceed to Dev.to
        // Canonical must exist first!
        return $github_result;
    }

    // STEP 2: Syndicate to Dev.to with canonical_url
    Logger::info( 'Step 2: Syndicating to Dev.to (with canonical_url)', ... );
    $devto_result = self::sync_to_devto( $post );

    if ( is_wp_error( $devto_result ) ) {
        // GitHub OK, Dev.to failed - partial success
        return array(
            'status'  => 'partial',
            'github'  => $github_result,
            'devto'   => $devto_result,
            'message' => 'Published to GitHub successfully, but Dev.to syndication failed.',
        );
    }

    // Both succeeded
    return array(
        'status'  => 'success',
        'github'  => $github_result,
        'devto'   => $devto_result,
        'message' => 'Published to GitHub and syndicated to Dev.to successfully.',
    );
}
```

**Key Points:**

1. **GitHub must succeed first** - If GitHub fails, Dev.to is skipped (no point in syndicating if canonical doesn't exist)

2. **Partial success handling** - If GitHub succeeds but Dev.to fails, it's logged as a warning (not total failure)

3. **Combined results** - Returns both GitHub and Dev.to results for transparency

4. **Comprehensive logging** - Each step logged separately for debugging

---

## UI Updates

### Publishing Destination Selector (General Tab)

**Before:**
- ⚪ Static Site (Hugo/Jekyll)
- ⚪ Dev.to Platform

**After:**
- ⚪ **GitHub Only (Hugo/Jekyll)**
  - *Publish to Hugo/Jekyll static site via GitHub. Requires GitHub credentials.*

- ⚪ **Dev.to Publishing**
  - *Publish to Dev.to. Mode determined by Dev.to settings: Primary (Dev.to only) or Secondary (GitHub first, then Dev.to with canonical URL).*

**Helper text:**
"Configure credentials in the Credentials tab. For Dev.to Secondary mode, configure both GitHub and Dev.to credentials."

---

### Dev.to Mode Selector (Credentials Tab)

**Before:**
- ⚪ **Primary**
  - *Dev.to is the main publication (no canonical URL added)*

- ⚪ **Secondary**
  - *Dev.to syndicates from another site (includes canonical_url to prevent SEO duplicate content)*

**After:**
- ⚪ **Primary**
  - *Dev.to only - no GitHub sync. Dev.to is the main publication.*

- ⚪ **Secondary (Dual Publishing)**
  - *GitHub first (canonical), then Dev.to syndication. Requires both GitHub and Dev.to credentials. Dev.to article includes canonical_url to prevent SEO duplicate content.*

---

## Configuration Examples

### Scenario 1: GitHub Only (Traditional)

**Settings:**
- General > Publishing Destination: **GitHub Only**
- Credentials > GitHub: Configured
- Credentials > Dev.to: (Not needed)

**Behavior:**
```
Sync Post → GitHub → Done
```

---

### Scenario 2: Dev.to Primary (Dev.to as main platform)

**Settings:**
- General > Publishing Destination: **Dev.to Publishing**
- Credentials > Dev.to Mode: **Primary**
- Credentials > Dev.to API Key: Configured
- Credentials > GitHub: (Not needed)

**Behavior:**
```
Sync Post → Dev.to only → Done
```

**Dev.to Article:**
```yaml
---
title: My Post
published: true
# No canonical_url - this IS the canonical
---
```

---

### Scenario 3: Dual Publishing (Hugo canonical + Dev.to syndication)

**Settings:**
- General > Publishing Destination: **Dev.to Publishing**
- Credentials > Dev.to Mode: **Secondary (Dual Publishing)**
- Credentials > Dev.to API Key: Configured
- Credentials > Dev.to Canonical URL: `https://yourblog.com`
- Credentials > GitHub: Configured ✅

**Behavior:**
```
Sync Post
  ↓
1. GitHub Sync (creates canonical at https://yourblog.com/my-post)
  ↓
2. Dev.to Syndication (with canonical_url)
  ↓
Done
```

**Dev.to Article:**
```yaml
---
title: My Post
published: true
canonical_url: https://yourblog.com/my-post  ← Points to Hugo site
---
```

**SEO Benefits:**
- Hugo site gets canonical credit
- Dev.to audience sees the article
- No duplicate content penalty
- Google knows Hugo is the original source

---

## Log Output Examples

### Successful Dual Publishing

```
[INFO] Sync runner started {"post_id":1492}
[INFO] Dual publishing mode: GitHub + Dev.to {"post_id":1492}
[INFO] Starting dual publish workflow {"post_id":1492}
[INFO] Step 1: Publishing to GitHub (canonical source) {"post_id":1492}
[INFO] GitHub connection validated {"post_id":1492}
[INFO] Media processor initialized {"post_id":1492}
[SUCCESS] GitHub sync complete {"commit":"abc123","file_path":"content/posts/2024-my-post.md"}
[SUCCESS] GitHub sync complete, proceeding to Dev.to syndication {"post_id":1492}
[INFO] Step 2: Syndicating to Dev.to (with canonical_url) {"post_id":1492}
[INFO] Starting Dev.to sync {"post_id":1492}
[INFO] Dev.to API response {"http_code":201,"success":true}
[SUCCESS] Dev.to sync complete {"article_id":789012,"url":"https://dev.to/..."}
[SUCCESS] Dual publish complete: GitHub + Dev.to {"post_id":1492,"github":"success","devto":"success"}
```

---

### Partial Success (GitHub OK, Dev.to Failed)

```
[INFO] Dual publishing mode: GitHub + Dev.to {"post_id":1492}
[INFO] Step 1: Publishing to GitHub (canonical source) {"post_id":1492}
[SUCCESS] GitHub sync complete {"commit":"abc123"}
[INFO] Step 2: Syndicating to Dev.to (with canonical_url) {"post_id":1492}
[ERROR] Dev.to API error {"http_code":401,"error":"Unauthorized"}
[WARNING] Dual publish: GitHub succeeded but Dev.to failed {"post_id":1492,"github":"success","devto_error":"..."}
```

**Result:** Post is on GitHub (canonical exists), but not on Dev.to. User can retry Dev.to later.

---

### GitHub Failure (Dev.to Skipped)

```
[INFO] Dual publishing mode: GitHub + Dev.to {"post_id":1492}
[INFO] Step 1: Publishing to GitHub (canonical source) {"post_id":1492}
[ERROR] GitHub authentication failed - 401 Bad credentials
[ERROR] Dual publish failed at GitHub step {"post_id":1492,"error":"..."}
```

**Result:** No publishing at all (correct behavior - can't syndicate without canonical).

---

## Decision Flow

```
User selects "Dev.to Publishing" in General tab
                    ↓
            Check Dev.to Mode
                    ↓
        ┌───────────┴───────────┐
        ↓                       ↓
    PRIMARY                 SECONDARY
        ↓                       ↓
  Dev.to only          Dual Publishing
        ↓                       ↓
  Requires:            Requires:
  - Dev.to API Key     - Dev.to API Key
                       - Dev.to Canonical URL
                       - GitHub credentials
        ↓                       ↓
  Sync → Dev.to        Sync → GitHub → Dev.to
```

---

## Migration Guide

### Existing Users (GitHub Only)

**Before v1.2.0:**
```
Settings: N/A
Sync: GitHub only
```

**After v1.2.0:**
```
General > Publishing Destination: Defaults to "GitHub Only"
Sync: GitHub only (unchanged)
```

**Action needed:** None (backward compatible)

---

### New Users (Want Dual Publishing)

**Step 1: Configure Credentials**
1. Credentials tab > GitHub section
   - Repository: `owner/repo`
   - Branch: `main`
   - Token: `ghp_...`
   - Test Connection ✅

2. Credentials tab > Dev.to section
   - API Key: `...`
   - Mode: **Secondary (Dual Publishing)**
   - Canonical URL: `https://yourblog.com`
   - Test Connection ✅

**Step 2: Select Adapter**
1. General tab > Publishing Destination
   - Select: **Dev.to Publishing**
   - Save Changes

**Step 3: Sync a Post**
```
Result:
- GitHub: https://yourblog.com/my-post (canonical)
- Dev.to: https://dev.to/you/my-post (with canonical_url)
```

---

## Testing Checklist

### Test 1: GitHub Only (Traditional)
- [ ] General > Select "GitHub Only"
- [ ] Sync post → GitHub only
- [ ] Log shows GitHub sync, no Dev.to
- [ ] Post exists on GitHub

### Test 2: Dev.to Primary
- [ ] General > Select "Dev.to Publishing"
- [ ] Credentials > Mode = Primary
- [ ] Sync post → Dev.to only
- [ ] Log shows Dev.to sync, no GitHub
- [ ] Post exists on Dev.to
- [ ] No canonical_url in front matter

### Test 3: Dual Publishing (Secondary)
- [ ] General > Select "Dev.to Publishing"
- [ ] Credentials > Mode = Secondary
- [ ] Credentials > Both GitHub and Dev.to configured
- [ ] Sync post → GitHub first, then Dev.to
- [ ] Log shows both steps
- [ ] Post exists on GitHub
- [ ] Post exists on Dev.to
- [ ] Dev.to article has canonical_url pointing to GitHub

### Test 4: Partial Failure Handling
- [ ] Configure for dual publishing
- [ ] Temporarily break Dev.to API key
- [ ] Sync post
- [ ] GitHub succeeds ✅
- [ ] Dev.to fails ❌
- [ ] Log shows warning (not error)
- [ ] Status = 'partial'

---

## Benefits

### For Users
1. **SEO Control** - Hugo site gets canonical credit
2. **Audience Reach** - Dev.to community sees articles
3. **Flexibility** - Can switch between modes
4. **No Duplication** - Canonical URL prevents penalties

### For Developers
1. **Clean Architecture** - Separate methods for each flow
2. **Comprehensive Logging** - Each step tracked
3. **Error Handling** - Partial success supported
4. **Extensibility** - Easy to add more platforms

---

## Code Changes Summary

### core/class-sync-runner.php

**Lines 66-77:** Enhanced routing logic
```php
// Check adapter type and mode
if ( 'devto' === $adapter_type ) {
    if ( 'secondary' === $devto_mode ) {
        return self::sync_dual_publish( $post ); // NEW
    }
    return self::sync_to_devto( $post );
}
return self::sync_to_github( $post );
```

**Lines 79-165:** Added sync_dual_publish() method
- Sequential execution: GitHub → Dev.to
- Comprehensive error handling
- Partial success support
- Detailed logging

**Total:** +90 lines

### admin/class-settings.php

**Lines 676-683:** Updated adapter descriptions
- Clarified "GitHub Only" vs "Dev.to Publishing"
- Explained secondary mode requires both credentials

**Lines 824-843:** Enhanced mode descriptions
- "Primary" → "Dev.to only - no GitHub sync"
- "Secondary" → "Secondary (Dual Publishing)" with full explanation

**Total:** +10 lines (changes only)

---

## Future Enhancements

1. **Per-post adapter override** - Meta box to choose destination per post
2. **Retry mechanism** - Automatic retry for Dev.to if GitHub succeeded
3. **Triple publishing** - GitHub + Dev.to + Medium/Hashnode
4. **Selective sync** - Choose which categories go to which platform
5. **Batch operations** - Bulk dual publish for existing posts

---

## Status

✅ **Dual publishing implemented**
✅ **UI updated with clear explanations**
✅ **Comprehensive logging**
✅ **Partial success handling**
✅ **Backward compatible**
✅ **Syntax validated**

**Ready for testing!**
