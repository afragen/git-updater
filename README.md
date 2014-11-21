# GitHub Updater
* Contributors: [Andy Fragen](https://github.com/afragen), [Gary Jones](https://github.com/GaryJones), [Seth Carstens](https://github.com/scarstens), [contributors](https://github.com/afragen/github-updater/graphs/contributors)
* Tags: plugin, theme, update, updater
* Requires at least: 3.8
* Tested up to: 4.1beta
* Stable tag: master
* License: GPLv2 or later
* License URI: http://www.gnu.org/licenses/gpl-2.0.html


A simple plugin to enable automatic updates to your GitHub or Bitbucket hosted WordPress plugins and themes.

This plugin is [not allowed in the wp.org repo](https://github.com/afragen/github-updater/issues/34). :frowning:

## Description

This plugin was designed to simply update any GitHub hosted WordPress plugin or theme. Your plugin or theme **must** contain a header in the style.css header or in the plugin's header denoting the location on GitHub. The format is as follows.

`GitHub Theme URI: afragen/test-child`  
`GitHub Theme URI: https://github.com/afragen/test-child`

or 

`GitHub Plugin URI: afragen/github-updater`  
`GitHub Plugin URI: https://github.com/afragen/github-updater`

...where the above URI leads to the __owner/repository__ of your theme or plugin. The URI may be in the format `https://github.com/<owner>/<repo>` or the short format `<owner>/<repo>`. You do not need both. Only one Plugin or Theme URI is required.

## Installation

### Composer

Run the composer command: ```composer require afragen/github-updater```


### Upload

1. Download the latest [tagged archive](https://github.com/afragen/github-updater/releases) (choose the "zip" option).
2. Go to the __Plugins -> Add New__ screen and click the __Upload__ tab.
3. Upload the zipped archive directly.
4. Go to the Plugins screen and click __Activate__.

### Manual

1. Download the latest [tagged archive](https://github.com/afragen/github-updater/releases) (choose the "zip" option).
2. Unzip the archive.
3. Copy the folder to your `/wp-content/plugins/` directory.
4. Go to the Plugins screen and click __Activate__.

Check out the Codex for more information about [installing plugins manually](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

### Git

Using git, browse to your `/wp-content/plugins/` directory and clone this repository:

`git clone https://github.com/afragen/github-updater.git`

Then go to your Plugins screen and click __Activate__.

### Install GitHub Updater as a Must Use Plugin (optional)

1. Choose a method from above for installation.
1. **DO NOT** activate!
1. Symlink `wp-content/plugins/github-updater/mu/ghu-loader.php` in `wp-content/mu-plugins`.

#### in Linux
```
cd <WordPress root>
ln -sv wp-content/plugins/github-updater/mu/ghu-loader.php wp-content/mu-plugins
```

#### in Windows (Vista, 7, 8)
```
cd /D <WordPress root>
mklink wp-content\mu-plugins\ghu-loader.php wp-content\plugins\github-updater\mu\ghu-loader.php
```

This way you get automatic updates and cannot deactivate the plugin.

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

There must be a `GitHub Plugin URI` or `Bitbucket Plugin URI` declaration in the plugin's header. The plugin's primary file **must** be named similarly to the repo name.

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

Optional headers `GitHub Access Token`, `GitHub Branch`, and `Bitbucket Branch` are available but not required.

## Branch Support

To specify a branch that you would like to use for updating, just add a `GitHub Branch` header.  If you develop on `master` and are pushing tags, GitHub Updater will update to the newest tag. If there are no tags or the specified branch is not `master` GitHub Updater will use the specified branch for updating.

The default state is either `GitHub Branch: master` or nothing at all. They are equivalent.

If you want to update against branch of your repository other than `master` and have that branch push updates out to users make sure you specify the testing branch in a header, i.e. `GitHub Branch: develop`. When you want users to update against the release branch just have them manually change the header to `GitHub Branch: master` or remove it completely. Tags will be ignored when a branch other than `master` is specified. In this case I would suggest semantic versioning similar to the following, `<major>.<minor>.<patch>.<development>`.

## Tagging

If `GitHub Branch` or `Bitbucket Branch` is not specified (or is set to `master`), then the latest tag will be used. GitHub Updater will preferentially use a tag over a branch in this instance.

## Bitbucket Support

Instead of the `GitHub Plugin URI` header you will need to use the `Bitbucket Plugin URI` header.

Instead of the `GitHub Theme URI` header you will need to use the `Bitbucket Theme URI` header.

The `Bitbucket Branch` header is supported for both plugins and themes.

## Private Repositories

### GitHub Private Repositories

In order to specify a private repository you will need to obtain a [personal access token](https://github.com/settings/tokens/new). Once you have this, simply add the token to the appropriate plugin or theme in the Settings page.

Leave this empty if the plugin or theme is in a public repository.

### Bitbucket Private Repositories

In order to specify a private repository you will need to add your Bitbucket password to the appropriate plugin or theme in the Settings page.

Leave this empty if the plugin or theme is in a public repository.

Regrettably, I still get an error when trying to download a Bitbucket private repository. I could use some [help in figuring this one out](https://github.com/afragen/github-updater/issues/59), though it seems Bitbucket knows this is an issue and won't fix. If someone wants to figure out and create a PR for oAuth...

## WordPress and PHP Requirements

There are now two **optional** headers for setting minimum requirements for both WordPress and PHP.

Use `Requires WP:` to set the minimum required version of WordPress needed for your plugin or theme. eg. `Requires WP: 3.8`

Use `Requires PHP:` to set the minimum required version of PHP needed for your plugin or theme. eg. `Requires PHP: 5.3`

At the moment the default values are **WordPress 0.0.0** and **PHP 5.2.3**

## Deleting Transients

If you use the **Check Again** button in the WordPress Updates screen then all the transients will be deleted and the API will be queried again. Be careful about refreshing the browser window after this as you may be continually deleting the transients.

## Hosting Plugin in WP.org Repository

If you develop your plugin on GitHub and it also resides in the WP.org repo, the plugin will preferentially pull updates from WP.org if `GitHub Branch: master`. If `GitHub Branch` is anything other than `master` then the update will pull from GitHub. Make sure that the version of your plugin uploaded to WP.org has `GitHub Branch: master`.

The same applies for Bitbucket hosted plugins.

## Extras

[szepeviktor](https://github.com/szepeviktor) has created an add-on plugin to GitHub Updater that identifies all plugins with an icon in the plugin view for GitHub or Bitbucket depending upon where they get updates. It's very clever.
<https://github.com/szepeviktor/wordpress-plugin-construction/tree/master/github-link>

## Issues

Please log issues on the GitHub at https://github.com/afragen/github-updater/issues

If you are using a WordPress Multisite installation, the plugin **should** be network activated.

When first downloading and installing a plugin from GitHub you might have to do the following, otherwise the next update may not be able to cleanup after itself and re-activate the updated plugin or theme.

1. Unzip the archive.
2. Fix the folder name to remove to extra stuff GitHub adds to the download, like _-master_.
3. Copy the folder to your plugins directory.

## ChangeLog

See [CHANGES.md](CHANGES.md). In your project create a `CHANGES.md` or `CHANGELOG.md` file.

## Credits

This plugin's theme updater class was based upon [Whitelabel Framework's updater-plugin.php](https://github.com/WordPress-Phoenix/whitelabel-framework/blob/master/inc/admin/updater-plugin.php), which was based upon https://github.com/UCF/Theme-Updater.

The plugin updater class was based upon [codepress/github-plugin-updater](https://github.com/codepress/github-plugin-updater).

Includes [Emanuil Rusev's](https://github.com/erusev) [Parsedown](https://github.com/erusev/parsedown) for rendering ChangeLogs.

## Pull Requests

Please fork and submit pull requests against the `develop` branch.
