<overview>
The user is building a production-grade WordPress plugin called "WP Jamstack Sync" that automatically synchronizes WordPress content to Hugo static sites via GitHub API. The plugin must follow specifications.md as the single source of truth, use WordPress native APIs exclusively (no shell commands per ADR-05), implement async processing with Action Scheduler, and maintain _jamstack_sync_status as the single source of truth. The work focused on implementing Phase 4 (Deletion Management), fixing multiple technical issues, implementing Bulk Sync functionality, and beginning support for WordPress Pages in addition to Posts.
</overview>

<history>
1. User requested Phase 4: Deletion Management implementation per Section 3.6 of specifications.md
   - Added DELETE_HOOK constant and enqueue_deletion() method to Queue_Manager
   - Created process_deletion() background handler with locking
   - Implemented Sync_Runner::delete() method with full deletion pipeline (Markdown + images)
   - Refactored Git_API::delete_file() to auto-fetch SHA and handle 404 gracefully
   - Added Git_API::list_directory() to enumerate image files for deletion
   - Added deletion hooks to Plugin class (wp_trash_post, before_delete_post)
   - Updated admin Columns class with 3 new statuses: deleting, deleted, delete_error
   - Result: Full deletion lifecycle implemented with async processing

2. User reported deletion failing with "Class 'WPJamstack\Adapters\Hugo_Adapter' not found"
   - Added file path caching in Sync_Runner::run() to save _jamstack_file_path meta
   - Enhanced Sync_Runner::delete() with three-tier path resolution (cache → generate → error)
   - Added require_once statements for adapter files in delete() method
   - Result: Fixed namespace loading issue, but revealed another problem

3. User reported syntax error: "unexpected token 'if', expecting 'function'" on line 536
   - Found duplicate code block in Git_API::get_file() method
   - Removed duplicate lines 536-541
   - Result: Syntax error fixed

4. User tested deletion but files remained on GitHub despite "action complete"
   - Enhanced logging in Git_API::get_file() and delete_file() methods
   - Discovered post 32 was synced before caching was added (no _jamstack_file_path meta)
   - Diagnosed: Old posts lack cached path, deletion fails if post permanently deleted
   - Result: Identified root cause - posts need re-sync to cache path

5. User reported deletion failing again with "Call to undefined method WPJamstack\Core\Git_API::get_headers()"
   - Added missing get_headers() private method to Git_API class
   - Method returns standard HTTP headers for all GitHub API requests
   - Result: Deletion now functional

6. User noted GitHub files appeared "after more than 5 minutes" during sync
   - This was GitHub's propagation delay, not a plugin issue
   - Verified commit was created successfully via logs
   - Result: No action needed, explained GitHub caching/propagation

7. User tested deletion and reported it worked, confirmed full lifecycle operational
   - Verified logs showed successful deletion workflow
   - Confirmed Markdown and image files removed from GitHub
   - Result: Deletion feature fully operational

8. User requested Bulk Sync feature per Section 3.7 of specifications.md
   - Added Queue_Manager::bulk_enqueue() method with staggered priorities (10-50)
   - Added Queue_Manager::get_queue_stats() method for real-time statistics
   - Enhanced Settings page with "Bulk Operations" section including:
     * "Synchronize All Posts" button with confirmation
     * Real-time progress bar
     * Queue statistics table (6 metrics)
     * Auto-refresh every 3 seconds via JavaScript polling
   - Added two AJAX handlers: ajax_bulk_sync() and ajax_get_stats()
   - Implemented duplicate job prevention (respects existing queue logic)
   - Result: Complete bulk sync feature with UI feedback

9. User confirmed "all is ok" and asked for cleanup, then requested Page post type support
   - Provided cleanup options (WordPress bulk trash recommended)
   - User manually cleaned up test posts/images
   - Started implementing page support by updating Plugin class:
     * Added should_sync_post_type() method (incomplete)
     * Updated handle_post_save(), handle_post_trash(), handle_post_delete() to check post type
   - Result: Page support partially implemented (INCOMPLETE - stopped mid-implementation)
</history>

