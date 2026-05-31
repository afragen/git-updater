## Architecture

### Entry point and bootstrap

`git-updater.php` is the plugin entry point. It loads the Composer autoloader, then on `plugins_loaded` calls `Bootstrap::run()`, which wires up all subsystems: Freemius licensing, REST API, Additions, and the main `Init` class.

`Init::run()` registers WordPress hooks. When running under WP-CLI, it also loads CLI classes and immediately triggers remote meta fetches.

### Singleton pattern

Nearly every class is accessed through `Fragen\Singleton::get_instance('ClassName', $this)`. This keeps a single shared instance per class. The first argument is the class name (relative to `Fragen\Git_Updater\` or a fully-qualified name), the second argument is passed to the constructor on first instantiation.

### Traits

Shared behaviour lives in traits under `src/Git_Updater/Traits/`:

- **`GU_Trait`** — used by almost every class. Contains all cache logic (`get_repo_cache`, `set_repo_cache`, `get_cache_key`), option loading, helper guards (`is_heartbeat`, `is_wp_cli`, `should_run_on_current_page`), and `get_class_vars()` for reading static properties from other classes via reflection.
- **`API_Common`** — used by `API` and API subclasses. Contains shared API response parsing logic (base64 decode, release assets, branch/tag parsing).
- **`Basic_Auth_Loader`** — adds HTTP Basic Auth headers to requests when credentials are configured.

### Caching

All API data is cached in WordPress site options. Cache keys follow the pattern `ghu-<md5(slug)>` for the main 12-hour cache and `ghu-<md5(slug_error)>` for the separate 60-minute error cache. The error cache uses a dedicated site option key so it survives main cache expiry independently.

`set_repo_cache($id, $response, $repo, $timeout)` — writes a single keyed value into the cache array for a repo. The `$repo` argument selects which site option (false = current `$this->type->slug`). `$timeout` is a strtotime-compatible string (e.g. `'+60 minutes'`).

`set_repo_cache()` uses `$cache['timeout'] = $cache['timeout'] ?? strtotime($timeout)`, so the `timeout` field is preserved across per-entry writes within a cycle. The flip side: once a `timeout` exists on the option (even an expired one from a previous cycle), subsequent `set_repo_cache()` calls will never refresh it. After a complete fetch cycle, `GU_Trait::set_repo_cache_timeout($slug)` must be called explicitly to write the fresh default-`$hours` timeout. See *Cache completion tracking* below.

### API layer

`src/Git_Updater/API/API.php` — base class for all git host APIs. The central method is `api($endpoint)`, which:
1. Resolves the endpoint URL via `get_api_url()` (replaces `:owner`, `:repo`, etc. placeholders).
2. Checks the error cache (`slug_error` key); if fresh (within 60 min), returns `false` without making an HTTP request.
3. Makes `wp_remote_get()` if the error cache is cold.
4. On `WP_Error` (network failure), returns the `WP_Error` immediately.
5. On non-200 response, writes the 60-minute error cache entry, then returns the decoded body (e.g. `stdClass{message:'Not Found'}`).
6. On 200, returns the decoded body.

`api()` does not cache HTTP responses itself. Whether to skip API calls entirely is controlled exclusively by `maybe_extend_repo_cache()` in `get_remote_api_info()`, which gates the whole secondary-call block in `Base::get_remote_repo_meta()`.

### `get_remote_api_*` tri-state returns

The seven shared API methods in `API_Common` (`get_remote_api_tag`, `get_remote_api_changes`, `get_remote_api_readme`, `get_remote_api_assets`, `get_remote_api_repo_meta`, `get_remote_api_branches`, `get_remote_api_contents`) return `bool|null`:

- **`true`** — ran and cached useful data.
- **`null`** — ran but found nothing (repo has no tags, no readme, etc.); a placeholder with `->message` is cached. Counts as complete.
- **`false`** — `WP_Error` (network failure, DNS, SSL). Does NOT count as complete; causes a retry on the next WordPress update check.

Note: when the error cache is active, `api()` returns literal `false`. In the `get_remote_api_*` methods this `false` hits the `!$response` branch and returns `null` (counted as complete, honouring the error cache's intent to stop retrying for 60 min).

### Cache completion tracking (`$cache['ran']`)

`Base::get_remote_repo_meta()` runs the seven secondary API calls unconditionally after `get_remote_info()` succeeds — there is no `is_wp_cli()` gate. It records which calls completed in `$cache['ran']` using a ternary + `array_filter` pattern:

```php
$ran   = [];
$ran[] = false !== $repo_api->get_repo_contents()    ? 'contents' : null;
// ... six more lines ...
$repo_api->set_repo_cache( 'ran', array_filter( $ran ) );
$repo_api->set_repo_cache_timeout( $repo->slug );
```

`array_filter` strips `null` (WP_Error calls), leaving only string keys of completed calls.

`GU_Trait::set_repo_cache_timeout($slug)` runs immediately after the `'ran'` write. It is a no-op unless `$cache['ran']` contains all seven expected entries (`contents`, `assets`, `readme`, `changes`, `tags`, `branches`, `meta`); on a complete cycle it writes `cache['timeout'] = strtotime('+' . $hours . ' hours')` (default 12, applies `gu_repo_cache_timeout` filter with `$id = 'ran'`). This is the only path that refreshes the cache timeout after a new-version fetch — without it the prior cycle's expired timeout lingers and forces redundant API calls on the next pass within the same request (e.g. from the `wp_update_plugins` / `wp_update_themes` actions wired by `Base::background_update()`, which fires in addition to `Base::load()`'s direct call).

`GU_Trait::maybe_extend_repo_cache( $remote_headers, $repo, $old_version )` uses `array_diff($expected, $cache['ran'])` to confirm all seven completed before extending the 6-hour cache timeout. An incomplete `$ran` causes it to return `false`, which makes `get_remote_repo_meta()` re-run all secondary calls on the very next WordPress update check — no need to wait for cache expiry. The timeout comparison uses `$cache['timeout'] ?? 0` so a missing key safely passes `0` (treated as expired) rather than causing a TypeError in PHP 8.

The `$old_version` parameter is the remote version from **before** this fetch, captured in `get_remote_api_info()` prior to calling `set_repo_cache()`. This prevents the version comparison from always seeing equal values (the ordering bug: comparing the freshly-written cache value against itself). When `$old_version` differs from the newly fetched version, `maybe_extend_repo_cache()` returns `false` and the secondary calls run to refresh all repo data.

`src/Git_Updater/API/GitHub_API.php` implements `API_Interface` and extends `API`. Additional git host APIs (Bitbucket, GitLab, Gitea) are loaded via add-on plugins and registered through the `gu_get_repo_api` filter.

`API_Interface` defines the contract all git-host API classes must implement: `get_remote_info`, `get_remote_tag`, `get_remote_changes`, `get_remote_readme`, `get_repo_meta`, `get_remote_branches`, `get_release_asset`, `construct_download_link`, `add_endpoints`, plus response-parsing and settings methods.

### Plugin and Theme update flow

`Plugin` and `Theme` classes discover installed plugins/themes with git headers, call the relevant API to fetch remote metadata, and hook into `site_transient_update_plugins` / `site_transient_update_themes` to inject update data into WordPress's standard update mechanism.

Plugin/theme repo objects (`$this->type`) are `stdClass` instances populated with fields like `slug`, `git`, `owner`, `branch`, `primary_branch`, `enterprise`, `enterprise_api`, `gist_id`.

### Additions

`src/Git_Updater/Additions/` — allows registering repos that lack proper plugin/theme file headers (e.g. mu-plugins, non-standard layouts). Configured via the `git_updater_additions` site option and the `gu_additions` filter.

### REST API

`src/Git_Updater/REST/REST_API.php` — registers endpoints under `git-updater/v1`. Used for webhook-triggered updates. `Rest_Update` handles the actual update logic. A legacy `wp_ajax_git-updater-update` handler is also maintained for backwards compatibility.

### WP-CLI

`src/Git_Updater/WP_CLI/CLI.php` — registers `wp git-updater` commands. `CLI_Integration.php` provides subcommands for listing/updating specific plugins and themes. `CLI_Common.php` holds shared cache-clearing and utility logic. CLI classes are only loaded when `WP_CLI` is defined.

### Settings and options

All plugin options are stored in a single site option `git_updater` (an array). `GU_Upgrade` handles migration from legacy `github_updater` option names. Settings UI is in `Settings.php`; per-repo authentication fields are added via `gu_add_settings` and `gu_add_repo_setting_field` filters implemented in each API class.

### Coding standards

PHPCS uses the `WordPress` ruleset with several exclusions defined in `phpcs.xml`. Notable: short array syntax (`[]`) is enforced, file naming and variable naming WordPress conventions are relaxed, and some Squiz control structure rules are disabled.
