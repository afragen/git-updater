# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## General Instructions

Do not make any changes until you have 95% confidence in what you need to build. Ask me follow-up questions until you reach that confidence.

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

### `merge_and_reschedule_cron_batch()` replaces `is_cron_event_scheduled()` at scheduling sites
`Plugin::get_remote_plugin_meta()` and `Theme::get_remote_theme_meta()` now call `merge_and_reschedule_cron_batch($hook, $repos)` instead of the old guard+schedule pattern. The helper reads `_get_cron_array()`, merges any existing scheduled batch's `args[0]` into the new array (keyed by slug, so deduplication is automatic), calls `wp_unschedule_hook($hook)` to clear old events, then schedules one new consolidated event. `is_cron_event_scheduled()` is retained for read-only queries but is no longer called at the scheduling sites. Tests that assert a cron event was scheduled should still bust the object cache first: `wp_cache_delete('cron', 'options')`.

### `gu_additions` filter is called with 3 args; register with `add_filter(..., 10, 3)`
`get_theme_meta()` applies the filter as `apply_filters('gu_additions', null, $themes, 'theme')`. A listener registered without specifying the accepted-arg count (default 1) will never receive the `$type` argument. Always pass the priority and count explicitly:
```php
add_filter( 'gu_additions', function( $value, $themes, $type ) { ... }, 10, 3 );
```

### `gu_disable_wpcron` path in `get_remote_theme/plugin_meta()` needs no HTTP mock
`Base::get_remote_repo_meta()` has an early return: `if ($disable_wp_cron && !can_update()) return false`. In tests there is no admin user, so `can_update()` always returns false. When `gu_disable_wpcron` is true the method short-circuits before any HTTP call, so no `pre_http_request` mock is needed.

### `get_theme_meta()` — `$all_headers` is set before the first loop
`$all_headers = $this->get_headers('theme')` is set at the top of `get_theme_meta()`, before the `foreach ($paths as $slug => $path)` loop. This matches the Plugin pattern. `gu_additions` theme injection works correctly even in bare environments with no installed themes.

### `get_theme_meta()` branch migration: set `Base::$options` before constructing Theme
`Theme::$options` (private static) is copied from `Base::$options` in the Theme constructor via `get_class_vars('Base', 'options')`, then `load_options()` resets `Base::$options` from the DB. To make `self::$options['current_branch_X']` available inside `get_theme_meta()` during construction, set `Base::$options['current_branch_X']` before `new Theme()`. The value persists in `Theme::$options` for the duration of that `get_theme_meta()` call.

### Multisite `after_theme_row` actions added by `get_remote_theme_meta()`; clean up in `tear_down()`
When `is_multisite()` is true, `get_remote_theme_meta()` adds `after_theme_row` and `after_theme_row_{slug}` actions for each theme in config. Add `remove_all_actions('after_theme_row')` and `remove_all_actions("after_theme_row_{slug}")` to `tear_down()` to prevent cross-test contamination.

### `.git/HEAD` branch override bug in Theme.php (fixed)
The original `.git/HEAD` detection block (lines 228–231) mistakenly wrote to `$git_plugin['branch']` instead of `$git_theme['branch']` — a copy-paste error from Plugin.php. Fixed: the branch is now correctly assigned to `$git_theme['branch']`. Tests for this path create a temporary `.git/HEAD` file in the fixture theme directory and clean it up in a `finally` block.

### `get_theme_meta()` — `! array_key_exists($key, $all_headers)` vs `null === $key` continue branches
Line 171 has two continue conditions: `null === $key` (no 'themeuri'-containing key found) and `! array_key_exists($key, $all_headers)` (key found but not a registered header). The `null` branch is triggered by a `gu_additions` theme whose array has no key matching `stripos($key, 'themeuri')`. The `! array_key_exists` branch is triggered by a `gu_additions` theme with a key that contains 'themeuri' (e.g. `'CustomThemeURI'`) but is NOT in the registered headers returned by `get_headers('theme')`. These are distinct test paths.

### `get_theme_meta()` — `gu_additions` themes without a `'Name'` key skip `local_path` and `.git/HEAD`
The block at lines 210–221 (setting `local_path`, `local_version`, `name`, etc.) only runs when `isset($theme['Name'])` is true. For `gu_additions` injected themes without `'Name'`, these fields are never set. Consequently `isset($git_theme['local_path'])` is false, and the `.git/HEAD` branch-override block (line 228) is also skipped. Additionally, accessing `$paths[$slug]` for gu_additions slugs that are not in `wp_get_themes()` would produce an undefined-key notice — so gu_additions themes should never include `'Name'`.

