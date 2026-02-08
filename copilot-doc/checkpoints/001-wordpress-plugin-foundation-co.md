<overview>
The user is building a production-grade WordPress plugin called "WP Jamstack Sync" that automates publishing from WordPress to Hugo static sites via GitHub API. The plugin must follow specifications.md as the single source of truth, use WordPress native APIs exclusively (no shell commands), implement async queue processing with Action Scheduler, encrypt GitHub tokens with AES-256-CBC, and maintain _jamstack_sync_status as the single source of truth for sync status. The approach has been incremental: plugin bootstrap → core infrastructure → production hardening → GitHub integration → adapter layer → settings UI → dependency packaging.
</overview>

<history>
1. User requested plugin file structure and bootstrap foundation
   - Created wp-jamstack-sync.php with proper headers, version checks (WP 6.9+, PHP 8.1+), activation/deactivation hooks
   - Generated directory structure matching specifications section 7.1
   - Implemented .env loader for development environment only

2. User requested core class skeletons (structure only, no logic)
   - Created class-plugin.php (singleton bootstrap)
   - Created class-sync-runner.php (sync orchestrator stub)
   - Created class-queue-manager.php (async queue abstraction stub)
   - Created class-logger.php (logging interface stub)
   - Created class-git-api.php (GitHub API client stub)
   - All classes use declare(strict_types=1) and full docblocks

3. User requested production-hardened Queue_Manager implementation
   - Implemented full enqueue/cancel/get_status/retry_failed methods
   - Added transient-based locking (60s auto-expiration)
   - Added retry counter with max 3 attempts
   - Added duplicate job prevention and race condition protection
   - Implemented status lifecycle (pending/processing/success/error/cancelled)
   - Action Scheduler preferred, WP Cron fallback
   - Registered Queue_Manager::init() in Plugin bootstrap

4. User requested Plugin bootstrap wiring
   - Modified Plugin::__construct() to hook into plugins_loaded
   - Added load_core_classes() to require all core files
   - Added init_core_systems() to call Queue_Manager::init()
   - Updated wp-jamstack-sync.php to load and initialize Plugin class

5. User requested Git_API::test_connection() implementation
   - Implemented full connection test with GitHub REST API
   - Added validation for token, repo format, permissions
   - Comprehensive error handling (401, 403, 404, rate limits)
   - Uses wp_remote_get() with proper headers
   - Full logging via Logger class

6. User requested completion of core plugin foundation
   - Implemented Logger with file logging, debug log, and database storage
   - Implemented all Git_API methods (get_branch_sha, create_or_update_file, get_file, delete_file, get_rate_limit)
   - Updated Sync_Runner with validation, simple markdown generation, and GitHub upload
   - All classes now have working implementations

7. User requested adapter layer and settings UI
   - Created interface-adapter.php with convert(), get_file_path(), get_front_matter() methods
   - Created class-hugo-adapter.php implementing Adapter_Interface
   - Hugo adapter generates YAML front matter with title, date, tags, categories, author, featured_image
   - Basic HTML to Markdown conversion implemented
   - Created class-settings.php with Settings API integration
   - Settings fields: github_repo, github_branch, github_token (password), debug_mode
   - AJAX "Test Connection" button with nonce security
   - Created class-admin.php to coordinate admin UI
   - Added admin CSS and JavaScript files

8. User requested security implementation and async logic completion
   - Added AES-256-CBC token encryption in Settings::sanitize_settings()
   - Uses wp_salt('auth') and wp_salt('nonce') for encryption key and IV
   - Added decrypt_token() method in Git_API constructor
   - Queue_Manager already had full Action Scheduler implementation (enqueue/cancel/process_sync)
   - Confirmed wpjamstack_sync_post hook registered and calls Sync_Runner::run()

9. User requested admin visibility layer and content conversion enhancements
   - Created class-columns.php with visual status indicators (dashicons: clock, spinner, checkmark, warning)
   - Added timestamp display ("Synced X ago")
   - Inline CSS for color-coded status display with spinner animation
   - Enhanced Hugo_Adapter with improved HTML to Markdown conversion
   - Added Gutenberg block handling
   - Updated Sync_Runner to use Hugo_Adapter instead of simple markdown
   - Added update_sync_meta() to maintain _jamstack_sync_status and _jamstack_sync_last

10. User requested dependency packaging
    - Created composer.json with woocommerce/action-scheduler, intervention/image, league/html-to-markdown
    - Updated wp-jamstack-sync.php to load vendor/autoload.php if present
    - Refactored Hugo_Adapter to use League\HTMLToMarkdown\HtmlConverter with fallback
    - Installed Composer via apt
    - Installed required PHP extensions (php-xml, php-mbstring, php-gd, php-curl)
    - Ran composer install successfully
    - Dependencies installed in vendor/ directory
