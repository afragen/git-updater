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

### Install screen GitHub OAuth autocomplete

`Install.php` adds three `wp_ajax_*` AJAX handlers that power the autocomplete UX on the Install Plugin / Install Theme tab when a GitHub OAuth token is active.

**AJAX registration** — `Install::register_ajax_handlers()` is called from `Settings::load_hooks()` (not from `Install::run()`) so the handlers are available on all admin requests, including `admin-ajax.php`. `Install::run()` is gated behind `! wp_doing_ajax()` in `Settings::page_init()`, so any hook registration inside `run()` would be invisible to AJAX requests.

**`gu_github_repos`** — Accepts a `q` query param. Calls `fetch_all_github_repos()` which paginates `/user/repos` (type=all) and then paginates `/orgs/{org}/repos` for every org returned by `/user/orgs`. Results are deduplicated by `full_name` and cached in a site transient keyed by `md5($token)` for 5 minutes. The handler filters the cached list by `q` and returns up to 20 matches.

**`gu_github_branches`** — Accepts a `repo` param (`owner/repo`). Paginates `/repos/{owner}/{repo}/branches` and caches the branch name list for 5 minutes.

**`gu_github_repo_info`** — Accepts a `repo` param. Fetches `/repos/{owner}/{repo}` and returns `default_branch`, `private`, and `owner` login. Cached 5 minutes.

**Script data** — `load_js()` localises `guInstallData` onto `gu-install` with:
- `ajaxurl` — `admin-ajax.php` URL
- `nonce` — `gu_github_install_autocomplete` nonce
- `github_oauth` — `'1'` when a GitHub OAuth token is stored
- `github_username` — authenticated user's login (from `/user`, cached 1 hour via `get_github_username()`)
- `github_orgs` — lowercase array of org slugs the user belongs to (from `/user/orgs`, cached 1 hour via `get_github_org_logins()`), used to recognise org repos as "connected account" repos

**JS behaviour** (`js/gu-install-vanilla.js`) — The autocomplete runs only when `github_oauth === '1'`. Both the URI and Branch fields get independent dropdowns with:
- Debounced input (250 ms) with an immediate CSS spinner while waiting
- Keyboard navigation: ↑/↓ moves highlight, Enter selects, Escape closes
- ARIA: `role="combobox"` on inputs, `role="listbox"` on lists, `role="option"` on items, `aria-expanded`, `aria-activedescendant`
- `li._guRepo` / `li._guBranch` properties store the data object for keyboard selection without re-parsing `dataset`

When a URI from a connected account is entered or selected, `applyConnectedRepoState()`:
1. Sets the host dropdown to `github` and dispatches a `change` event (to trigger existing show/hide logic for other API token fields)
2. Calls `hideHostAndTokenFields()` **after** the dispatch — the change handler would otherwise re-show the `github_setting` rows
3. Fetches and autofills the default branch if the field is empty or set to `master` and the actual default differs

The "connected account" check compares the repo owner (extracted from full URL or `owner/repo` slug) against `github_username` and all entries in `github_orgs`.

### Coding standards

PHPCS uses the `WordPress` ruleset with several exclusions defined in `phpcs.xml`. Notable: short array syntax (`[]`) is enforced, file naming and variable naming WordPress conventions are relaxed, and some Squiz control structure rules are disabled.
