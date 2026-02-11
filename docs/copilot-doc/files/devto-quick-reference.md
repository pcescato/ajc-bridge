# Dev.to Adapter - Quick Reference

## File Summary

| File | Lines | Purpose |
|------|-------|---------|
| `adapters/class-devto-adapter.php` | 458 | Converts WP posts to Dev.to markdown |
| `core/class-devto-api.php` | 315 | REST API client for Dev.to |
| `admin/class-settings.php` | +145 | Settings UI + AJAX handler |
| `core/class-sync-runner.php` | +163 | Routing + Dev.to sync logic |
| `assets/js/admin.js` | +43 | AJAX test + mode toggle |

---

## Key Methods

### DevTo_Adapter
```php
convert( \WP_Post $post ): string                // Main conversion
get_front_matter( \WP_Post $post ): array        // Generate front matter
get_file_path( \WP_Post $post ): string          // Returns '' (no files)
```

### DevTo_API
```php
publish_article( string $markdown, ?int $id ): array|\WP_Error  // POST/PUT article
test_connection(): bool|\WP_Error                                // Validate API key
get_articles( int $page, int $per_page ): array|\WP_Error       // Fetch articles
```

### Sync_Runner
```php
run( int $post_id ): array|\WP_Error             // Main entry point
sync_to_devto( \WP_Post $post ): array|\WP_Error // Dev.to flow
sync_to_github( \WP_Post $post ): array|\WP_Error// GitHub flow
```

---

## Settings Keys

```php
$settings = get_option( 'atomic_jamstack_settings', array() );

$settings['devto_api_key']       // string - API key
$settings['devto_mode']          // 'primary' or 'secondary'
$settings['devto_canonical_url'] // string - Base URL for secondary mode
$settings['adapter_type']        // 'hugo' or 'devto'
```

---

## Post Meta Keys

```php
// Dev.to specific
get_post_meta( $post_id, '_devto_article_id', true );  // int - Dev.to article ID
get_post_meta( $post_id, '_devto_article_url', true ); // string - Public URL

// Shared with GitHub sync
get_post_meta( $post_id, '_jamstack_sync_status', true );     // 'pending'|'processing'|'success'|'failed'
get_post_meta( $post_id, '_jamstack_sync_last', true );       // int - Timestamp
get_post_meta( $post_id, '_jamstack_sync_start_time', true ); // int - For timeout
```

---

## Dev.to Front Matter

```yaml
---
title: Post Title                           # Required
published: true                             # bool
description: SEO meta description           # Max 160 chars
tags: tag1, tag2, tag3, tag4               # Max 4, lowercase
cover_image: https://example.com/img.jpg   # Absolute URL required
canonical_url: https://yourblog.com/slug   # Secondary mode only
series: Category Name                       # Optional
---

Markdown content here...
```

---

## API Endpoints

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/api/articles` | Create new article |
| PUT | `/api/articles/{id}` | Update existing article |
| GET | `/api/articles/me/published` | Test connection / list articles |

**Headers:**
```
api-key: YOUR_API_KEY
Content-Type: application/json
```

**Request Body:**
```json
{
  "article": {
    "body_markdown": "---\ntitle: Post\n---\n\nContent"
  }
}
```

---

## Critical Patterns

### Image URLs
```php
// ✅ CORRECT - Absolute URL
$cover_image = wp_get_attachment_url( $thumbnail_id );
if ( ! parse_url( $cover_image, PHP_URL_SCHEME ) ) {
    $cover_image = home_url( $cover_image );
}

// ❌ WRONG - File path
$cover_image = get_attached_file( $thumbnail_id );  // NO!
```

### Settings Merge
```php
// ✅ CORRECT - Preserves other tabs
$existing = get_option( 'atomic_jamstack_settings', array() );
$sanitized = array(); // Add fields from POST
$merged = array_merge( $existing, $sanitized );
return $merged;

// ❌ WRONG - Loses other settings
return $sanitized;  // NO!
```

### Error Handling
```php
// ✅ CORRECT - Return WP_Error
if ( empty( $api_key ) ) {
    return new \WP_Error( 'missing_api_key', 'API key required' );
}

// ❌ WRONG - Throw exception (unless in try/catch)
throw new \Exception( 'API key required' );  // NO!
```

---

## AJAX Actions

| Action | Capability | Purpose |
|--------|------------|---------|
| `atomic_jamstack_test_devto` | manage_options | Test API key |

**JavaScript:**
```javascript
$.ajax({
    url: ajaxurl,
    method: 'POST',
    data: {
        action: 'atomic_jamstack_test_devto',
        nonce: atomicJamstackAdmin.testConnectionNonce,
        api_key: $('input[name="atomic_jamstack_settings[devto_api_key]"]').val()
    },
    success: function(response) {
        if (response.success) {
            // Show green checkmark
        } else {
            // Show red X + error
        }
    }
});
```

---

## Logger Usage

```php
use AtomicJamstack\Core\Logger;

