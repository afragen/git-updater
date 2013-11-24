# ChangeLog for GitHub Updater

## 1.8.x

 * rearranged where I put `GitHub Plugin URI` header, etc. in README and in this plugin.
 * minor spelling fixes

## 1.8.1

 * added some variable declarations
 * added early return in no GitHub sourced plugins or themes are identified

## 1.8.0

 * refactored to use base class `GitHub_Updater` and extending classes `GitHub_Plugin_Updater` and `GitHub_Theme_Updater`.

## 1.7.4

 * changed method of not overwriting extra headers to pass array.

## 1.7.3

 * change `'...'` to `&#8230` in renaming notification
 * fix to not overwrite extra headers of other plugins.

## 1.7.2

 * removed sorting option from `scandir`. Doesn't work with older versions of PHP < 5.4.0
 * removed extraneous data from array in `multisite_get_themes`

## 1.7.1

 * updated the transient for themes
 * replaced `readdir` with `scandir` for creating WP\_Theme object in multisite

## 1.7.0

 * updated class-theme-updater.php to utilize WP\_Theme class
 * added method `get_remote_tag` to update plugins using tags or branch, depending upon which has greater version number.
 * `get_remote_tag` uses transient to limit calls to API
 * fix for `wp_get_themes` not working under plugin network activation on multisite installation. I recreated `wp_get_themes` by reading in the theme directory and adding the WP\_Theme object of `wp_get_theme( 'dir_in_themes_dir' )` to an array.

## 1.6.1

 * bugfix for undeclared variable $github_plugins

## 1.6.0

 * Added separate method to parse plugin repo info from header
 * Shortened GitHub Plugin URI to only use owner/repo
 * Shortened GitHub Theme URI to only use owner/repo

## 1.5.0

* Lots of documentation and some bug fixes. Thanks @GaryJones
* Made version checking regex more compatible. Thanks @GaryJones
* Added ability to define branch to update.
* Refactored plugin/theme renaming code.
* Added `GitHub Branch` feature - Thanks @GaryJones
* Trying to comply with WP Coding Standards.
* Major thanks to @GaryJones for all the pull requests and generally improving this project.

## 1.4.3

* Fixed a couple of non-fatal PHP errors. Thanks @jazzsequence

## 1.4.2

* Cleaned up readme's markdown.

## 1.4.1

* Fixed the README to more accurately reflect support for both plugins and themes.

## 1.4

* Fix for rename functions to be more precise, otherwise might rename wp.org repo themes.

## 1.3

* Simplify a couple of if statements.

## 1.2

* Fix to ignore renaming for wp.org plugins

## 1.1

* Sanity check for theme api uri

## 1.0

* Serialized WP\_Theme object to search for added GitHub header, lots of help from Seth. No more `file_get_contents`.
* Converted plugin class and added it to make joint plugin/theme updater.

## 0.2

* Code cleanup.
* Limit `file_get_contents` to 2000 bytes.

## 0.1

* Initial commit