</history>

<work_done>
Files created:
- wp-jamstack-sync/wp-jamstack-sync.php (main plugin bootstrap with autoloader)
- wp-jamstack-sync/composer.json (dependency configuration)
- core/class-plugin.php (singleton bootstrap with plugins_loaded hook)
- core/class-queue-manager.php (production-hardened async queue with locking)
- core/class-sync-runner.php (sync orchestrator using Hugo_Adapter)
- core/class-git-api.php (GitHub API client with all methods, token decryption)
- core/class-logger.php (file + debug log + database storage)
- adapters/interface-adapter.php (adapter contract)
- adapters/class-hugo-adapter.php (WordPress to Hugo with League\HTMLToMarkdown)
- admin/class-settings.php (settings page with AES-256-CBC token encryption)
- admin/class-admin.php (admin coordinator)
- admin/class-columns.php (posts list status column with visual indicators)
- assets/css/admin.css (status styling with animations)
- assets/js/admin.js (AJAX connection test)

Work completed:
- [x] Plugin bootstrap with version checks and activation hooks
- [x] Core class structure with all interfaces defined
- [x] Production-hardened Queue_Manager with locking and retry logic
- [x] Full GitHub API integration (test_connection, CRUD operations)
- [x] Logger with file, debug log, and database storage
- [x] Hugo adapter with YAML front matter generation
- [x] Settings page with encrypted token storage
- [x] Admin columns with visual status indicators
- [x] Token encryption/decryption (AES-256-CBC)
- [x] Enhanced HTML to Markdown conversion
- [x] Composer dependencies installed
- [ ] Action Scheduler bundling (currently via Composer)
- [ ] Post save hooks to trigger auto-sync
- [ ] Bulk actions in posts list
- [ ] WP-CLI commands
- [ ] Image optimization integration
- [ ] Testing in WordPress environment

Current state:
- Plugin foundation is complete and internally consistent
- All core classes have working implementations
- Dependencies installed via Composer (vendor/ directory present)
- PSR-4 autoloading warnings expected (WordPress file naming convention)
- Ready for WordPress installation and testing
- No syntax errors in PHP files
</work_done>

<technical_details>
Architecture decisions (from specifications.md and ADRs):
- Single source of truth: _jamstack_sync_status post meta for all sync status
- Queue_Manager is the ONLY async entry point (no sync logic elsewhere)
- Sync_Runner is the ONLY sync logic entry point (all work delegated here)
- WordPress native APIs only: wp_remote_* for HTTP, no shell_exec/Git CLI (ADR-05)
- Action Scheduler preferred, WP Cron as fallback
- Asynchronous by default: admin interface never blocks (ADR-06)

Security implementation:
- AES-256-CBC encryption for GitHub tokens
- Encryption key: hash('sha256', wp_salt('auth'), true)
- Encryption IV: substr(hash('sha256', wp_salt('nonce'), true), 0, 16)
- Token encrypted in Settings::sanitize_settings(), decrypted in Git_API::__construct()
- All settings sanitized (sanitize_text_field, sanitize_textarea_field)
- Nonces used for all AJAX requests

Queue Manager implementation:
- Transient-based locking with 60-second auto-expiration (prevents deadlocks)
- Retry counter stored in _jamstack_retry_count post meta (max 3 attempts)
- Duplicate job prevention: checks if already pending/processing before enqueuing
- Status lifecycle: pending → processing → success/error/cancelled
- Lock acquisition before processing, always released after (even on error)
- is_scheduled() check using as_has_scheduled_action() or wp_next_scheduled()

Hugo Adapter details:
- Front matter: YAML format with title, date, lastmod, draft, description, tags, categories, author, featured_image
- File path pattern: content/posts/YYYY-MM-DD-slug.md
- Uses League\HTMLToMarkdown\HtmlConverter when available, fallback to basic conversion
- Strips Gutenberg block comments
- Converts WordPress tags → Hugo tags, categories → Hugo categories
- Featured image extracted via get_post_thumbnail_id()

Git API implementation:
- Base URL: https://api.github.com
- User-Agent: WP-Jamstack-Sync/{VERSION}
- All methods use wp_remote_get/wp_remote_request
- File content base64 encoded for API
- SHA required for updates/deletes (retrieved via get_file())
- Rate limit detection via x-ratelimit-remaining header

Issues resolved:
- Composer not installed: Fixed with apt install composer
- Missing PHP extensions: Installed php-xml, php-mbstring, php-gd, php-curl for League\HTMLToMarkdown
- Token storage security: Implemented AES-256-CBC encryption
- PSR-4 warnings: Expected due to WordPress class-*.php naming convention, autoloader works correctly

