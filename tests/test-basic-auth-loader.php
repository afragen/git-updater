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
use Fragen\Git_Updater\Base;

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
		return $rm->invoke( $this->api, $url );
	}

	/**
	 * Invoke the private get_slug_for_credentials() method via reflection.
	 *
	 * @return string|false
	 */
	private function get_slug_for_credentials( array $headers, array $repos, string $url, array $options ) {
		$rm = new ReflectionMethod( $this->api, 'get_slug_for_credentials' );
		return $rm->invoke( $this->api, $headers, $repos, $url, $options );
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
}
