# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```sh
# Install PHP dependencies
composer install

# Install JS dependencies (required for wp-env)
npm install

# Lint (PHPCS)
composer lint

# Auto-fix linting issues (PHPCBF)
composer format

# Static analysis
composer phpstan

# Regenerate PHPStan baseline (after intentional changes that add new errors)
composer phpstan-baseline

# Run PHPUnit tests via wp-env (single site)
composer test          # delegates to: npm test
npm test

# Run PHPUnit tests via wp-env (multisite)
composer test-ms       # delegates to: npm run test:multisite
npm run test:multisite

# Run PHPUnit tests with code coverage (requires Xdebug — installed automatically on wp-env start)
npm run test:coverage

# Start/stop wp-env Docker environment
# Note: afterStart lifecycle script installs Xdebug into the tests-cli container
npm run wp-env start
npm run wp-env stop

# Run a single test class or method
# Inside the wp-env tests-cli container, add --filter to the phpunit invocation:
npm run wp-env -- run tests-cli /var/www/html/wp-content/plugins/git-updater/vendor/bin/phpunit --config=/var/www/html/wp-content/plugins/git-updater/phpunit.xml --filter=Test_API
```

## Testing Environment

Tests use `@wordpress/env` (wp-env) — a Docker-based WordPress environment. The plugin is mounted inside the `tests-cli` container at `/var/www/html/wp-content/plugins/git-updater/`. The WordPress test library is pre-provisioned by wp-env at `/tmp/wordpress-tests-lib` inside the container. `tests/bootstrap.php` falls back to that path automatically when `WP_TESTS_DIR` is unset.

The `WP_TESTS_PHPUNIT_POLYFILLS_PATH` is passed explicitly in the npm scripts to point to the vendored `yoast/phpunit-polyfills`.

PHPStan is configured at level 6 (`phpstan.neon`) with pre-existing errors tracked in `phpstan-baseline.neon`. The baseline should be regenerated with `composer phpstan-baseline` when intentional changes alter the error set.

All `missingType.iterableValue` and `missingType.return` errors have been resolved across the codebase. When adding new methods or properties, follow the established PHPDoc conventions:
- Use specific array value types: `array<string, mixed>`, `array<int, string>`, `array<string, stdClass>`, etc. — never bare `array`
- Add `@return void` to every method that returns nothing
- Repo config collections are typed `array<string, stdClass>`; option arrays are `array<string, mixed>`

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

### API layer

`src/Git_Updater/API/API.php` — base class for all git host APIs. The central method is `api($endpoint)`, which:
1. Resolves the endpoint URL via `get_api_url()` (replaces `:owner`, `:repo`, etc. placeholders).
2. Checks the main repo cache; if hit, returns cached data.
3. Checks the error cache (`slug_error` key); if fresh (within 60 min), returns `false` without making an HTTP request.
4. Makes `wp_remote_get()` if both caches are cold.
5. On non-200 response, writes to the error cache and returns `false`.
6. On 200, stores the decoded body in the main cache.

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

## Testing gotchas

### REST API test setup
Always use `rest_get_server()` — never instantiate `WP_REST_Server` directly or call `register_endpoints()` manually. WordPress enforces that `register_rest_route()` is called inside the `rest_api_init` hook. `rest_get_server()` fires that hook automatically. Reset between tests with `$GLOBALS['wp_rest_server'] = null` before calling `rest_get_server()`.

### Composer platform pin
`composer.json` pins `"platform": {"php": "8.2"}`. Do not change this. Without it, Composer running on PHP 8.3+ resolves `doctrine/instantiator` 2.1.0, which uses typed class constants (`private const string …`) — PHP 8.3+ syntax — causing a parse error in the PHP 8.2 wp-env container that silently fails all tests in the affected file.

### HTTP mocking with `pre_http_request`
Mock all outbound HTTP via the `pre_http_request` filter (not `wp_remote_get` stubs). Return a `WP_Error` to short-circuit, or a properly structured response array: `['response' => ['code' => 200], 'body' => '...', 'headers' => []]`. Convenience helper `http_response(string $body, int $code = 200)` is available in `WP_Http_TestCase`-derived test classes.

### Error cache contamination
Any non-200 HTTP response from `API::api()` writes a 60-minute error cache entry (`ghu-<md5(slug_error)>` site option). Subsequent calls within that window return `false` immediately without hitting the network. In tests: ensure mocks return 200 for all paths, or delete the error site option in `tear_down()`.

### `convertNoticesToExceptions` is on
`phpunit.xml` sets `convertNoticesToExceptions="true"`. Undefined array key accesses (PHP 8 notices) fail tests immediately. Always initialise array keys before access, or use `isset()` / `??` guards.

### api.wordpress.org update-check mocks
WordPress core's `wp_update_plugins()` does `json_decode($body, true)` then directly accesses `$response['plugins']`. Return the correct structure to avoid undefined-key failures:
- Plugins: `{"plugins": [], "translations": [], "no_update": []}`
- Themes: `{"themes": [], "translations": [], "no_update": []}`

### Fixture plugin path in `.wp-env.json`
Plugin paths must be prefixed with `./` (e.g. `"./tests/fixtures/plugins/test-gu-plugin"`). Without the prefix, wp-env treats the string as a `owner/repo` GitHub slug and fails with "repository not found".

### Trait test pattern: use `GitHub_API`, not trait-on-test-class
Methods that call `get_class_vars()` (e.g. `set_repo_cache`, `get_error_codes`) rely on `Singleton::get_instance('API\API', ...)` resolving relative to the caller's namespace. When called from a global-namespace test class the resolution fails. Always instantiate `GitHub_API` and call trait methods through it.

### `get_remote_api_info()` success path requires dot_org cache pre-seeding
`get_remote_api_info()` (called via `GitHub_API::get_remote_info()`) invokes `get_dot_org_data()`, which hits `api.wordpress.org` unless the main cache already contains a `dot_org` key. Pre-seed it in the test:
```php
update_site_option( $this->api->get_cache_key('test-plugin'), [
    'dot_org' => 'not in dot org',
    'timeout' => strtotime('+12 hours'),
] );
```

### `parse_release_asset()` does not guard against `false`
`parse_release_asset()` checks `is_wp_error($response)` but not `false`. If `api()` returns `false` (via error cache) the subsequent `foreach($response as $release)` throws a TypeError. When testing failure paths for `get_release_assets()`, use a `WP_Error` mock via `pre_http_request` rather than seeding the error cache:
```php
add_filter('pre_http_request', fn() => new WP_Error('http_request_failed', 'Connection refused'), 10, 3);
```

### Cron scheduling in tests: use past timestamps
`wp_get_ready_cron_jobs()` only returns events with a timestamp ≤ `time()`. Scheduling with `time() + HOUR_IN_SECONDS` (future) makes the event invisible. Use `time() - HOUR_IN_SECONDS` (1 hour ago) — past-due but within the 24-hour `is_cron_overdue()` window, so no error is triggered.

### `get_api_release_asset()` is commented out in GitHub_API
`GitHub_API::get_release_asset()` has its body commented out; calling it is a no-op. The underlying trait method `get_api_release_asset()` is `final public` and can be invoked directly in tests:
```php
$this->api->get_api_release_asset( 'github', '/repos/test-owner/test-plugin/releases/latest' );
```

### Dev-release tag format for `parse_release_asset()`
The dev-release regex `/[^v]+(?:nightly|alpha|beta|RC){1}[0-9]{0,}/i` requires at least one non-`v` character *before* the keyword. A bare `beta1` does not match; use `1.0.0-beta1`, `2.0.0-nightly20240601`, etc.

### Testing cache-hit paths: `seed_main_cache()` helper pattern
Pre-populate the main site option to drive the cached branch without HTTP. Merge with a future timeout so `get_repo_cache()` doesn't reject the entry:
```php
update_site_option(
    $this->api->get_cache_key( 'test-plugin' ),
    array_merge( [ 'timeout' => strtotime( '+12 hours' ) ], $data )
);
```
Methods that use `get_repo_cache($slug, false)` (ignore timeout) also read stale entries, so the timeout value doesn't matter for those callers.

### `get_addon_api_results()` uses `wp_remote_post`, interceptable via `pre_http_request`
`Add_Ons::get_addon_api_results()` calls `wp_remote_post()` for each of the four add-on slugs. Mock all outbound HTTP via `add_filter('pre_http_request', ...)` — the filter intercepts POST requests too. Results are cached only when all four addons succeed (count check); partial results are returned but not cached. Cache key is `ghu-` + md5('gu_addon_api_results').

### Additions\Settings has a static `$options_additions` property
`Settings::$options_additions` is a static property populated in `__construct()` from `get_site_option('git_updater_additions', [])`. In tests, reset it before constructing: `Additions_Settings::$options_additions = []`. Set it directly on the class (not via site option) to drive `callback_checkbox()` checked-state tests.

### Repo_List_Table requires WP_List_Table to be loaded
`Repo_List_Table` extends `WP_List_Table`. The file-level guard loads it automatically when the class file is autoloaded, but as belt-and-suspenders add `require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php'` in `set_up()`. The constructor works in the tests-cli container without full admin context; `get_current_screen()` returns null but does not error.

