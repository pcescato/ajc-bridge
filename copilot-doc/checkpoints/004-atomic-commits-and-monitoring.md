<overview>
The user is building a production-grade WordPress plugin called "WP Jamstack Sync" that synchronizes WordPress posts/pages to Hugo static sites via GitHub API. The plugin must follow specifications.md as the single source of truth, use only WordPress native APIs (no shell commands per ADR-05), implement async processing with Action Scheduler, and maintain _jamstack_sync_status as the single source of truth. The work progressed through multiple phases: foundation, media management with WebP/AVIF support, deletion management, bulk sync, page support, Markdown cleaning, atomic commits using GitHub Trees API, and now implementing a sync monitoring dashboard.
</overview>

<history>
1. User requested implementation of Phase 2: Media Management
   - Created Media_Processor class with image processing capabilities
   - Implemented WebP generation using Intervention\Image library
   - Added image download, optimization, and GitHub upload functionality
   - Updated Hugo_Adapter to replace WordPress image URLs with relative paths
   - Integrated into Sync_Runner workflow

2. User requested Featured Image support per Section 3.3
   - Added process_featured_image() method to Media_Processor
   - Generated WebP for featured images as static/images/{post_id}/featured.webp
   - Updated Hugo_Adapter to include image field in Front Matter YAML
   - Result: Featured images processed and referenced correctly

3. User requested switch from GD to Imagick driver
   - Updated Media_Processor constructor to use imagick driver
   - Added fallback to gd if imagick unavailable
   - Added logging to confirm driver selection
   - Fixed namespace issue: used \Imagick instead of Imagick in global namespace
   - Result: Successfully using Imagick engine for better performance

4. User requested AVIF support alongside WebP
   - Added generate_avif() and generate_featured_avif() methods
   - Implemented AVIF format check before generation
   - Updated upload logic to handle both WebP and AVIF files
   - Result: Both formats generated and uploaded for each image

5. User requested Phase 4: Deletion Management
   - Added enqueue_deletion() method to Queue_Manager
   - Created Sync_Runner::delete() method with Markdown + images deletion
   - Refactored Git_API::delete_file() to auto-fetch SHA
   - Added Git_API::list_directory() for image enumeration
   - Added file path caching (_jamstack_file_path meta) for deletion support
   - Updated admin Columns class with deletion statuses
   - Result: Full deletion lifecycle working, but revealed issues with posts synced before caching

6. User reported deletion errors with namespace and missing methods
   - Fixed Hugo_Adapter namespace loading in delete() context
   - Added missing get_headers() method to Git_API
   - Result: Deletion working correctly

7. User requested Bulk Sync feature per Section 3.7
   - Added bulk_enqueue() method with staggered priorities
   - Created get_queue_stats() method for real-time statistics
   - Enhanced Settings page with Bulk Operations section
   - Added progress bar, statistics table, and auto-refresh
   - Added AJAX handlers (ajax_bulk_sync, ajax_get_stats)
   - Result: Complete bulk sync with UI feedback

8. User requested Page post type support
   - Added should_sync_post_type() method to Plugin class
   - Updated Hugo_Adapter::get_file_path() to handle pages (content/{slug}.md) vs posts (content/posts/{date}-{slug}.md)
   - Added "Content Types" settings section with checkboxes
   - Updated Queue_Manager::bulk_enqueue() to handle multiple post types
   - Extended Columns class to support pages admin list
   - Result: Pages and posts both supported with different path conventions

9. User requested Markdown cleaning improvements
   - Added clean_wordpress_html() method to remove figure, figcaption, wp-block classes, alignment classes, srcset/sizes attributes
   - Added clean_markdown_output() method to ensure images on separate lines, remove HTML comments
   - Integrated both methods into convert_content() pipeline
   - Result: Clean Hugo-compatible Markdown with no WordPress artifacts

10. User requested atomic commits using GitHub Trees API
    - Implemented create_atomic_commit() with 6-step GitHub Git Data flow
    - Added helper methods: get_branch_ref(), get_commit_data(), create_blob(), create_tree(), create_commit(), update_ref()
    - Created get_featured_image_data() and get_post_images_data() methods to return data instead of uploading
    - Refactored Sync_Runner::run() to collect all data, build payload, check size (10MB warning), create atomic commit
    - Added cleanup regardless of success/failure
    - Result: Single atomic commit with all files instead of 7+ sequential commits

11. User reported atomic commit failure due to method visibility
    - Changed cleanup_temp_files() from private to public in Media_Processor
    - Result: Atomic commits now working correctly