Logger::info( 'Starting Dev.to sync', array( 'post_id' => 123 ) );
Logger::success( 'Sync complete', array( 'article_id' => 456 ) );
Logger::error( 'API error', array( 'error' => 'Unauthorized' ) );
Logger::warning( 'Post not published', array( 'status' => 'draft' ) );
```

---

## Testing Commands

```bash
# Syntax check
php -l adapters/class-devto-adapter.php
php -l core/class-devto-api.php

# Test API connection (wp-cli)
wp eval 'require_once ATOMIC_JAMSTACK_PATH . "core/class-devto-api.php"; $api = new \AtomicJamstack\Core\DevTo_API(); var_dump( $api->test_connection() );'

# Test adapter conversion
wp eval 'require_once ATOMIC_JAMSTACK_PATH . "adapters/class-devto-adapter.php"; $post = get_post(1); $adapter = new \AtomicJamstack\Adapters\DevTo_Adapter(); echo $adapter->convert($post);'

# Test sync
wp eval 'require_once ATOMIC_JAMSTACK_PATH . "core/class-sync-runner.php"; $result = \AtomicJamstack\Core\Sync_Runner::run(1); var_dump($result);'
```

---

## Common Errors

| Error | Cause | Solution |
|-------|-------|----------|
| `missing_api_key` | No API key configured | Add key in settings |
| `connection_failed` | Invalid API key | Check key at dev.to/settings/extensions |
| `HTTP 401` | Expired/revoked key | Generate new API key |
| `HTTP 422` | Invalid markdown format | Check front matter syntax |
| `HTTP 429` | Rate limit exceeded | Wait and retry |
| Cover image not showing | Relative URL used | Ensure absolute URL with scheme |
| Tags rejected | More than 4 tags | Limit to 4 in adapter |
| Post stuck on "processing" | Exception during sync | Check logs, status updated in finally block |

---

## Debugging Steps

1. **Check logs:** `wp-content/uploads/atomic-jamstack-logs/`
2. **Check post meta:** `SELECT * FROM wp_postmeta WHERE post_id = X AND meta_key LIKE '_devto%' OR meta_key LIKE '_jamstack%';`
3. **Enable debug mode:** Settings > General > Debug Logging
4. **Test API key:** Use "Test Connection" button in settings
5. **Verify image URLs:** Inspect cover_image in front matter
6. **Check API response:** Look for HTTP codes in logs
7. **Validate markdown:** Copy markdown output and test on Dev.to manually

---

## Migration from GitHub

If switching from GitHub to Dev.to:

1. Keep existing GitHub settings (don't delete)
2. Add Dev.to API key in Credentials tab
3. **Future:** Change adapter_type to 'devto' in settings
4. Test with one post first
5. Monitor `_devto_article_id` post meta
6. Article IDs allow updates (not duplicates)

---

## Performance Notes

- **Dev.to sync:** ~2-5 seconds per post
- **GitHub sync:** ~10-30 seconds per post (images + Git)
- **Rate limits:** Dev.to has undocumented limits (be conservative)
- **Timeout:** 30 seconds for publish, 15 for test
- **No queuing:** Sync happens immediately (use Action Scheduler for bulk)

---

## Security Checklist

- ✅ API key stored in database (not exposed in HTML/JS)
- ✅ AJAX nonce verification
- ✅ Capability checks (manage_options)
- ✅ Input sanitization (sanitize_text_field, esc_url_raw)
- ✅ Output escaping (esc_html, esc_attr)
- ✅ POST data unslashed (wp_unslash)
- ✅ URL scheme validation (parse_url)
- ⚠️ Consider API key encryption (future)

---

## Quick Start

```php
// 1. Get API key from Dev.to
// https://dev.to/settings/extensions

// 2. Add to settings (admin or code)
$settings = get_option( 'atomic_jamstack_settings', array() );
$settings['devto_api_key'] = 'YOUR_API_KEY';
$settings['devto_mode'] = 'primary'; // or 'secondary'
$settings['devto_canonical_url'] = 'https://yourblog.com'; // if secondary
$settings['adapter_type'] = 'devto'; // Future: set in UI
update_option( 'atomic_jamstack_settings', $settings );

// 3. Sync a post
require_once ATOMIC_JAMSTACK_PATH . 'core/class-sync-runner.php';
$result = \AtomicJamstack\Core\Sync_Runner::run( $post_id );

// 4. Check result
if ( is_wp_error( $result ) ) {
    echo 'Error: ' . $result->get_error_message();
} else {
    echo 'Success! Article ID: ' . $result['id'];
    echo 'URL: ' . $result['url'];
}
```

---

## Status: Production Ready ✅