### `wp_theme_update_row()` echoes directly; requires WP_Plugins_List_Table
`Theme::wp_theme_update_row()` echoes HTML directly — capture it with `ob_start()`/`ob_get_clean()`. It calls `$this->base->update_row_enclosure()` which internally calls `_get_list_table('WP_Plugins_List_Table')`. Load the needed admin includes in `set_up()`:
```php
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-plugins-list-table.php';
require_once ABSPATH . 'wp-admin/includes/template.php'; // defines _get_list_table
```
All three are available in the wp-env tests-cli container. The early-return path (when `$theme_key` is absent from `update_themes->response`) produces no output and needs no admin setup.

### `Additions::deduplicate()` reads plugin/theme cache with full timeout check
`deduplicate()` calls `get_repo_cache('git_updater_repository_add_plugin')` and `get_repo_cache('git_updater_repository_add_theme')` with the default `$timeout = true`. Seed these site options with a future `timeout` key or the cache will be ignored: `update_site_option('ghu-' . md5('git_updater_repository_add_plugin'), ['git_updater_repository_add_plugin' => [...], 'timeout' => strtotime('+12 hours')])`.

### Cron clearing after transaction rollback: bust the object cache first
`wp_clear_scheduled_hook($hook)` and `wp_unschedule_hook($hook)` read the cron schedule from the WordPress object cache before writing. After a WP_UnitTestCase DB transaction rollback the object cache can be stale — the DB holds the original (bootstrap-scheduled) cron event but the cache reflects the cleared state written by the previous test's `tear_down()`. Calling `wp_clear_scheduled_hook` against a stale cache is a no-op that leaves the DB event in place.

Always bust the cache before clearing cron hooks in `set_up()` and `tear_down()`:
```php
wp_cache_delete( 'cron', 'options' );
wp_unschedule_hook( 'gu_get_remote_plugin' );
```

The `gu_get_remote_plugin` hook is bootstrapped at `init` time (via `Base::load()` → `get_meta_plugins()`) so it will always be present in the pre-test DB state.

### `gu_additions` filter is called with 3 args; register with `add_filter(..., 10, 3)`
`get_theme_meta()` applies the filter as `apply_filters('gu_additions', null, $themes, 'theme')`. A listener registered without specifying the accepted-arg count (default 1) will never receive the `$type` argument. Always pass the priority and count explicitly:
```php
add_filter( 'gu_additions', function( $value, $themes, $type ) { ... }, 10, 3 );
```

### `gu_disable_wpcron` path in `get_remote_theme/plugin_meta()` needs no HTTP mock
`Base::get_remote_repo_meta()` has an early return: `if ($disable_wp_cron && !can_update()) return false`. In tests there is no admin user, so `can_update()` always returns false. When `gu_disable_wpcron` is true the method short-circuits before any HTTP call, so no `pre_http_request` mock is needed.
