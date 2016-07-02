=== GitHub Updater ===
Contributors: afragen, garyj, sethmatics
Donate link: http://thefragens.com/github-updater-donate
Tags: plugin, theme, update, updater, github, bitbucket, gitlab, remote install
Requires at least: 4.0
Tested up to: 4.6
Stable tag: master
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

This plugin was designed to simply update any GitHub hosted WordPress plugin or theme. Currently, plugins or themes hosted on GitHub, Bitbucket, or GitLab are also supported. Additionally, self-hosted installations of GitHub or GitLab are supported. It also allows for remote installation of plugins or themes into WordPress.

Your plugin or theme **must** contain a header in the style.css header or in the plugin's header denoting the location on GitHub. The format is as follows.

`GitHub Plugin URI: afragen/github-updater`
`GitHub Plugin URI: https://github.com/afragen/github-updater`

or

`GitHub Theme URI: afragen/test-child`
`GitHub Theme URI: https://github.com/afragen/test-child`

...where the above URI leads to the __owner/repository__ of your theme or plugin. The URI may be in the format `https://github.com/<owner>/<repo>` or the short format `<owner>/<repo>`. You do not need both. Only one Plugin or Theme URI is required. You **must not** include any extensions like `.git`.

The following headers are available for use depending upon your hosting source.

### GitHub
* GitHub Plugin URI
* GitHub Theme URI
* GitHub Branch
* GitHub Enterprise

###Bitbucket
* Bitbucket Plugin URI
* Bitbucket Theme URI
* Bitbucket Branch

###GitLab
* GitLab Plugin URI
* GitLab Theme URI
* GitLab Branch
* GitLab Enterprise
* GitLab CE

== WordPress and PHP Requirements ==

There are two **optional** headers for setting minimum requirements for both WordPress and PHP.

Use `Requires WP:` to set the minimum required version of WordPress needed for your plugin or theme. eg. `Requires WP: 3.8`

Use `Requires PHP:` to set the minimum required version of PHP needed for your plugin or theme. eg. `Requires PHP: 5.3.0`

At the moment the default values are **WordPress 3.8.0** and **PHP 5.3.0**

== Release Assets ==

An **optional header** is available for use if your plugin or theme requires updating via a release asset.

Use `Release Asset:`. eg., `Release Asset: true`.

Your release asset filename is generated automatically and **must** have the following format or there will be an update error.

Example, `$repo-$tag.zip` where `$repo` is the repository slug and ``$tag` is the newest release tag, example `test-plugin-0.7.3.zip`.

**You must tag your releases to use this feature.**

== Developer Hooks ==

There are 2 added filter hooks specifically for developers wanting to distribute private themes/plugins to clients without the client having to interact with the Settings page.

The first allows the developer to set the GitHub Access Token for a specific plugin or theme. The anonymous function must return a **single** key/value pair where the key is the plugin/theme repo slug and the value is the token.

`
add_filter( 'github_updater_token_distribution',
	function () {
		return array( 'my-private-theme' => 'kjasdp984298asdvhaljsg984aljhgosrpfiu' );
	} );
`

The second hook will simply make the Settings page unavailable.

`add_filter( 'github_updater_hide_settings', '__return_true' );`

