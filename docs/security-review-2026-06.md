# Git Updater — Security Review (June 2026)

Branch reviewed: `develop` (commits up to `c3e2dd94`).
Scope: full codebase audit — all PHP under `src/Git_Updater/`, the bootstrap file, and admin/REST/AJAX surfaces.
Output: written findings + concrete patch shapes. No source code has been modified by this review.

## Executive summary

| ID  | Severity | Title                                                                           | Files                                                |
| --- | -------- | ------------------------------------------------------------------------------- | ---------------------------------------------------- |
| H1  | High     | Non-timing-safe API key comparison on four webhook endpoints                    | `REST/REST_API.php`, `REST/Rest_Update.php`          |
| H2  | High     | API key generated with `md5( uniqid( wp_rand(), true ) )`                       | `Remote_Management.php:43`                           |
| H3  | High     | OAuth callback has no `state` parameter — token-injection CSRF risk             | `OAuth/OAuth_Connect.php`                            |
| H4  | High     | Webhook responses reflect raw `$_GET` (leaks the API key back over the wire)    | `REST/Rest_Update.php:308,335`, `REST/REST_API.php:701,710` |
| H5  | High     | XSS in Additions repo list table (unescaped column output)                      | `Additions/Repo_List_Table.php:127,130,171`          |
| M1  | Medium   | Unauthenticated public AJAX update endpoint (compounds H1+H4)                   | `REST/REST_API.php:53`                               |
| M2  | Medium   | Superglobal write `$_REQUEST['override'] = true` mid-request                    | `REST/REST_API.php:567`                              |
| M3  | Medium   | `error_log( json_encode( $response ) )` may write sensitive payloads to disk    | `REST/Rest_Update.php:464-465`                       |
| M4  | Medium   | OAuth tokens stored plaintext in site option                                    | `OAuth/OAuth_Connect.php:290-313`, all `API/*.php`   |
| L1  | Low      | All REST routes use `permission_callback => '__return_true'`                    | `REST/REST_API.php` (all `register_rest_route`)      |
| L2  | Low/Info | `unserialize()` on cached additions options                                     | `Additions/Additions.php:178`                        |
| L3  | Low/Info | No SSRF host allow-list on provider HTTP calls (admin-controlled hostnames)     | `API/*.php`                                          |
| L4  | Info     | `download_url` from API JSON used directly in `wp_remote_get`                   | `API/GitHub_API.php:437`                             |

Two architectural decisions are flagged at the end ("Decisions needed") — M4 (encrypt-at-rest) and L1 (move auth to `permission_callback`).

---

## High

### H1 — Non-timing-safe API key comparison

**Locations**

- `src/Git_Updater/REST/REST_API.php:377` — `get_remote_repo_data()`
- `src/Git_Updater/REST/REST_API.php:644` — `flush_repo_cache()`
- `src/Git_Updater/REST/REST_API.php:681` — `reset_branch()`
- `src/Git_Updater/REST/Rest_Update.php:266` — `process_request()`

**Issue.** Each call site compares the stored API key against the request-supplied key with `!==`. Native string `!==` short-circuits on the first byte that differs, so the comparison time leaks the length of the matching prefix. The current key is a 32-char MD5 hex string (lowercase `[0-9a-f]`), so the search space is 16^32 and remote-network jitter is large compared to per-byte timing differences; a practical timing attack against a live install is unlikely. The fix is mechanical and removes the discussion.

**Impact.** Theoretical. Low under current key generation; rises if H2 lands and keys get longer/random — the comparison should be safe before then.

**Suggested patch.** Use `hash_equals()`. The two operands are already strings; cast defensively in case a stored option is `false`.

