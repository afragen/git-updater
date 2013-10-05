# GitHub Updater

A simple plugin to enable automatic updates to your GitHub hosted WordPress plugins and themes.

This plugin is not allowed in the wp.org repo. :frowning:

## Description

This plugin was designed to simply update any GitHub hosted WordPress plugin or theme. Your plugin or theme **must** contain a header in the style.css header or in the plugin's header denoting the location on GitHub. The format is as follows.

`GitHub Theme URI: afragen/test-child`

or 

`GitHub Plugin URI: afragen/github-updater`

...where the above URI leads to the __owner/repository__ of your theme or plugin.

## Requirements
 * WordPress 3.4 (tested up to 3.6.1)

## Installation

### Upload

1. Download the latest tagged archive (choose the "zip" option).
2. Go to the __Plugins -> Add New__ screen and click the __Upload__ tab.
3. Upload the zipped archive directly.
4. Go to the Plugins screen and click __Activate__.

### Manual

1. Download the latest tagged archive (choose the "zip" option).
2. Unzip the archive.
3. Copy the folder to your `/wp-content/plugins/` directory.
4. Go to the Plugins screen and click __Activate__.

Check out the Codex for more information about [installing plugins manually](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

### Git

Using git, browse to your `/wp-content/plugins/` directory and clone this repository:

`git clone git@github.com:afragen/github-updater.git`

Then go to your Plugins screen and click __Activate__.

## Usage

### Themes

There must be a `GitHub Theme URI` declaration in the `style.css` file and you **must** create a tag in GitHub for each version.

~~~css
/*
Theme Name: Test
Theme URI: http://drfragen.info/
GitHub Theme URI: afragen/test-child
Version: 0.1.0
Description: Child theme of TwentyTwelve.
Author: Andy Fragen
Template: twentytwelve
Template Version: 1.0.0
*/
~~~

### Plugins 
In your plugin the following is an example. You do not need to create a tag in GitHub for your plugin version.

~~~php
/*
Plugin Name:       GitHub Updater
Plugin URI:        https://github.com/afragen/github-updater
GitHub Plugin URI: afragen/github-updater
Description: Plugin and Theme Updater classes to pull updates of the GitHub based plugins and themes into wordpress. Theme class based upon <a href="https://github.com/WordPress-Phoenix/whitelabel-framework">Whitelabel Framework</a> modifications. Plugin class based upon <a href="https://github.com/codepress/github-plugin-updater">codepress/github-plugin-updater</a>.
Version:           1.0.0
Author:            Andy Fragen
License:           GNU General Public License v2
License URI:       http://www.gnu.org/licenses/gpl-2.0.html
Domain Path:       /languages
Text Domain:       github-updater
*/
~~~

Optional plugin headers `GitHub Access Token:` and `GitHub Branch:` are available but not required.

The only extra character allowed in a URI is `-`. Let me know if there is a need for others.

## Issues

Please log issues on the GitHub at https://github.com/afragen/github-updater/issues

If you are using a WordPress Multisite installation, theme updating only works when the plugin has been activated inside each blog. That means no Network Activation - for now.

## ChangeLog

See [CHANGES.md](CHANGES.md).

## Credits

This plugin's theme updater class was based upon [Whitelabel Framework's updater-plugin.php](https://github.com/WordPress-Phoenix/whitelabel-framework/blob/master/inc/updater-plugin.php), which was based upon https://github.com/UCF/Theme-Updater.

The plugin updater class was based upon [codepress/github-plugin-updater](https://github.com/codepress/github-plugin-updater).

Built by [Andy Fragen](https://github.com/afragen) and [contributors](https://github.com/afragen/github-updater/graphs/contributors)
