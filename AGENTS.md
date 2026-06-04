# Plan: Diagnose and fix PCLZIP_ERR_BAD_FORMAT in git-updater-lite downloads

## Problem

Updating via git-updater-lite fails with:
```
PCLZIP_ERR_BAD_FORMAT (-10) : Unable to find End of Central Dir Record signature
```

This means WordPress received a file that is not a valid zip. The download flow is:

```
Lite client                          Server (REST_API)
    │                                      │
    ├─ GET /download-token/{slug}  ──────► │ get_download_token()
    │                                      │   → build_download_metadata()
    │   ◄── { download_link: signed_url } │   → sign_download_url(slug, 60)
    │                                      │
    ├─ download_url(signed_url)  ────────► │ proxy_download()
    │                                      │   → verify_download_signature()
    │                                      │   → build_download_metadata()
    │                                      │   → wp_remote_get(upstream, stream:true)
    │                                      │   → send_file_response() → exit
    │   ◄── zip bytes (or not?)           │
    │                                      │
    ├─ WordPress tries to unzip            │
    └─ PCLZIP_ERR_BAD_FORMAT              │
```

## Root cause analysis

`PCLZIP_ERR_BAD_FORMAT` means `download_url()` returned a **file path** (not WP_Error), so the HTTP status was 200, but the file content is not a valid zip. Possible causes:

### Cause 1: Upstream returns non-zip on HTTP 200
`proxy_download()` checks `$status_code === 200` but does not validate the response is actually a zip. If the upstream provider returns an HTML error page, a redirect body, or a partial response with 200 status, the corrupt file is streamed to the client.

### Cause 2: `send_file_response()` uses `exit` instead of REST response
`proxy_download()` calls `send_file_response()` which uses raw `header()`, `readfile()`, and `exit`. This bypasses the REST API response pipeline. If any output has been buffered or sent before `send_file_response()`, the file content may be corrupted. The REST server never gets to finalize the response.

### Cause 3: Token expiry race (less likely)
The signed URL has a 60-second TTL. If the client is slow between token fetch and download, the URL expires. `proxy_download()` returns a 403 WP_Error → `download_url()` returns WP_Error → lite client returns WP_Error → WordPress catches it. This should NOT produce PCLZIP_ERR_BAD_FORMAT, so this is unlikely the cause but worth hardening.

## Plan

### Step 1: Validate upstream response in `proxy_download()` (server)

In `proxy_download()`, after `wp_remote_get()` succeeds with 200, verify the response is actually a zip before streaming.

**File:** `src/Git_Updater/REST/REST_API.php`

- After `$body_file = wp_remote_retrieve_body( $upstream )`, check that `$body_file` is not empty
- Check that the file starts with the zip magic bytes (`PK\x03\x04` — the local file header signature)
- If validation fails, return a WP_Error instead of streaming corrupt data

This catches Cause 1 (upstream returns non-zip on 200).

### Step 2: Replace `exit` with proper REST response in `proxy_download()` (server)

The `send_file_response()` method uses `exit` to terminate after streaming. This bypasses the REST API response pipeline and can corrupt output. Replace it with a proper `WP_REST_Response` that streams the file.

**File:** `src/Git_Updater/REST/REST_API.php`

- In `proxy_download()`, instead of calling `send_file_response()` → `exit`, return a `WP_REST_Response` with the file body
- Set proper headers (`Content-Type: application/zip`, `Content-Disposition`, `Content-Length`)
- Remove `send_file_response()` and `register_temp_file_cleanup()` (or keep cleanup as shutdown function)
- The temp file can be cleaned up after the response is sent via a shutdown function or `rest_post_send_before` action

This fixes Cause 2 (exit corrupts REST response).

### Step 3: Add zip validation in lite client (client)

In the lite client's `upgrader_pre_download` hook, after `download_url()` returns a file path, validate the file before returning it to WordPress.

**File:** `git-updater-lite/Lite.php`

- After `$temp_file = download_url( $fresh_url, 300 )`, check that the file exists and starts with zip magic bytes
- If invalid, delete the temp file and return a `WP_Error` with a descriptive message
- This is a defense-in-depth measure — the server should also validate, but the client should not trust the content

### Step 4: Extend token TTL if needed (server, optional)

The 60-second TTL may be tight for slow connections. Consider extending to 300 seconds (5 minutes) to reduce expiry races.

**File:** `src/Git_Updater/REST/REST_API.php`

- In `get_download_token()`, change `sign_download_url( $slug, 60 )` to `sign_download_url( $slug, 300 )`
- This is a defensive hardening, not the root cause

### Step 5: Add error logging (server)

Add logging when `proxy_download()` encounters unexpected conditions (non-zip response, empty body) to aid future debugging.

**File:** `src/Git_Updater/REST/REST_API.php`

- Log upstream status code, content type, and body size when validation fails
- Use `error_log()` or the existing git-updater logging mechanism

### Step 6: Tests

**File:** `tests/test-rest-api.php` or `tests/test-rest-download-proxy.php`

- Add test for `proxy_download()` when upstream returns non-zip content (200 status but HTML body)
- Add test for `proxy_download()` when upstream returns empty body
- Add test for zip magic byte validation
- Update existing `send_file_response` tests if the method signature changes

## Verification

- Run `npm test` — all tests must pass
- Manual test: trigger a git-updater-lite update and verify the zip downloads and installs correctly
- Test error cases: mock upstream returning HTML on 200, verify WP_Error is returned