```diff
--- a/src/Git_Updater/REST/REST_API.php
+++ b/src/Git_Updater/REST/REST_API.php
@@ -374,7 +374,7 @@ class REST_API {
 	public function get_remote_repo_data( WP_REST_Request $request ) {
 		// Test for API key and exit if incorrect.
-		if ( $this->get_class_vars( 'Remote_Management', 'api_key' ) !== $request->get_param( 'key' ) ) {
+		if ( ! hash_equals( (string) $this->get_class_vars( 'Remote_Management', 'api_key' ), (string) $request->get_param( 'key' ) ) ) {
 			return [ 'error' => 'Bad API key. No repo data for you.' ];
 		}
@@ -641,7 +641,7 @@ class REST_API {
 	public function flush_repo_cache( $request ) {
 		// Test for API key and exit if incorrect.
-		if ( $this->get_class_vars( 'Remote_Management', 'api_key' ) !== $request->get_param( 'key' ) ) {
+		if ( ! hash_equals( (string) $this->get_class_vars( 'Remote_Management', 'api_key' ), (string) $request->get_param( 'key' ) ) ) {
 			return (object) [ 'error' => 'Bad API key. No flush for you.' ];
 		}
@@ -678,7 +678,7 @@ class REST_API {
 		try {
 			// Test for API key and exit if incorrect.
-			if ( $this->get_class_vars( 'Remote_Management', 'api_key' ) !== $request->get_param( 'key' ) ) {
+			if ( ! hash_equals( (string) $this->get_class_vars( 'Remote_Management', 'api_key' ), (string) $request->get_param( 'key' ) ) ) {
 				throw new UnexpectedValueException( 'Bad API key. No branch reset for you.' );
 			}
```

```diff
--- a/src/Git_Updater/REST/Rest_Update.php
+++ b/src/Git_Updater/REST/Rest_Update.php
@@ -263,7 +263,7 @@ class Rest_Update {
 		try {
 			if ( ! $key
-				|| get_site_option( 'git_updater_api_key' ) !== $key
+				|| ! hash_equals( (string) get_site_option( 'git_updater_api_key' ), (string) $key )
 			) {
 				throw new UnexpectedValueException( 'Bad API key.' );
 			}
```

---

### H2 — API key generated with `md5( uniqid( wp_rand(), true ) )`

**Location.** `src/Git_Updater/Remote_Management.php:41-45`

```php
public function ensure_api_key_is_set() {
    if ( ! self::$api_key ) {
        update_site_option( 'git_updater_api_key', md5( uniqid( (string) wp_rand(), true ) ) );
    }
}
```

**Issue.** `uniqid()` is a microtime-based identifier with limited entropy; `wp_rand()` mixes in PHP's `mt_rand()`, which is not cryptographic. The final `md5()` shrinks the output to 128 bits regardless of input entropy. Neither component is suitable for generating a long-lived bearer token.

**Impact.** An attacker who can guess the rough server time of plugin install + observe `mt_rand()` output through another vector could narrow the key space. Defense-in-depth fix.

**Suggested patch.** `bin2hex( random_bytes( 32 ) )` is 64 chars of hex — keeps the existing character class (hex), keeps `sanitize_text_field` happy, and is cryptographically random.

```diff
--- a/src/Git_Updater/Remote_Management.php
+++ b/src/Git_Updater/Remote_Management.php
@@ -39,7 +39,7 @@ class Remote_Management {
 	public function ensure_api_key_is_set() {
 		if ( ! self::$api_key ) {
-			update_site_option( 'git_updater_api_key', md5( uniqid( (string) wp_rand(), true ) ) );
+			update_site_option( 'git_updater_api_key', bin2hex( random_bytes( 32 ) ) );
 		}
 	}
```

**Migration.** Existing keys keep working — there is no length check on the stored value. Operators wanting the upgrade can hit Settings → Remote Management → Reset REST API key and the new value will be generated.

---

### H3 — OAuth callback has no `state` parameter

**Locations.**

- `src/Git_Updater/OAuth/OAuth_Connect.php:138-163` — `render_connect_button()` builds the authorize URL.
- `src/Git_Updater/OAuth/OAuth_Connect.php:170-196` — `handle_callback()` accepts `provider` + `gu_exchange_code` from `$_GET`, calls `fetch_token_from_connector()`, and writes the returned access token into the site option.

```php
public function handle_callback(): void {
    if ( ! current_user_can( is_multisite() ? 'manage_network_options' : 'manage_options' ) ) {
        wp_die( esc_html__( 'Forbidden', 'git-updater' ) );
    }

    $provider      = sanitize_key( $_GET['provider'] ?? '' );
    $exchange_code = sanitize_text_field( wp_unslash( $_GET['gu_exchange_code'] ?? '' ) );
    // ...
}
```

