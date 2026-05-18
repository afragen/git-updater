# Git Updater

![downloads](https://img.shields.io/github/downloads/afragen/git-updater/total) ![downloads@latest](https://img.shields.io/github/downloads/afragen/git-updater/latest/total)

![WordPress Tests](https://github.com/afragen/git-updater/workflows/WordPress%20Tests/badge.svg)

* Contributors: [Andy Fragen](https://github.com/afragen), [contributors](https://github.com/afragen/git-updater/graphs/contributors)
* Tags: packages, update, github, language pack
* Requires at least: 5.9
* Requires PHP: 8.0
* Stable tag: [master](https://github.com/afragen/git-updater/releases/latest)
* Donate link: <https://thefragens.com/git-updater-donate>
* License: GPL-3.0-or-later

A simple plugin to enable automatic updates to your GitHub hosted WordPress plugins, themes, and language packs. Additional API plugins available for Bitbucket, GitLab, Gitea, and Gist.

[Comprehensive information regarding Git Updater is available in the Knowledge Base.](https://git-updater.com/knowledge-base)

[Install the latest version here.](https://github.com/afragen/git-updater/releases/latest)

## Description

This plugin was originally designed to simply update any GitHub hosted WordPress plugin or theme. Your plugin or theme **must** contain a header in the style.css header or in the plugin's header denoting the location on GitHub. The format is as follows.

    GitHub Plugin URI: https://github.com/afragen/git-updater

or

    GitHub Theme URI: https://github.com/afragen/test-child

...where the above URI leads to the __owner/repository__ of your theme or plugin. The URI format is `https://github.com/<owner>/<repo>`. You **should not** include any extensions like `.git`.

### API Plugins

API plugins for Bitbucket, GitLab, Gitea, and Gist are available. API plugins are available for a one-click install from the **Add-Ons** tab.

* [Git Updater - Bitbucket](https://github.com/afragen/git-updater-bitbucket/releases/latest)
* [Git Updater - GitLab](https://github.com/afragen/git-updater-gitlab/releases/latest)
* [Git Updater - Gitea](https://github.com/afragen/git-updater-gitea/releases/latest)
* [Git Updater - Gist](https://github.com/afragen/git-updater-gist/releases/latest)

### OAuth for API providers

Git Updater includes a reusable OAuth 2.0 authorization-code flow with PKCE for Git API providers. The bundled GitHub settings tab can use it to save an OAuth token into the existing `github_access_token` option, and API add-ons can reuse the same callback/state/token exchange helper for their own providers.

For GitHub, create an OAuth app with the callback URL shown on the GitHub settings tab. Then set `GU_GITHUB_OAUTH_CLIENT_ID` in `wp-config.php`, optionally set `GU_GITHUB_OAUTH_CLIENT_SECRET` and `GU_GITHUB_OAUTH_SCOPE`, or return those values with the `gu_github_oauth_credentials` filter. Use **Authorize via GitHub OAuth** to complete the flow.

### Sponsor

Purchase a license at the [Git Updater Store](https://git-updater.com/store/). An unlimited yearly license is very reasonable and allows for authenticated API requests. There is an initial free trial period. After the trial period Git Updater will not be able to make authenticated API requests.

You can [sponsor me on GitHub](https://github.com/sponsors/afragen) to help with continued development and support.

## Slack

We now have a [Slack team for Git Updater](https://git-updater.slack.com). Please [click here for an invite](https://join.slack.com/t/git-updater/shared_invite/zt-1extq97hy-FjA1QAhjGNDzmFjjlRv3rg). You will be automatically added to the _#general_ and _#support_ channels. Please take a look at other channels too.

## Translations

If you are a polyglot I would greatly appreciate translation contributions to [GlotPress for Git Updater](https://translate.git-updater.com).
