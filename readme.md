## === GitHub Theme Updater ===  
Contributors: afragen, scarstens, codepress  
Tags: plugin, theme, update, github  
Requires at least: 3.4  
Tested up to: 3.6  
Plugin URI: https://github.com/afragen/github-updater  
Stable tag: master  
Version: 1.4.2  
License: GPLv2 or later  
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A simple plugin to enable automatic updates to your GitHub hosted WordPress plugins and themes. This plugin is not allowed in the wp.org repo. :(

### == Description ==

This plugin was designed to simply update any GitHub hosted WordPress plugin or theme. Your plugin or theme **must** contain a header in the style.css header or in the plugin's header denoting the location on GitHub. The format is as follows.

`GitHub Theme URI: https://github.com/afragen/test-child`

or 

`GitHub Plugin URI: https://github.com/afragen/github-updater`

Where the above URL leads to the repository of your theme or plugin.

### == Installation ==

This section describes how to install the plugin and get it working.

1. Upload `github-theme-updater` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

### == Frequently Asked Questions ==

There must be a `GitHub Theme URI` declaration in the `style.css` file and you must create a tag in GitHub for each version.

    /*
    Theme Name: Test
    Theme URI: http://drfragen.info/
    GitHub Theme URI: https://github.com/afragen/test-child
    Description: Child-Theme of TwentyTwelve.
    Author: Andy Fragen
    Template: twentytwelve
    Template Version: 1.0
    Version: 0.1
    */

In your plugin the following is an example. You do not need to create a tag in GitHub for your plugin version.

    /*
    Plugin Name: GitHub Updater
    Plugin URI: https://github.com/afragen/github-updater
    GitHub Plugin URI: https://github.com/afragen/github-updater
    Description: Plugin and Theme Updater classes to pull updates of the GitHub based plugins and themes into wordpress. Theme class based upon <a href="https://github.com/WordPress-Phoenix/whitelabel-framework">Whitelabel Framework</a> modifications. Plugin class based upon <a href="https://github.com/codepress/github-plugin-updater">codepress/github-plugin-updater</a>.
    Version: 1.0
    Author: Andy Fragen
    License: GNU General Public License v2
    License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
    */

The only extra character allowed in a URI is `-`. Let me know if there is a need for others.

This plugin's theme updater class was based upon <a href="https://github.com/WordPress-Phoenix/whitelabel-framework/blob/master/inc/updater-plugin.php">Whitelabel Framework's updater-plugin.php</a>, which was based upon https://github.com/UCF/Theme-Updater. The plugin updater class was based upon <a href="https://github.com/codepress/github-plugin-updater">codepress/github-plugin-updater</a>

### == Issues ==

Please log issues on the GitHub at https://github.com/afragen/github-theme-updater/issues

### == Changelog ==

= 1.4.2 =

* cleaned up readme's markdown.

= 1.4.1 =

* oops fixed the readme to more accurately reflect support for both plugins and themes.

= 1.4 =

* fix for rename functions to be more precise, otherwise might rename wp.org repo themes.

= 1.3 =

* simplify a couple of if statements

= 1.2 =

* fix to ignore renaming for wp.org plugins

= 1.1 =

* sanity check for theme api uri

= 1.0 =

* initial commit to WordPress repo
* serialized WP_Theme object to search for added GitHub header, lots of help from Seth. No more `file_get_contents`
* converted plugin class and added it to make joint plugin/theme updater.


= 0.2 =

* code cleanup
* limit `file_get_contents` to 2K bytes

= 0.1 =

* Initial commit

