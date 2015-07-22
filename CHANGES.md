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
* use https://api.wordpress.org not http
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
* changed `is_a()` to `instanceof` per https://core.trac.wordpress.org/changeset/31188
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
* trying to follow <a href="https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate">WordPress Plugin Boilerplate</a>, so renamed `classes` to `includes`
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
