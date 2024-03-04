#### [unreleased]

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