### `get_theme_meta()` — ThemeID header → non-null `slug_did`
`parse_extra_headers()` reads `$headers['ThemeID']` and sets `$header['did']`. When truthy, `$git_theme['slug_did']` is computed as `slug . '-' . get_did_hash(did)`. Inject a `gu_additions` theme with `'ThemeID' => 'did:example:abc'` to cover this path.

### `get_remote_theme_meta()` — direct fetch when cache is warm; repo object needs `owner`, `enterprise`, `enterprise_api`
When `waiting_for_background_update($repo)` returns false (non-empty cache), `Base::get_remote_repo_meta($repo)` is called directly. This eventually calls `API::api()` → `get_api_url()`, which reads `$this->type->owner`, `$this->type->enterprise`, and `$this->type->enterprise_api` from the repo object. These are not in `make_theme_obj()` defaults. Add them as overrides: `make_theme_obj(['owner' => 'afragen', 'enterprise' => null, 'enterprise_api' => null, ...])`. Detect the direct-fetch path via the `do_action('get_remote_repo_meta', ...)` hook that fires at the end of `get_remote_repo_meta()`; clean up with `remove_all_actions('get_remote_repo_meta')` in `tear_down()`.

### `get_plugin_meta()` — Plugin-exclusive `gu_fix_repo_slug` filter modifies the result key
`gu_fix_repo_slug` is applied at line 224 of Plugin.php after the repo object is assembled. The filter receives the full `$git_plugin` array; its returned `['slug']` value becomes the key in the `$git_plugins` result. There is no Theme equivalent. Always clean up with `remove_all_filters('gu_fix_repo_slug')` in `tear_down()`.

### `get_repo_slugs()` — test via Plugin Singleton as upgrader_object, not null
`get_repo_slugs(string $slug, $upgrader_object = null)` with `$upgrader_object = null` sets it to `$this` (GitHub_API). `GitHub_API` has no declared `$config` property so `get_class_vars('GitHub_API', 'config')` returns `false`, and `(array) false = [false]` causing a TypeError when the foreach tries `$repo->slug`. Instead, pass a real `Plugin` Singleton as the upgrader object: `Singleton::get_instance('Fragen\Git_Updater\Plugin', $this->api)`. For a nonexistent slug the loop finds no match and returns `[]`.

### `waiting_for_background_update(null)` — use `gu_config_pre_process` filter to empty repos
When called with `null`, the method merges Plugin and Theme configs then iterates. In the test environment the fixture plugin IS in Plugin config (with empty cache), so `$waiting` is non-empty and the method returns `true`. To test the false path, add `add_filter('gu_config_pre_process', '__return_empty_array')` before invoking, forcing `$repos = []` → `$waiting = []` → `false`. Clean up with `remove_all_filters('gu_config_pre_process')` in `tear_down()`.

### `get_github_rate_limit_headers()` — mock with `CaseInsensitiveDictionary` as headers value
`get_github_rate_limit_headers()` calls `wp_remote_retrieve_headers($response)->getAll()`. When short-circuited via `pre_http_request`, the filter must return an array whose `headers` key is a `WpOrg\Requests\Utility\CaseInsensitiveDictionary` instance. Use `new CaseInsensitiveDictionary(['x-ratelimit-reset' => (string)(time() + 300)])` for the reset-time test and `new CaseInsensitiveDictionary([])` for the 60-minute default test. Import with `use WpOrg\Requests\Utility\CaseInsensitiveDictionary;`.

### `get_repo_slugs()` dirname match — use `ReflectionProperty` to inject synthetic `$config`
`Plugin::$config` and `Theme::$config` are both declared `private`. To inject a synthetic entry where `dirname($repo->file)` differs from `$repo->slug` (the `-master` suffix scenario), use `ReflectionProperty`:
```php
$ref      = new ReflectionProperty( get_class( $obj ), 'config' );
$ref->setAccessible( true );
$original = $ref->getValue( $obj );
$ref->setValue( $obj, [ 'my-plugin' => (object) [ 'slug' => 'my-plugin', 'file' => 'my-plugin-master/my-plugin.php' ] ] );
try {
    $result = $this->invoke_get_repo_slugs( 'my-plugin-master', $obj );
} finally {
    $ref->setValue( $obj, $original );
}
```
The same pattern applies when the Theme Singleton's config is empty (fixture theme not discovered by `get_theme_meta()` in the test env) — inject a minimal `['test-gu-theme' => (object)['slug' => 'test-gu-theme', 'file' => 'test-gu-theme/style.css']]` and restore in `finally`.

