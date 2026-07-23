# GitHub OAuth Setup — App Types, Repository Access, and Connector Configuration

## Architecture

Git Updater never talks to GitHub's OAuth endpoints directly. A hosted connector plugin (git-updater-oauth-connector) holds the app credentials and brokers the flow:

```
WordPress site (git-updater)
    │  1. "Connect" button → redirects admin to connector
    ▼
Connector host (git-updater-oauth-connector)
    │  2. Redirects to GitHub (authorize or installation URL)
    ▼
GitHub
    │  3. User authorizes / installs → callback to connector with code
    ▼
Connector host
    │  4. Exchanges code for token, hands it back via one-time exchange code
    ▼
WordPress site
    5. Stores access_token, refresh_token, expires_in in `git_updater` site option
```

Token refresh follows the same brokered path: the site POSTs its refresh token to the connector's `/git-updater/github/oauth/refresh` endpoint, which calls GitHub and returns the new (rotated) token pair.

## Choosing an App Type

GitHub offers two app types. Both use the same authorize/token endpoints and both support expiring user tokens (8-hour access token, 6-month refresh token). They differ in how repository access is granted.

| | OAuth App | GitHub App |
|---|---|---|
| Client ID format | 20-char hex | Starts with `Iv` (e.g. `Iv1.…`) |
| Access token prefix | `gho_` | `ghu_` |
| Token expiry | Off by default — enable **"Expire user authorization tokens"** in app settings | On by default — do **not** opt out under Optional Features |
| Permission model | OAuth scopes (connector sends `scope=repo`) | Fine-grained permissions from app registration (`Contents: Read-only`) |
| Repo access granted by | The token itself — every repo the user can access | Per-account **installation** with repository selection |
| Repos another user owns (user is a collaborator) | **Automatic** — covered by `repo` scope | **Owner must install the app** on their account |
| Tokens per user | **10 per app/scope** — the 11th authorization silently revokes the oldest | No documented limit |
| GitHub recommendation | Legacy | Recommended |

## Repository Access Matrix (the important part)

How a repo becomes accessible to the connected site, per app type:

| Repository location | OAuth App (`repo` scope) | GitHub App |
|---|---|---|
| User's own repos | Automatic | User installs app on their account, selects repos (or "All repositories") |
| Another user's repo (user is collaborator) | **Automatic** | **The repo owner must install the app** on their own account and select that repo. Only then can the collaborator's `ghu_` token access it (token = app-installed repos ∩ repos the user can access) |
| Organization repos | Automatic | An org owner installs the app on the org and selects repos |
| Repos created after connecting | Automatic | Automatic only if "All repositories" was selected at install time; otherwise the installation must be updated to add them |

**Consequence for multi-user connectors:** if users commonly work with repos owned by clients or other users, the GitHub App requires every one of those owners to install the app. The OAuth App has no such friction — every repo the user can access works immediately.

## Connector Behavior by App Type

The connector detects the app type from the client ID and adjusts automatically:

- **GitHub App (`Iv*` client ID) + `GIT_UPDATER_GITHUB_APP_SLUG` set** — users are sent to `https://github.com/apps/<slug>/installations/new?state=…`, where they select repositories. With **"Request user authorization (OAuth) during installation"** enabled in the app settings, installation also completes OAuth authorization and redirects to the callback with `code` + `state`.
- **GitHub App without the slug** — falls back to `/login/oauth/authorize` (no repository selection possible; logs a `github_app_slug_missing` security event).
- **OAuth App** — sent to `/login/oauth/authorize?…&scope=repo` (unchanged legacy behavior).

Token exchange and refresh are identical for both: the connector captures `expires_in`, `refresh_token`, and `refresh_token_expires_in` when GitHub returns them, and git-updater stores and refreshes them transparently. Apps without expiring tokens keep working unchanged (no expiry metadata stored, refresh machinery dormant).

## Setup: GitHub App

