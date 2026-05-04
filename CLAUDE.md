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

# Start/stop wp-env Docker environment
npm run wp-env start
npm run wp-env stop

# Run a single test class or method
# Inside the wp-env tests-cli container, add --filter to the phpunit invocation:
npm run wp-env -- run tests-cli /var/www/html/wp-content/plugins/git-updater/vendor/bin/phpunit --config=/var/www/html/wp-content/plugins/git-updater/phpunit.xml --filter=Test_API
```

## Testing Environment

Tests use `@wordpress/env` (wp-env) — a Docker-based WordPress environment. The plugin is mounted inside the `tests-cli` container at `/var/www/html/wp-content/plugins/git-updater/`. The WordPress test library is pre-provisioned by wp-env at `/tmp/wordpress-tests-lib` inside the container. `tests/bootstrap.php` falls back to that path automatically when `WP_TESTS_DIR` is unset.

The `WP_TESTS_PHPUNIT_POLYFILLS_PATH` is passed explicitly in the npm scripts to point to the vendored `yoast/phpunit-polyfills`.

PHPStan is configured at level 5 (`phpstan.neon`) with pre-existing errors tracked in `phpstan-baseline.neon`. The baseline should be regenerated with `composer phpstan-baseline` when intentional changes alter the error set.

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
