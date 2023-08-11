#### [unreleased]

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

#### 11.1.10 / 2022-11-05
* fix Appsero cron task from running more than scheduled

#### 11.1.9 / 2022-10-30
* remove all `Ignore` items

#### 11.1.8 / 2022-10-24
* remove premium add-ons from `Add-Ons`

#### 11.1.7 / 2022-10-24
* load `wp-admin/includes/file.php` for when move_dir() in core, avoid redeclaration error
* allow updating of Git Remote Updater
* now using `str_contains`

#### 11.1.6 / 2022-10-05
* update Appsero SDK options

#### 11.1.5 / 2022-10-05
* pass correct file path to Appsero SDK

#### 11.1.4 / 2022-10-04 (hotfix)
* initialize Appsero SDK from `plugins_loaded` hook

#### 11.1.3 / 2022-10-04
* replace Freemius SDK with Appsero SDK

#### 11.1.2 / 2022-09-27
* deprecate `gu_maybe_auto_update` cron event

#### 11.1.1 / 2022-09-03
* skip `rename_on_activation` when updating from webhook

#### 11.1.0 / 2022-09-02
* add `str_contains`, `str_starts_with`, `str_ends_with` polyfills
* decrease WP requirement back to 5.2
* update `Shim` for improved function
* load `Shim` in autoloader

#### 11.0.6 / 2022-08-31
* fix `rename_on_activation` with `move_dir`

#### 11.0.5 / 2022-08-29
* now requires WP 5.9 for readme parser `str_contains()` polyfill

#### 11.0.4 / 2022-08-27
* composer update for class parser updates
* back to `sanitize_url`
* update `class Shim`
* update Freemius/wordpress-sdk
* update Slack invite link, need to update for every 100 uses

#### 11.0.3 / 2022-05-29
* call `wp_cache_flush()` after 'Refresh Cache'

#### 11.0.2 / 2022-05-16
* update `wp-dismiss-notice` with transient and only poll `wp_remote_get()` weekly

#### 11.0.1 / 2022-05-10
* account for `WP_Error` as parameter
* fix cleanup after update
* fix PHP Warning

#### 11.0.0 / 2022-04-24
* add plugin version to Settings page
* requires PHP 7.2+

#### 10.10.0 / 2022-04-23
* add most of `move_dir()` and `is_virtualbox()`
* update above to work with Rollback
* put `move_dir()` and `is_virtualbox()` in `class Shim` for simpler usage from core functions
* make a new directory in `wp-content/upgrade/` for download slug fixing in `Base::upgrader_source_selection` to make better use of `move_dir()`
* delete new upgrade directory
* update API error caching, default 60 minute timeout

#### 10.9.0 / 2022-04-04
* revert usage of `move_dir()` and `is_virtualbox()` -- for now

