<?php
/**
 * Tests for Basic_Auth_Loader trait changes:
 * 1. Bug fix: isset($headers['host']) === $api_domain (bool vs string) corrected
 *    to isset($headers['host']) && $headers['host'] === $api_domain.
 * 2. Slug extraction restructured to check for array BEFORE sanitize_text_field,
 *    restoring TGMPA compatibility that was accidentally broken.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\API\GitHub_API;
use Fragen\Git_Updater\API\Language_Pack_API;
use Fragen\Git_Updater\Base;
use Fragen\Git_Updater\OAuth\OAuth_Connect;
use Fragen\Singleton;

class Test_Basic_Auth_Loader extends WP_UnitTestCase {

	/**
	 * @var GitHub_API
	 */
	private GitHub_API $api;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->api = new GitHub_API( $this->make_type() );
	}

	public function tear_down(): void {
		unset( $_REQUEST['slug'], $_REQUEST['plugin'], $_REQUEST['plugins'], $_REQUEST['themes'] );
		unset( $_POST['git_updater_api'], $_POST['git_updater_repo'] );
		parent::tear_down();
	}

	private function make_type(): stdClass {
		$type                 = new stdClass();
		$type->slug           = 'test-plugin';
		$type->git            = 'github';
		$type->type           = 'plugin';
		$type->owner          = 'test-owner';
		$type->branch         = 'master';
		$type->primary_branch = 'master';
		$type->enterprise     = false;
		$type->enterprise_api = null;
		$type->gist_id        = null;
		return $type;
	}

	/**
	 * Invoke the private get_credentials() method via reflection.
	 * In PHP 8.1+, setAccessible() is a no-op so invoke() works directly.
	 *
	 * @param string $url
	 * @return array<string, mixed>
	 */
	private function get_credentials( string $url ): array {
		$rm = new ReflectionMethod( $this->api, 'get_credentials' );
		PHP_VERSION_ID < 80100 && $rm->setAccessible( true );
		return $rm->invoke( $this->api, $url );
	}

	/**
	 * Invoke the private get_slug_for_credentials() method via reflection.
	 *
	 * @return string|false
	 */
	private function get_slug_for_credentials( array $headers, array $repos, string $url, array $options ) {
		$rm = new ReflectionMethod( $this->api, 'get_slug_for_credentials' );
		PHP_VERSION_ID < 80100 && $rm->setAccessible( true );
		return $rm->invoke( $this->api, $headers, $repos, $url, $options );
	}

	/**
	 * Invoke the private get_type_for_credentials() method via reflection.
	 */
	private function get_type_for_credentials( string $slug, array $repos, string $url ): string {
		$rm = new ReflectionMethod( $this->api, 'get_type_for_credentials' );
		PHP_VERSION_ID < 80100 && $rm->setAccessible( true );
		return $rm->invoke( $this->api, $slug, $repos, $url );
	}

	// -------------------------------------------------------------------------
	// Bug fix: isset($headers['host']) === $api_domain
	// -------------------------------------------------------------------------

	/**
	 * After the bug fix, when the URL host matches the configured api_domain
	 * (default: api.wordpress.org), credentials['api.wordpress'] must be the
	 * host string (truthy), triggering the early return path.
	 *
	 * Before the fix: isset(...) returned bool true, which was compared with
	 * === to the string 'api.wordpress.org', always yielding false — the early
	 * return was never reached.
	 */
	public function test_credentials_for_wordpress_org_host_are_truthy(): void {
		$credentials = $this->get_credentials( 'https://api.wordpress.org/plugins/info/1.0/' );

		$this->assertSame(
			'api.wordpress.org',
			$credentials['api.wordpress'],
			'credentials[api.wordpress] must equal the host string for api.wordpress.org URLs.'
		);
	}

	/**
	 * For any non-WordPress.org URL, credentials['api.wordpress'] must be
	 * false so that normal auth-header processing continues.
	 */
	public function test_credentials_for_github_api_host_are_false(): void {
		$credentials = $this->get_credentials( 'https://api.github.com/repos/owner/repo' );

		$this->assertFalse(
			$credentials['api.wordpress'],
			'credentials[api.wordpress] must be false for non-WordPress.org URLs.'
		);
	}

	/**
	 * add_auth_header() must return the original $args array unchanged for
	 * WordPress.org URLs — no auth header should be injected.
	 */
	public function test_add_auth_header_unchanged_for_wordpress_org_url(): void {
		$args   = [ 'headers' => [] ];
		$result = $this->api->add_auth_header( $args, 'https://api.wordpress.org/plugins/info/1.0/' );

		$this->assertSame( $args, $result );
	}

	// -------------------------------------------------------------------------
	// Slug extraction: array before sanitize (TGMPA fix)
	// -------------------------------------------------------------------------

	/**
	 * When $_REQUEST['slug'] is an array (as TGMPA sends it), the restructured
	 * code must detect the array BEFORE sanitize_text_field converts it to an
	 * empty string, and extract the last element.
	 */
	public function test_slug_extraction_handles_array_slug_request(): void {
		$_REQUEST['slug'] = [ 'my-plugin/my-plugin.php' ];

		$headers = [ 'host' => 'api.github.com' ];
		$slug    = $this->get_slug_for_credentials( $headers, [], 'https://api.github.com/', [] );

		$this->assertSame(
			'my-plugin',
			$slug,
			'Array slug should be popped and dirname extracted (TGMPA compatibility).'
		);
	}

	/**
	 * When $_REQUEST['slug'] is a plain string, it is sanitized normally.
	 */
	public function test_slug_extraction_handles_string_slug_request(): void {
		$_REQUEST['slug'] = 'my-plugin/my-plugin.php';

		$headers = [ 'host' => 'api.github.com' ];
		$slug    = $this->get_slug_for_credentials( $headers, [], 'https://api.github.com/', [] );

		$this->assertSame( 'my-plugin', $slug );
	}

	/**
	 * When $_REQUEST['slug'] is absent but $_REQUEST['plugin'] is set,
	 * the plugin key is used as the fallback.
	 */
	public function test_slug_extraction_falls_back_to_plugin_request_key(): void {
		unset( $_REQUEST['slug'] );
		$_REQUEST['plugin'] = 'my-plugin/my-plugin.php';

		$headers = [ 'host' => 'api.github.com' ];
		$slug    = $this->get_slug_for_credentials( $headers, [], 'https://api.github.com/', [] );

		$this->assertSame( 'my-plugin', $slug );
	}

	/**
	 * When neither slug nor plugin is in the request, slug returns false.
	 */
	public function test_slug_extraction_returns_false_when_no_request_keys(): void {
		unset( $_REQUEST['slug'], $_REQUEST['plugin'], $_REQUEST['plugins'], $_REQUEST['themes'] );

		$headers = [ 'host' => 'api.github.com' ];
		$slug    = $this->get_slug_for_credentials( $headers, [], 'https://api.github.com/', [] );

		$this->assertFalse( $slug );
	}

	// -------------------------------------------------------------------------
	// unset_release_asset_auth()
	// -------------------------------------------------------------------------

	/**
	 * When the URL contains an S3 hostname, the Authorization header is removed
	 * because S3 uses query-string auth and an Authorization header conflicts.
	 */
	public function test_unset_release_asset_auth_removes_authorization_for_s3_url(): void {
		$args = [ 'headers' => [ 'Authorization' => 'Bearer token123' ] ];
		$url  = 'https://github-releases.s3.amazonaws.com/12345/release.zip';

		$result = $this->api->unset_release_asset_auth( $args, $url );

		$this->assertArrayNotHasKey( 'Authorization', $result['headers'] );
	}

	/**
	 * When the URL contains the GitHub objects CDN, the Authorization header
	 * is removed (same S3-backed storage).
	 */
	public function test_unset_release_asset_auth_removes_authorization_for_github_objects_url(): void {
		$args = [ 'headers' => [ 'Authorization' => 'Bearer token123' ] ];
		$url  = 'https://objects.githubusercontent.com/github-production-release-asset/release.zip';

		$result = $this->api->unset_release_asset_auth( $args, $url );

		$this->assertArrayNotHasKey( 'Authorization', $result['headers'] );
	}

	/**
	 * For a regular GitHub API URL the Authorization header is preserved.
	 */
	public function test_unset_release_asset_auth_preserves_authorization_for_api_url(): void {
		$args = [ 'headers' => [ 'Authorization' => 'Bearer token123' ] ];
		$url  = 'https://api.github.com/repos/owner/repo/releases/assets/1';

		$result = $this->api->unset_release_asset_auth( $args, $url );

		$this->assertArrayHasKey( 'Authorization', $result['headers'] );
		$this->assertSame( 'Bearer token123', $result['headers']['Authorization'] );
	}

	/**
	 * When there is no Authorization header, unset_release_asset_auth() returns
	 * the args unchanged regardless of URL.
	 */
	public function test_unset_release_asset_auth_no_op_when_no_authorization_header(): void {
		$args = [ 'headers' => [ 'Accept' => 'application/octet-stream' ] ];
		$url  = 'https://github-releases.s3.amazonaws.com/release.zip';

		$result = $this->api->unset_release_asset_auth( $args, $url );

		$this->assertSame( $args, $result );
	}

	// -------------------------------------------------------------------------
	// add_accept_header()
	// -------------------------------------------------------------------------

	/**
	 * When headers is not set at all, add_accept_header() initialises it to an
	 * empty array and returns args without error.
	 */
	public function test_add_accept_header_initialises_missing_headers_key(): void {
		$args   = [];
		$result = $this->api->add_accept_header( $args );
		$this->assertArrayHasKey( 'headers', $result );
		$this->assertIsArray( $result['headers'] );
	}

	/**
	 * When headers is already an array, add_accept_header() returns it
	 * without introducing unexpected keys when no git-server header is present.
	 */
	public function test_add_accept_header_returns_args_unchanged_for_non_git_headers(): void {
		$args   = [ 'headers' => [ 'Accept' => 'application/json' ] ];
		$result = $this->api->add_accept_header( $args );
		$this->assertArrayHasKey( 'Accept', $result['headers'] );
		$this->assertSame( 'application/json', $result['headers']['Accept'] );
	}

	// -------------------------------------------------------------------------
	// download_package()
	// -------------------------------------------------------------------------

	/**
	 * When args['filename'] is null, download_package() must NOT attempt to
	 * add auth or accept headers (it short-circuits) and still removes itself
	 * from the http_request_args filter.
	 */
	public function test_download_package_skips_processing_when_filename_is_null(): void {
		add_filter( 'http_request_args', [ $this->api, 'download_package' ], 10, 2 );

		$args   = [ 'filename' => null, 'headers' => [] ];
		$result = $this->api->download_package( $args, 'https://example.com/release.zip' );

		$this->assertSame( $args, $result );
		// Confirm filter was removed (calling again would be a no-op if it was removed).
		$this->assertFalse( has_filter( 'http_request_args', [ $this->api, 'download_package' ] ) );
	}

	/**
	 * When args['filename'] is NOT null, download_package() must invoke
	 * add_auth_header(), unset_release_asset_auth(), and add_accept_header()
	 * (lines 40–42) and still remove itself from the filter.
	 * Using a WordPress.org URL keeps add_auth_header() on its early-return
	 * path so no actual credentials are needed.
	 */
	public function test_download_package_processes_headers_when_filename_is_set(): void {
		add_filter( 'http_request_args', [ $this->api, 'download_package' ], 10, 2 );

		$args   = [ 'filename' => '/tmp/pkg.zip', 'headers' => [] ];
		$result = $this->api->download_package( $args, 'https://api.wordpress.org/plugins/info/1.0/' );

		$this->assertIsArray( $result );
		$this->assertFalse( has_filter( 'http_request_args', [ $this->api, 'download_package' ] ) );
	}

	// -------------------------------------------------------------------------
	// add_auth_header() — credential paths
	// -------------------------------------------------------------------------

	/**
	 * When a github_access_token is configured, add_auth_header() must inject
	 * a Bearer Authorization header and a github slug header (lines 64–68, 77, 82).
	 */
	public function test_add_auth_header_adds_bearer_token_for_github_with_access_token(): void {
		update_site_option( 'git_updater', [ 'github_access_token' => 'test-token' ] );
		$_REQUEST['slug'] = 'test-plugin';

		$result = $this->api->add_auth_header(
			[ 'headers' => [] ],
			'https://api.github.com/repos/test-owner/test-plugin/contents/readme.txt'
		);

		$this->assertSame( 'Bearer test-token', $result['headers']['Authorization'] );
		$this->assertSame( 'test-plugin', $result['headers']['github'] );
	}

	/**
	 * When credentials have a type but no token, add_auth_header() must set the
	 * git-server header keyed by type without an Authorization header
	 * (lines 79–80, 82).
	 */
	public function test_add_auth_header_sets_type_header_when_no_token(): void {
		// No github_access_token in options → token resolves to null.
		$_REQUEST['slug'] = 'test-plugin';

		$result = $this->api->add_auth_header(
			[ 'headers' => [] ],
			'https://api.github.com/repos/test-owner/test-plugin/contents/readme.txt'
		);

		$this->assertArrayNotHasKey( 'Authorization', $result['headers'] );
		$this->assertSame( 'test-plugin', $result['headers']['github'] );
	}

	// -------------------------------------------------------------------------
	// get_credentials() — Language_Pack_API instanceof branch
	// -------------------------------------------------------------------------

	/**
	 * When get_credentials() is called on a Language_Pack_API instance,
	 * it must override $type and $slug from $this->type (lines 130–131).
	 */
	public function test_get_credentials_overrides_slug_and_type_for_language_pack_api(): void {
		$type     = $this->make_type(); // git='github', slug='test-plugin'
		$lang_api = new Language_Pack_API( $type );
		$rm       = new ReflectionMethod( $lang_api, 'get_credentials' );
		PHP_VERSION_ID < 80100 && $rm->setAccessible( true );

		$credentials = $rm->invoke(
			$lang_api,
			'https://api.github.com/repos/test-owner/test-plugin/contents/lang-pack.json'
		);

		// The Language_Pack_API branch sets $type from $this->type->git.
		$this->assertSame( 'github', $credentials['type'] );
		$this->assertTrue( $credentials['isset'] );
	}

	// -------------------------------------------------------------------------
	// get_slug_for_credentials() — bulk update and URL-path fallback paths
	// -------------------------------------------------------------------------

	/**
	 * When $_REQUEST['plugins'] is set and the URL contains one of the plugin
	 * dirnames, get_slug_for_credentials() must return the matching slug
	 * (lines 190, 197–205).
	 */
	public function test_get_slug_for_credentials_bulk_plugins_request_matches_url(): void {
		$_REQUEST['plugins'] = 'my-plugin/my-plugin.php,other-plugin/other.php';

		$result = $this->get_slug_for_credentials(
			[ 'host' => 'api.github.com' ],
			[],
			'https://api.github.com/repos/owner/my-plugin/releases/latest',
			[]
		);

		$this->assertSame( 'my-plugin', $result );
	}

	/**
	 * When $_REQUEST['themes'] is set and the URL contains one of the theme
	 * slugs, get_slug_for_credentials() must return the matching slug
	 * (lines 192–194, 197–205).
	 */
	public function test_get_slug_for_credentials_bulk_themes_request_matches_url(): void {
		$_REQUEST['themes'] = 'my-theme,other-theme';

		$result = $this->get_slug_for_credentials(
			[ 'host' => 'api.github.com' ],
			[],
			'https://api.github.com/repos/owner/my-theme/releases/latest',
			[]
		);

		$this->assertSame( 'my-theme', $result );
	}

	/**
	 * When no REQUEST slug is found and the URL path contains a segment that
	 * matches a key in $repos, get_slug_for_credentials() must return that key
	 * (lines 209–215).
	 */
	public function test_get_slug_for_credentials_matches_slug_from_url_path(): void {
		$repos   = [ 'my-plugin' => (object) [ 'git' => 'github' ] ];
		$headers = [ 'path' => '/repos/owner/my-plugin/contents/file.php' ];

		$result = $this->get_slug_for_credentials(
			$headers,
			$repos,
			'https://api.github.com/repos/owner/my-plugin/contents/file.php',
			[]
		);

		$this->assertSame( 'my-plugin', $result );
	}

	/**
	 * When a URL path segment matches $this->type->gist_id, the method must
	 * return $this->type->slug (lines 217–221).
	 */
	public function test_get_slug_for_credentials_matches_via_gist_id(): void {
		$type          = $this->make_type(); // slug='test-plugin'
		$type->gist_id = 'abc123def';
		$gist_api      = new GitHub_API( $type );
		$rm            = new ReflectionMethod( $gist_api, 'get_slug_for_credentials' );
		PHP_VERSION_ID < 80100 && $rm->setAccessible( true );

		$result = $rm->invoke(
			$gist_api,
			[ 'path' => '/gists/abc123def/contents' ],
			[],
			'https://api.github.com/gists/abc123def/contents',
			[]
		);

		$this->assertSame( 'test-plugin', $result );
	}

	// -------------------------------------------------------------------------
	// get_type_for_credentials() — slug-in-repos, WP-CLI, Remote Install paths
	// -------------------------------------------------------------------------

	/**
	 * When the slug is present in $repos with a git property, the method returns
	 * that repo's git type (line 242 — ternary true branch).
	 */
	public function test_get_type_for_credentials_uses_repo_git_type_for_known_slug(): void {
		$repos  = [ 'my-plugin' => (object) [ 'git' => 'bitbucket' ] ];
		$result = $this->get_type_for_credentials(
			'my-plugin',
			$repos,
			'https://api.bitbucket.org/2.0/repositories/owner/my-plugin'
		);

		$this->assertSame( 'bitbucket', $result );
	}

	/**
	 * When slug is empty and a repo's download_link matches the URL, the method
	 * returns that repo's git type (lines 247–251 — WP-CLI path).
	 */
	public function test_get_type_for_credentials_uses_download_link_for_wpcli(): void {
		$url   = 'https://api.github.com/repos/owner/my-plugin/zipball/main';
		$repos = [ 'my-plugin' => (object) [ 'git' => 'github', 'download_link' => $url ] ];

		$result = $this->get_type_for_credentials( '', $repos, $url );

		$this->assertSame( 'github', $result );
	}

	/**
	 * When $_POST contains git_updater_api and git_updater_repo and the URL
	 * contains the repo basename, the method returns the POST-supplied type
	 * (lines 257–260 — Remote Install path).
	 */
	public function test_get_type_for_credentials_uses_post_data_for_remote_install(): void {
		$_POST['git_updater_api']  = 'gitlab';
		$_POST['git_updater_repo'] = 'my-plugin.zip';
		$url                       = 'https://gitlab.com/some/path/my-plugin.zip';

		$result = $this->get_type_for_credentials( '', [], $url );

		$this->assertSame( 'gitlab', $result );
	}

	// -------------------------------------------------------------------------
	// unset_release_asset_auth() — X-Amz- check
	// -------------------------------------------------------------------------

	/**
	 * A URL containing 'X-Amz-' (signed S3 query string) must have its
	 * Authorization header removed — covers the third item in $release_asset_parts.
	 */
	public function test_unset_release_asset_auth_removes_authorization_for_x_amz_url(): void {
		$args = [ 'headers' => [ 'Authorization' => 'Bearer token123' ] ];
		$url  = 'https://custom-bucket.example.com/asset.zip?X-Amz-Signature=abc123&X-Amz-Expires=3600';

		$result = $this->api->unset_release_asset_auth( $args, $url );

		$this->assertArrayNotHasKey( 'Authorization', $result['headers'] );
	}

	// -------------------------------------------------------------------------
	// add_accept_header() — git-server header handling
	// -------------------------------------------------------------------------

	/**
	 * When a 'github' header key is present and the repo cache contains
	 * release_asset_download, add_accept_header() must merge an
	 * Accept: application/octet-stream header and remove the 'github' key
	 * (lines 308–314).
	 */
	public function test_add_accept_header_adds_octet_stream_for_github_release_asset(): void {
		$slug      = 'test-plugin';
		$cache_key = $this->api->get_cache_key( $slug );
		update_site_option( $cache_key, [ 'release_asset_download' => 'https://cdn.example.com/release.zip' ] );

		// On CI no repos are installed, so get_running_git_servers() returns [].
		// Force 'github' into the list so the foreach body executes.
		add_filter( 'gu_running_git_servers', fn() => [ 'github' ] );

		$result = $this->api->add_accept_header( [ 'headers' => [ 'github' => $slug ] ] );

		remove_all_filters( 'gu_running_git_servers' );
		delete_site_option( $cache_key );

		$this->assertSame( 'application/octet-stream', $result['headers']['Accept'] );
		$this->assertArrayNotHasKey( 'github', $result['headers'] );
	}

	/**
	 * When a 'github' header key is present but the repo cache has no
	 * release_asset_download entry, no Accept header is added and the
	 * 'github' key is still removed (lines 308, 314 — false branch).
	 */
	public function test_add_accept_header_removes_github_header_without_release_asset(): void {
		add_filter( 'gu_running_git_servers', fn() => [ 'github' ] );

		$result = $this->api->add_accept_header( [ 'headers' => [ 'github' => 'no-cache-slug' ] ] );

		remove_all_filters( 'gu_running_git_servers' );

		$this->assertArrayNotHasKey( 'Accept', $result['headers'] );
		$this->assertArrayNotHasKey( 'github', $result['headers'] );
	}

	// -------------------------------------------------------------------------
	// add_auth_header() — proactive token refresh
	// -------------------------------------------------------------------------

	/**
	 * When a token is expired and a refresh token is available,
	 * add_auth_header() must proactively refresh and use the new token.
	 */
	public function test_add_auth_header_refreshes_expired_token_proactively(): void {
		// Store an expired token with refresh token.
		update_site_option( 'git_updater', [
			'github_access_token'       => 'old_expired_token',
			'github_refresh_token'      => 'refresh_token_value',
			'github_token_expires_in'   => 7200,
			'github_token_acquired_at'  => time() - 7201, // Expired.
		] );
		$_REQUEST['slug'] = 'test-plugin';

		// Mock the connector refresh endpoint.
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if ( strpos( $url, '/oauth/refresh' ) !== false ) {
					return [
						'response' => [ 'code' => 200 ],
						'body'     => wp_json_encode( [ 'access_token' => 'refreshed_token', 'expires_in' => 7200 ] ),
						'headers'  => [],
					];
				}
				return $preempt;
			},
			10,
			3
		);

		// Set connector URL on the OAuth singleton.
		$oauth = Singleton::get_instance( OAuth_Connect::class, $this->api );
		$oauth->connector_url = 'https://connector.example.com/';

		$result = $this->api->add_auth_header(
			[ 'headers' => [] ],
			'https://api.github.com/repos/test-owner/test-plugin/contents/readme.txt'
		);

		$this->assertSame( 'Bearer refreshed_token', $result['headers']['Authorization'] );
		$this->assertSame( 'test-plugin', $result['headers']['github'] );
	}

	/**
	 * When a token is not expired, add_auth_header() must not attempt refresh.
	 */
	public function test_add_auth_header_skips_refresh_when_token_fresh(): void {
		update_site_option( 'git_updater', [
			'github_access_token'       => 'fresh_token',
			'github_refresh_token'      => 'refresh_token_value',
			'github_token_expires_in'   => 7200,
			'github_token_acquired_at'  => time(), // Just acquired, not expired.
		] );
		$_REQUEST['slug'] = 'test-plugin';

		// Track if refresh endpoint is called.
		$refresh_called = false;
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$refresh_called ) {
				if ( strpos( $url, '/oauth/refresh' ) !== false ) {
					$refresh_called = true;
				}
				return $preempt;
			},
			10,
			3
		);

		$oauth = Singleton::get_instance( OAuth_Connect::class, $this->api );
		$oauth->connector_url = 'https://connector.example.com/';

		$result = $this->api->add_auth_header(
			[ 'headers' => [] ],
			'https://api.github.com/repos/test-owner/test-plugin/contents/readme.txt'
		);

		$this->assertFalse( $refresh_called );
		$this->assertSame( 'Bearer fresh_token', $result['headers']['Authorization'] );
	}

	/**
	 * When a token is expired but no refresh token is stored,
	 * add_auth_header() must use the existing (expired) token without error.
	 */
	public function test_add_auth_header_skips_refresh_when_no_refresh_token(): void {
		update_site_option( 'git_updater', [
			'github_access_token'       => 'expired_token',
			'github_token_expires_in'   => 7200,
			'github_token_acquired_at'  => time() - 7201,
		] );
		$_REQUEST['slug'] = 'test-plugin';

		$oauth = Singleton::get_instance( OAuth_Connect::class, $this->api );
		$oauth->connector_url = 'https://connector.example.com/';

		$result = $this->api->add_auth_header(
			[ 'headers' => [] ],
			'https://api.github.com/repos/test-owner/test-plugin/contents/readme.txt'
		);

		$this->assertSame( 'Bearer expired_token', $result['headers']['Authorization'] );
	}
}