1. GitHub → **Settings → Developer settings → GitHub Apps → New GitHub App**.
2. **Callback URL**: `https://<connector-host>/git-updater/github/oauth/callback`.
3. Check **"Request user authorization (OAuth) during installation"** so installation also performs OAuth authorization.
4. **Webhook**: uncheck **Active**.
5. **Permissions → Repository**: `Contents: Read-only` (`Metadata: Read-only` is auto-granted).
6. **Where can this GitHub App be installed?**: "Any account" for multi-user connectors.
7. **Optional Features → User-to-server token expiration**: leave enabled (default).
8. Copy the **Client ID** and generate a **Client secret**. Find the **app slug** in the "Public link" field (`https://github.com/apps/<slug>`) — do not guess it from the app name.

## Setup: OAuth App (simpler alternative)

1. GitHub → **Settings → Developer settings → OAuth Apps → New OAuth App**.
2. **Authorization callback URL**: `https://<connector-host>/git-updater/github/oauth/callback`.
3. Enable **"Expire user authorization tokens"** so tokens expire and can be refreshed.
4. Copy the Client ID and generate a Client secret.

## Connector Host Configuration

Resolved environment-variable-first, then PHP constant (`src/Configuration/GitHubConfiguration.php`):

```php
// wp-config.php on the connector host
define( 'GIT_UPDATER_GITHUB_CLIENT_ID', 'Iv1.…' );      // or 20-char hex for OAuth App
define( 'GIT_UPDATER_GITHUB_CLIENT_SECRET', '…' );
define( 'GIT_UPDATER_GITHUB_APP_SLUG', 'git-updater-oauth-connector' ); // GitHub App only
```

Optional debug observability — logs which expiring-token fields GitHub returned (never the values):

```php
define( 'GIT_UPDATER_OAUTH_CONNECTOR_DEBUG', true );
```

## Multi-Site and Concurrency Behavior

- **Per-flow isolation**: each authorization gets a unique 32-hex `state` (600s TTL) caching that site's return URL, and a unique one-time `exchange_<code>` for the token handoff. Concurrent flows from many sites never collide.
- **Per-site tokens**: each site stores its own access/refresh pair. Refresh rotation on one site is independent of all others.
- **Same-site concurrent refreshes**: git-updater's lock + result transients (`gu_oauth_refresh_lock_github` 30s, `gu_oauth_refresh_result_github` 60s) prevent duplicate refreshes; a stale-refresh-token race self-heals on the next attempt.
- **Connector rate limiting**: per client IP, failed attempts only (10 per 5 min) — normal concurrent use doesn't accumulate.
- **GitHub API rate limits** are per GitHub user (5,000 req/hr): one GitHub account connecting many sites shares that budget across all of them (same as reusing one PAT).
- **OAuth App token limit**: 10 tokens per user/app/scope — the 11th site a single GitHub user connects silently revokes the oldest site's token. GitHub App user tokens have no documented limit.

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| `https://github.com/apps/<slug>/installations/new` returns 404 | Wrong slug (slugs come from the app name and may differ from guesses), or app is private ("Only on this account") and you're not the owner | Copy the slug from the app's **Public link** field in General settings |
| Connect flow never offers repository selection | Connector is using the plain authorize URL — app slug not configured | Set `GIT_UPDATER_GITHUB_APP_SLUG` on the connector host |
| Connected but private repos invisible | App authorized but not installed on the repo owner's account | Repo owner installs the app via the public link and selects the repo — or switch to an OAuth App |
| `state_invalid` error during connect | Authorization took longer than the 600s state TTL, or `state` was dropped | Restart the connect flow |
| No `expires_in` in token response | OAuth App without the expiry opt-in, or GitHub App opted out | Enable "Expire user authorization tokens" (OAuth App) / don't opt out (GitHub App) |
| Refresh fails with `invalid_grant` | Refresh token expired (~6 months unused) or already rotated | Disconnect and reconnect via Settings → GitHub |
| A site's token suddenly stops working after another site connects (OAuth App) | 10-token per user/app/scope limit — oldest was revoked | Reconnect the affected site, or migrate to a GitHub App |