### `get_repo_slugs()` AJAX path — mock via `wp_doing_ajax` filter + real nonce
`wp_doing_ajax()` applies the `wp_doing_ajax` filter, so `add_filter('wp_doing_ajax', '__return_true')` mocks AJAX context without defining `DOING_AJAX`. `check_ajax_referer('updates')` is satisfied by placing `wp_create_nonce('updates')` in `$_REQUEST['_ajax_nonce']` — works with user_id=0 (no logged-in user required). Unset `$_POST['action']`, `$_POST['git_updater_repo']`, and `$_REQUEST['_ajax_nonce']` in `tear_down()`. The nonce must be created fresh each test because `wp_create_nonce` is time-sensitive.

### `Basic_Auth_Loader` — testing private credential helpers via reflection
`get_credentials()`, `get_slug_for_credentials()`, and `get_type_for_credentials()` are all `private`. Access them with `ReflectionMethod::invoke()`. Always call through a `GitHub_API` (or `Language_Pack_API`) instance — not a bare test class — so the `Singleton` namespace resolution works correctly.

### `Basic_Auth_Loader::get_credentials()` Language_Pack_API branch
`Language_Pack_API` extends `API` and can be instantiated directly: `new Language_Pack_API($type)`. Its constructor calls `parent::__construct()` (sets static options/headers from Base) then sets `$this->type = $type`. Use this to cover the `$this instanceof Language_Pack_API` branch in `get_credentials()` (lines 129–131).

### `Basic_Auth_Loader::add_auth_header()` — control credentials via site option
`get_credentials()` reads `get_site_option('git_updater')` directly (not `Base::$options`). To test the Bearer-token path: `update_site_option('git_updater', ['github_access_token' => 'test-token'])` and set `$_REQUEST['slug'] = 'the-slug'`. To test the no-token (type-only) path: leave the site option absent — `$token` resolves to `null`, triggering the `elseif` at line 79.

### `Basic_Auth_Loader::add_accept_header()` — git-server header path
To exercise the `in_array($key, get_running_git_servers())` branch, pass `['headers' => ['github' => $slug]]`. Pre-seed the repo cache with `update_site_option('ghu-' . md5($slug), ['release_asset_download' => 'https://...'])` (no timeout needed — `get_repo_cache($value, false)` ignores timeout) to trigger the `Accept: application/octet-stream` merge. Without the cache entry the `github` key is still unset but no Accept header is added.

### `Basic_Auth_Loader` Remote Install POST path — clean up `$_POST` in `tear_down()`
`get_type_for_credentials()` reads `$_POST['git_updater_api']` and `$_POST['git_updater_repo']`. Always unset both in `tear_down()` to prevent cross-test contamination.

### `waiting_for_background_update($repo)` — injecting `$base` when `$repo->git` is set
The method reads `$this->base::$git_servers[$repo->git]` (line 547). `$this->base` is `null` on a bare `GitHub_API` instance because only `Plugin`, `Theme`, `Init`, and `Branch` constructors set it. To test the `$repo->git` branch from a `GitHub_API` instance, inject via `ReflectionProperty`:
```php
$rp = new ReflectionProperty( $this->api, 'base' );
$rp->setAccessible( true );
$rp->setValue( $this->api, Singleton::get_instance( 'Fragen\Git_Updater\Base', $this->api ) );
```

### `get_repo_slugs(slug, null)` — invoke on Plugin instance, not GitHub_API
When `$upgrader_object = null`, the method sets `$upgrader_object = $this`. If `$this` is `GitHub_API` (no `$config`), `get_class_vars` returns `false` and `foreach ((array) false ...)` causes a TypeError. Invoke via reflection on a `Plugin` Singleton instead so `$this->plugin_obj->config` resolves cleanly:
```php
$rm     = $this->api->get_reflection_method( $this->plugin_obj, 'get_repo_slugs' );
$result = $rm->invoke( $this->plugin_obj, 'nonexistent-slug', null );
```

### `Skip_Updates` plugin stub via `eval()` in `override_dot_org()` tests
`override_dot_org()` checks `class_exists('\Fragen\Skip_Updates\Bootstrap')`. To cover that branch in tests, create a stub with `eval()` — PHP allows `namespace` declarations inside `eval`:
```php
private function ensure_skip_updates_stub(): void {
    if ( ! class_exists( '\Fragen\Skip_Updates\Bootstrap' ) ) {
        eval( 'namespace Fragen\\Skip_Updates; class Bootstrap {}' );
    }
}
```
The class_exists guard prevents "Cannot redeclare class" errors across test runs. The `skip_updates` site option controls which slugs are matched; delete it inline after each test.