<work_done>
Files modified:
- core/class-queue-manager.php: Added deletion methods (enqueue_deletion, process_deletion), bulk sync methods (bulk_enqueue, get_queue_stats), DELETE_HOOK constant
- core/class-git-api.php: Refactored delete_file() with auto-SHA fetch and 404 handling, added list_directory() and get_headers() methods, enhanced get_file() logging
- core/class-sync-runner.php: Added delete() method with full deletion pipeline, added file path caching in run() method, three-tier path resolution
- core/class-plugin.php: Added deletion hooks (handle_post_trash, handle_post_delete), PARTIALLY updated post type handling (INCOMPLETE)
- admin/class-settings.php: Added Bulk Operations section with progress bar and statistics table, added AJAX handlers (ajax_bulk_sync, ajax_get_stats), JavaScript polling
- admin/class-columns.php: Added 3 new deletion statuses (deleting, deleted, delete_error) with icons and labels

Work completed:
- [x] Phase 4: Deletion Management fully implemented
- [x] File path caching for post-deletion scenarios
- [x] Enhanced logging throughout deletion workflow
- [x] Missing get_headers() method added
- [x] Bulk Sync feature fully implemented
- [x] Queue statistics and progress tracking
- [ ] Page post type support (STARTED but INCOMPLETE)

Current state:
- Deletion: Working correctly with cached paths
- Bulk Sync: Fully functional with UI feedback
- Page support: Partially implemented - Plugin class updated but Hugo_Adapter and Settings not yet modified
- All syntax validated for completed features

Issues encountered and resolved:
1. Namespace loading - Fixed with require_once and file path caching
2. Duplicate code - Removed from Git_API
3. Missing method - Added get_headers()
4. Old posts without cache - Documented, requires re-sync
</work_done>

<technical_details>
**Architecture:**
- Single source of truth: _jamstack_sync_status post meta for all sync operations
- Queue_Manager is ONLY async entry point, Sync_Runner is ONLY sync logic entry point
- WordPress native APIs only: wp_remote_* for HTTP, no shell commands (ADR-05)
- Action Scheduler for async processing with WP Cron fallback
- File path caching: _jamstack_file_path meta added during sync for deletion support

**Deletion Workflow:**
- Three-tier path resolution: 1) Use cached path (fastest), 2) Generate with adapter (requires files), 3) Error if no path
- Git_API::delete_file() automatically fetches SHA before deletion
- 404 responses treated as success (file already deleted = desired state)
- Images deleted individually (GitHub API requirement, no batch delete)
- Non-blocking: image deletion failures don't stop Markdown deletion

**Bulk Sync Optimization:**
- Staggered priorities: 10 + (index % 40) spreads load across priorities 10-50
- Duplicate prevention: Skips posts with status 'pending' or 'processing'
- JavaScript polling: Updates stats every 3 seconds, stops when queue empty
- Performance: Uses WP_Query with 'fields' => 'ids' for minimal memory

**Security:**
- AES-256-CBC token encryption (wp_salt('auth') + wp_salt('nonce'))
- Nonce verification for all AJAX endpoints
- Capability checks: manage_options required

**GitHub API:**
- Headers standardized via get_headers() method
- DELETE requires file SHA (fetched via GET first)
- 404 handling: file not found = success (idempotent operations)
- Rate limit: 5,000 calls/hour (authenticated)

**Post Meta Keys:**
- _jamstack_sync_status: Status tracking (pending, processing, success, error, cancelled, deleting, deleted, delete_error)
- _jamstack_sync_timestamp: Last action timestamp
- _jamstack_file_path: Cached GitHub file path (added during sync for deletion support)
- _jamstack_retry_count: Retry counter (max 3)

**Known Gotchas:**
- Posts synced before file path caching was added lack _jamstack_file_path meta (must re-sync to enable deletion)
- GitHub propagation can take 5+ minutes for files to appear in web UI (API reflects changes immediately)
- Global namespace classes need leading backslash: \Imagick not Imagick inside WPJamstack namespace
- Hugo_Adapter files must be required explicitly in deletion context (not autoloaded)

**Unanswered Questions:**
- Page support: What Hugo path convention should be used? content/pages/{slug}.md or content/{slug}.md?
- Page support: Should pages use date-based paths or just slugs?
- Bulk operations: Should there be a "Bulk Delete" feature or is WordPress native bulk trash sufficient?
</technical_details>

