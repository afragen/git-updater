# GitHub Updater

A simple plugin to enable automatic updates to your GitHub or Bitbucket hosted WordPress plugins and themes.

This plugin is not allowed in the wp.org repo. :frowning:

## Description

This plugin was designed to simply update any GitHub hosted WordPress plugin or theme. Your plugin or theme **must** contain a header in the style.css header or in the plugin's header denoting the location on GitHub. The format is as follows. The folder name of the theme or plugin **must** be the same as the repo name.

`GitHub Theme URI: afragen/test-child`  
`GitHub Theme URI: https://github.com/afragen/test-child`

or 

`GitHub Plugin URI: afragen/github-updater`  
`GitHub Plugin URI: https://github.com/afragen/github-updater`

...where the above URI leads to the __owner/repository__ of your theme or plugin. The URI may be in the format `https://github.com/<owner>/<repo>` or the short format `<owner>/<repo>`.

## Requirements
 * WordPress 3.4 (tested up to 3.9)

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

There must be a `GitHub Theme URI` or `Bitbucket Theme URI` declaration in the `style.css` file.

~~~css
/*
Theme Name:       Test
Theme URI:        http://drfragen.info/
Version:          0.1.0
Description:      Child theme of TwentyTwelve.
Author:           Andy Fragen
Template:         twentytwelve
Template Version: 1.0.0
GitHub Theme URI: https://github.com/afragen/test-child
GitHub Branch:    master
*/
~~~

### Plugins 

There must be a `GitHub Plugin URI` or `Bitbucket Plugin URI` declaration in the plugin's header.

~~~php
/*
Plugin Name:       GitHub Updater
Plugin URI:        https://github.com/afragen/github-updater
Description:       A plugin to automatically update GitHub hosted plugins and themes into WordPress. Plugin class based upon <a href="https://github.com/codepress/github-plugin-updater">codepress/github-plugin-updater</a>. Theme class based upon <a href="https://github.com/WordPress-Phoenix/whitelabel-framework">Whitelabel Framework</a> modifications.
Version:           1.0.0
Author:            Andy Fragen
License:           GNU General Public License v2
License URI:       http://www.gnu.org/licenses/gpl-2.0.html
Domain Path:       /languages
Text Domain:       github-updater
GitHub Plugin URI: https://github.com/afragen/github-updater
GitHub Branch:     master
*/
~~~

Optional headers `GitHub Access Token`, `GitHub Branch`, `GitHub Timeout`, `Bitbucket Branch`, and `Bitbucket Timeout` are available but not required.

## Tagging

If `GitHub Branch` or `Bitbucket Branch` is not specified (or is set to `master`), then the latest tag will be used. GitHub Updater will preferentially use a tag over a branch in this instance.

## Branch Support

To specify a branch that you would like to use for updating, just add a `GitHub Branch` header.  If you develop on `master` and are pushing tags, GitHub Updater will update to the newest tag. If there are no tags or the specified branch is not `master` GitHub Updater will use the specified branch for updating.

The default state is either `GitHub Branch: master` or nothing at all. They are equivalent.

If you want to update against branch of your repository other than `master` and have that branch push updates out to users make sure you specify the testing branch in a header, i.e. `GitHub Branch: develop`. When you want users to update against the release branch just have them manually change the header to `GitHub Branch: master` or remove it completely. Tags will be ignored when a branch other than `master` is specified. In this case I would suggest semantic versioning similar to the following, `<major>.<minor>.<patch>.<development>`.

## Bitbucket Support

The `Bitbucket Branch` header is supported for both plugins and themes.

### Bitbucket Plugin Support

Instead of the `GitHub Plugin URI` header you will need to use the `Bitbucket Plugin URI` header.

### Bitbucket Theme Support

Instead of the `GitHub Theme URI` header you will need to use the `Bitbucket Theme URI` header.

## Private Repositories

### GitHub Private Repositories

In order to specify a private repository you will need to obtain a [personal access token](https://github.com/settings/tokens/new). Once you have this, simply add the header `GitHub Access Token: xxxxxxxxx` to your plugin or theme.

### Bitbucket Private Repositories

The header should be in the following format: `Bitbucket Plugin URI: https://<user>:<password>@bitbucket.org/<owner>/<repo>` or `Bitbucket Theme URI: https://<user>:<password>@bitbucket.org/<owner>/<repo>`

## Setting Transient Timeout

The default number of hours for a plugin/theme's transient to expire is 4 hours. You may add an optional header, `GitHub Timeout` or `Bitbucket Timeout` to set a different transient timeout. The header will accept numeric values representing the number of hours for the plugin/theme's transient timeout. These values are floats.

## Issues

Please log issues on the GitHub at https://github.com/afragen/github-updater/issues

If you are using a WordPress Multisite installation, the plugin should be network activated.

## ChangeLog

See [CHANGES.md](CHANGES.md).

## Credits

This plugin's theme updater class was based upon [Whitelabel Framework's updater-plugin.php](https://github.com/WordPress-Phoenix/whitelabel-framework/blob/master/inc/admin/updater-plugin.php), which was based upon https://github.com/UCF/Theme-Updater.

The plugin updater class was based upon [codepress/github-plugin-updater](https://github.com/codepress/github-plugin-updater).

Built by [Andy Fragen](https://github.com/afragen), [Gary Jones](https://github/GaryJones) and [contributors](https://github.com/afragen/github-updater/graphs/contributors)

Includes [Michel Fortin](https://github/com/michelf)'s [PHP-Markdown](https://github.com/michelf/php-markdown) for rendering ChangeLogs.