### `populate_api_data()` — tags cache holds version-string arrays, not raw API objects
`$cache['tags']` stores the output of `parse_tag_response()` — an array of version name strings like `['1.0.0', '0.9.0']`. `parse_tags()` then iterates these and builds the download-URL map. Seed tags tests with string arrays:
```php
$this->seed_cache( [ 'tags' => [ '1.0.0', '0.9.0' ] ] );
```

### `populate_api_data()` meta case — pass `$this->api->type` as `$repo`
`add_meta_repo_object()` reads `$this->type->repo_meta` from the `$repo_api` argument. The method sets `$repo->repo_meta = $value` on the `$repo` argument. For the assignment to reach `$repo_api->type->repo_meta`, pass `$this->api->type` as `$repo` so both point to the same object:
```php
$this->api->populate_api_data( $this->api->type, $this->api );
$this->assertSame( '2024-01-01T00:00:00Z', $this->api->type->last_updated );
```

### `WP_DEBUG` is `false` in the wp-env test container; annotate guarded blocks
The wp-env `wp-config.php` defines `WP_DEBUG = false`. Any code inside `if (defined('WP_DEBUG') && WP_DEBUG)` is unreachable in tests. Annotate such blocks with `// @codeCoverageIgnoreStart` / `// @codeCoverageIgnoreEnd` rather than trying to test them.

### `set_readme_info()` — `$type->sections` is an array after the merge, not stdClass
After `set_readme_info()` runs, `$this->type->sections` is the result of `array_merge((array)$sections, (array)$readme['sections'])` — a plain PHP array. Use array access (`$this->type->sections['other_notes']`), not object access (`->other_notes`), in assertions.

### `get_release_asset_redirect()` — simulate redirect by calling `set_redirect()` in `pre_http_request` mock
The `requests-requests.before_redirect` action only fires during real HTTP redirects; `pre_http_request` short-circuits before it. To test the success path (lines that cache and return `$this->redirect`), call `$api->set_redirect($url)` inside the `pre_http_request` filter callback before returning the mock response. This sets `$this->redirect` as if the redirect action had fired.

### `gu_post_api_response_body` filter — wrap cache-entry in `md5($url)` key to test line 275
`API::api()` checks `if (!empty($response[md5($url)]) && is_array($response[md5($url)]))` after applying `gu_post_api_response_body`. To exercise the unwrap path, pre-compute `$url = $this->api->get_api_url($endpoint)` then add a filter that returns `[md5($url) => $response]`. Clean up with `remove_all_filters('gu_post_api_response_body')` in `tear_down()`.

### `settings_hook()` lambda — fire `do_action('gu_add_settings', ...)` to cover the callback body
`settings_hook($git)` registers a lambda on `gu_add_settings` that calls `$git->add_settings($auth_required)`. The constructor calls `settings_hook($this)`, so the registered `$git` is the API instance. Fire `do_action('gu_add_settings', ['github_private' => false, 'github_enterprise' => false])` in a test to invoke the lambda and cover that line.

### `construct_download_link()` dev asset path — seed `release_assets` with both `assets` and `dev_assets` keys
Lines 171-174 (the `gu_dev_release_asset` filter block) require `release_assets['dev_assets']` to be non-empty and `version_compare(asset_version, dev_asset_version, '<')` to return true. Seed the cache with `['assets' => ['1.0.0' => stable_url], 'dev_assets' => ['2.0.0-beta1' => dev_url]]` and add `add_filter('gu_dev_release_asset', '__return_true')`. The `gu_dev_release_asset` filter cleanup is already in `Test_GitHub_API_DownloadLink_ReleaseAsset::tear_down()`.

### Sub-cache methods respect the main timeout — no `timeout=false`
`get_remote_api_tag()`, `get_remote_api_changes()`, `get_remote_api_readme()`, and `get_remote_api_assets()` all use `get_repo_cache($slug) ?: []` (respects timeout). When the main cache is expired these methods return `false` for their sub-key and make a fresh HTTP call. **Cache-hit tests for these methods must seed a future timeout** — tests using `seed_cache()` or `seed_main_cache()` already do this correctly. The `?: []` guard converts a `false` return (expired cache) to `[]` so that subsequent `$cache['key'] ?? false` array accesses are safe under `convertNoticesToExceptions`.

