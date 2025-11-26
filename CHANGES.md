#### [unreleased]

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