Dependencies:
- woocommerce/action-scheduler ^3.7 (async queue management)
- intervention/image ^2.7 (future image optimization)
- league/html-to-markdown ^5.1 (professional HTML to Markdown conversion)

Quirks and gotchas:
- WordPress file naming uses class-*.php instead of PSR-4 compliant Class.php
- Composer autoloader loaded conditionally (only if vendor/autoload.php exists)
- Logger only writes when debug_mode enabled in settings
- Queue_Manager::SYNC_HOOK = 'wpjamstack_sync_post' (must match in Action Scheduler)
- Hugo adapter applies the_content filter before conversion (runs shortcodes, etc.)
- Git_API creates files when SHA is null, updates when SHA provided
- Settings stored in single serialized option: wpjamstack_settings

Unanswered questions:
- How to bundle Action Scheduler library for WordPress.org submission?
- Should image optimization happen before or after Markdown conversion?
- What triggers initial sync (post save hooks not yet implemented)?
- How to handle media library images vs external images?
</technical_details>

<important_files>
- wp-jamstack-sync/wp-jamstack-sync.php
  - Main plugin file with WordPress headers
  - Loads Composer autoloader (line 24-26)
  - Loads .env in development (line 30-32)
  - Version checks in wpjamstack_activate() (line 83-108)
  - Loads and initializes Plugin class (line 133-137)

- core/class-plugin.php
  - Singleton bootstrap class
  - Constructor hooks into plugins_loaded (line 36)
  - init() loads core classes and initializes systems (line 52-70)
  - load_core_classes() requires all core files (line 76-81)
  - init_core_systems() calls Queue_Manager::init() (line 90)
  - Conditional loading for admin and CLI (line 96-115)

- core/class-queue-manager.php
  - Production-hardened async queue manager (582 lines)
  - Constants: SYNC_HOOK, META_STATUS, MAX_RETRIES=3, LOCK_EXPIRATION=60 (line 30-66)
  - enqueue() with duplicate prevention and retry limits (line 83-181)
  - cancel() with lock cleanup (line 190-218)
  - process_sync() with lock acquisition and error handling (line 363-450)
  - acquire_lock()/release_lock() using transients (line 476-514)

- core/class-git-api.php
  - GitHub REST API client with token decryption
  - Constructor loads encrypted token and decrypts (line 55-74)
  - decrypt_token() method (line 76-88)
  - test_connection() with comprehensive error handling (line 97-269)
  - create_or_update_file() for commits (line 308-359)
  - All methods use wp_remote_* exclusively

- core/class-sync-runner.php
  - Central sync orchestrator (only entry point for sync logic)
  - run() validates post, uses Hugo_Adapter, uploads to GitHub (line 30-81)
  - update_sync_meta() maintains single source of truth (line 108-117)
  - Loads adapter interface and Hugo_Adapter (line 50-51)

- adapters/class-hugo-adapter.php
  - WordPress to Hugo Markdown converter
  - convert() generates YAML + Markdown (line 30-44)
  - get_front_matter() extracts tags, categories, author, featured_image (line 62-101)
  - convert_content() uses League\HTMLToMarkdown with fallback (line 109-142)
  - basic_html_to_markdown() for fallback conversion (line 162-220)

- admin/class-settings.php
  - Settings page with WordPress Settings API
  - sanitize_settings() with token encryption (line 87-121)
  - encrypt_token() using AES-256-CBC (line 123-137)
  - decrypt_token() public static method (line 146-160)
  - ajax_test_connection() for connection testing (line 333-350)

- admin/class-columns.php
  - Custom "Jamstack Sync" column in posts list
  - Visual indicators: clock (pending), spinner (processing), checkmark (success), warning (error)
  - render_column() displays status with timestamp (line 50-78)
  - add_column_styles() for CSS animations (line 117-160)

- composer.json
  - Defines PHP 8.1+ requirement
  - Dependencies: action-scheduler, intervention/image, html-to-markdown
  - PSR-4 autoload configuration (line 15-19)
</important_files>

<next_steps>
No immediate next steps required. Plugin foundation is complete and ready for WordPress installation testing.

Future enhancements needed:
1. Bundle Action Scheduler as internal library (currently loaded via Composer)
2. Add post save hooks to trigger automatic sync on publish/update
3. Implement bulk actions in posts list (sync selected posts)
4. Create WP-CLI commands (wp jamstack sync --all)
5. Integrate Intervention/Image for image optimization
6. Add settings field for Hugo content directory path
7. Test full sync pipeline in WordPress environment
8. Create README.md with installation and usage instructions
9. Add error recovery UI (retry failed syncs from admin)
10. Implement queue status dashboard

Blockers:
- None (plugin is internally consistent and ready for testing)
</next_steps>