**Issue.** The capability check stops anonymous attackers, but it does not prove the callback corresponds to a flow the *current admin* started. An attacker who controls an `exchange_code` valid on the connector (for example, one obtained from their own OAuth grant to an attacker-owned GitHub account) can craft a link:

```
https://victim.example/wp-admin/admin-post.php?action=gu_oauth_callback&provider=github&gu_exchange_code=<attacker_code>
```

If the victim is a logged-in network admin and clicks it, the site writes an attacker-controlled access token over the legitimate one. Subsequent updates would then fetch archives from the attacker's account.

**Impact.** Account confusion / supply-chain compromise. Requires social engineering of an admin but no other interaction. The `disconnect` handler already uses `check_admin_referer( 'gu_oauth_disconnect_$provider' )` (line 206) — the connect side is the gap.

**Suggested patch.** Round-trip a `state` value through the connector. This requires the connector to echo `state` back on the callback URL — coordinate the connector change if it doesn't already.

```diff
--- a/src/Git_Updater/OAuth/OAuth_Connect.php
+++ b/src/Git_Updater/OAuth/OAuth_Connect.php
@@ -138,9 +138,15 @@ class OAuth_Connect {
 	private function render_connect_button( string $provider, array $config, string $connector ): void {
 		$callback_url = $this->get_callback_url( $provider );

+		// Generate single-use state token, stash for callback verification.
+		$state = wp_generate_password( 32, false );
+		set_site_transient( "gu_oauth_state_$provider", $state, 10 * MINUTE_IN_SECONDS );
+
 		// Build the authorize URL on the connector.
 		$authorize_url = $connector . 'git-updater/' . $provider . '/oauth/authorize';
 		$authorize_url = add_query_arg( 'redirect', rawurlencode( $callback_url ), $authorize_url );
+		$authorize_url = add_query_arg( 'state', $state, $authorize_url );
@@ -170,12 +176,21 @@ class OAuth_Connect {
 	public function handle_callback(): void {
 		if ( ! current_user_can( is_multisite() ? 'manage_network_options' : 'manage_options' ) ) {
 			wp_die( esc_html__( 'Forbidden', 'git-updater' ) );
 		}

 		$provider      = sanitize_key( $_GET['provider'] ?? '' );
 		$exchange_code = sanitize_text_field( wp_unslash( $_GET['gu_exchange_code'] ?? '' ) );
+		$state         = sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) );

 		if ( ! isset( self::PROVIDERS[ $provider ] ) || empty( $exchange_code ) ) {
 			$this->redirect_with_status( $provider, 'oauth_error' );
 			return;
 		}

+		$expected_state = get_site_transient( "gu_oauth_state_$provider" );
+		delete_site_transient( "gu_oauth_state_$provider" );
+		if ( empty( $expected_state ) || empty( $state ) || ! hash_equals( (string) $expected_state, $state ) ) {
+			$this->redirect_with_status( $provider, 'oauth_error' );
+			return;
+		}
+
 		$result = $this->fetch_token_from_connector( $provider, $exchange_code );
```

**Connector dependency.** The connector at `GIT_UPDATER_OAUTH_CONNECTOR_URL` needs to preserve `state` from the authorize URL through to the final redirect. If it doesn't today, this is a coordinated change.

---

### H4 — Webhook responses reflect raw `$_GET` (leaks the API key)

**Locations.**

- `src/Git_Updater/REST/Rest_Update.php:308` (error branch of `process_request`)
- `src/Git_Updater/REST/Rest_Update.php:335` (success branch of `process_request`)
- `src/Git_Updater/REST/REST_API.php:701` (success branch of `reset_branch`)
- `src/Git_Updater/REST/REST_API.php:710` (error branch of `reset_branch`)

All four sites do `'webhook' => $_GET` inside the JSON response that `log_exit()` emits.

