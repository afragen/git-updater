# Git Updater

![downloads](https://img.shields.io/github/downloads/afragen/git-updater/total) ![downloads@latest](https://img.shields.io/github/downloads/afragen/git-updater/latest/total)

![WordPress Tests](https://github.com/afragen/git-updater/workflows/WordPress%20Tests/badge.svg)

* Contributors: [Andy Fragen](https://github.com/afragen), [contributors](https://github.com/afragen/git-updater/graphs/contributors)
* Tags: plugin, theme, update, github, language pack
* Requires at least: 5.9
* Requires PHP: 7.4
* Stable tag: [master](https://github.com/afragen/git-updater/releases/latest)
* Donate link: <https://thefragens.com/git-updater-donate>
* License: MIT

A simple plugin to enable automatic updates to your GitHub hosted WordPress plugins, themes, and language packs. Additional API plugins available for Bitbucket, GitLab, Gitea, and Gist.

[Comprehensive information regarding Git Updater is available in the Knowledge Base.](https://git-updater.com/knowledge-base)

[Install the latest version here.](https://github.com/afragen/git-updater/releases/latest)

## Description

This plugin was originally designed to simply update any GitHub hosted WordPress plugin or theme. Your plugin or theme **must** contain a header in the style.css header or in the plugin's header denoting the location on GitHub. The format is as follows.

    GitHub Plugin URI: afragen/git-updater
    GitHub Plugin URI: https://github.com/afragen/git-updater

or

    GitHub Theme URI: afragen/test-child
    GitHub Theme URI: https://github.com/afragen/test-child

...where the above URI leads to the __owner/repository__ of your theme or plugin. The URI may be in the format `https://github.com/<owner>/<repo>` or the short format `<owner>/<repo>`. You do not need both. Only one Plugin or Theme URI is required. You **should not** include any extensions like `.git`.

### API Plugins

API plugins for Bitbucket, GitLab, Gitea, and Gist are available. API plugins are available for a one-click install from the **Add-Ons** tab.

* [Git Updater - Bitbucket](https://github.com/afragen/git-updater-bitbucket/releases/latest)
* [Git Updater - GitLab](https://github.com/afragen/git-updater-gitlab/releases/latest)
* [Git Updater - Gitea](https://github.com/afragen/git-updater-gitea/releases/latest)
* [Git Updater - Gist](https://github.com/afragen/git-updater-gist/releases/latest)

### Sponsor

Purchase a license at the [Git Updater Store](https://git-updater.com/store/). An unlimited yearly license is very reasonable and allows for authenticated API requests. There is an initial free trial period. After the trial period Git Updater will not be able to make authenticated API requests.

You can [sponsor me on GitHub](https://github.com/sponsors/afragen) to help with continued development and support.

## Slack

We now have a [Slack team for Git Updater](https://git-updater.slack.com). Please [click here for an invite](https://join.slack.com/t/git-updater/shared_invite/zt-1extq97hy-FjA1QAhjGNDzmFjjlRv3rg). You will be automatically added to the _#general_ and _#support_ channels. Please take a look at other channels too.

## Translations

If you are a polyglot I would greatly appreciate translation contributions to [GlotPress for Git Updater](https://translate.git-updater.com).
