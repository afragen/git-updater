# Auth Token Hardening — Signed Download Proxy

## Problem

The `update-api` REST endpoint (`GET /wp-json/git-updater/v1/update-api/?slug=<slug>`) is publicly accessible (`permission_callback: __return_true`) and returns cleartext auth tokens in the JSON response body for private repositories and repos with per-slug tokens. Anyone who knows a private repo's slug can retrieve its bearer token.

**Current response for private repos:**
```json
{
  "download_link": "https://github.com/owner/repo/archive/v1.0.0.zip",
  "auth_header": {
    "headers": {
      "Authorization": "Bearer ghp_xxx",
      "github": "repo-slug",
      "Accept": "application/octet-stream"
    }
  }
}
```

**Consumer:** git-updater-lite fetches this endpoint, extracts `auth_header`, and injects it as HTTP headers when WordPress downloads the package zip. Lite checks `property_exists($this->api_data, 'auth_header')` — if the field is absent, no headers are injected (no-op).

## Approach: Signed Download Proxy

Instead of returning the auth token, replace `download_link` with a signed, time-limited proxy URL that points back to the server. The server fetches the upstream package using its stored auth token and streams it to the client.

**New flow:**
1. `update-api` returns `download_link` pointing to: `/wp-json/git-updater/v1/download/{slug}?expires={ts}&signature={hmac}`
2. `auth_header` is **removed** from the response entirely
3. When WordPress downloads from the proxy URL, the `download` endpoint:
   - Validates the HMAC signature and expiry (5 min TTL)
   - Reconstructs the real upstream download URL and auth headers (same logic as current `get_api_data`)
   - Streams the upstream response back to the client

**Why this works for git-updater-lite:**
- `download_link` is still a URL — the upgrader uses it directly
- `auth_header` is absent — lite's `add_auth_header()` becomes a no-op
- **No changes required in git-updater-lite**

## Scope: Only Repos Requiring Auth

The proxy applies **only** when auth headers would have been returned. Public repos that don't need auth continue to download directly from the git source.

| Repo type | Current behavior | Proposed behavior |
|---|---|---|
| **Public GitHub/Bitbucket, no token** | `auth_header` already stripped, direct download link | **No change** — direct download link, no proxy |
| **Private repo (any host)** | `Authorization` header in response | **Proxy** — signed URL, no auth in response |
| **Repo with per-slug token** | `Authorization` header in response | **Proxy** — signed URL, no auth in response |
| **Public GitLab/Gitea** | Auth header in response (these hosts require auth) | **Proxy** — signed URL, no auth in response |

The condition mirrors the existing `$private_or_token` + gitlab/gitea check in `get_api_data()`.

## Files Modified

### `src/Git_Updater/REST/REST_API.php`

#### New route registration

Added after existing `update-api` route:
```
GET /wp-json/git-updater/v1/download/{slug}?expires={ts}&signature={hmac}
```

#### New methods

- **`sign_download_url( string $slug, int $ttl_seconds = 300 ): string`** — Generates HMAC-SHA256 signed proxy URLs using `wp_salt('auth')` as the secret.

- **`verify_download_signature( string $slug, int $expires, string $signature ): bool`** — Validates signature and expiry using `hash_equals()` for timing-safe comparison.

- **`proxy_download( WP_REST_Request $request ): WP_Error|void`** — Core proxy handler. Validates signature, resolves upstream URL + auth headers via `build_download_metadata()`, fetches from git host with `wp_remote_get( stream => true )`, streams zip to client. Uses `register_shutdown_function` for temp file cleanup.

- **`build_download_metadata( string $slug ): array|WP_Error`** — Resolves upstream download URL and auth headers server-side. Includes all three header-building steps:
  1. `API::add_auth_header()` — Authorization + git-server identification
  2. `API::unset_release_asset_auth()` — strips auth from AWS/S3 URLs
  3. `API::add_accept_header()` — `Accept: application/octet-stream` for GitHub release assets

#### Modified `get_api_data()`

