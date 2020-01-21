![GitHub Updater](./assets/GitHub_Updater_logo_small.png)

# GitHub Updater

[![Build Status](https://travis-ci.org/afragen/github-updater.svg?branch=develop)](https://travis-ci.org/afragen/github-updater)

* Contributors: [Andy Fragen](https://github.com/afragen), [Gary Jones](https://github.com/GaryJones), [Seth Carstens](https://github.com/sethcarstens), [Mikael Lindqvist](https://github.com/limikael), [contributors](https://github.com/afragen/github-updater/graphs/contributors)
* Tags: plugin, theme, update, updater, github, bitbucket, gitlab, remote install
* Requires at least: 4.6
* Requires PHP: 5.6
* Tested up to: 5.4
* Stable tag: [master](https://github.com/afragen/github-updater/releases/latest)
* Donate link: <https://thefragens.com/github-updater-donate>
* License: GPLv2 or later
* License URI: <https://www.gnu.org/licenses/gpl-2.0.html>

A simple plugin to enable automatic updates to your GitHub, Bitbucket, GitLab, or Gitea hosted WordPress plugins, themes, and language packs. It also allows for the remote installation of plugins or themes.

[Comprehensive information regarding GitHub Updater is available on the wiki.](https://github.com/afragen/github-updater/wiki)

## Description

This plugin was designed to simply update any GitHub hosted WordPress plugin or theme. Your plugin or theme **must** contain a header in the style.css header or in the plugin's header denoting the location on GitHub. The format is as follows.

    GitHub Plugin URI: afragen/github-updater
    GitHub Plugin URI: https://github.com/afragen/github-updater

or

    GitHub Theme URI: afragen/test-child
    GitHub Theme URI: https://github.com/afragen/test-child

...where the above URI leads to the __owner/repository__ of your theme or plugin. The URI may be in the format `https://github.com/<owner>/<repo>` or the short format `<owner>/<repo>`. You do not need both. Only one Plugin or Theme URI is required. You **should not** include any extensions like `.git`.

## Slack

We now have a [Slack team for GitHub Updater](https://github-updater.slack.com). Please [click here for an invite](https://github-updater.herokuapp.com). You will be automatically added to the _#general_ and _#support_ channels. Please take a look at other channels too.
