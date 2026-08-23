#### [unreleased]
* fix stale error_cache blocking repo updates — `error_cache` is now cleared on successful (200) API responses, and `get_cached_error_flags()` checks `error_timeout` expiry, so repos no longer remain permanently flagged as "waiting" after a transient 401/404 error resolves
* fix flush-repo-cache button now triggers wp-cron immediately via admin-ajax.php instead of waiting for the next page load — the Settings page button uses a new AJAX handler that flushes the repo cache and calls wp_cron() to fetch fresh data right away, so the broken indicator appears immediately without requiring a page refresh; multisite subsites rely on the main site's cron to avoid performance issues
* fix `construct_download_link()` reading stale tag data — it now reads `tags` and `newest_tag` from the repo cache (`get_repo_cache()`) in place of `$this->type` values, so non-fetch callers (rollback, branch switch, REST update, branch listings) resolve the correct zipball endpoint and release-asset gate even when the repo object has not been hydrated by a fetch
* compute the download link on the fly in `plugins_api()`, `themes_api()`, and `update_site_transient()` via `construct_download_link()` instead of reading the repo object's `download_link` — the object property could be empty or stale (tag-specific/non-release-asset), while the cache-backed computation always serves the canonical package URL without persisting anything
* refactor tag-specific release asset logic — tags only get release asset download links when `release_asset` is true and the tag matches the newest release asset version (stable or dev based on `gu_dev_release_asset` filter); non-matching tags and all branches get zipball download links; release asset lookups use cached data instead of API calls; `release_asset` cache column is now a boolean and `release_asset_download` is set to the newest release asset URL
* fix dev release asset `newest_tag` mismatch — when the newest dev release asset key does not match `remote_version` (e.g., dev asset key is `1.3.0-beta1` but `remote_version` is `1.3.0`, or dev asset key is `28.3-nightly` but `remote_version` is `28.3.20260824`), `newest_tag` is set to `remote_version` to ensure consumers receive accurate `download_link` data
* fix download-link decision in non-fetch callers — `use_release_asset()` now decides from the explicit target branch/tag instead of the stale `$this->type->branch`, so branch switches, tag rollbacks, and branch listings get the correct release asset or zipball even when the repo object reflects a previously tracked branch
* fix tag rollback downloads the latest release asset — `construct_download_link()` now fetches the release asset for the specific tag being rolled back to instead of always returning the latest release; tag-specific assets are not cached as the primary `release_asset_download`
* remove `ensure_download_data()` — the cache-only hydration bridge is no longer needed: `get_remote_repo_meta()` sets the correct branch-or-tag `download_link` on the config, and the consumers (`plugins_api()`, `themes_api()`, `update_site_transient()`) skip waiting repos or only serve repos with a completed fetch, so the fallback's branch/tag logic was dead weight
* fix non-dev release asset download link preferring branch over tag — `construct_download_link()` keeps the newest-tag URL when the repo is on its primary branch with tags, and the branch URL only when on a non-primary branch; a resolved release asset (stable or dev) still wins over either
* fix `gu_dev_release_asset` one-sided asset maps — `construct_download_link()` no longer requires both stable and dev asset maps to be populated; a dev-only repo (empty `assets`) now resolves the dev asset instead of falling through to the zipball, and a stable-only repo still resolves the stable asset
* fix `gu_dev_release_asset` download overriding an existing link — `construct_download_link()` now evaluates the dev release asset even when the repo object already carries a zipball or stable `download_link`, so the dev asset URL wins when newer instead of the consumer listing the stale zipball; a non-dev or older-dev link is left untouched
* fix `gu_dev_release_asset` handling in cache-only consumers — the dev-release-asset filter is honored in `construct_download_link()` at fetch time (picking the dev asset URL when newer), and `newest_tag` is set to the dev version when the filter is active; `update_site_transient()` / `plugins_api()` / `themes_api()` serve the persisted values on cache-only requests; also guard against a non-scalar `release_asset_download` and a missing `db_version` option in `GU_Upgrade::run()`
* fix stale `newest_tag`/`download_link` in `update_site_transient()`, `plugins_api()`, and `themes_api()` for release-asset repos — persist `newest_tag` as a named cache entry at fetch time and set `download_link` on the config via `construct_download_link()`, so cache-only requests serve the persisted values and no longer see the `'0.0.0'` sentinel or an empty package; `themes_api()` now also returns `download_link`
* fix recurring "Cron unschedule event error for hook: gu_get_remote_plugin/gu_get_remote_theme" — `merge_and_reschedule_cron_batch()` now skips re-scheduling when a *due* event for the hook already exists (the wp-cron runner is executing it), so the plugin's wp-cron-init no longer re-adds the event at a future timestamp, which kept repos perpetually pending and raced core's post-run unschedule (the WP core `could_not_set` false-positive, Trac #57271); the fallback re-checks `wp_next_scheduled()` before re-scheduling so a concurrent write that succeeded is not duplicated
* fix intermittent "Cron unschedule event error for hook: gu_get_remote_plugin/gu_get_remote_theme" — `merge_and_reschedule_cron_batch()` now performs the unschedule + reschedule in a single `cron` option write instead of `wp_unschedule_hook()` + `wp_schedule_single_event()`, so a transient DB write failure or a concurrent request can no longer trigger the core `could_not_set` error or leave duplicate cron events
* add filter `git_updater_skip_oauth_reminder` to skip the OAuth email reminder and change the admin notice on a per-provider basis — when the filter returns true for a provider, the email reminder is skipped and the admin notice shows "OAuth reminder suppressed by filter" instead of the standard "access was revoked, please reconnect" message
* fix release-asset download links on first run — run `sort_tags()` in `get_remote_api_tag()` so `newest_tag` is set before `construct_download_link()`, reorder `get_remote_repo_meta()` to build the download link before `populate_api_data()` merges cache data, and drop the now-redundant `sort_tags()` call from `populate_api_data()`; the first request now resolves the release-asset URL instead of falling back to the zipball
* internal: drop the never-read `gu_refresh_cache` transient write from `Settings::refresh_caches()` (the table flush is the real effect); remove dead `ghu-<md5>` site-option cleanup/seeding from the test suite, which referenced the pre-cache-table option scheme — the one release-assets seeding now writes to the cache table where production reads it
* cache: store a repo's API error cache on its own cache row instead of a detached `{slug}_error` row, so a 404/401 from a private repo with an empty or incorrect auth token is visible to `get_cached_error_flags()` and `waiting_for_background_update()` — the "please be patient" notice now shows while the error backoff is active
* oauth: send the OAuth token revocation reminder email daily instead of every 36 hours
* cache: `Branch::get_current_branch()` now falls back to the cached `primary_branch` before `$repo->branch` when no `current_branch` is set, so the REST webhook path (`Rest_Update::get_local_branch()`) uses the same persisted branch as the Plugin/Theme config resolution after a branch reset
* cache: `set_repo_cache()` now refreshes a dead/zero/expired row timeout to `now + $hours` when written with `$timeout=false`, instead of preserving it forever. Previously a reset (`delete_repo_api_data`/`delete_all_api_data`, which null `timeout`) or natural expiry left the row at timeout 0, so `get_repo_cache($timeout=true)` returned `false` and forced a refetch on every page load/cron run. A still-valid non-zero timeout is still preserved (by design, so per-step writes don't bump expiry)
* security: `encodeURIComponent()` the selected branch/tag before appending it to the rollback href in the theme branch switcher, so a crafted remote branch name cannot inject into the URL
* security: require a nonce and `manage_options`/`manage_network_options` capability to reset the Remote Management REST API key, so a low-privilege user cannot rotate it via a crafted request (availability DoS on webhooks); the reset form now emits a nonce field
* security: settings save handlers (`Settings`, `Additions/Settings`, `Lite_Domains`) now require `manage_options` (single-site) or `manage_network_options` (multisite) in addition to the nonce, so a low-privilege user cannot alter tokens, Additions, or lite-domain settings
* security: `Abstract_Cache_Table::whitelist()` now fails closed with an exception on an unknown column instead of silently rewriting it to `slug` (which could corrupt a row key)
* db: only drop the network-wide cache table on uninstall from the main site on multisite, so a subsite uninstall no longer wipes the shared cache
* security: verify the settings nonce and `install_plugins`/`install_themes` capability in `Install::install()` before processing a remote install — closes a CSRF install vector
* cache: `delete_all_cached_data()` and the `flush-repo-cache` endpoint now clear API-derived data but preserve each repo's `current_branch` selection, so upgrades/refreshes re-collect API data without resetting the user's active branch; add `Abstract_Cache_Table::delete_all_api_data()` and `delete_repo_api_data()`
* cache: `set_repo_cache()` reads only the target column (+ timeout) instead of the full 22-column LONGTEXT row to compare values before writing — drops a full-row read per cache write
* fix "Please be patient while WP-Cron finishes making API calls." admin notice never clearing: `waiting_for_background_update()` now treats a repo as still waiting only when it has no `ran` row (fetch cycle hasn't run) or a non-empty `error_cache` (a step errored and still needs a retry). Previously it required all 7 `ran` steps to have returned data, so healthy repos that legitimately lack optional files (no readme/changelog/assets/tags) were stuck "waiting" forever. Add `Abstract_Cache_Table::get_cached_error_flags()` (memory-safe `slug, error_cache` projection) and unify both the null (notice) and non-null (per-repo fetch routing) branches on the same "no ran row OR pending error" definition
* fix WPCS errors in `Bootstrap::cache_table_setup()` and `Abstract_Cache_Table::get_repo()` (column-projection `InterpolatedNotPrepared`); cache the cache-table existence check in `Bootstrap::cache_table_setup()` via `wp_cache_get`/`wp_cache_set` (1-day TTL) so the per-request `SHOW TABLES` query is skipped after first install, and add `tests/test-bootstrap.php` coverage for all `cache_table_setup()` branches
* convert the remaining full-row `get_repo_cache()`/`get_repo()` reads to column projections: `GU_Trait` (`set_repo_cache_timeout` → `ran`, `is_fetch_complete` → `ran`, `maybe_extend_repo_cache` → `timeout`), `API_Common` (`get_remote_api_info` → `repo_headers`, `get_remote_api_assets` → `contents`, `get_api_release_assets` → `release_assets`), `API::api()` → multi-column `[error_cache, error_timeout]`, `REST_API::get_api_data` → multi-column `[release_asset_download, release_asset]` (fixes a half-converted single-column site that broke the release-asset redirect `elseif` branch) and `build_download_metadata` → multi-column `[release_asset_download, release_asset_redirect, release_asset]`, and `Readme_Parser` → `assets`; `Repo_Cache_Table::get_entry()` and `Abstract_Cache_Table::get_error_cache()` now delegate to the projected read instead of loading the full row and picking one key
* fix projected single-column read in `Abstract_Cache_Table::get_repo()` losing legitimate empty-string (`''`) column values: `$wpdb->get_var()` coerces `''` to `null`, so `reset_branch`'s `''` write to `current_branch` read back as `null`; switch the single-column path to `$wpdb->get_row( …, ARRAY_N )`, which preserves the `''`/`NULL` distinction
* extend column projection to multi-column reads: `get_repo()` / `get_repo_cache()` now accept an array of columns (e.g. `['tags','changes','readme','meta','branches','release_asset','release_assets']`) and issue `SELECT col1, col2, …` instead of `SELECT *`. Convert the remaining hot-path full-row reads to projections — `populate_api_data()` (7 columns), `API_Common` (`repo_headers` ×2, `contents` ×3, `release_asset`, `release_assets`), `Branch::set_branch_on_switch` (`tags`/`repo_headers`/`branches`), and `API::get_release_asset_redirect` (`timeout`/`release_asset`/`release_asset_redirect`/`repo`). Single-column callers unchanged. This removes the last `SELECT *` on the update-check path; per-repo read payload drops from ~30 KB (all 22 LONGTEXT columns) to a few KB. Also add `timeout` and `error_timeout` to the column whitelist so the projection path can read them (previously they were rejected by the whitelist and the timeout check silently read the `slug` column, casting to 0 and wrongly expiring every cached row)
* add column projection to `Abstract_Cache_Table::get_repo()` / `GU_Trait::get_repo_cache()` so a single-field read (e.g. `current_branch`, `release_asset_download`, `assets`, `primary_branch`, `dot_org`, `languages`, `addon_api_results`) does `SELECT <column>` + unserializes only that one column instead of `SELECT *` + unserializing all 22 LONGTEXT columns; convert high-frequency single-column callers (`Branch::get_current_branch`, `Basic_Auth_Loader` release-asset check, `Plugin`/`Theme` meta branch reads, `Base::add_assets`, `REST_API`/`Rest_Update` branch + release-asset reads, `Add_Ons`, `Language_Pack_API`, `API::get_dot_org_data`) to pass the column. Full-row behavior unchanged for callers that need multiple fields (`populate_api_data`, `set_repo_cache` diff, `is_fetch_complete`, `API_Common`). Per single-field read drops from a ~30 KB full-row unserialize to ~0.1–5 KB
* refactor `waiting_for_background_update()` to detect incomplete caches via the `ran` column instead of bulk-loading full rows: replace the `get_all_rows()` `SELECT *` + unserialize of every repo's full 22-column payload (held twice, in `$ghu_lookup` and `$row_cache`) with a `SELECT slug, ran` projection (`get_cached_ran()`) that unserializes only the tiny `ran` array; a repo is now "waiting" if it has no row or an incomplete `ran` set, so repos with partial/limited data are correctly re-queued. Extract `EXPECTED_RAN_STEPS` so `is_fetch_complete()` and the null branch share one definition; remove the now-unused `get_cached_slugs()`
* Add-Ons tab: lay out the plugin cards in a flexbox grid. Cards display side-by-side at a 400px minimum width (so the icon and action links margins don't crowd the description text), growing to fill available horizontal space; stack to full width on screens under 480px. Previously the cards rendered as a vertical stack of full-width divs because core's plugin-install CSS was never enqueued and the cards are children of `#the-list`, not `.wp-list-table.plugin-install` (the flex container is on the direct parent). 2 cards per row at medium viewports, 3 at wide viewports (≥ 1240px)
* skip the `set_repo_cache()` DB write when the new value matches the cached value for the same column. Uses `maybe_serialize()` for the comparison so the round-trip is canonical; `error_cache` is excluded (always refreshes so the short retry window works). All 16+ call sites in `API_Common` plus the `Plugin`/`Theme` meta writes benefit automatically. Cost is a single in-memory row-cache hit plus two `maybe_serialize()` calls — much cheaper than the `INSERT … ON DUPLICATE KEY UPDATE` it replaces
* skip redundant `primary_branch`/`current_branch` cache writes in `Plugin::get_plugin_meta()` and `Theme::get_theme_meta()` — read the cached values from the existing `$row_cache` lookup and only call `set_repo_cache()` when the resolved value differs from the cached one; first-run / missing-cache still seeds both columns. 2 writes per repo per request → 0–2, with steady-state 0 once the cache is warm
* memoize `Abstract_Cache_Table::get_repo()` per request with a `$row_cache` keyed by slug, populated by `get_repo()` and `get_all_rows()`, and invalidated by every write path (`add_entry`/`update_entry`/`delete_entry`/`delete_repo`/`set_repo_timeout`/`set_error_cache`/`prune_stale`/`delete_all_repos`/`uninstall_table`); eliminates the 3–5 redundant `SELECT *` calls per slug per refresh cycle that the previous hot path issued
* refactor repo API cache from per-repo `ghu-<md5>` site options into a dedicated `git_updater_cache` table (`Fragen\Git_Updater\DB\Repo_Cache_Table`); all `get_repo_cache`/`set_repo_cache` reads and writes now go through the table, with per-column `timeout` and a separate `error_timeout` for error-cache expiry
* store per-repo `current_branch` solely in the `git_updater_cache` table; remove the redundant `current_branch_<slug>` key from the `git_updater` options and its write/prune/preserve paths in `Branch`, `Rest_Update`, `REST_API`, `Settings`, and `GU_Upgrade`
* reflect cache-table migration and `current_branch` consolidation in the test suite (new `tests/test-cache-table.php`; updated cache-seeding helpers across test files)
* persist `primary_branch` and `current_branch` in the `git_updater_cache` table from `Plugin::parse_meta()` and `Theme::parse_meta()`; the existing `.git/HEAD` block in those methods continues to override the cache for locally version-controlled installs, making the table the source of truth for the active branch on every parse
* make `Abstract_Cache_Table::install_table()` drop the table before `dbDelta` to guarantee a clean schema; `dbDelta` is unreliable for adding new columns to an existing table, and the only production caller (`GU_Upgrade::run`) flushes the cache on every upgrade, so the net effect is unchanged
* fix stale release-asset cache after a remote version change — when the fetched remote version differs from the cached version, `maybe_extend_repo_cache()` now drops the cached `release_assets`, `release_asset`, and `release_asset_download` entries so the lazy release-asset fetch rebuilds them from the new remote during the same cycle instead of serving the previous version's asset list and download URL

#### 14.3.0 / 2026-08-08
* fix `use_release_asset()` first-run gating — restore the `newest_tag` proxy instead of gating on the cached `release_assets` list, which is only populated after the decision; when no release asset is found the update fails with an empty download link rather than falling back to unbuilt tag source (GitHub and Gitea)
* update `coverage-exclude.json` multisite exclusion for `OAuth_Connect.php` from stale line 343 to the current multisite-only branch at line 362
* guard all remaining `ReflectionMethod::setAccessible()`/`ReflectionProperty::setAccessible()` calls in tests with `PHP_VERSION_ID < 80100` to avoid deprecation on PHP 8.5
* email the site admin when a provider's OAuth token refresh fails and the token is deleted, with a 36-hour reminder cron while the token remains empty; a "token is empty" variant of the reminder email is sent only to premium license holders
* show the OAuth revocation notice on the settings page whenever the Connect button is displayed (no token stored), not only when the revoked flag is set
* extract `GU_Trait::is_fetch_complete()` to centralize the "all API data returned" check (table-backed via the `ran` column); refactor `set_repo_cache_timeout()` and `maybe_extend_repo_cache()` to use it

#### 14.2.3 / 2026-08-05
* remove filter `gu_dev_release_asset_version`
* for dev release assets use actual remote version number if different from release asset version

#### 14.2.1 / 2026-08-04
* add filter `gu_dev_release_asset_version` for devs who do it differently

#### 14.2.0 / 2026-07-29
* delete a provider's OAuth token automatically when a token refresh returns an empty `access_token` — the Connect button reappears and a "access was revoked, please reconnect" notice is shown (via a persistent per-provider option flag) so the user knows to re-authorize on the provider site
* switch OAuth revocation notice from a 15-minute transient to a persistent site option flag (`gu_oauth_revoked_{provider}`) so the notice survives until the admin reconnects
* clear the local stale credential in `add_auth_header()` when a proactive refresh fails and deletes the token, avoiding a wasted 401 round-trip with the now-deleted token

#### 14.1.0 / 2026-07-24
* add body-based "Bad Credentials" detection to API error handling — when a response (200 or 4xx) contains this message, Git Updater now automatically attempts a token refresh and retries the request, improving recovery from invalid/expired tokens that aren't signaled by 401/403 status codes
* refactor `API::api()` method — extracted token refresh retry logic into `maybe_refresh_token_and_retry()`, `should_attempt_token_refresh()`, and `has_bad_credentials_message()` for improved readability and testability
* add comprehensive tests for new token refresh scenarios including 200/4xx with "Bad Credentials", ensuring correct behavior with and without refresh tokens
* fix `GitHub_API::construct_download_link()` clobbering a valid cached `release_asset_download` with `false` when no release assets are returned — only cache a resolved asset URL
* fix `REST_API::build_download_metadata()` to build auth headers after the final download link is resolved, so release asset and redirect overrides get correct headers
* add `: bool` return type declaration to `GU_Trait::use_release_asset()`
* fix `Repo_List_Table::column_default()` to return `false` for empty `release_asset`/`private_package`/`uses_lite` columns
* implement two-step download flow for `git-updater-lite` to resolve cache mismatch between signed URL TTL and 6-hour client cache
* add REST endpoint for generating fresh 60-second signed URLs for lite updates
* isolate token URL generation strictly to the `update-api` route; main plugin continues to receive 12-hour signed URLs
* add server-centric domain validation for private packages (optional, filterable via `git_updater_lite_authorized_domains`)
* new `Lite_Domains` settings class and UI for managing authorized domains per slug with automatic subdomain matching
* new "Uses Git Updater Lite" checkbox in Additions settings to manually flag packages for domain configuration
* auto-detect private packages with `Update URI` header for domain configuration recommendations
* client-side interception in `git-updater-lite` to fetch fresh download tokens via `upgrader_pre_download` hook
* add domain header (`X-GU-Site-Domain`) to download token requests for server-side validation
* add comprehensive documentation in `docs/lite-update-flow.md` explaining the new download flow and security features
* add 100% test coverage for new `Lite_Domains` class, `get_download_token` endpoint, and `uses_lite` UI elements

#### 14.0.2 / 2026-07-22
* always show API errors in error log
* flush cache on update to 14.0.2

#### 14.0.0 / 2026-07-21
* fix `Additions/Repo_List_Table` security — remove blanket `WordPress.Security.ValidatedSanitizedInput` suppression; add capability check (`manage_options`/`manage_network_options`) and proper nonce verification on the delete path; sanitize bulk `slug` array so checkbox deletions actually work; fix `wp_slash`→`wp_unslash` on `page`/`tab` reads; remove orphaned unverified `_wpnonce_list` field
* fix test isolation: clear the WordPress theme cache in `GU_Test_Case::tear_down()` so each test re-scans installed themes; otherwise a stale `wp_get_themes()` cache from a prior test hid the `test-gu-theme` fixture from Git Updater's `get_theme_meta()`, causing `Test_Theme_Get_Theme_Meta` and `Test_Rest_Update_Full_Path` failures in the full suite (they passed in isolation)
* add `index.php` to the `test-gu-theme` fixture so it is a valid standalone WordPress theme
* new easter egg: the repo dashicon is now a button that flushes the cache of that specific repository via the `flush-repo-cache` REST endpoint (no page navigation); the broken dashicon is revealed on a successful flush
* fix `Rest_Update::update_plugin()` inverted activation check — `activate_plugin()` returns `null` on success and `WP_Error` on failure; old code `if ( ! $activate )` silently swallowed failures; now reports error message from `WP_Error`
* fix `API::api()` missing `WP_Error` check after OAuth retry — retry response now validated like the initial request
* fix `API::get_release_asset_redirect()` AWS cache age calculation to use `$this->hours` instead of hardcoded `-12 hours`
* fix `OAuth_Connect::refresh_token()` race condition — use site transient lock and result coordination so concurrent requests reuse a successful refresh instead of re-posting the (potentially rotated) refresh token
* fix switch statements in `GU_Upgrade::run()`, `Rest_Update::get_webhook_source()`, and `CLI_Integration::process_args()` to use `switch ( true )` so boolean/isset cases actually match
* fix `Base::set_options_filter()` to use `get_site_option( 'git_updater', [] )` preventing TypeError on PHP 8+ when option absent
* fix `Messages::get_license()` dismissible notice ID mismatch — `data-dismissible` now matches `is_admin_notice_active()` check
* fix `Additions/Settings::callback_field()` missing `echo` on `esc_attr()` for input `id` attribute
* fix `Theme::get_theme_meta()` bitwise `&` changed to logical `&&` in URI header filter
* fix `Plugin::get_plugin_meta()` misplaced parenthesis in `empty()` call for URI header filter
* fix `GU_Trait::parse_header_uri()` to return early for malformed URLs where `parse_url()` returns false
* fix `Plugin::get_plugin_meta()` and `Theme::get_theme_meta()` — check `file_get_contents()` return before processing `.git/HEAD`; compute `array_keys( self::$extra_headers )` once before loop; call `update_site_option()` once after loop instead of per-iteration
* fix `API::api()` — store `json_decode()` result in variable to avoid double decode; use shorter error cache timeout (5 min) for transient HTTP errors (503, 429) vs 60 min for permanent (404, 410)
* fix `GU_Trait::get_class_vars()` to cache `ReflectionObject` and `Property` objects (not values) reducing reflection overhead
* fix `Basic_Auth_Loader::get_credentials()` to cache merged repo configs as static property instead of rebuilding on every API call
* fix `GU_Trait::waiting_for_background_update()` to batch-load all `ghu-*` cache options in single query instead of N+1
* fix `GU_Trait::delete_all_cached_data()` to remove redundant bulk `DELETE` query — `delete_site_option()` already handles both DB and cache invalidation
* fix `Base::set_defaults()` to use `update_site_option()` instead of `add_site_option()` which silently fails if option exists; remove dead `$this->$type->requires = ''` assignment
* fix `Rest_Update::update_plugin()` and `update_theme()` to use direct array key lookup instead of foreach loop
* fix `Bootstrap::remove_cron_events()` to include `gu_delete_access_tokens` in cleanup list
* fix `GU_Trait::merge_and_reschedule_cron_batch()` to return early if hook already scheduled
* fix `Remote_Management::reset_api_key()` to atomically replace API key instead of delete-then-recreate
* fix `Branch::plugin_branch_switcher()` to check cache before triggering full API metadata fetch cycle
* fix `Add_Ons::get_addon_api_results()` to cache partial results with selective retry — only missing addons are fetched on cache hit, full results cached 7 days, partial 8 hours
* fix `REST_API::get_remote_repo_data()` to return cached update data instead of forcing `wp_update_plugins()` and `wp_update_themes()` full update cycle
* add fallback cache timeout in `set_repo_cache_timeout()` to prevent infinite re-fetching when API sub-calls fail (partial `ran`)
* added support for OAuth tokens
* add "Remove Token" button to settings — visible for manual API tokens (PATs) only, not OAuth tokens
* updated for Claude Opus 4.7 security review
* remove release asset redirect from `GitHub_API`, no longer used
* add tests for `get_release_asset_redirect()` AWS expiration and REST key paths, achieving 100% line coverage on `API.php`
* fix pre-existing test failure `test_get_api_data_covers_release_asset_download_path` by seeding `release_assets` cache entry
* update `Freemius/wordpress-sdk`

#### 13.0.1 / 2026-06-04
* remove all Authorization headers from REST endpoints, under specific circumstances this could have leaked access tokens. Thanks to Simon Tiplady, Timo Klemm, and Thomas Johannessen for disclosure.
* Updating private repositories using Git Updater Lite will not work with this version

#### 13.0.0 / 2026-05-31 🎂
* use `afragen/wp-readme-parser` drop-in replacement for `afragen/wordpress-plugin-readme-parser`
* update requirements to PHP 8.0 for new parser due to testing
* add `maybe_extend_repo_cache()` to update the timeout if the remote and cached version numbers are same, should avoid API calls for current data
* update `(get|set)_repo_cache()`
* fix wp-cron and multisite
* more efficient use of cache
* decrease data stored with API request response
* fix `Release Asset` header to save as boolean
* add `populate_api_data()` to populate even when API requests are skipped
* set `error_cache` to its own cached state outside the main repo cache
* fix Add_Ons cache to use dedicated repo key with proper timeout handling
* fix `GitHub_API::get_remote_readme()` missing return statement
* fix `GU_Trait::use_release_asset()` undefined property PHP 8.x warnings via null coalescing
* fix `Basic_Auth_Loader::get_slug_for_credentials()` array slug check order — `is_array()` must precede `sanitize_text_field()` so TGMPA array slugs are not silently discarded
* borrow function from FAIR Connect to sort plugins_api modal tabs in correct order
* add PHPStan level 6 testing and a whole mess of phpunit tests with load of help from Claude
* consolidate cron task to eliminate potential duplication of API requests
* update instructions for CLI installation

#### 12.24.2 / 2026-03-25
* update freemius/wordpress-sdk
* fix delete_all_cached_data() for multisite thanks Eileen Mack

#### 12.24.1 / 2026-03-18
* fix `flush-repo-cache` REST endpoint, was getting caught in `$existing_cache`
* added `should_run_on_current_page()` and check pages for loading certain parts of plugin
* update `$slug` initialization in `Base::upgrader_source_selection()` as `get_repo_slugs()` now with type checking
* fix language pack GitHub download URI
* remove type hint for `$source` in `Base::upgrader_source_selection()` as it can be `WP_Error`

#### 12.24.0 / 2026-03-11
* update erusev/parsedown to 1.8
* remove soft match in `get_repo_slugs()`
* add guard to `set_readme_info()`
* add function to check timeout validity
* re-use valid cache timeout
* refactor getting cache key to `get_cache_key()`
* ensure newest tag present in release assets array
* check to see `$existing_cache` timeout is valid
* update `Language_API` to correctly get credentials
* add missing API values for packages as Additions

#### 12.23.1 / 2026-02-12
* add guard to release asset development download link in REST API
* fix potential race condition when saving cache to multi-server/clustered environments [#1133](https://github.com/afragen/git-updater/issues/1133), thanks @Ipstenu

#### 12.23.0 / 2026-02-11
* guard on `ReflectionProptery::setAccessible()` deprecated for PHP 8.5 and included in PHP 8.1+
* case-insensitive matching for `alpha|beta|RC`
* make `Language_Pack::update_site_transient()` a static
* fix REST API to return correct download link depending upon development channel

#### 12.22.0 / 2026-01-13
* added `gu_dev_release_asset` filter for dev release assets
* added `channel` query arg for dev release assets when using `update-api` REST endpoint
* send a saved access token with `update-api` REST API if one exists
* omit non-shared packages from REST API

#### 12.21.0 / 2025-12-31 🎆
* remove `git_updater_plugin_updates` and `git_updater_theme_updates` options, see [#1119](https://github.com/afragen/git-updater/issues/1119)
* add `gu_plugin_name()` to return plugin name, slug or slug-didhash
* change `can_update()` check to `manage_options` for `DISALLOW_FILE_MODS` constant
* MIT to GPL-3.0-or-later because of distributed components, etc
* cast `$response` elements to object in `parse_contents_response()`

#### 12.20.2 / 2025-12-08
* harden REST API data for versions if relesase_assets and tags are empty -- this can happen if too many tags are created that aren't semver format
* limit REST API to return last 20 versions
* update REST API conditional logic for setting release asset download link
* add guard for missing/empty assets in `Readme_Parser`
* move some `phpcs:disable` to package header
* update to `erusev/parsedown": "dev-master#0b274ac959624e6c6d647e9c9b6c2d20da242004"` for PHP 8.5 compliance, thanks @thefrosty
* standardize to `composer lint` and `composer format`

#### 12.20.1 / 2025-11-26
* initialize `$created_at` variable, possibly fixes PHP Error
* update actions/checkout
* update mu-loader.php

#### 12.20.0 / 2025-11-24
* move tag sort outside of loop
* use auth key for REST endpoint to flush repository cache for possible abuse
* added `Screenshots` section to plugin modal
* get `created_at` per release asset
* update `freemius/wordpress-sdk`
* Cache Add-Ons for 7 days

#### 12.19.0 / 2025-09-29
* setup for Gitea release assets
* use `mcaskill/composer-exclude-files` to exclude autoload of `freemius/wordpress-sdk/start.php`
* harden `parse_meta_response()`
* modify dot org check for package added to mirror like AspireCloud
* don't overwrite `requires` and `requires_php` data from readme.txt if already exists
* correctly parse for multiple release assets per release
* update POT GitHub Action
* refactor `add_accept_header()`
* set `release_assets` and `release_asset_download` for latest release asset

#### 12.18.1 / 2025-08-06
* data check on release assets

#### 12.18.0 / 2025-08-04
* update cache delete and don't use `wp_cache_flush`
* always show download link in REST endpoint
* improved reverse sort for branch/tag versions
* get all release assets from GitHub API and pick release asset download from release assets array, other APIs get latest release asset only
* update `parse_tag()` and `sort_tags()`
* update branch switching tags

#### 12.17.3 / 2025-07-31
* add new `Security` header with value of email or URI

#### 12.17.2 / 2025-07-26
* update `GU_Freemius` for FAIR installation

#### 12.17.1 / 2025-07-20
* add remote data for `did`, `slug_hash` if added via `Additions`
* use `Bearer` for token with GitHub API

#### 12.17.0 / 2025-07-14
* un-escape stuff, more uses of `use`
* add `License` header info
* add `Update URI` header info
* add `get_did_hash()` to get hash of DID
* add `get_file_without_did_hash()`
* simplify check for `rename_on_activation()`
* update `freemius/wordpress-sdk`

#### 12.16.1 / 2025-06-12
* add DID
* update rollback sort
* update banner image

#### 12.16.0 / 2025-06-09
* change callback from `new REST_API()` to `$this`
* collect `Author URI` from headers.
* add action hook to `Base::get_remote_repo_meta`
* get all versions of release assets, similar to tags/rollbacks
* add compatibility check for AspireUpdate and FAIR Package Manager

#### 12.15.1 / 2025-05-20
* update stability of composer requirements

#### 12.15.0 / 2025-05-20
* update to correct format of readme tags
* add correctly formated date/time for `update-api` REST endpoint
* remove deprecated hooks from v10 and earlier
* add error checking to `parse_contents_response()`
* update Freemius/wordpress-sdk
* add support for `Plugin ID` and `Theme ID` headers for FAIR
* update `composer.json`

#### 12.14.0 / 2025-02-26
* make sure proper release asset headers are added even if access token not set
* ensure _short description_ is 150 characters or less

#### 12.13.0 / 2025-02-21
* update caching
* add `versions` to REST endpoint for `{plugins|themes|update}-api`
* update generate POT workflow

#### 12.12.1 / 2025-02-12
* revert uninstall back to Freemius

#### 12.12.0 / 2025-02-10
* save source with `Additions`
* update `Additions::deduplicate()`
* update `Base::upgrader_source_selection()` rename to allow for AJAX installation, thanks @costdev
* add `git-updater-collections`to `Add-Ons`
* make list table show all elements
* add `Private Package` option for `Additions`, these private packages are not to be shared with aggregators
* switch to standard `uninstall.php` as issue with calling `Freemius` during their `after_uninstall` hook
* add early exit in `get_repo_slugs()` during AJAX installation for `Add-Ons`
* remove soft match in `get_repo_slugs()`
* removed `Add_Ons::upgrader_source_selection` no longer needed
* save/export tags from `readme.txt` for REST endpoint

#### 12.11.0 / 2025-02-02
* update Additions to add additional listings
* more updates for possibly passing `null`
* update `REST_API::get_api_data`
* update `Theme` to add `theme_uri` to update transient
* update to pass complete data for multiple uses of `gu_additions` hook

#### 12.10.1 / 2025-01-30
* fix issue with release asset
* add guard to `Add-Ons`
* remove `git-updater-federation` from `Add-Ons`

#### 12.10.0 / 2025-01-29
* refactor `Add_Ons` to use `plugins-api` REST endpoint and standard plugin card
* added features by @costdev for AJAXifying
* added parsing of `Update URI` and `Requires Plugins` headers
* increase requirements to PHP 8+
* added REST endpoint to export data from `Additions`
* added REST endpoint to export Update API data from `Additions`
* update Freemius/wordpress-sdk
* change 'API Add-Ons' to 'Add-Ons'

#### 12.9.0 / 2025-01-07
* add API get for repo root contents for efficiency
* add feature to virtually add repos via Additions tab to server REST update-api endpoint
* switch to getting most data via API calls and not from locally installed files
* add REST endpoint to individually flush repo cache
* fix `Basic_Auth_Loader::get_slug_for_credentials()` to get slug for gist
* update `$release_asset_parts` in `Basic_Auth_Loader::unset_release_asset_auth()` for AWS download link
* improved release asset handling

#### 12.8.0 / 2024-12-21
* update GitHub release asset parsing
* update `REST_API` for Bitbucket update link
* update `REST_API` for `update-api` route

#### 12.7.2 / 2024-12-18
* update `freemius/wordpress-sdk`
* use `mcaskill/composer-exclude-files` to exclude autoloading `start.php` from Freemius, issues arise
* update `REST_API::get_api_data()` to always get current release asset redirect as appropriate

#### 12.7.1 / 2024-12-02
* use `get_file_date()` to return plugin version
* fix `API::get_dot_org_data()` to work with WPE mirror

#### 12.7.0 / 2024-11-30
* fix missing/incorrect textdomains
* look for `__()` functions loading in hooks before `init`
* remove `load_plugin_textdomain()`
* add git host icon to single site theme description
* don't save to GitHub.com access token from single repo remote install
* fix PHP 8.1 creation dynamic variable from `class REST_API`
* update REST API response to return `plugins_api()` or `themes_api()` style response

#### 12.6.0 / 2024-10-13
* check existence of `FS__RESOLVE_CLONE_AS` before setting
* add filter hook `gu_api_domain` to set domain for default API updating
* add filter hook `gu_ignore_dot_org` to completely ignore updates from dot org. Works as if every plugin/theme is in the `gu_override_dot_org` hook

#### 12.5.0 / 2024-08-16
* update `class-parser.php`
* update `Requires PHP` to 7.4 for `class-parser.php`
* update `Requires WP` to 5.9
* update `freemius/wordpress-sdk`
* update `printf()` in `class Branches`
* fix old `git-updater-pro` and `git-updater-additions` textdomains
* update `Base::upgrader_source_selection()` when trying to update `$source` and `$new_source` when destination directories are identical
* remove unused parameters in certain functions

#### 12.4.0 / 2024-03-04
* update `freemius/wordpress-sdk`
* update `class-parser.php`
* use `is_wp_version_compatible()` and `is_php_version_compatible()` in `GU_Trait::can_update_repo()`
* update `gu-loader.php` with generic loader
* update `Readme_Parser::trim_length`

#### 12.3.1 / 2023-10-19
* update `freemius/wordpress-sdk`
* WPCS 3.0.0 linting
* popup on icon for "Updates via Git Updater", thanks @BrianHenryIE

#### 12.3.0 / 2023-08-10
* update Bitbucket Add-on message for consistency
* ensure `Shim` available during `register_activation_hook()`
* add conditional to `get_remote_api_branches()` to ensure `$response` is not a scalar
* use null coalescing operator
* update for PHP 8.2

#### 12.2.3 / 2023-06-27
* composer update
* get `gu_disable_cron` hook result once per repository
* ensure git class is instantiated when checking `waiting_for_background_update()`
* add check for `$response->error` to `API::validate_response()`
* update `freemius/wordpress-sdk` to 2.5.10

#### 12.2.2 /2023-05-22
* add back Network only activate for multisite, may cause issue where post-license activation Freemius doesn't re-direct to network admin
* update anonymous functions as static functions for better performance
* composer update

#### 12.2.1 / 2023-04-21
* ensure `$wp_filesystem` set for `Bootstrap::rename_on_activation()`
* uninstall tested to function correctly

#### 12.2.0 / 2023-04-20
* update `freemius/wordpress-sdk`
* update `afragen/wp-dismiss-notice`
* don't save unused data from `API_Common::parse_release_asset()`
* don't use Freemius uninstall, use previous `uninstall.php`
* more PHP 8.2 compatibility
* composer update
* update `REST_API::get_plugins_api_data()` to return response without download link using boolean value in `download` query arg
* hide Freemius menus with `gu_hide_settings` filter
* more specific hiding of Git Updater settings

#### 12.1.3 / 2023-03-20
* improved setting/default of `$options['bypass_background_processing']`
* improved setting/default of `$options['branch_switch']`
* display upgrade notice on `update-core.php`
* composer update `afragen/singleton` for PHP 8.2 compat

#### 12.1.2 / 2023-02-08
* fix for webhook updating issue if `$branches` not defined, thanks @awunsch

#### 12.1.1 / 2023-02-07
* remove force of Network activation, messes up Freemius license activation on multisite
* composer update

#### 12.1.0 / 2023-02-06
* further limit log of HTTP errors, trying for only once per plugin
* eliminate Freemius clone resolution popup
* update `Shim:move_dir()` for improved error messaging
* update comparison in `Base::upgrader_source_selection` of `$source` and `$new_source`

#### 12.0.4 / 2023-01-27
* update to use `str_contains()`
* log HTTP errors only hourly
* update `Shim::move_dir()` to exit early if source and destination differ only by case or trailing slash

#### 12.0.3 / 2023-01-19
* update `Shim` for `move_dir()` and `wp_opcache_invalidate_directory()`
* composer update

#### 12.0.2 / 2023-01-12
* PHP 8.1 compatibility fix, thanks @chesio
* other PHP 8.1 fixes
* declare `class API` variable `$type`, avoid future PHP issues, thanks @chesio

#### 12.0.1 / 2023-01-02
* cleanup parsing of GitHub release assets
* composer update to fix dependency conflict

#### 12.0.0 / 2022-12-12
* ensure `$wp_filesystem` is available
* re-integrate Git Updater PRO
* integrate Git Updater Additions
* add git logo to subtab, hide for now
* load API tabs of installed/active API plugins
* set Git Updater to auto-update with new `$db_version`
* replace Appsero SDK with Freemius SDK
* suspend Freemius plugin updating for Git Updater
* fix uninstall.php for Freemius