**Issue.** For the unauthenticated `wp_ajax_nopriv_git-updater-update` route and the `git-updater/v1/update` REST route, the legitimate caller supplies `?key=<API_KEY>&plugin=…`. The response body reflects that query string verbatim. The `key` then appears in:

- Any upstream proxy / reverse-proxy access log that records bodies.
- The caller's own logs if it logs responses (CI runners typically do).
- An attacker's view if they were able to coerce a request via a different vulnerability (e.g. SSRF in a third-party plugin) — they'd see the key come back.

It also reveals the parameter set to anyone who can hit the endpoint with any input.

**Impact.** API key disclosure under common operational logging patterns. The endpoint is the highest-privilege one in the plugin (triggers code installation).

**Suggested patch.** Build the echo from the already-parsed `$args`, never from `$_GET` directly, and drop `key`.

```diff
--- a/src/Git_Updater/REST/Rest_Update.php
+++ b/src/Git_Updater/REST/Rest_Update.php
@@ -260,6 +260,14 @@ class Rest_Update {
 		$override   = $args['override'] ?? false;
 		$deprecated = $args['deprecated'] ?? '';
+
+		$echo_args = [
+			'plugin'     => $plugin,
+			'theme'      => $theme,
+			'tag'        => $tag,
+			'branch'     => $branch,
+			'committish' => $committish,
+			'override'   => (bool) $override,
+		];

 		$start          = microtime( true );
 		$current_branch = 'master';
@@ -305,7 +313,7 @@ class Rest_Update {
 			$http_response = [
 				'success'      => false,
 				'messages'     => $e->getMessage(),
-				'webhook'      => $_GET, // phpcs:ignore WordPress.Security.NonceVerification
+				'webhook'      => $echo_args,
 				'elapsed_time' => round( ( microtime( true ) - $start ) * 1000, 2 ) . ' ms',
 				'deprecated'   => $deprecated,
 			];
@@ -332,7 +340,7 @@ class Rest_Update {
 		$response = [
 			'success'      => true,
 			'messages'     => $this->get_messages(),
-			'webhook'      => $_GET, // phpcs:ignore WordPress.Security.NonceVerification
+			'webhook'      => $echo_args,
 			'elapsed_time' => round( ( microtime( true ) - $start ) * 1000, 2 ) . ' ms',
 			'deprecated'   => $deprecated,
 		];
```

And in `REST_API.php::reset_branch()`:

```diff
--- a/src/Git_Updater/REST/REST_API.php
+++ b/src/Git_Updater/REST/REST_API.php
@@ -685,6 +685,11 @@ class REST_API {
 			$plugin_slug = $request->get_param( 'plugin' );
 			$theme_slug  = $request->get_param( 'theme' );
 			$options     = $this->get_class_vars( 'Base', 'options' );
+			$echo_args   = [
+				'plugin' => $plugin_slug,
+				'theme'  => $theme_slug,
+			];
 			$slug        = ! empty( $plugin_slug ) ? $plugin_slug : $theme_slug;
@@ -698,7 +703,7 @@ class REST_API {
 			$response = [
 				'success'      => true,
 				'messages'     => 'Reset to primary branch complete.',
-				'webhook'      => $_GET, // phpcs:ignore WordPress.Security.NonceVerification
+				'webhook'      => $echo_args,
 				'elapsed_time' => round( ( microtime( true ) - $start ) * 1000, 2 ) . ' ms',
 			];
@@ -707,7 +712,7 @@ class REST_API {
 			$response = [
 				'success'      => false,
 				'messages'     => $e->getMessage(),
-				'webhook'      => $_GET, // phpcs:ignore WordPress.Security.NonceVerification
+				'webhook'      => $echo_args ?? [],
 				'elapsed_time' => round( ( microtime( true ) - $start ) * 1000, 2 ) . ' ms',
 			];
```

(The `$echo_args ?? []` in the catch handles the case where the exception fires before `$echo_args` is set, e.g. on bad API key.)

---

### H5 — XSS in Additions repo list table

**Location.** `src/Git_Updater/Additions/Repo_List_Table.php:119-132, 149-177`

