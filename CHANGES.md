#### [unreleased]
* fixes for PHP 7.4

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
