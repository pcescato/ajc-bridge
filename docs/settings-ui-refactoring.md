# Settings Page UI Refactoring

## Overview

The settings page has been refactored to use a tabbed interface for better organization and enhanced GitHub token security.

## Architecture

### Tab Structure

```
Settings (Main Tab)
├── General (Sub-Tab)
│   ├── Content Types
│   ├── Hugo Configuration
│   └── Debug Settings
└── GitHub Credentials (Sub-Tab)
    └── GitHub Configuration
        ├── Repository
        ├── Branch
        └── Personal Access Token

Bulk Operations (Main Tab)
└── Bulk Sync Controls

Sync History (Separate Page)
└── Monitoring Dashboard
```

## Features Implemented

### 1. Tabbed Interface

**General Tab**
- Content Types selection (Posts/Pages)
- Hugo Configuration (Front Matter Template)
- Debug Settings (Enable logging)

**GitHub Credentials Tab**
- Repository (owner/repo format)
- Branch (default: main)
- Personal Access Token (masked for security)

### 2. Token Security

**Protection Mechanisms:**

1. **Value Masking**
   - Stored tokens displayed as `••••••••••••••••`
   - Never displays actual token value in UI
   - Shows "Token already saved" placeholder

2. **Preserve Existing Token**
   - Empty field = Keep existing token
   - New value = Update token
   - Masked value = Keep existing token

3. **Sanitization Logic**
   ```php
   if ( ! empty( $input['github_token'] ) ) {
       if ( $token !== '••••••••••••••••' ) {
           // Update with new token
           $sanitized['github_token'] = self::encrypt_token( $token );
       } else {
           // Keep existing token
           $sanitized['github_token'] = $existing_settings['github_token'];
       }
   } else {
       // Keep existing token when empty
       $sanitized['github_token'] = $existing_settings['github_token'];
   }
   ```

### 3. UX Improvements

**Active Tab Preservation**
- Tab state preserved after settings save
- Uses hidden form field + redirect filter
- Seamless user experience

**Test Connection Button**
- Located in GitHub Credentials tab
- Verifies token without full sync
- Immediate feedback via AJAX

**Contextual Help Text**
- Different messages for new vs. existing token
- Clear instructions on token management
- Links to GitHub token creation page

## Implementation Details

### Files Modified

1. **admin/class-settings.php**
   - Added `handle_settings_redirect()` method
   - Modified `register_settings()` for conditional section registration
   - Updated `render_settings_tab()` with sub-tab navigation
   - Enhanced `render_token_field()` with masking
   - Improved `sanitize_settings()` with token preservation

### URL Structure

**General Settings:**
```
?page=atomic-jamstack-settings&tab=settings&settings_tab=general
```

**GitHub Credentials:**
```
?page=atomic-jamstack-settings&tab=settings&settings_tab=credentials
```

**Bulk Operations:**
```
?page=atomic-jamstack-settings&tab=bulk
```

### Conditional Section Registration

Settings sections are registered conditionally based on active tab:

```php
$current_tab = isset( $_GET['settings_tab'] ) ? sanitize_key( $_GET['settings_tab'] ) : 'general';

if ( 'general' === $current_tab ) {
    // Register General tab sections
}

if ( 'credentials' === $current_tab ) {
    // Register Credentials tab sections
}
```

This approach:
- Improves performance (only load needed sections)
- Keeps codebase organized
- Prevents section conflicts

## Security Considerations

### Token Storage

1. **Encryption**: Tokens encrypted using AES-256-CBC
2. **Never Displayed**: Original token never shown in UI
3. **Update Protection**: Can't accidentally overwrite with empty value
4. **Admin Only**: `manage_options` capability required

### Form Handling

1. **Nonce Verification**: WordPress handles via `settings_fields()`
2. **Capability Check**: Verified before rendering and saving
3. **Sanitization**: All inputs sanitized before storage
4. **XSS Prevention**: Output escaped properly

## User Workflows

### First-Time Setup

1. Navigate to Settings > General
2. Configure Content Types and Hugo settings
3. Switch to GitHub Credentials tab
4. Enter Repository (e.g., `username/repo`)
5. Enter Branch (default: `main`)
6. Enter Personal Access Token
7. Click "Test Connection" to verify
8. Save Changes

### Updating Token

1. Navigate to Settings > GitHub Credentials
2. Token field shows `••••••••••••••••`
3. Click in field and enter new token
4. Click "Test Connection" (optional)
5. Save Changes
6. Old token replaced with new one

### Keeping Existing Token

1. Navigate to Settings > GitHub Credentials
2. Token field shows `••••••••••••••••`
3. Leave field unchanged OR clear it
4. Modify other settings (repo, branch)
5. Save Changes
6. Token remains unchanged

## Testing Checklist

- [x] General tab displays correct sections
- [x] Credentials tab displays GitHub settings
- [x] Token field shows masked value
- [x] Empty token preserves existing value
- [x] New token updates correctly
- [x] Active tab preserved after save
- [x] Test Connection button functional
- [x] All translations working
- [x] No console errors
- [x] Mobile responsive

## Browser Compatibility

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## Accessibility

- Keyboard navigation supported
- Screen reader friendly
- WCAG 2.1 Level AA compliant
- Focus indicators visible

## Performance

- No additional database queries
- Minimal JavaScript overhead
- CSS uses native WordPress styles
- Fast page load times

## Future Enhancements

**Potential Improvements:**

1. **Token Validation**
   - Real-time token format validation
   - Scope verification (check repo permissions)
   - Expiration warning

2. **Multiple Tokens**
   - Support for multiple repositories
   - Token rotation system
   - Backup token configuration

3. **Visual Indicators**
   - Connection status badge
   - Last verified timestamp
   - Token expiration countdown

4. **Advanced Settings**
   - Image quality sliders
   - Commit message templates
   - Scheduling options

5. **Export/Import**
   - Settings export (excluding tokens)
   - Bulk configuration
   - Migration tools

## Troubleshooting

### Token Not Saving

**Problem**: Token appears to save but doesn't work

**Solution**:
1. Clear the token field completely
2. Enter a fresh token from GitHub
3. Click "Test Connection" before saving
4. Save and verify connection

### Tab Not Preserved

**Problem**: Returns to wrong tab after save

**Solution**:
1. Ensure JavaScript is enabled
2. Check for conflicting plugins
3. Clear browser cache
4. Test in incognito mode

### Masked Value Shown in Code

**Problem**: Worried about security of `••••••••••••••••`

**Explanation**: This is purely a UI placeholder. The actual encrypted token is never sent to the browser. The masked value is generated on each page load and has no relation to the real token.

## Code Examples

### Checking Token Presence

```php
$settings = get_option( 'atomic_jamstack_settings', array() );
$has_token = ! empty( $settings['github_token'] );

if ( $has_token ) {
    // Token configured
} else {
    // Prompt user to configure
}
```

### Decrypting Token

```php
$encrypted_token = $settings['github_token'];
$decrypted_token = Settings::decrypt_token( $encrypted_token );
// Use $decrypted_token for API calls
```

## Version History

- **1.1.0** - Added tabbed interface and token security
- **1.0.0** - Initial single-page settings

## Migration Notes

**Upgrading from 1.0.0:**

No migration required. Existing settings and tokens remain intact. The new UI automatically organizes them into tabs.

**Downgrading:**

Not recommended. If necessary, existing tokens will still work but UI will revert to single-page layout.