#### 10.8.0 / 2022-04-03
* use `move_dir()` and `is_virtualbox()` from [#51875](https://core.trac.wordpress.org/ticket/51857) [PR #2225](https://github.com/WordPress/wordpress-develop/pull/2225/files)
* revert fix directory rename for single file plugin update

#### 10.7.2 / 2022-03-31
* fix directory rename for single file plugin update
* revert GitHub_API release asset URL to return to redirect URL

#### 10.7.1 / 2022-03-12
* save GitHub release asset data in `parse_release_asset()`
* clean up `get_release_asset_redirect()`
* add geopattern-icon default plugin icon
* use direct GitHub release asset URL, not redirect URL
* add filter `gu_plugin_assets_dir` to specify location of repository banners/icons
* expanded support banners: .jpg, .png, RTL
* normalize `Tested up to` data for point releases, similar to dot org
* remove `noopener` from `target=_blank` links

#### 10.7.0 / 2022-03-06
* fix long standing object cache conflict with refresh cache and missing GitHub subtab by always showing GitHub subtab
* return `get_remote_repo_meta()` data when called from Git Updater PRO REST API
* add `default` icon data to `Plugin` object
* add additional release asset data to repo cache

#### 10.6.15 / 2022-03-02
* show overridden plugins/themes by [Skip Updates](https://wordpress.org/plugins/skip-updates/) plugin in Git Updater Settings tab
* use `sanitize_key()` for nonces
* update Freemius/wordpress-sdk

#### 10.6.14 / 2022-02-05
* allow hooks to run if no settings to be saved in `Settings::update_settings()`
* composer update

#### 10.6.13 / 2022-02-01
* update nonce conditionals, require variables be set

#### 10.6.12 / 2022-01-18
* composer update for `wp-dependency-installer` fixes

#### 10.6.11 /2022-01-14
* remove nonce verification from `Basic_Auth_Loader`, ignore the known

#### 10.6.10 / 2022-01-09
* really try to fix WPCS, it's a combination of ignoring the known and verifying the unknown

#### 10.6.9 / 2021-12-19
* composer update, fixes [#975](https://github.com/afragen/git-updater/issues/975)

#### 10.6.8 / 2021-12-17
* initialize variable in `Basic_Auth_Loader::unset_release_asset_auth()`
* composer update

#### 10.6.7 / 2021-12-07
* update `Basic_Auth_Loader::unset_release_asset_auth()` to account for new location of some GitHub release assets

#### 10.6.6 / 2021-10-27
* update regex for finding content directory, fixes [#971](https://github.com/afragen/git-updater/issues/971)

#### 10.6.5 / 2021-10-21
* sanitize array key with `sanitize_title_with_dashes()` not `sanitize_file_name()`, for underscores in key. Other devs may hook into `sanitize_file_name` filter and not unhook -- causing problems.

#### 10.6.4 / 2021-09-24
* composer update, cause of course I needed to fix something

#### 10.6.3 / 2021-09-24
* somewhere along the way the filepath to assets no longer works for display, now requires a URL
* composer update

#### 10.6.2 / 2021-09-24
* oops, variable is static

#### 10.6.1 / 2021-09-24 **Hotfix**
* don't load `pluggable.php` too early, call `wp_create_nonce()` in `plugins_loaded` hook

#### 10.6.0 / 2021-09-23
* loads of security updates, nonce all the things

#### 10.5.2 / 2021-09-05
* skip Git Updater PRO features of `Base::upgrader_source_selection()` if updating Git Updater PRO, needed for new rollback update failure feature

#### 10.5.1 / 2021-09-04
* set default value for Skip Updates option to empty array if nothing present
* add file_exists check to  `get_repo_requirements()`
* set `local_path` correctly

#### 10.5.0 / 2021-08-29
* only use `esc_attr_e` for translating strings
* ksort additions into plugin/theme array
* use `gu_config_pre_process` filter in `update_site_transient()`
* remove vanity star ratings
* speed up `get_dot_org_data()` by using API 1.2 and `wp_remote_head()`

#### 10.4.2 / 2021-07-21
* always get current release asset redirect URL on REST update
* directly call `get_remote_repo_meta()` and load `site_transient` hooks in WP-CLI for plugin/theme updating via WP-CLI
* use git branch as displayed branch for plugins/themes installed under git VCS

#### 10.4.1 / 2021-07-11
* added `class Shim` for PHP 5.6 compatibility, will remove when WP core changes minimum requirement

#### 10.4.0 / 2021-07-04 🎆
* add new WP-Cron task to run concurrently to `wp_version_check` so that Git Updater managed plugins and themes can take advantage of auto updating 🤞
* added better check to see if background updating cron event is already scheduled
* add `Ignore()` of certain premium add-ons so not needed in the individual plugins

#### 10.3.4 / 2021-06-22
* refactor `get_repo_requirements()`
* update Slack info
* improve `plugins_api()` defaults

#### 10.3.3 / 2021-06-15
* add some defaults into `plugins_api()`
* remove Freemius from autoloader
* more error checking

#### 10.3.2 / 2021-06-14
* fix `set_no_api_check_readme_changes()` conditional

#### 10.3.1 / 2021-06-14
* update `update_site_transient()` if repo skips API checks

#### 10.3.0 / 2021-06-14
* add `class Ignore` to make it simpler to remove a repository from Git Updater functions

#### 10.2.2 / 2021-06-04
* fix duplicate pre-process filter

#### 10.2.1 / 2021-06-03
* fix _View details_ for repos not checking API

#### 10.2.0 / 2021-06-02
* add filter to pre-process configuration array of repositories
* add filter to modify repos on waiting for background tasks

#### 10.1.0 / 2021-05-27
* cache GitHub API response failures for rate limit timeout to avoid hammering the API
* add constant `GU_MU_LOADER` to aid in mu-plugin loading of Git Updater PRO
* catch API errors when GitHub personal access token is set, fixes [#947](https://github.com/afragen/git-updater/issues/947)
* improved error messaging
* oops, forgot to load `GU_Trait` for renaming from `develop` branch installation

#### 10.0.2 / 2021-05-18
* fix to use `intval()` as `abs()` more type specific in PHP8, fixes [#952](https://github.com/afragen/git-updater/issues/952)
* fix to display **GitLab** subtab when only using GitLab CE, fixes [#949](https://github.com/afragen/git-updater/issues/949) thanks @AMCodeHub and @kmitch-duke-edu

#### 10.0.1 / 2021-05-18
* update error log message branding
* ensure custom icon shows in update notice from Freemius

#### 10.0.0 / 2021-05-17
##### Requires PHP 7.0+
* added default values in API constructors for future proofing
* correctly apply `Primary Branch` with rollback to tag
* removed Git APIs and placed in plugins
* move `Branch` to Git Updater PRO
* restructure for `API\API.php` and `REST\REST_API`, `REST\Rest_Update`, and `REST\Rest_Upgrader_Skin`
* move REST, WP-CLI, and `Remote Management` to  Git Updater PRO
* remove deprecated elements of `Remote_Management`
* remove `Settings::set_auth_required()`, now set in API plugins
* update `Settings::unset_stale_options()`
* added filters to added data from API plugins
  * added filter for setting API URL data
  * added filter for setting API remote install data
  * added filters for setting API language pack data
  * added filter to get API object
  * added filters for Basic Auth settings
  * added filter `gu_parse_release_asset`
  * added filter `gu_parse_headers_enterprise_api`
  * add filter `gu_post_api_response_body`
  * add filter `gu_get_git_icon_data`, this change requires PHP 7.0+ for `dirname( __DIR__, 2 )`
  * add filter `gu_parse_enterprise_headers`
  * add filter `gu_fix_repo_slug`
  * add filter `gu_parse_api_branches`
  * add filter `gu_running_git_servers`
* remove deprecated override dot org constant
* added setting to display `_deprecated_hook()` data in debug.log
* skip `_deprecated_hook()` `trigger_error()` in development environment
* zero value of repo cache release asset `$url` if `wp_remote_get( $url )` not HTTP code 200 when checking release asset redirect
* add **Add-Ons** tab for installing API plugins
* add Freemius integration for analytics
* update assets

#### 9.9.10 / 2021-02-18
* fix change to `redirect_on_save()`
* fix issue when more than 100 branches are present and primary branch in plugin/theme is changed and not in branches array, thanks @bph

#### 9.9.9 / 2021-02-17
* update for WP 5.7 CSS changes
* update for setting branch on rollback
* add `shields.io` download stats to README
* add compatibility with [Skip Updates](https://wordpress.org/plugins/skip-updates)

#### 9.9.8 / 2021-02-01
* fix odd return from Gitea API branch request
* update for new URL to GitHub release asset redirect, fixes [#929](https://github.com/afragen/github-updater/issues/929)

#### 9.9.7 / 2021-01-11
* fix PHP8 error in `set_branch_on_switch()`, [#925](https://github.com/afragen/github-updater/issues/925)

#### 9.9.6 / 2021-01-08
* this fix for odd log errors, hopefully, doesn't create new errors

#### 9.9.5 / 2021-01-07
* fix odd error I see in the logs
* use GitHub Actions for CI
* fix some docBlock settings
* don't set branch on rollback to tag, fixes [#921](https://github.com/afragen/github-updater/issues/921)
* temp fix to composer resource while waiting for upstream fix [#922](https://github.com/afragen/github-updater/issues/922)
* update some composer resources

#### 9.9.4 / 2020-11-21
* update to latest `class-parser.php` and `Readme_Parser` cleanup
* extra testing to remove `@` ( silencing )
* update `ghu-loader.php`
* add API error to `debug.log` [#911](https://github.com/afragen/github-updater/issues/911)
* added `Gist_API` and `Language_Pack_API` to `Basic_Auth_Loader`, oops

#### 9.9.3 / 2020-11-04
* update `class-parser.php`, now allows for sending text blob as input, thanks @dd32
* no longer need to use data URLs as potential security risk [#909](https://github.com/afragen/github-updater/issues/909)

#### 9.9.2 / 2020-11-03
* add filter to modify release asset rollback, 🖕 Gutenberg
* fixed logic in `github_updater_no_release_asset_branches`

#### 9.9.1 / 2020-11-03
* use data URL in `Readme_Parser` instead of creating/deleting temp file
* add filter `github_updater_no_release_asset_branches` to remove all branches from the branch switcher for release assets leaving only the tags

#### 9.9.0 / 2020-10-05
* refactor of branch switch row by @pbiron, looks fabulous!!
* test for existence of `$token->newest_tag` in `REST_API` or error may result
* update to allow for multiple release assets but then only use release asset named per schema, `$repo-$tag.zip`
* make branch switch message for 'no tags' rollback message as list item

#### 9.8.1 / 2020-08-06
* update `Themes` to populate `$transient->no_update` for Auto-updates link

#### 9.8.0 / 2020-08-01
* `permission_callback` arg to `register_rest_route()` as this is now [required](https://core.trac.wordpress.org/changeset/48526)
* fix error in `move()` if directory doesn't exist
* revert to `$wp_filesystem->move()` when not FS_METHOD === 'direct'
* add `primary_branch` and `tag` to REST API response for repo data
* add a few additional items to the update packages

#### 9.7.1 / 2020-07-20
* correctly set Bypass WP-Cron Background Processing checkbox if filter set elsewhere

#### 9.7.0 / 2020-07-09
* use _dynamic_ constant for GitHub Updater plugin directory based on namespace
* update to use `Languages` header as base for language pack packages, this should allow for self-hosted git servers
* add header `Primary Branch` for those devs looking to replace `master`
* fix PHP error when installing Gist by setting default branch to `master`
* automatically add git host icons to plugin/theme row meta
* update composer dependencies

#### 9.6.1 / 2020-06-11
* exit early from `Gist_API::construct_download_link()` if meta not present
* fix saved value when `Bitbucket_Server_API` tag response is empty
* fix issue if Bitbucket API branch response is malformed, fixes [#875](https://github.com/afragen/github-updater/issues/875)
* fix PHP warning in `GHU_Trait::is_duplicate_wp_cron_event` when no cron events present

#### 9.6.0 / 2020-06-01
* add WP-CLI branch switching
* keep _Activate Plugin_ link on remote install
* add `class Gist_API` to install/update GitHub Gists, themes will use hash as slug
* add filter `github_updater_number_rollbacks` to set the number of tagged releases (rollbacks) available in branch switching

#### 9.5.2 / 2020-05-09
* no need for using release asset with GHU

#### 9.5.1 / 2020-05-09
* test `Readme_Parser::__construct()` `file_put_contents()` with additional test for success, hopefully squashes [#704](https://github.com/afragen/github-updater/issues/704) once and for all, actual fix is to set constant `WP_TEMP_DIR` as appropriate
* prevent error if no credentials are set
* un-screwup Bitbucket Server, sorry @allrite, fixes [#872](https://github.com/afragen/github-updater/issues/872)

#### 9.5.0 / 2020-04-17
* allow for repos using release assets to have branch switcher
* switching away from `master` or tag will use that branch for updating, not the release asset
* update to JS to work with IE11, thanks @sharevb, arrow functions not supported in IE11
* removed filter `github_updater_hide_branch_switcher` in favor of better branch switching
* direct injection of authentication headers into `wp_remote_get()`
* filter added for adding authentication headers for downloads packages
* don't try to check the `is_private` status for GitHub release assets. All are stored on AWS anyway and occasionally the `is_private` status will not have been set resulting in an incorrect cached value
* limit rollback to current tag only, effectively a re-install of current tag

#### 9.4.2 / 2020-04-10 -HotFix 2-
* fixed problem with incorrectly sanitizing remote install URI fragment

#### 9.4.1 / 2020-04-04 -HotFix-
* fixed problem with sanitizing

#### 9.4.0 / 2020-04-04
* set `minimum-stability: dev` in composer.json, helps with dependency loading for `dev-master`
* fix potential PHP warning in `Basic_Auth_Loader::get_slug_for_credentials()` when installer, like TGMPA, passes as array and not string
* define `$error_code[{git}]['git']` for certain errors to avoid PHP undefined index warning
* update calls for Bitbucket Server REST API v7, thanks @Idealien
* explicitly ignore themes without a root `style.css` file to avoid PHP warnings, thanks @cliffordp
* move `Settings` action link to front
* add Bitbucket pseudo-token, `username:password` for some private repos
* Bitbucket credentials will automatically be converted to pseudo-tokens
* update WP-CLI integration for Bitbucket pseudo-token
* add plugins without updates to `$transient->no_update` to add _View details_ link, thanks @robincornett
* no longer need to test if private repo when sending auth headers, auth headers are always sent
* lots of escaping/sanitization/phpcs ignoring
* added filter `github_updater_hide_branch_switcher` to hide branch switcher
* added dependency check for composer's autoloader

#### 9.3.2 / 2020-02-19
* fixed some PHP warnings and 401 errors when access tokens not set in `Basic_Auth_Loader`
* allow URL to a git host API to add header during installation
* removed saving and use of Enterprise Access Tokens, must use individual repo tokens
* use `PRIVATE-TOKEN: <token>` header for authentication in GitLab < v12.2

#### 9.3.1 / 2020-02-09
* try to ensure authentication headers aren't injected where they shouldn't be, bad Andy 🤦‍♂️

#### 9.3.0 / 2020-02-06
* remove GitHub deprecation notice
* transition from GitHub access token query arg to Basic Authentication
* fixed theme update View details display [#849](https://github.com/afragen/github-updater/issues/849)
* more fixes PHP 7.4 warnings
* refactor from using access token endpoints to Basic Authentication headers

#### 9.2.4 / 2020-02-04
* add notice re: GitHub deprecation notice 🤬

#### 9.2.3 / 2020-01-31
* fixes for PHP 7.4 warnings

#### 9.2.2 / 2020-01-29
* fix WP-CLI issue needing to explicitly have class loaded to get class name for `add_command()`, thanks @chesio
* bunch of WPCS fixes and miles to go...

#### 9.2.1 / 2020-01-28
* add `Bypass WP-Cron Background Processing` setting

#### 9.2.0 / 2020-01-21
* fix PHP warning [#823](https://github.com/afragen/github-updater/issues/823), thanks @pbiron
* remove scheduled cron events on deactivation
* added function to rename or recursively copy from `$source` to `$destination` and remove files/directories after copying. Should be more versatile than `$wp_filesystem->move()`. Fixes [#826](https://github.com/afragen/github-updater/issues/826)
* no longer any need to manipulate release assets in `upgrader_source_selection`
* test for correct REST API key for `repos` endpoint
* add local version to `repos` REST endpoint, thanks @Raruto
* remove `repos` and `update` REST endpoints from index, thanks @Raruto

#### 9.1.0 / 2019-12-16
* run API calls for everyone with wp-cron, not just privileged users, hopefully this allows for better integration with remote management services
* don't run API calls for non-privileged users when bypassing wp-cron
* only show Settings for privileged users

#### 9.0.1 / 2019-12-04
* fix PHP version check, fixes [#824](https://github.com/afragen/github-updater/issues/824)

#### 9.0.0 / 2019-11-19
* refactor to remove class extends
* update renaming functions
* refactor to how plugin and theme meta are obtained, now using `get_file_data()`
* remove reliance on `extra_{$context}_filter` to add extra headers
* update for new GitHub Updater Additions
* update `sanitize()` to use `sanitize_text_field()` if variable is a MIME type
* improve branch setting for `Rest_Update`
* added `class REST_API` to define and use the REST API instead of `admin-ajax.php`
* updated downloadable JSON config file for Git Bulk Updater
* support WP core `Requires at least` header in favor of `Requires WP` header

#### 8.9.0 / 2019-09-30
* update all instances of `WP_Upgrader_Skin` to include new spread operator, https://core.trac.wordpress.org/changeset/46125
* update URI parsing to allow for `.` in repository name while still removing `.git`. Thanks @ymauray for the nudge
* make downloadable JSON config files to work with [Git Bulk Updater](https://github.com/afragen/git-bulk-updater)
* fix multisite saving of Remote Management settings

#### 8.8.2 / 2019-07-02
* added check for `Basic_Auth_Loader::get_credentials()` to match `$slug` and `$git`, fixes edge case [#796](https://github.com/afragen/github-updater/issues/796)
* refactored `Basic_Auth_Loader::get_credentials()` to split out `Basic_Auth_Loader::get_slug_for_credentials()` and `Basic_Auth_Loader::get_type_for_credentials()`
* created more precise adding and removing `Basic_Auth_Loader` hooks
* fixed `Bitbucket_API` return when no tags found

#### 8.8.1 / 2019-06-11
* set `homepage` to `PluginURI` or `ThemeURI`, fixes [#791](https://github.com/afragen/github-updater/issues/791)
* fixed Bitbucket release asset updates for proper containing folder structure, thanks @benoitchantre for the bug report

#### 8.8.0 / 2019-05-15
* switched from `pre_set_site_transient_update_{plugins|themes}` to `site_transient_update_{plugins|themes}`
* update `Remote_Management` to work with filter change
* update `CLI_Integration` to work with filter change
* use `GITHUB_UPDATER_DIR` constant for all enqueuing

#### 8.7.3 / 2019-04-08
* fixed PHP notices on Install [#775](https://github.com/afragen/github-updater/issues/775)
* updated location of `tmp-readme.txt` file to use `get_temp_dir()`, thanks @DavidAnderson684
* a11y updates for `label for=...`
* fixed to only set cron event for main site only when `DISABLE_WP_CRON` is set, fixes [#782](https://github.com/afragen/github-updater/issues/782)
* a11y updates for settings tabs
* remove filter for `http_request_args` after use, fixes [#783](https://github.com/afragen/github-updater/issues/783)

#### 8.7.2 / 2019-03-09
* hotfix to add parity for themes and prevent PHP warning

#### 8.7.1 / 2019-03-09
* add new filter hook `github_updater_post_construct_download_link` to allow for returning your own download link
* deprecate filter hook `github_updater_set_rollback_package` as the above replaces it
* add _looser_ check of `Base::get_repo_slugs()`, thanks @sc0ttkclark
* update `class Bitbucket_Server_API`, thanks @allrite for the access
* added filter hook `github_updater_repo_cache_timeout` to change default timeout per repository, thanks @sc0ttkclark

#### 8.7.0 / 2019-02-24
* update `Readme_Parser` for changelog and description parsing
* add filter `github_updater_temp_readme_filepath` to change default location if server has permissions issues, fixes [#766](https://github.com/afragen/github-updater/issues/766)
* fix `Readme_Parser` to use `version_compare()` when checking compatibility with `create_contributors()`
* add commit hash and timestamp to branch data, timestamp not returned by this particular GitHub API call 😞
* add filter `github_updater_remote_is_newer` to use your own version comparison function

#### 8.6.3 / 2019-02-04
* use Update PHP messaging as in WP 5.1 in version check

#### 8.6.2 / 2019-01-14
* fix for bug with Bitbucket endpoints, fixes [#757](https://github.com/afragen/github-updater/issues/757)

#### 8.6.1 / 2019-01-11
* remove `tmp-readme.txt` after parsing, fixes [#754](https://github.com/afragen/github-updater/issues/754)
* directly call `wp_cron()` after refreshing cache
* update POT via `composer.json` and wp-cli
* moved `get_file_headers()` to `trait GHU_Trait`
* cleanup extra header key/value pairs
* add endpoint to Bitbucket to get more than default number of tags, branches, or release assets. Fixes [#752](https://github.com/afragen/github-updater/issues/752) thanks @idpaterson

#### 8.6.0 / 2018-12-28 🎂
* add action hook `github_updater_post_rest_process_request` for @Raruto
* add filter hook `github_updater_set_rollback_package` for @sc0ttclark and @moderntribe
* return null for `API_Common::parse_release_asset()` when invalid `$response`, fixes [#750](https://github.com/afragen/github-updater/issues/750)
* make GitHub private repos with release assets use redirect for download link, fixes [#751](https://github.com/afragen/github-updater/issues/751)

#### 8.5.2 / 2018-12-10
* fixed parsing of wp.org readme changelog items

#### 8.5.1 / 2018-11-30
* refactor release asset API calls to `trait API_Common`
* updated GitLab API v4 endpoints, thanks for all the notice GitLab 😩

#### 8.5.0 / 2018-11-26
* silence rename PHP warning during plugin update
* specify branch for changelog
* refactored dot org override, constant deprecated in favor of new filter `github_updater_override_dot_org`
* now using vanilla JS for Install settings
* refactored GitHub release asset code to get direct download link
* refactored Bitbucket release asset code to get redirected download link for AWS
* refactored GitLab release asset code to get redirected download link
* exit early if checking _View details_ but not done with background update, avoids PHP notices
* updated to add/use composer dependencies and autoloader

#### 8.4.2 / 2018-11-01
* updated password fields to not autoload saved passwords, thanks @figureone
* fixed error when saving Remote Management options

#### 8.4.1 / 2018-10-24
* updated PAnD library with `forever` fix, this was my fault 💩

#### 8.4.0 / 2018-10-23
* use new constant for assets
* update error checking for `WP_Error` response from `wp_remote_get()`
* updated to use Bitbucket API 2.0 where appropriate
* refactor API calls with new `trait API_Common`
* attempted to update `class Bitbucket_Server_API`, please let me know if I made 💩
* refactor release asset and AWS download link code
* use action hook `requests-requests.before_redirect` to get AWS redirect URL
* fix for [creating proper GitHub Enterprise base URL](https://github.com/afragen/github-updater/pull/721), oops. Thanks @rlindner
* fixed [#714](https://github.com/afragen/github-updater/issues/714), get correct Bitbucket release asset download link from AWS
* update to `class-parser.php` r7679
* don't run on heartbeat API 💗
* only run on `admin-ajax.php` when possibly attempting sequential shiny updates, fixes [#723](https://github.com/afragen/github-updater/issues/723)
* update Persist Admin notices Dismissal library

#### 8.3.1 / 2018-09-13
* created `class Bootstrap` to setup plugin loading
* fixed issue with `load_plugin_textdomain()` not loading completely (now loading in `init` hook), thanks @pnoeric and @garrett-eclipse

#### 8.3.0 / 2018-09-12
* test to ensure `file_put_contents()` works
* overwrite `tmp-readme.txt` instead of delete
* delete `tmp-readme.txt` on uninstall
* switched check for user privileges to `update_{plugins|themes}` and `install_{plugins|themes}`
* refactored addition of Install tabs for specific privileges
* switch `repo -> slug` and `slug -> file` in plugin/theme objects for more consistency with WP core
* add `override` query arg for RESTful updates to specific tags
* refactor to remove redundancy between rollback and branch switch
* fixed incorrect update notification after update, fixes [#698](https://github.com/afragen/github-updater/issues/698)
* fixed to only load `Settings` on appropriate pages, fixes [#711](https://github.com/afragen/github-updater/issues/711)
* fixed issue where saving options during background updating could cause some checkbox options to be cleared, [5d68ea5](https://github.com/afragen/github-updater/commit/5d68ea54385a2fe62093e25ef42672bbfd504f89)
* updated error handling of Singleton factory
* added remote install from a zipfile, remote URL or local file
* added 'git' and directly declare 'type' in `class Plugin|Theme`
* started to add language pack support for Gitea
* use WPCS 1.1.0

#### 8.2.1 / 2018-07-22
* fixed setting of `Requires PHP` header in `API::set_readme_info()`

#### 8.2.0 / 2018-07-15
* fixed `register_activation_hook` to add the `develop` branch if that is the source
* refactored `class Readme_Parser` to use unmodified `vendor/class-parser.php`
* add `Requires PHP` info to _More Detail_ window

#### 8.1.2 / 2018-06-28
* fixed malformed link tag, thanks @alexclassroom
* updated POT

#### 8.1.1 / 2018-06-27
* updated GitLab CE/Enterprise to use GitLab API v4
* urlencode part of request to dot org API to avoid redirect

#### 8.1.0 / 2018-06-26
* added `register_activation_hook` to correctly rename directory to `github-updater` on activation; activation will fail if rename successful.

#### 8.0.0 / 2018-06-20
##### This update requires PHP 5.6 or greater
* added multiple action/filter hooks for adding data to Settings
* refactored `Settings` to add data via hooks
* refactored `class Basic_Auth_Loader` to `trait Basic_Auth_Loader`
* added `trait GHU_Trait` wih common code
* moved traits to own sub-directory
* removed old extended naming code
* refactored Remote Management to new `class Remote_Management`
* converted short array syntax
* removed callback passing of object by reference, it seems of dubious value
* use `ReflectionObject` in `GHU_Trait::get_class_vars()` to pass arbitrary class properties
* refactored WP-CLI integrations
* removed `class Additions`, now self-contained in [GitHub Updater Additions](https://github.com/afragen/github-updater-additions)
* refactored `Install::install()` a bit more
* use new `github_updater_admin_pages` filter hook for adding `index.php` from Remote Management
* ensure that all API install fields are available for all installed APIs
* updated `class-parser.php` the dot org readme parser
* updated POT with more translator messages
* fixed to only load install JS in admin pages
* updated `GitLab_API` for API v4

#### 7.6.2 / 2018-04-27
* move `auth_required` stuff from `Base` to `Settings`
* prevent admin notice from showing when no GitLab.com repo exists
* remove caching of `get_plugins()` and `wp_get_themes()` as it seems to result in issues for some users

#### 7.6.1 / 2018-04-11
* check `file_exists()` in `Base::set_installed_apis()` to avoid issue if class not yet loaded prior to checking Settings, fixes [#662](https://github.com/afragen/github-updater/issues/662) and [#667](https://github.com/afragen/github-updater/issues/667)

#### 7.6.0 / 2018-04-08
* added "safety orange" warning dashicon when waiting for WP-Cron to finish
* changed all password fields to use `type="password"`
* refactored setting of contributor data for [r42631](https://core.trac.wordpress.org/changeset/42631)
* moved GitLab specific admin notices to `GitLab_API`
* pass `$this` in `Singleton::get_instance()` instead of using `debug_backtrace()`
* refactor `Singleton` to automatically find namespaced class
* added some error handling to `Singleton`
* fixed error messaging
* added support for [Gitea](http://gitea.io/) thanks to [Marco Betschart](https://github.com/marbetschar)
* refactored code out of `class API` into specific API classes
* simplify RESTful update code, no longer parses webhook payload just webhook itself
* updated RESTful update code to use `site_transient_{$transient}` filter to add to update transient
* added error logging to RESTful update code as sometimes GitLab.com seems to timeout the response, thanks @Raruto

#### 7.5.0 / 2018-01-28
* fixed _View detail_ ratings for large projects with lots of issues
* fixed `API::set_readme_info()` to see passed parameter as readme data
* added title attribute to icons on Settings subtabs, thanks @petemolinero
* created new `class Init` to help unclutter `class Base`
* fixed PHP Warning if saving empty Remote Management Settings
* changed some variable and function names to be more descriptive
* moved Singleton Factory out of namespace
* moved capabilities check into `class Init`
* moved API classes to subdirectory
* moved WP-CLI classes to subdirectory
* refactored autoloader to grab all subdirectories
* fixed for new WP.org Plugin API response
* updated `vendor/class-parser.php` and `vendor/persist-admin-notices-dismissal`
* fixed `composer.json` for new license format

#### 7.4.4 / 2017-11-29
* fixed bug in remote install where Bitbucket credentials weren't transferred to Basic_Auth_Loader, [#630](https://github.com/afragen/github-updater/issues/630)

#### 7.4.3 / 2017-11-07
* set all extra header values in `Base::parse_extra_headers()`
* added more error messaging for `class WP_Error`
* fixed some issues with GitHub Release Assets

#### 7.4.2 / 2017-10-25
* added check to see if wp-cron is updating and if not send and error message
* fix for WP-CLI updating for private Bitbucket repos, thanks @v8-ict

#### 7.4.1 / 2017-10-22
* oops, during refactor of `Install` I copied the incorrect query for GitHub's remote install

#### 7.4.0 / 2017-10-21
* use wp-cron for background processing of `wp_remote_get()` calls for getting repo data 🚀
* fixed [#603](https://github.com/afragen/github-updater/issues/603) by not creating generic global variables accidentally
* fixed issue with remote install of private Bitbucket repos
* added plugin icons to `update-core.php` page for WP 4.9
* fixed stale AWS download link for GitHub release asset
* cache `get_plugins()` and `wp_get_themes()` for short period giving better performance to some admin pages, fixes [#612](https://github.com/afragen/github-updater/issues/612)
* refactor of methods from `class Base` to `class API`
* created `class API_PseudoTrait` to share methods of `class API`, workaround for OOP traits
* fixed removal of stale options

#### 7.3.1 / 2017-09-20
* removed parent constructor from `Branch`, thanks @fwolfst

#### 7.3.0 / 2017-09-15
* removed non-constructor stuff from all constructors
* added `parent::__construct()` to extended classes where needed
* fixed [#568](https://github.com/afragen/github-updater/issues/586), thanks @bradmkjr
* fixed multisite bug for theme update rows that I introduced in v7.0.0 :-(
* fixed PHP notice [#591](https://github.com/afragen/github-updater/issues/591)
* fixed bug with current branch data being deleted when saving settings with refactor of `Settings::filter_options()`
* fixed issues with _up to date_ notice during branch switch [#598](https://github.com/afragen/github-updater/issues/598)

#### 7.2.0 / 2017-08-30
* added a static proxy class to use for creating Singletons
* fixed Override Dot Org for themes
* fixed PHP Notice [#584](https://github.com/afragen/github-updater/issues/584)
* fixed bug introduced in readme.txt parsing [#589](https://github.com/afragen/github-updater/issues/589)
* fixed bug introduced in v7.0.0 with linter updates to properly display multisite theme updates in themes.php
* fixed branch setting bug [#592](https://github.com/afragen/github-updater/issues/592) by moving trigger from filter hook to direct call, thanks @rob and @idpaterson

#### 7.1.0 / 2017-08-10
* always show _Install_ button for single site theme when branch switch is active [#567](https://github.com/afragen/github-updater/issues/567)
* fixed override of dot org to correctly ignore dot org updates [#581](https://github.com/afragen/github-updater/issues/581)
* no more extended naming
* added constant for overriding dot org updates when plugins have identical slugs, `GITHUB_UPDATER_OVERRIDE_DOT_ORG` replacing the `GITHUB_UPDATER_EXTENDED_NAMING` constant
* added Overriding Dot Org functions for both plugins and themes

#### 7.0.0 / 2017-08-01
* added support for GitLab Groups [#556](https://github.com/afragen/github-updater/issues/556), thanks @rolandsaven
* refactored Settings and Install to place API Settings data in individual API classes
* refactored Settings to make smaller methods
* simplified `composer.json`, removed autoload section and no need to require `composer/installer`
* many PHP Inspections fixes
* fixed `class Rest_Update` for PHP 5.3 compatibility, thanks @epicfaace
* created `class Branch` to automatically set correct branch during branch switch or install. No more need for Branch header. This is a breaking change as `master` will become the default branch for all repositories. You will need to use _Branch Switch_ to reinstall the current branch for it to be correctly set.

#### 6.3.5 / 2017-06-29
* hotfix to `composer.json` to remove classmap and files, I think I messed something up.

#### 6.3.4 / 2017-05-28
* fixed [#547](https://github.com/afragen/github-updater/issues/547) for RESTful updating after breaking it again
* fixed PHP errors [#550](https://github.com/afragen/github-updater/issues/550)

#### 6.3.3 / 2017-05-16
* definitive fix for [#549](https://github.com/afragen/github-updater/issues/549)
* update to `class-parser.php@5483`

#### 6.3.2 / 2017-05-09
* added _broken_ setting to repo not returning HTTP 200 for the main file
* ~~fixed PHP error [#549](https://github.com/afragen/github-updater/issues/549)~~
* added div class to Settings page to create more specific CSS selectors

#### 6.3.1 / 2017-05-01
* simplify uninstall.php
* ensure Basic Auth headers are loaded for RESTful updating [#547](https://github.com/afragen/github-updater/issues/547)

#### 6.3.0 / 2017-04-26
* fixed to not run `load_pre_filters()` during WP-CLI, fixes [#528](https://github.com/afragen/github-updater/issues/528) thanks @egifford
* hopefully fixed annoying, intermittent PHP notices empty `parse_header_uri()` output
* added a singleton to `class Settings` to avoid duplicate loads [#531](https://github.com/afragen/github-updater/issues/531)
* refactored subtabs for Settings page
* refactored parsing of extra headers, `Enterprise` and `CE` headers no longer needed
* added support for Bitbucket Server!! Thanks @lkistenkas for access and especially to @BjornW for kicking it off
* refactored `add_endpoints()` to use everywhere
* now requires WordPress 4.4 and above
* update to latest wp.org `class-parser.php`
* move enqueuing of plugin CSS to `Base::init()`
* refactored Language Pack updating to their own classes
* split out abstract methods from `abstract class API` to `interface API_Interface`
* make Autoloader better functioning as a drop-in
* switched logic for plugin branch switching and setting the update transient
* refactor `add_access_token_endpoint()` to `class API`
* refactor Basic Authentication headers to `class Basic_Auth_Loader`
* moved checkboxes before titles in Settings
* updated wiki screenshots
* fixed to call `load_options()` in `Base::init()` to properly utilize options
* add red (#f00) warning dashicon in Settings for repo with malformed header URI

#### 6.2.2 / 2017-02-09
* fixed for updating via webhook from GitHub tagged release, declare branch as `master`
* refactored Install download link generation
* fixed PHP notices [#525](https://github.com/afragen/github-updater/issues/525)
* replaced method with `mb_strrpos()` in `class-parser.php` as some users don't have this function
* fixed JSON syntax error in GitHub webhook payload
* fixed GitLab Install tab to always show access token
* fixed GitLab Settings to show individual access tokens

#### 6.2.1 / 2017-02-02
* removed `wp_cache_flush()` for Install page, not needed with `Base::admin_pages_update_transients()`
* hotfix for upgrade routine to properly flush caches :P

#### 6.2.0 / 2017-02-02
* added WP-CLI compatibility
* refactored `Base::admin_pages_update_transient()` and `API::wp_update_response()` to use `Base::make_update_transient_current()`, this fixed some PHP notices [#508](https://github.com/afragen/github-updater/issues/508)
* added banner display to plugin `View details` iframe
* change `API::get_dot_org_data` to use JSON response to avoid PHP notices
* refactored `GitHub_API::get_repo_meta()` for simplification
* moved some repo renaming to their own methods from `Base::upgrader_source_selection()` to `Base::fix_misnamed_directory()`, `Base::extended_naming()`, and `Base::fix_gitlab_release_asset_directory()`
* moved a couple `class-parser.php` mods to separate functions in `class Readme_Parser`
* refactored `GitHub_API::get_repo_meta()` to use more efficient API call, gets forks also, thanks @egifford
* introduce some variability to transient expiration per plugin
* switch to storing repo data in options table instead of using transients, this should help with object caching which doesn't like transients
* fixed branch switching with extended naming [#520](https://github.com/afragen/github-updater/issues/520), thanks @joelworsham
* updated continuous integration via RESTful endpoints to also update based upon a new tag/release of the repo

#### 6.1.1 / 2016-11-29
* hotfix to flush cache during upgrade routine

#### 6.1.0 / 2016-11-28
* improved transient saving to save optimized version of transient rather that whole API response
* changed _Refresh Cache_ to POST to only run once.
* fixed `API::wp_update_response` to properly reset the update transient after a shiny update or cache flush
* added `Base::admin_pages_update_transient` to properly reset the update transient on plugins.php and themes.php pages
* fixed Bitbucket authentication during AJAX update
* changed to use dashicon to identify private repos in Settings
* fixed transient update when doing shiny updates
* added ability to update from GitHub release asset
* added our own PHP version check
* refactored setting of update transient during rollback, should eliminate the _up to date_ message and rollback failures
* added `class GHU_Upgrade` to run upgrade functions if needed
* fixed initial display of update for dot org plugins with higher version numbers on git repos when they should be updating from dot org [496](https://github.com/afragen/github-updater/issues/496)
* refactored query to wp.org for plugin data
* revert javascript href call because Firefox can't have nice things
* fixed to allow themes to rollback at any time
* renamed filter hook `github_updater_token_distribution` to `github_updater_set_options` as more descriptive
* added deprecated hook notice for `github_updater_token_distribution`
* fixed setting of GitLab meta
* changed to not skip setting meta when no update available
* fixed `uninstall.php` for option not transient

#### 6.0.0 / 2016-10-26
* added `class Language_Pack` and new repo, [Language Pack Maker](https://github.com/afragen/github-updater-language-pack-maker), to create and update from a separate Language Pack repository.
* added new header for Language Pack updates. Language Pack updates can and will now be decoupled from the plugin release.
* obfuscated token/password values in Settings page, for @scarstens
* added support for [GitLab Build Artifacts as Release Assets](https://gitlab.com/help/user/project/builds/artifacts.md), [#459](https://github.com/afragen/github-updater/issues/459)
* improved check for private repo, removes public repos from Settings page when no updates are available
* improved to provide Settings page with dynamically displayed sub-tabs
* added display of installed plugins/themes using GitHub Updater in Settings sub-tabs
* added ability to enter Bitbucket credentials to Install tabs if not already present
* moved action/filter hook calls out of constructors, make @carlalexander happy
* improved to incorporate GitLab personal access tokens, users will need to reset tokens.
* added a filter hook `'github_updater_run_at_scale'` to skip several API calls making GitHub Updater at scale more performant, see README for usage details
* added several hooks for  [WP REST Cache](https://github.com/afragen/wordpress-rest-cache) and @scarstens
* skip API calls for branches and tags if branch switching not enabled
* refactored `delete_all_transients()` to delete from database, only called in `class Base`
* refactored and improved _branch switching_ to be consistent among plugins and themes. This means plugins now can rollback to one of the previous 3 tagged releases.
* fixed `get_repo_slugs()` for initially misnamed repository, ie `github-updater-develop`
* renamed `Refresh Transients` to `Refresh Cache`, hopefully to provide more clarity
* refactored to only load GHU site options and other database queries for privileged users on backend only
* added query arg of `?per_page=100` to GitLab query for project IDs, this is max number able to be retrieved, yes an edge case [#465](https://github.com/afragen/github-updater/issues/465)

#### 5.6.2 / 2016-09-24
* added reset of _update\_plugins_ and _update\_themes_ transient with _Refresh Transients_
* throw Exception for webhook update if PUSH is to branch different from webhook
* removed translations from RESTful endpoint responses, only visible from webhook or direct call
* fixed PHP fatal during heartbeat for `class PAnD` not found, early exit in class too early, [#453](https://github.com/afragen/github-updater/issues/453)
* fixed PHP notice in `Bitbucket_API`, [#451](https://github.com/afragen/github-updater/issues/451)

#### 5.6.1 / 2016-09-15
* fixed PHP notices when parsing `readme.txt` with missing data
* fixed PHP fatal by namespacing `class WordPressdotorg\Plugin_Directory\Readme\Parser`
* fixed PHP fatal in `WordPressdotorg\Plugin_Directory\Readme\Parser` by avoiding dereferenced array call

#### 5.6.0 / 2016-09-14
* added `Refresh Transients` button to Settings page because the `Check Again` button is going away
* added `redirect_on_save()` for Settings page
* switched to slightly modified version of [wp.org plugin readme parser](https://meta.trac.wordpress.org/browser/sites/trunk/wordpress.org/public_html/wp-content/plugins/plugin-directory/readme/class-parser.php), now accepts _Markdownified_ readme.txt files
* fixed re-activation of RESTful plugin update, multisite vs single site
* when creating Settings page, check current Plugin/Theme class instance, not transient. Fixes issue where remote install of private repo not having private settings saved.
* fixed PHP errors in Settings page
* fixed saving issues with checkboxes during remote install of private Bitbucket repo
* added one day dismissal of admin notices using [persist-admin-notices-dismissal library](https://github.com/collizo4sky/persist-admin-notices-dismissal)
* Settings page now uses same function to update settings for both single/multisite
* temporary fix for AJAX updates of private Bitbucket repos [#432](https://github.com/afragen/github-updater/issues/432), can only do one per page load, not very AJAXy :P
* fixed `class Rest_Update` to avoid potential race conditions when RESTful endpoint is used as a webhook
* added `branch` and `branches` to update transient, might be able to use this in RESTful update sometime
* fixed extended naming when installing forks of plugins and plugins

#### 5.5.0 / 2016-07-02
* better internationalization for changing plugin _View details_ link
* refactored and improved `class Additions` for `GitHub Updater Additions` plugin
* fixed using GitLab CE private token with using `class Install`
* reworked GitHub repo meta as search was occasionally flaky, now also using owner's repos check
* refactored adding extra headers
* added RESTful endpoints for updating from CLI or browser, courtesy of @limikael
* added reset of RESTful API key
* added CSS file to help display theme view details
* refactored `get_remote_{plugin|theme}_meta()` to `get_remote_repo_meta()` as it was in 4 different places :P
* updated for Shiny Updates
* fixed PHP fatal, thanks @charli-polo
* fixed displaying WP_Errors
* made error messages non-static
* fixed pesky PHP notice when updating from 5.4.1.3 [#403](https://github.com/afragen/github-updater/issues/403)
* added _aria-labels_ for screen readers
* always display theme rollback/branch switcher in single site installation [#411](https://github.com/afragen/github-updater/issues/411)
* fixed extended naming issue when branch switching, [#429](https://github.com/afragen/github-updater/issues/429)

#### 5.4.1 / 2016-04-21
* get tags for themes to rollback even if no updates are available. I was overzealous in cutting remote API calls.
* ManageWP now works for Remote Management.
* fixed bug in `GitLab_API` to use `path` and not `name`. Thanks @marbetschar
* added filter for background updates if set globally. Thanks @jancbeck
* fixed PHP notice when adding new Remote Management option
* deleted all transients on uninstall
* fixed logic for display of GitLab token fields and error notice
* displayed WP_Error message for `wp_remote_get()` error
* correctly get use GitLab namespace/project instead of project id when needed
* added `data-slug` to theme update rows so CSS may be applied
* now supports MainWP for remote management, thanks @ruben-
* typecast `readme.txt` response to array, fix for occasional malformed `readme.txt` file

#### 5.4.0 / 2016-3-18
* fixed deprecated PHP4 constructor in vendor class.
* added `class Additions` to process JSON config from hook to add repos to GitHub Updater, see [GitHub Updater Additions](https://github.com/afragen/github-updater-additions)
* added necessary code in `class Plugin` and `class Theme` for above
* skipped many remote API calls if no update available and use local files, huge performance boost :-)
* removed check for GitHub asset, this eliminates an API call for a rarely used feature
* added additional header `Release Asset: true` to add back ability to set download link to release asset.
* added function to remove _Basic Authentication_ header when downloading private Bitbucket release assets as they are stored on AmazonS3 and use [Query String Request Authentication Alternative](http://docs.aws.amazon.com/AmazonS3/latest/dev/RESTAuthentication.html#RESTAuthenticationQueryStringAuth)
* consolidated error messages to show only once per error
* added _Other Notes_ section to View details
* updated readme.txt with _Other Notes_ information

#### 5.3.4 / 2016-01-24
* reset 'new_version' in update transient to avoid _up to date_ failure with branch switching.
* fixed display of branch switching themes on single install.
* fixed bug in getting Bitbucket branch names.
* fixed to hide checkbox when active as mu-plugin.
* work better with shiny updates.

#### 5.3.3 / 2016-01-04
* removed added filters, below as they didn't add functionality to this plugin.
* try to use references to `&$this`
* added PHPUnit testing setup, I could use help writing tests. A great way to contribute. :-)

#### 5.3.2 / 2015-12-21
* code simplification for `upgrader_source_selection`
* fixed plugin branch switching to override _up-to-date_ message (most of the time)
* added filters for developers, well I wanted them anyway ;-)
  * `github_updater_plugin_transient_update`
  * `github_updater_theme_transient_update`
  * `github_updater_plugin_row_meta`
  * `github_updater_theme_row_meta`
  * `github_updater_append_theme_action`
* fixed renaming of updating plugins that were never initially renamed when first installed. Strange bug.

#### 5.3.1 / 2015-12-03
* fixed PHP notice during remote installation
* fixed remote install [#325](https://github.com/afragen/github-updater/issues/325)

#### 5.3.0 / 2015-11-25
* fixed parsing of `readme.txt` for donate link
* refactored transient storage resulting in significantly few database calls, more performant.
* moved `{get|set}_transient` functions to `abstract class API`
* fixed settings page saving errors.
* fixed shiny updates [#321](https://github.com/afragen/github-updater/issues/321)
* overhauled of renaming code back to using `upgrader_source_selection` and for WordPress 4.4 adding `$args['hook_extra']` to `upgrader_source_selection` filter. Thanks @dd32!

#### 5.2.0 / 2015-10-14
* fixed [#309](https://github.com/afragen/github-updater/issues/309) for proper GitHub Enterprise endpoints
* added setting for GitHub Enterprise personal access token
* new `function _add_access_token()` for `class GitHub_API`
* updatede `erusev/parsedown` to current release

#### 5.1.2 / 2015-09-25
* added `upgrader_source_selection` filter back for correct updating of current, active theme.
* fixed [#293](https://github.com/afragen/github-updater/issues/293) and [#297](https://github.com/afragen/github-updater/issues/297)
* removed `pre_http_request` filter blocking
* fixed javascript for theme rollback - @scarstens
* play nice with current master branch of wp-update-php

#### 5.1.1 / 2015-09-09
* hotfix to comment out `pre_http_request` filter. Updating of plugin doesn't work. I need to re-think this one.

#### 5.1.0 / 2015-09-09
* refactored Plugin and Theme constructors moving code calling APIs getting remote data to separate functions
* fixed [#281](https://github.com/afragen/github-updater/issues/281), removed 'Activate Plugin/Theme' buttons post-install
* fixed [#284](https://github.com/afragen/github-updater/issues/284) for GitLab CE/Enterprise install and update
* fixed to re-activate plugins after update, doesn't work with branch switching :person_frowning:
* fixed to correctly rename plugin/theme on update if installed from upload.
* added filter to `pre_http_response` to bypass certain plugins check using `wp_remote_get` with each page load in GitHub Updater. Bypass is only for 12 hours.
* cosmetic fix to display GitHub Updater as active when activated as mu-plugin
* fixed to `theme_api` 'View version details' CSS; better scrolling for changelog info
* fixed annoying PHP notice in `vendor/parse-readme.php` when _Upgrade Notice_ malformed
* fixed `API::return_repo_type` to add 'type' to array; allows easier instance creation of API classes
* updated POT file

#### 5.0.1 / 2015-08-18
* updated to current `erusev/parsedown` release, fixes PHP7 issue
* updated to current `WPupdatePHP/wp-update-php/release-1-1-0` branch

#### 5.0.0 / 2015-08-15
* fix rollback for GitLab themes
* add branch switcher for themes
* escape all printed strings
* changed from using `upgrader_source_selection` hook to `upgrader_post_install`, this greatly simplifies renaming
* removed `class Remote_Update` as it's no longer needed when using `upgrader_post_install` hook
* added **Remote Management** settings tab more cleanly support those services that currently integrate with GitHub Updater
* modified the process loading so faster for admin level users. Much thanks @khromov
* added hooks for devs to set GitHub Access Tokens and hide the Settings page. Please be sure your client will never need access to the Settings page. Thanks @oncecoupled
* fixed [#267](https://github.com/afragen/github-updater/issues/267) thanks @stevehenty and @rocketgenius

#### 4.6.2
* refactor remote update services to new `class Remote_Update`
* general security fixes, don't call files directly...
* fix/test for remote updating via InfiniteWP. Child themes are not identified by IWP as needing updates, otherwise it seems to work as expected.

#### 4.6.1
* fix for remote updating via iThemes Sync
* fix for renaming when AJAX updating of plugins

#### 4.6.0
* newer, much more precise method for renaming based upon selected repos from the dashboard. Yes, I tested on staging server. :-)
* added feature to use extended naming of plugin directories to avoid potential conflict with WP.org slugs. Props @reinink for the idea.
* strip `.git` from the end of the plugin or theme URI for those who haven't gotten to the README yet.
* added javascript show/hide options on the Install page.
* fixed boolean logic to _not_ display GitLab Private Token input on Install if it's already set.
* updated screenshots in README
* switched a number of methods to be non-static, anticipation of testing.
* [broken: renaming during updates from upgrade services](https://github.com/afragen/github-updater/issues/262)

#### 4.5.7
* hotfix GitLab private updating/installing
* fix some PHP notices

#### 4.5.6
* bugfix for renaming code to properly strip `<owner>-`
* most of Russian translation by [Anatoly Yumashev](https://github.com/yumashev)

#### 4.5.5
* back to simplifying the renaming code, always remember to test renaming on live server.
* strip `<owner>-` and `-<hash>` from beginning and end of update for more precise renaming
* I think this is the end of renaming for a while. :P

#### 4.5.4
* hotfix for renaming, I reverted back a bunch with more extensive testing on server. It's amazing how different renaming is locally vs on server.

#### 4.5.3
* updated language files -- oops

#### 4.5.2
* cleanup and refactor of renaming code.
* added Romanian translation by [Corneliu Cirlan](https://github.com/corneliucirlan)
* added Japanese translation by [ishihara](https://github.com/1shiharat)

#### 4.5.1
* fix bug so updates display without having to randomly refresh.

#### 4.5.0
* fix some PHP notices
* add update by GitHub release asset in lieu of update by tag when asset is present
* install asset via remote install if asset URI used
* refactor to simplify class structure, created `abstract class API` and `class Messages`
* add GitLab support!!
* refactor to set all git servers and extra headers to static arrays in `Base`
* remove checkbox when loaded as mu-plugin, props @pbearne

#### 4.4.0
* only add custom user agent once :P
* add support of GitHub Enterprise via new `GitHub Enterprise` header
* sanitize filter input
* add support for parsing `readme.txt` for _View details_ information using `WordPress_Plugin_Readme_Parser` by @markjaquith
* fixed _View details_ link to show for all cases when plugin using GitHub Updater
* refactor creation of header parts and URIs

#### 4.3.1
* Spanish translation by [Jose Miguel Bejarano](https://github.com/xDae)
* German translation by [Linus Metzler](https://github.com/limenet)
* squish PHP notices
* add custom user agent to `wp_remote_get` and tweak error message at request of GitHub ;-)
* fixed edge case renaming bug

#### 4.3.0
* use @WPUpdatePhp `class WPUpdatePhp` for PHP version checking
* use <https://api.wordpress.org> not http
* Arabic translation by [Hyyan Abo FAkher](https://github.com/hyyan)
* make strings better for translation - thanks @pedro-mendonca and @fxbenard
* additional Portuguese translation by [Pedro Mendonça](https://github.com/pedro-mendonca)
* refactor for getting local plugin and theme meta, now simpler for additional APIs (I'm thinking about you GitLab)
* fix link in README to GitHub Link
* correctly pass array as last argument in `add_settings_field`
* add focus to URI input field
* add Setting for personal GitHub Access Token to avoid API rate limit - thanks @mlteal
* add Setting for branch switching from the Plugins page
* add 'View details' link in Plugins page

#### 4.2.2
* fix POT and some updated languages, thanks @fxbenard
* fix PHP notice for `$options` settings on initial install - thanks @benosman

#### 4.2.1
* add PHP version check for graceful exit
* add to error message for 401 error.
* save settings when remote installing a private repo

#### 4.2.0
* added minutes until reset of GitHub API's rate limit to error message
* added `placeholder = "master"` to remote install branch text input
* I should have made the last version 4.2.0 as I added a new feature. I'll try to be better with semantic versioning in the future. ;-)

#### 4.1.4
* add message to certain admin pages when API returns HTTP error code
* update POT to remove HTML entity codes from strings and generally try to make i18n better
* Swedish translation by [Andréas Lundgren](https://github.com/Adevade)
* added logo to README and Settings page

#### 4.1.3
* use `strtolower` comparison of plugin directory and repo name. This might is an issue related to the manual installation of a plugin before any update might occur. This allows the **View details** screen to display in these instances where the case of the directory and repo aren't identical. This doesn't work for themes.

#### 4.1.2
* hide star ratings from **View details** screen for private repos

#### 4.1.1
* add `plugin` to `$response` in `Plugin::pre_set_site_transient_update_plugins` to fix PHP Notice
* rename `classes` to `src` to follow more conventional naming
* refactor renaming code to function under all circumstances, I hope ;-)

#### 4.1.0
* added remote installation of plugins or themes, both public and private
* remote installation using either full URI or short `<owner><repo>` format
* created new tabbed interface for settings
* added another screenshot to readme
* I'd like to apologize to all my translators for adding new strings often, you guys are great, thanks!

#### 4.0.1
* hotfix to force an array type when sanitizing settings, it gave me a fatal I wasn't expecting.

#### 4.0.0
* changed `is_a()` to `instanceof` per <https://core.trac.wordpress.org/changeset/31188>
* requires PHP 5.3 or greater as autoloader class requires namespacing
* updated all classes for namespacing
* renamed directory and class names to allow for PSR 4 style loading
* clean up a number of foreach loops where I was only using either key or value, not both
* Special thanks for all my translators, especially @grappler for adding translation key for description
* bugfix to correctly pick CHANGES.MD or CHANGELOG.MD regardless of case
* removed reading/saving `GitHub Access Token` header into settings. Must use Settings Page.

#### 3.2.3 - 3.2.6
* added French translation by @daniel-menard
* added Italian translation by @overclokk
* added Portuguese translation by @valeriosouza
* added Ukrainian translation by @andriiryzhkov (our first translation!!)

#### 3.2.2
* remove scraping of user/pass from Bitbucket URI as it's no longer needed
* use `Requires WP` header to fill view options detail
* rename private methods to begin with underscore
* add screenshot to README for Settings Page (only 70 kB)
* stop re-creating transient of transients if it already exists

#### 3.2.1
* refactored adding extra headers to `class GitHub_Updater` to ensure they're added before they're needed, resolves issue with WooThemes Updater plugin
* update .pot file

#### 3.2.0
* changed settings page and how Bitbucket Private repos authenticate with your username/password
* update .pot

#### 3.1.1
* minor transient cleanup
* update .pot file
* fix to get all themes under both single and multisite installs

#### 3.1.0
* woot!! - updating from Bitbucket private repos now works!!
* fix to only add HTTP Authentication header under correct circumstances. This obviates need to fix for other APIs that might also use HTTP Authentication.
* fix to correctly add GitHub Access Token from `$options` to `$download_link` - oops
* changes `$options` to `private static $options` to save a few database calls
* Settings page **only** shows private repos, except for initial setup
* simpler test for checking branch as download endpoint
* correctly use `parent::` instead of `self::`
* many updates for translation
* fix to ensure theme rollback and updating works in both single install and multisite
* fix to save settings from single site installations

#### 3.0.7
* more efficient solution to HTTP Authentication issues
* more efficient options cleanup
* remove some unnecessary code resulting in few database calls
* change default option setting to use `add_site_option` so not autoloading options

#### 3.0.6
* fix for other APIs that use HTTP Authentication, like JetPack - thanks @tsquez

#### 3.0.5
* fix more PHP Notices
* correctly set defaults for Settings page :P
* remove options for plugins/themes that are no longer present

#### 3.0.4
* Who would've thought `file_exists` was case-sensitive
* when checking meta, use `empty()` instead of `! isset()` for `null array`
* set defaults for Settings page
* fix a number of PHP Notices

#### 3.0.3
* Bugfix to properly authenticate on JetPack Stats page

#### 3.0.2
* simplify check and exit on Settings if no Bitbucket plugins/themes

#### 3.0.1
* Remove Bitbucket settings from page if no appropriate plugins or themes exist.

#### 3.0.0
* Settings Page for your GitHub Access Tokens
* added POT file and some more i18n fixes - thanks @grappler
* added `Requires WP` and `Requires PHP` headers to set minimum version requirements - for @GaryJ
* move update check to function to also check WP and PHP version requirements.
* unset any HTTP Authorization headers for GitHub API calls as this gives a 401 error. Rare potential bug if you have private Bitbucket repos.

#### 2.9.0
* move instantiation of `class GitHub_Plugin_Updater` and `class GitHub_Theme_Updater` into `GitHub_Updater::init()` and restrict to `current_user_can( 'update_plugins' )` and `current_user_can( 'update_themes' )` so that non-privileged users don't incur load time.
* now loading classes via `spl_autoload_register`
* switched to `erusev/parsedown` for rendering changelogs, faster and more light-weight.
* now parses remote file info to save only file headers to transient. Hopefully speeds up database retrieval of transient.
* added README link to GitHub Link plugin by @szepeviktor
* added mu-plugin option and instructions.
* above revisions mostly due to @szepeviktor prodding me. ;-)
* accept `CHANGES.md` or `CHANGELOG.md` for processing, for @GaryJ
* composer support added, thanks @hyyan

#### 2.8.1
* fix for WP Coding Guidelines
* added check for upgrade process instead of `$_GET['action']` (props @SLv99)
* launch classes from `GitHub_Updater::init()` so can load in `add_action( 'init', ...` from `__construct()`. Hopefully this will solve issues with remote upgraders like iThemes Sync, ManageWP, InfiniteWP, and MainWP. Thanks @jazzsequence for testing. Thanks @SLv99 for bringing this to my attention.

#### 2.8.0
* refactor API classes and `class GitHub_Updater` to add extra headers from API class. This should allow for better abstraction. Just need to call `GitHub_Updater_{repo}_API::add_headers()` in `class GitHub_Plugin_Updater` and `class GitHub_ Theme_Updater`.
* remove @since tags
* move `maybe_authenticate_http` to `class GitHub_Updater_Bitbucket_API` as it's not used elsewhere
* use non-strict check for http response code (thanks @echav)

#### 2.7.1
* added early exit if no local `CHANGES.md` file exists. This should save an API call.
* pull update from WP.org if plugin hosted in WP.org and branch is `master`.

#### 2.7.0
* created functions for getting and setting transients
* added deletion of all transients if _force-check_ is used
* removed `GitHub Timeout` and `Bitbucket Timeout` headers
* fix for `wp_remote_retrieve_response_code` check
* give Seth Carstens proper credit in README.md
* move `function make_rating` to `class GitHub_Updater`
* fix for plugin name in update detail view
* fix for Bitbucket repo with no branch tag
* set default timeout to 12 hours, same as WP.org
* fix for 3.9 setting theme update details to `display:none;`
* fix for error when installing themes from WP.org repo
* fix for incorrect plugin upgrade link in detail popup

#### 2.6.3
* quick error checking fix for `wp_remote_get` error to wordpress.org API - thanks @deckerweb

#### 2.6.1
* fixed CHANGES.md for GFM strike-through

#### 2.6.0
* added transient to `plugins_api` call
* better zeroing of variables in getting local theme data
* add error checking to loading of classes
* set default transient timeout to 4 hours
* added new header `GitHub Timeout` or `Bitbucket Timeout` to set individual plugin/theme transient timeout
* ~~fixed for Bitbucket private repos~~
* abide by WP Coding Guidelines, esp. for braces
* more error checking for correct variable fetch
* added graceful exit if repo does not exist

#### 2.5.0
* added `class GitHub_Updater_Bitbucket_API` for Bitbucket hosted plugins and themes.
* improvements to efficiency by not loading when `DOING_AJAX`
* improvements to efficiency in use of transients

#### 2.4.5
* set PHP MarkdownExtra posts and comments markup to false props @MikeHansonMe
* remove WP plugin header from `markdown.php`

#### 2.4.4
* forgot to include markdown.php - damn

#### 2.4.2
* removed PHP Markdown Lib as it required PHP >= 5.3 and that's higher than required by WordPress core.

#### 2.4.1
* switched from PHP Markdown Classic to the new PHP Markdown Lib to prevent collisions with other plugins, like Markdown On Save/Improved that also load PHP Markdown or PHP MarkdownExtra.

#### 2.4.0
* fixed transient assignment for tags returning empty array.
* added transient for `CHANGES.md` to themes, should further cut down on API 403 errors.
* new feature: theme rollback to previous version thanks @scarstens
* changed update methodology to use most recent tag first. If not tagged update from default branch.

#### 2.3.3
* fixed download link to have correct base URI for Repository Contents API. Oops.

#### 2.3.2
* rewrite of `GitHub_Update_GitHub_API::construct_download_link` to download zipball and provide appropriate endpoint.

#### 2.3.1
* now saving transient and adding early return if API returns 404, this should speed up plugin when repo doesn't have `CHANGES.md` file and provide for early return in no tags have been created. If no tags have been created the API is still hit.

#### 2.3.0
* moved action hook to remove `after_theme_row_$stylesheet` to `class GitHub_Theme_Updater`
* added feature: if branch other than `master` is specified then tagged version will be ignored. This should make it much easier for beta testing to groups. See [README.md](https://github.com/afragen/github-updater/blob/develop/README.md)
* converted `class GitHub_Update_GitHub_API` to extension of `class GitHub_Updater`
* combined `description` and `changelog` to show in theme detail view. Rough formatting. Multisite only.
* greatly simplified bug fix from 2.2.2, now using Themes API.

#### 2.2.2
* bug fix for removing update notice for WP.org repo themes. Oops.

#### 2.2.1
* minor code simplifications
* many thanks to @grappler for solving how to remove default `after_theme_row_$stylesheet`

#### 2.2.0
* moved check and load for `markdown.php` into only function that uses it.
* minor README updates
* added abort if this plugin called directly
* added additional data to update available screen in both plugins and themes - issue #8
* removed requirement for tags in theme updating
* removed extra line endings from `remote_version`
* added ratings function for creating star ratings based upon GitHub repo data.
* bring parts of `class GitHub_Theme_Updater` code on par with `class GitHub_Plugin_Updater`
* added 'ghu-' prefix to transients
* ripped out theme rollback code. Moved to it's own branch on GitHub.
* add custom `after_theme_update_{$stylesheet}` detail.

#### 2.1.1
* bug fix to return early from call to `plugins_api` if not getting plugin information. Fixes issue with Plugin Search.

#### 2.1.0
* simplify check for `class Markdown_Parser`
* refactor to pass `class GitHub_Update_GitHub_API` as class object. This should enable the creation of other class objects for Bitbucket, etc.
* fix for setting branch when API not responding
* fix for setting download link when API not responding
* redesigned filter for setting transient timeout, but still not working (pull requests welcome)

#### 2.0.1
* bug fix to not load `markdown.php` twice. Just in case it's loaded by some other plugin.

#### 2.0.0
* rearranged where I put `GitHub Plugin URI` header, etc. in README and in this plugin.
* minor spelling fixes
* renamed some functions for their hooks
* refactored `class GitHub_Plugin_Updater` and `class GitHub_Theme_Updater` to use stdClass objects
* further refactored base class `GitHub_Updater` to contain renaming code and create stdClass objects for data.
* added some ability to see changelog for GitHub hosted plugins.
* trying to follow [WordPress Plugin Boilerplate](https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate), so renamed `classes` to `includes`
* refactored putting all remote api calls in new `class GitHub_Plugin_Updater_API`.
* Theme updating should now be able to have a specified branch.
* works on WordPress 3.8
* included Michel Fortin's [PHP-Markdown](http://michelf.ca/projects/php-markdown/) for rendering `CHANGES.md`

#### 1.8.1
* added some variable declarations
* added early return in no GitHub sourced plugins or themes are identified

#### 1.8.0
* refactored to use base class `GitHub_Updater` and extending classes `GitHub_Plugin_Updater` and `GitHub_Theme_Updater`.

#### 1.7.4
* changed method of not overwriting extra headers to pass array.

#### 1.7.3
* change `'...'` to `&#8230` in renaming notification
* fix to not overwrite extra headers of other plugins.

#### 1.7.2
* removed sorting option from `scandir`. Doesn't work with older versions of PHP < 5.4.0
* removed extraneous data from array in `multisite_get_themes`

#### 1.7.1
* updated the transient for themes
* replaced `readdir` with `scandir` for creating WP\_Theme object in multisite

#### 1.7.0
* updated class-theme-updater.php to utilize WP\_Theme class
* added method `get_remote_tag` to update plugins using tags or branch, depending upon which has greater version number.
* `get_remote_tag` uses transient to limit calls to API
* fix for `wp_get_themes` not working under plugin network activation on multisite installation. I recreated `wp_get_themes` by reading in the theme directory and adding the WP\_Theme object of `wp_get_theme( 'dir_in_themes_dir' )` to an array.

#### 1.6.1
* bug fix for undeclared variable $github_plugins

#### 1.6.0
* Added separate method to parse plugin repo info from header
* Shortened GitHub Plugin URI to only use owner/repo
* Shortened GitHub Theme URI to only use owner/repo

#### 1.5.0
* Lots of documentation and some bug fixes. Thanks @GaryJones
* Made version checking regex more compatible. Thanks @GaryJones
* Added ability to define branch to update.
* Refactored plugin/theme renaming code.
* Added `GitHub Branch` feature - Thanks @GaryJones
* Trying to comply with WP Coding Standards.
* Major thanks to @GaryJones for all the pull requests and generally improving this project.

#### 1.4.3
* Fixed a couple of non-fatal PHP errors. Thanks @jazzsequence

#### 1.4.2
* Cleaned up readme's markdown.

#### 1.4.1
* Fixed the README to more accurately reflect support for both plugins and themes.

#### 1.4
* Fix for rename functions to be more precise, otherwise might rename wp.org repo themes.

#### 1.3
* Simplify a couple of if statements.

#### 1.2
* Fix to ignore renaming for wp.org plugins

#### 1.1
* Sanity check for theme api uri

#### 1.0
* Serialized WP\_Theme object to search for added GitHub header, lots of help from Seth. No more `file_get_contents`.
* Converted plugin class and added it to make joint plugin/theme updater.

#### 0.2
* Code cleanup.
* Limit `file_get_contents` to 2000 bytes.

#### 0.1
* Initial commit