### `Rest_Update` non-object transient branches — priority-15 filter insertion pattern
`Rest_Update::update_plugin()` and `update_theme()` each register a closure at priority 15 on `site_transient_update_plugins` / `site_transient_update_themes`. Inside the closure, lines like `if (!is_object($current)) { $current = new stdClass(); ... }` guard against a false/null transient. However, `Plugin::update_site_transient()` and `Theme::update_site_transient()` are also registered at priority 15 (via `load_pre_filters()` during WordPress init) and run first — they convert `false` to `stdClass` before the source closure sees it. A priority-5 filter that returns `false` is overridden by the priority-15 Plugin/Theme filter.

To cover lines 119-120 (plugin) and 192-193 (theme): delete the site transient, then add a `fn() => false` filter **also at priority 15** AFTER WordPress has already registered Plugin/Theme's filter but BEFORE `update_plugin()`/`update_theme()` registers the source closure. Registration order within the same priority determines execution order. The sequence becomes:
1. Plugin/Theme filter (15, pos1): `false` → `stdClass`
2. Test filter (15, pos2): `stdClass` → `false`
3. Source closure (15, pos3): `false` → `!is_object` → guarded lines execute.

```php
delete_site_transient( 'update_plugins' );
add_filter( 'site_transient_update_plugins', fn() => false, 15, 1 );
// Then call update_plugin() — it registers the source closure at priority 15 pos3.
```

### `get_release_asset_redirect()` in REST context — use GitHub API URL, inject API singleton type
When `REST_API::get_api_data()` calls `get_release_asset_redirect($repo_cache['release_asset'], true)` it reads `$this->type->slug` on the shared `API` singleton to look up the cache key. A bare `stdClass()` without `slug` resolves to `md5('')` — a wrong cache key. Inject the correct slug via `ReflectionProperty`:
```php
$api_singleton  = Singleton::get_instance( 'Fragen\Git_Updater\API\API', new REST_API() );
$rp             = new ReflectionProperty( get_class( $api_singleton ), 'type' );
$rp->setAccessible( true );
$saved_type     = $rp->getValue( $api_singleton );
$type_obj       = new stdClass();
$type_obj->slug = 'test-gu-plugin';
$rp->setValue( $api_singleton, $type_obj );
// ... test ...
$rp->setValue( $api_singleton, $saved_type ); // restore in finally/tear_down
```
Also: the `release_asset` value in the seeded cache must be a URL interceptable by `pre_http_request` — use a GitHub API URL (`https://api.github.com/repos/owner/repo/releases/assets/1234`) rather than `https://example.com/...`, since the mock only intercepts `api.github.com` requests.

### `do_action('admin_enqueue_scripts')` in tests — call `set_current_screen()` first
Firing the `admin_enqueue_scripts` action without a screen context causes WP Site Health (`class-wp-site-health.php`) to throw "Attempt to read property 'id' on null". Always call `set_current_screen('plugins')` (or the appropriate screen slug) before firing this action in tests.

### `get_git_icon()` plugin-type path — call from inside a filter with 'plugin' in its name
`get_git_icon()` calls `current_filter()` to determine type: if the filter name contains 'plugin', type='plugin'; otherwise type='theme'. When called directly (no active filter), `current_filter()` returns '' → type='theme'. To test the plugin path, wrap the call inside `apply_filters('plugin_action_links_gu_test', [])` so `current_filter()` returns a string containing 'plugin'.

### `fix_misnamed_directory()` lines 566–571 — only reachable when `$new_source = ''`
`new_source` is always set to `trailingslashit(remote_source) . $slug` before `fix_misnamed_directory()` is called, so `basename($new_source)` always equals `$slug` — meaning line 562's guard catches the slug match first. The ONLY way to reach lines 566–571 is when `$new_source = ''` (Plugin_Upgrader with no hook_extra and no AJAX sets slug='', new_source=''). In that case `basename('')=''` ≠ any non-empty slug → falls through to line 566. Test via `ReflectionMethod` calling `fix_misnamed_directory` directly with an empty `$new_source` string and a non-config slug.

### `Base::$options` is a static property — restore in `tear_down()` after direct manipulation
When tests set `Base::$options = [...]` directly (e.g. to test `set_defaults()` paths), restore it in `tear_down()`: `Base::$options = get_site_option('git_updater', [])`. Otherwise subsequent tests inherit the mutated static state.

### `get_remote_repo_meta()` null-API early return — use `$repo->git = 'bitbucket'`
`get_repo_api('bitbucket', $repo)` returns null when no Bitbucket add-on is installed (the Bitbucket API class is not registered via `gu_get_repo_api` filter). This is the simplest way to exercise the `$api === null → return false` branch at line 328 without installing any add-on plugin.