<important_files>
- core/class-queue-manager.php (700+ lines)
  - Why: Central async queue management, all background operations flow through here
  - Changes: Added deletion methods (enqueue_deletion, process_deletion), bulk sync (bulk_enqueue, get_queue_stats)
  - Key sections: Lines 30-40 (constants), 298-376 (bulk_enqueue), 590-648 (get_queue_stats)

- core/class-git-api.php (720+ lines)
  - Why: All GitHub API communication, handles file operations
  - Changes: Refactored delete_file() to auto-fetch SHA, added list_directory() and get_headers(), enhanced logging
  - Key sections: Lines 113-128 (get_headers), 445-533 (get_file with logging), 556-642 (delete_file), 687-750 (list_directory)

- core/class-sync-runner.php (320+ lines)
  - Why: ONLY sync logic entry point, orchestrates all sync operations
  - Changes: Added delete() method (145 lines), added file path caching in run()
  - Key sections: Lines 110-113 (path caching), 187-316 (delete method with three-tier resolution)

- core/class-plugin.php (310+ lines)
  - Why: Singleton bootstrap, registers all WordPress hooks
  - Changes: Added deletion hooks, PARTIALLY updated for post type support (INCOMPLETE)
  - Key sections: Lines 147-151 (hooks registration), 167-211 (handle_post_save), 224-251 (handle_post_trash), 266-293 (handle_post_delete)
  - Status: INCOMPLETE - should_sync_post_type() method referenced but not implemented yet

- admin/class-settings.php (550+ lines)
  - Why: Settings page with bulk operations UI
  - Changes: Added Bulk Operations section, progress bar, statistics table, AJAX handlers
  - Key sections: Lines 41-45 (AJAX hook registration), 334-509 (Bulk Operations UI with JavaScript), 517-542 (ajax_bulk_sync), 550-561 (ajax_get_stats)

- admin/class-columns.php (330+ lines)
  - Why: Custom "Jamstack Sync" column in posts list
  - Changes: Added 3 deletion statuses (deleting, deleted, delete_error)
  - Key sections: Lines 103-114 (get_status_icon with deletion icons), 123-135 (get_status_label with deletion labels)

- adapters/class-hugo-adapter.php (406 lines)
  - Why: WordPress to Hugo Markdown conversion
  - Changes: None yet, but NEEDS updates for page support
  - Key sections: Lines 88-106 (get_file_path - needs page logic), Lines 69-117 (get_front_matter)
  - Status: NOT YET UPDATED for page support

- core/class-media-processor.php (863 lines)
  - Why: Image processing with WebP/AVIF generation
  - Changes: None needed (confirmed images use post_id directory regardless of post type)
  - Key sections: Lines 209-354 (process_featured_image), 374-419 (process_single_image)
</important_files>

<next_steps>
Immediate task: Complete Page post type support implementation (INCOMPLETE)

Remaining work:
1. Add should_sync_post_type() method to Plugin class
   - Check settings for enabled post types
   - Default to ['post'] if not configured
   
2. Update Hugo_Adapter::get_file_path() method
   - Add post type check
   - Posts: content/posts/{date}-{slug}.md (existing)
   - Pages: content/{slug}.md (new - common Hugo convention)
   
3. Update Settings page
   - Add "Post Types" settings section
   - Add checkboxes for "Posts" and "Pages"
   - Save as array in settings (e.g., 'enabled_post_types' => ['post', 'page'])
   
4. Update Queue_Manager::bulk_enqueue()
   - Read enabled post types from settings
   - Query multiple post types if both enabled
   
5. Verify and test
   - Ensure date/lastmod still populated for pages in front matter
   - Confirm image directory static/images/{post_id}/ works for pages
   - Test sync, update, and deletion for pages

Blocked/Questions:
- Need to confirm Hugo path convention for pages: content/{slug}.md vs content/pages/{slug}.md
- Should pages include date in filename or just slug?
- Front matter for pages: Should 'date' be post creation date or modification date?

Next action: Implement should_sync_post_type() method and complete Plugin class updates, then move to Hugo_Adapter path logic.
</next_steps>