```php
public function column_default( $item, $column_name ) {
    switch ( $column_name ) {
        case 'uri':
        case 'slug':
        case 'primary_branch':
        case 'release_asset':
        case 'type':
        case 'private_package':
            return $item[ $column_name ];      // unescaped, line 127
        default:
            return print_r( $item, true );     // line 130
    }
}

public function column_slug( $item ) {
    // ...
    return sprintf(
        '%1$s <span style="color:silver">(id:%2$s)</span>%3$s',
        $item['slug'],                          // unescaped, line 171
        $item['ID'],
        $this->row_actions( $actions )
    );
}
```

**Issue.** `sanitize_text_field` (applied on intake — `Additions/Settings.php:291`) strips line breaks and tags-with-newlines but does **not** strip `<` or `"` from single-line strings. Stored `<img src=x onerror=alert(1)>` in a `uri` field, for example, would render and execute when the additions list table renders. Attacker prerequisite is admin write access to the additions list (so it's admin→admin XSS), but the additions list is shared across the network — an attacker who compromises one network admin can persist into the next admin's session.

**Impact.** Persistent admin-context XSS. Bounded by the admin-write prerequisite but landlords the attacker who briefly gets that access.

**Suggested patch.** Escape on output. Replace `print_r` with a json-encoded escape (or remove it — it's a developer debug path).

```diff
--- a/src/Git_Updater/Additions/Repo_List_Table.php
+++ b/src/Git_Updater/Additions/Repo_List_Table.php
@@ -120,11 +120,9 @@ class Repo_List_Table extends \WP_List_Table {
 		switch ( $column_name ) {
 			case 'uri':
 			case 'slug':
 			case 'primary_branch':
 			case 'release_asset':
 			case 'type':
 			case 'private_package':
-				return $item[ $column_name ];
+				return esc_html( (string) $item[ $column_name ] );
 			default:
-				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
-				return print_r( $item, true ); // Show the whole array for troubleshooting purposes.
+				return esc_html( (string) wp_json_encode( $item ) );
 		}
 	}
@@ -167,7 +165,7 @@ class Repo_List_Table extends \WP_List_Table {
 		return sprintf(
 			/* translators: 1: title, 2: ID, 3: row actions */
 			'%1$s <span style="color:silver">(id:%2$s)</span>%3$s',
 			/*$1%s*/
-			$item['slug'],
+			esc_html( (string) $item['slug'] ),
 			/*$2%s*/
-			$item['ID'],
+			esc_html( (string) $item['ID'] ),
 			/*$3%s*/
 			$this->row_actions( $actions )
 		);
```

The `$item['ID']` escape is defense-in-depth — the value is an MD5 of the slug today, but escaping it costs nothing.

---

## Medium

### M1 — Unauthenticated public AJAX update endpoint

**Location.** `src/Git_Updater/REST/REST_API.php:53`

```php
add_action( 'wp_ajax_nopriv_git-updater-update', [ Singleton::get_instance( 'REST\Rest_Update', $this ), 'process_request' ] );
```

**Issue.** Intentionally public so external CI / webhooks can POST. Auth is the shared `key` only, with no nonce and no rate limit. This is acceptable as a webhook design **only** if H1 (timing-safe comparison) and H4 (no key reflection) both land. Without them, the leaked-key window from H4 combined with the comparison from H1 makes the endpoint substantially weaker.

**Impact.** Compounds H1 + H4. No standalone vulnerability.

**Suggested patch.** No code change beyond H1/H4. Add a comment so a future maintainer doesn't quietly weaken the threat model:

```diff
-		// Deprecated AJAX request.
-		add_action( 'wp_ajax_git-updater-update', [ Singleton::get_instance( 'REST\Rest_Update', $this ), 'process_request' ] );
-		add_action( 'wp_ajax_nopriv_git-updater-update', [ Singleton::get_instance( 'REST\Rest_Update', $this ), 'process_request' ] );
+		/*
+		 * Deprecated AJAX request. The `nopriv` variant is intentional — external CI / webhook
+		 * callers POST here. Authentication is the shared API key, validated inside
+		 * Rest_Update::process_request() with hash_equals(). Do not echo the key back in
+		 * responses (see Rest_Update::process_request `webhook` field).
+		 */
+		add_action( 'wp_ajax_git-updater-update', [ Singleton::get_instance( 'REST\Rest_Update', $this ), 'process_request' ] );
+		add_action( 'wp_ajax_nopriv_git-updater-update', [ Singleton::get_instance( 'REST\Rest_Update', $this ), 'process_request' ] );
```

---

### M2 — Superglobal write `$_REQUEST['override'] = true`

**Location.** `src/Git_Updater/REST/REST_API.php:567`

```php
} elseif ( isset( $repo_cache['release_asset'] ) && $repo_cache['release_asset'] ) {
    $_REQUEST['override']           = true;
    $repo_api_data['download_link'] = Singleton::get_instance( 'Fragen\Git_Updater\API\API', $this )->get_release_asset_redirect( $repo_cache['release_asset'], true );
    unset( $repo_api_data['auth_header'] );
}
```

**Issue.** Mutating the request superglobal couples the REST handler to whatever downstream code happens to read `$_REQUEST['override']`. It also defeats the audit story of "user input flows in here, sanitized once, validated once."

**Impact.** Maintenance / auditability risk; not directly exploitable. Worth fixing while the surrounding code is in security review.

**Suggested patch.** Trace the consumer of `$_REQUEST['override']` (likely `Rest_Update::process_request_data()` or the upgrader hook chain) and pass `override` through the explicit args path instead. This is a small refactor; flag for a follow-up branch rather than rushing into this PR.

---

### M3 — `error_log( json_encode( $response ) )` may write sensitive payloads to disk

**Location.** `src/Git_Updater/REST/Rest_Update.php:464-465`

```php
// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
error_log( json_encode( $response, $json_encode_flags ) );
```

**Issue.** After H4 lands the response no longer contains `key`, but the `messages` field can still carry exception text (`'Bad API key. ...'`, provider-rate-limit messages with auth hints) and the path is unconditional — it logs on every webhook hit, not only on debug. On hosts where `error_log` writes a world-readable file (some shared hosting), this becomes an info-disclosure channel.

**Suggested patch.** Gate on `WP_DEBUG`, and redact the `webhook` field defensively:

```diff
-		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
-		error_log( json_encode( $response, $json_encode_flags ) );
+		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
+			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
+			error_log( json_encode( $response, $json_encode_flags ) );
+		}
```

---

### M4 — OAuth tokens stored plaintext in site option

**Locations.**

- Writers: `src/Git_Updater/OAuth/OAuth_Connect.php:290-313` (`save_token()`), `src/Git_Updater/Settings.php` (settings save path)
- Readers: `src/Git_Updater/Traits/Basic_Auth_Loader.php:148`, all `src/Git_Updater/API/*.php`

**Issue.** Every access token (GitHub, GitLab, Bitbucket, Gitea — both manual PATs and OAuth-acquired) lives in plaintext inside `get_site_option( 'git_updater' )`. Any other plugin running with `manage_options` capability can read these tokens via the standard options API. Database backup files contain them in cleartext.

**Impact.** Lateral compromise: a single malicious admin plugin or one stolen database backup leaks credentials for every git provider configured.

**Suggested patches — pick one.** This is a meaningful architectural decision (see "Decisions needed").

(a) **Encrypt at rest** using `sodium_crypto_secretbox`, keyed by a value the operator defines in `wp-config.php`:

```php
// In wp-config.php
define( 'GIT_UPDATER_TOKEN_KEY', '<32 random bytes, hex-encoded>' );
```

```php
// New helper class
final class Token_Vault {
    public static function encrypt( string $plaintext ): ?string { ... }
    public static function decrypt( string $ciphertext ): ?string { ... }
}
```

Migration: on first load, detect option values that don't decrypt → assume legacy plaintext → re-save encrypted.

(b) **Document and rely on hosting hardening.** Add an admin notice on the Settings page making the storage model explicit ("Tokens are stored in the WordPress options table. Treat database backups as sensitive.") and leave the code alone.

Either is defensible. Option (a) is a meaningful new dependency on a constant the operator must set; option (b) is honest about the threat model.

---

## Low / Informational

### L1 — All REST routes use `permission_callback => '__return_true'`

**Location.** `src/Git_Updater/REST/REST_API.php` — every `register_rest_route` call.

**Issue.** WordPress convention is for `permission_callback` to return a `WP_Error` or `false` for unauthorized requests; the REST server then sets 401/403 and the standard WAF / log filtering paths catch it. Today, unauthenticated key-protected calls return HTTP 200 with `{ 'error': 'Bad API key. ...' }`. This:

- Masks failed-auth in HTTP-status-based monitoring.
- Makes log filtering harder.
- Leaks the auth design via the body rather than the protocol-correct mechanism.

**Suggested fix (optional, architectural).** For the four endpoints that require a key (`repos`, `flush-repo-cache`, `update`, `reset-branch`), move the comparison into `permission_callback` and return `new WP_Error( 'gu_bad_key', 'Bad API key.', [ 'status' => 401 ] )`. Leave `test`, `namespace`, `plugins-api`, `themes-api`, `update-api`, `update-api-additions`, `get-additions-data` as `__return_true` with a `// Intentionally public read-only.` comment.

This is a behavior change for any caller that parses the `200 + error: …` shape. Flagged for decision.

### L2 — `unserialize()` on cached additions options

**Location.** `src/Git_Updater/Additions/Additions.php:178`

```php
$options = array_map( 'unserialize', array_unique( array_map( 'serialize', $options ) ) );
```

**Issue.** Classic `serialize → array_unique → unserialize` deduplication. Source is the `git_updater_additions` site option, written by the plugin's own admin form. Not a trust boundary crossing. No change recommended.

### L3 — No SSRF host allow-list on provider HTTP calls

**Locations.** All `src/Git_Updater/API/*.php` (`GitHub_API`, `GitLab_API`, `Bitbucket_API`, `Gitea_API`).

**Issue.** Hostnames come from admin-configured options (the GitLab/Gitea server URL is operator-supplied). An admin could in principle aim the plugin at an internal address (`http://localhost:8080/...`) — admin→admin SSRF. Mitigated by admin trust, but worth being aware of if the threat model ever broadens.

### L4 — `download_url` from provider API JSON

**Location.** `src/Git_Updater/API/GitHub_API.php:437` (and parallel sites in other providers).

**Issue.** `wp_remote_get( $asset->download_url )` where `$asset` is JSON from the provider. Acceptable: the value originates from the trusted provider domain.

---

## Decisions needed

1. **M4 — encryption at rest.** Pick (a) encrypt with a wp-config-defined key, or (b) document and rely on hosting hardening. Encryption is a one-time migration cost; documentation is zero-cost but offers nothing new.

2. **L1 — move key validation into `permission_callback`.** Cleaner WordPress shape, returns proper 401s — but changes the response body for callers that already special-case the current `{ 'error': ... }` payload. Need to know who uses Git Remote Updater / similar tooling.

3. **H3 — connector coordination.** The `state` round-trip requires the OAuth connector to preserve `state`. If you control the connector, this is a coordinated two-side change; if not, the patch needs a fallback (e.g. accept missing `state` only when `expected_state` is also missing, transitionally).

---

## Verification (for the follow-up branch that lands these patches)

1. `composer lint` — PHPCS clean.
2. `composer phpstan` — must stay clean, or regenerate the baseline with `composer phpstan-baseline` and note the reason in the commit message.
3. `npm test` — single-site PHPUnit.
4. `npm run test:multisite` — multisite PHPUnit.
5. Test suites most likely to need updates:
   - REST API tests (H1 / H4 change the response shape; H4 removes `key` from the `webhook` echo).
   - `OAuth_Connect` tests (H3 introduces a `state` round-trip — the test mock for `fetch_token_from_connector` and the `set_site_transient` flow need to be updated).
   - `Repo_List_Table` coverage (H5 changes column output — golden-string comparisons may need `&lt;` / `&amp;` updates).
6. Manual: `curl` the webhook from a local wp-env with correct key + with wrong key; verify response no longer reflects `key`, and (if L1 lands) the wrong-key path returns HTTP 401.
