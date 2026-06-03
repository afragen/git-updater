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

Proxy URLs expire in 5 minutes. Lite caches API responses for 6 hours. Stale downloads fail with 403; WordPress re-checks on next cycle. This is acceptable — a brief delay for stale-cache scenarios. The 5-minute TTL is the secure default.

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