For the `update-api` route only:
- Conditionally replaces `download_link` with signed proxy URL when auth is required
- Always removes `auth_header` from response

For `plugins-api`/`themes-api`: existing behavior unchanged.

## Failure Modes

| Failure | Proxy response | Client (lite) experience |
|---|---|---|
| Signature expired/tampered | HTTP 403 | Download fails, retries next cron check |
| Slug not found / private_package | HTTP 404 | Download fails, retries next cycle |
| Upstream network error | HTTP 502 | Download fails, retries next cycle |
| Upstream HTTP error (403/404) | HTTP 502 | Persists until token/config fixed |
| Disk full / temp file failure | HTTP 500 | Shutdown handler cleans up temp file |
| Client disconnect mid-stream | Script continues, temp file cleaned | Partial zip detected, retry next cycle |

## Signed URL TTL

Two TTLs are in use, and both must stay at least as long as the cache that stores the link:

- **Proxy URLs** (`/download/{slug}`) default to **12 hours** (`sign_download_url()`, matching the `API::$hours` repo-cache window). The link is persisted inside the `update_plugins` / `update_themes` transients and reused whenever the admin acts, so a shorter TTL would hand the upgrader an expired signature — e.g. metadata fetched now, "Update" clicked six hours later.
- **Token URLs** (`/download-token/{slug}`, the lite 2-step flow) use **60 seconds**, because the client mints and redeems them immediately.

Earlier revisions of this document said 5 minutes; that was never the implemented default and is not the current behaviour.

> Historical note: the pre-fix shape described at the top of this document (cleartext `auth_header` in the `update-api` response) no longer exists. `auth_header` is built only in `build_download_metadata()` and is consumed server-side by `proxy_download()`; it is never returned to a client on any route.

## Verification

1. `verify_download_signature()` with valid, expired, and tampered signatures
2. `proxy_download()` with a mock upstream returning a known zip
3. Manual: curl `update-api` — confirm `auth_header` absent, `download_link` points to proxy for private repos
4. Manual: curl proxy URL — confirm zip downloads; after expiry — confirm 403
5. Public repo test: confirm `download_link` still points directly to github.com
6. Release asset test: confirm `Accept: application/octet-stream` sent upstream
7. git-updater-lite integration: install lite on separate site, confirm updates work
8. `npm test` / `npm run test:multisite` — all existing tests pass
9. `composer lint` — PHPCS clean

## Accepted residuals

Three low-severity residuals are accepted by design:

1. **Token-backed metadata fetch before the private-package 403.** In `get_api_data()` the
   private-package gate runs *after* `get_remote_repo_meta()`, and `gu_disable_wpcron` is forced
   false, so an anonymous caller of a private slug can still trigger one upstream call using the
   site's stored token before the 403 is returned. The package bytes are never served. An early
   deny would require trusting a cached `is_private`, which conflicts with the chosen
   fail-open-on-missing-signal trade-off. Accepted.

2. **Authorization forwarded across cross-host redirects.** The credential is attached only when
   the resolved host is in the provider's `gu_credential_hosts` set (destination-scoped
   fail-closed), but WordPress' bundled Requests re-sends the original `Authorization` header on a
   3xx to a different host. No `requests-requests.before_redirect` strip is installed: the initial
   request is already host-scoped and the marginal risk is a host the site already trusts for code.
   Accepted.

3. **No replay protection on the Lite client key.** `X-GU-Lite-Key` is a static bearer key; a
   captured key is replayable until its grant is revoked. Accepted over TLS on a short-lived
   download URL.

## Lite client key (private packages)

Private packages are gated on an approved Lite client key, not the removed `X-GU-Site-Domain`
header. Each `git-updater-lite` site generates its own 256-bit key lazily (only when it meets a
private package) and sends it as `X-GU-Lite-Key`; the server stores only its SHA-256 hash and
serves the package once an administrator approves the client. See
[lite-update-flow.md](./lite-update-flow.md) and [private-package-rollout.md](./private-package-rollout.md).