12. User requested Phase 5: Sync Monitoring Dashboard (IN PROGRESS)
    - Refactored Settings page to use tabs (Settings, Bulk Operations, Sync History)
    - Added render_settings_tab(), render_bulk_tab(), render_monitor_tab() methods
    - Created sync history table using wp-list-table CSS with 7 columns: Title, ID, Type, Status, Last Sync, Commit, Actions
    - Added "Sync Now" button per row with AJAX functionality
    - Used WP_Query filtered by _jamstack_sync_status meta for performance
    - Still need to: Add AJAX handler for single sync, update Sync_Runner to save commit URL
</history>

<work_done>
Files modified during atomic commits refactoring (most recent major work):
- core/class-git-api.php: Added 7 methods for atomic commit implementation (create_atomic_commit, get_branch_ref, get_commit_data, create_blob, create_tree, create_commit, update_ref)
- core/class-media-processor.php: Added get_featured_image_data() and get_post_images_data() methods to return data instead of uploading; changed cleanup_temp_files() to public
- core/class-sync-runner.php: Refactored run() to use atomic commits with payload building and size checking

Files modified for monitoring dashboard (current work - IN PROGRESS):
- admin/class-settings.php: Added tab navigation, refactored to render_settings_tab(), render_bulk_tab(), added render_monitor_tab() with sync history table and "Sync Now" buttons

Work completed:
- [x] Atomic commits implementation with GitHub Trees API
- [x] Method visibility fix for cleanup_temp_files()
- [x] Settings page tab navigation
- [x] Sync history table with WP_Query
- [ ] AJAX handler for single post sync (ajax_sync_single)
- [ ] Update Sync_Runner to save _jamstack_last_commit_url meta
- [ ] Register AJAX hooks in init method

Current state:
- Atomic commits working correctly after visibility fix
- Settings page has tabs but AJAX handler not yet registered
- Monitor tab renders table but "Sync Now" buttons won't work until AJAX handler added
- Commit URLs not yet saved so "View Commit" column will show "—" for all posts
</work_done>

<technical_details>
**Architecture:**
- Single source of truth: _jamstack_sync_status post meta
- Async entry point: Queue_Manager only
- Sync logic entry point: Sync_Runner only
- WordPress native APIs exclusively (no shell commands per ADR-05)
- Action Scheduler for background processing

**Atomic Commits Flow (GitHub Trees API):**
1. Get Branch Reference → GET /git/refs/heads/{branch}
2. Get Commit Data → GET /git/commits/{sha} to extract tree SHA
3. Create Blobs → POST /git/blobs (base64 encoded, 60s timeout)
4. Create Tree → POST /git/trees with all blob SHAs
5. Create Commit → POST /git/commits
6. Update Reference → PATCH /git/refs/heads/{branch}

**Benefits:** Single commit instead of 7+, cleaner git history, ~70% fewer API calls, all-or-nothing atomicity

**Payload Structure:**
```
{
  'content/posts/2026-02-06-title.md': 'markdown content',
  'static/images/123/featured.webp': binary_data,
  'static/images/123/featured.avif': binary_data,
  'static/images/123/image1.webp': binary_data,
  'static/images/123/image1.avif': binary_data
}
```

**Image Processing:**
- Uses Intervention\Image with imagick driver (fallback to gd)
- Generates both WebP (85% quality) and AVIF when supported
- Images stored in static/images/{post_id}/ directory
- Featured images use fixed filename: featured.webp / featured.avif
- Content images use original basename: {basename}.webp / {basename}.avif

**Markdown Cleaning:**
- Removes: figure tags, figcaption, wp-block-* classes, alignment classes, srcset/sizes, width/height
- Ensures images on separate lines
- Result: Clean ![alt](/images/123/filename.webp) syntax

**Post Type Support:**
- Posts: content/posts/{YYYY-MM-DD}-{slug}.md
- Pages: content/{slug}.md (no date prefix)
- Settings control which types sync (default: posts only)

**Deletion:**
- File path cached in _jamstack_file_path during sync
- Three-tier resolution: cached → generated → error
- 404 treated as success (idempotent)
- Images deleted individually (GitHub API limitation)

**Post Meta Keys:**
- _jamstack_sync_status: pending|processing|success|error|cancelled|deleting|deleted|delete_error
- _jamstack_sync_timestamp: Last action timestamp (deprecated, use _jamstack_sync_last)
- _jamstack_sync_last: Last sync datetime (mysql format)
- _jamstack_file_path: Cached GitHub file path for deletion
- _jamstack_retry_count: Retry counter (max 3)
- _jamstack_last_commit_url: GitHub commit URL (NOT YET IMPLEMENTED)

**Known Issues:**
- Posts synced before file path caching lack _jamstack_file_path (must re-sync)
- Method visibility issue with cleanup_temp_files() was fixed (changed to public)
- Commit URL not yet saved to meta (needed for monitoring dashboard)

