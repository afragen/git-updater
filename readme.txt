# GitHub Updater
Contributors: afragen, garyj, sethcarstens, limikael
Donate link: https://thefragens.com/github-updater-donate
Tags: plugin, theme, language pack, updater, remote install
Requires at least: 4.6
Requires PHP: 5.6
Tested up to: 5.4
Stable tag: master
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

## Description

This plugin was designed to simply update any GitHub hosted WordPress plugin or theme. Currently, plugins or themes hosted on GitHub, Bitbucket, GitLab, or Gitea are also supported. Additionally, self-hosted installations of GitHub or GitLab are supported. It also allows for remote installation of plugins or themes into WordPress.

Your plugin or theme **must** contain a header in the style.css header or in the plugin's header denoting the location on GitHub. The format is as follows.

    GitHub Plugin URI: afragen/github-updater
    GitHub Plugin URI: https://github.com/afragen/github-updater

or

    GitHub Theme URI: afragen/test-child
    GitHub Theme URI: https://github.com/afragen/test-child

...where the above URI leads to the __owner/repository__ of your theme or plugin. The URI may be in the format `https://github.com/<owner>/<repo>` or the short format `<owner>/<repo>`. You do not need both. Only one Plugin or Theme URI is required. You **must not** include any extensions like `.git`.

The following headers are available for use depending upon your hosting source.

### GitHub
* GitHub Plugin URI
* GitHub Theme URI
* GitHub Languages

### Bitbucket
* Bitbucket Plugin URI
* Bitbucket Theme URI
* Bitbucket Languages

### GitLab
* GitLab Plugin URI
* GitLab Theme URI
* GitLab Languages
* GitLab CI Job

### Gitea
* Gitea Plugin URI
* Gitea Theme URI
* Gitea Languages

## Frequently Asked Questions

#### Wiki

[Comprehensive information regarding GitHub Updater is available on the wiki.](https://github.com/afragen/github-updater/wiki)

#### Slack

We now have a [Slack team for GitHub Updater](https://github-updater.slack.com). Please [click here for an invite](https://github-updater.herokuapp.com). You will be automatically added to the _#general_ and _#support_ channels. Please take a look at other channels too.