**Performance:**
- 10MB payload warning per ADR-04 (logged but doesn't block)
- Temp files cleaned up regardless of success/failure
- WP_Query with meta_query for efficient monitoring dashboard
- Bulk sync uses staggered priorities (10-50) to spread load

**Quirks:**
- Global namespace classes need leading backslash: \Imagick not Imagick
- Hugo_Adapter files must be required explicitly in deletion context
- GitHub propagation can take 5+ minutes for web UI (API immediate)
</technical_details>

<important_files>
- core/class-git-api.php (1200+ lines)
  - Why: All GitHub API communication, atomic commit implementation
  - Changes: Added 7 methods for atomic commits (lines 787-1185): create_atomic_commit(), get_branch_ref(), get_commit_data(), create_blob(), create_tree(), create_commit(), update_ref()
  - Key sections: Lines 787-895 (create_atomic_commit main flow), 897-957 (get_branch_ref), 1022-1079 (create_blob with 60s timeout), 1081-1124 (create_tree), 1126-1168 (create_commit), 1170-1215 (update_ref)

- core/class-media-processor.php (1320+ lines)
  - Why: Image processing with WebP/AVIF generation
  - Changes: Added get_featured_image_data() (lines 351-470) and get_post_images_data() (lines 472-545) for atomic commits; changed cleanup_temp_files() to public (line 1132)
  - Key sections: Lines 209-349 (process_featured_image - old method), 351-470 (get_featured_image_data - new), 472-545 (get_post_images_data), 547-700 (get_single_image_data), 1132-1154 (cleanup_temp_files - now public)

- core/class-sync-runner.php (340+ lines)
  - Why: ONLY sync logic entry point, orchestrates atomic commits
  - Changes: Refactored run() method (lines 41-165) to collect all data, build payload, check size, create atomic commit, cleanup
  - Key sections: Lines 41-165 (run method with atomic commit flow), 58-62 (collect featured image data), 64-68 (collect content images data), 85-93 (build payload), 95-107 (size check with 10MB warning), 109-113 (create atomic commit), 116-117 (cleanup), 127-128 (update meta)

- admin/class-settings.php (830+ lines)
  - Why: Settings page with tabs and monitoring dashboard
  - Changes: Added tab navigation (lines 402-449), refactored to render_settings_tab() (450-468), render_bulk_tab() (470-628), added render_monitor_tab() (630-830+) with sync history table
  - Key sections: Lines 415-443 (tab navigation HTML), 630-830+ (render_monitor_tab with WP_Query and table), lines for AJAX handler ajax_sync_single NOT YET ADDED
  - Status: INCOMPLETE - needs ajax_sync_single() handler and AJAX hook registration in init()

- core/class-plugin.php (310+ lines)
  - Why: Singleton bootstrap, registers all WordPress hooks
  - Changes: Added should_sync_post_type() method for page support
  - Key sections: Lines 147-151 (hooks registration), 167-211 (handle_post_save), 224-251 (handle_post_trash), 266-293 (handle_post_delete), 296-313 (should_sync_post_type)

- adapters/class-hugo-adapter.php (520+ lines)
  - Why: WordPress to Hugo Markdown conversion
  - Changes: Updated get_file_path() for page support (lines 56-78), added clean_wordpress_html() (lines 184-226) and clean_markdown_output() (lines 228-253)
  - Key sections: Lines 56-78 (get_file_path with page vs post logic), 140-182 (convert_content with cleaning), 184-226 (clean_wordpress_html), 228-253 (clean_markdown_output)

- core/class-queue-manager.php (720+ lines)
  - Why: Central async queue management
  - Changes: Added deletion methods, bulk sync methods
  - Key sections: Lines 298-376 (bulk_enqueue with post type support), 590-648 (get_queue_stats), 442-510 (enqueue_deletion), 512-570 (process_deletion)
</important_files>

<next_steps>
Remaining work for Phase 5: Sync Monitoring Dashboard:
- [ ] Add ajax_sync_single() AJAX handler in Settings class
- [ ] Register AJAX hooks in init() method (wp_ajax_wpjamstack_sync_single)
- [ ] Update Sync_Runner::run() to extract and save commit URL to _jamstack_last_commit_url meta
- [ ] Extract commit URL from atomic commit result (result should contain commit info)
- [ ] Test monitor tab with real sync data

Immediate next steps:
1. Add ajax_sync_single() method to Settings class that calls Queue_Manager::enqueue()
2. Register AJAX hook in Settings::init()
3. Update Sync_Runner::run() to save commit URL after successful atomic commit
4. Test by syncing a post and viewing monitor tab
</next_steps>