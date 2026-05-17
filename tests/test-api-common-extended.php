<?php
/**
 * Extended tests for API_Common trait methods not covered by test-api-common.php.
 *
 * Covers:
 * - get_remote_api_info()     via GitHub_API::get_remote_info()     — fail/success paths
 * - get_remote_api_repo_meta() via GitHub_API::get_repo_meta()      — fail/success paths
 * - get_remote_api_assets()   via GitHub_API::get_repo_assets()     — fail/success paths
 * - get_api_release_assets()  via GitHub_API::get_release_assets()  — fail/success paths
 * - get_remote_api_contents() via GitHub_API::get_repo_contents()   — fail/success paths
 *
 * HTTP is mocked via the pre_http_request filter throughout.
 * Dot-org cache is pre-seeded so get_dot_org_data() never reaches api.wordpress.org.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\API\GitHub_API;
use Fragen\Git_Updater\Base;

class Test_API_Common_Extended extends WP_UnitTestCase {

	/**
	 * @var GitHub_API
	 */
	private GitHub_API $api;

	/**
	 * @var stdClass
	 */
	private stdClass $type;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->type = $this->make_type();
		$this->api  = new GitHub_API( $this->type );
	}

	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'gu_always_fetch_update' );
		delete_site_option( $this->api->get_cache_key( 'test-plugin' ) );
		delete_site_option( $this->api->get_cache_key( 'test-plugin_error' ) );
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
		$type->sections       = [];
		return $type;
	}

	private function mock_http_response( int $code, array $body = [] ): array {
		return [
			'response' => [ 'code' => $code, 'message' => 200 === $code ? 'OK' : 'Error' ],
			'body'     => wp_json_encode( $body ),
			'headers'  => [],
			'cookies'  => [],
		];
	}

	private function mock_http_response_raw( int $code, string $body ): array {
		return [
			'response' => [ 'code' => $code, 'message' => 200 === $code ? 'OK' : 'Error' ],
			'body'     => $body,
			'headers'  => [],
			'cookies'  => [],
		];
	}

	private function intercept_http_with( array $return ): void {
		add_filter( 'pre_http_request', fn() => $return, 10, 3 );
	}

	/**
	 * Pre-seed the dot_org cache key so get_dot_org_data() does not make an
	 * outbound call to api.wordpress.org.
	 */
	private function seed_dot_org_cache(): void {
		$cache_key = $this->api->get_cache_key( 'test-plugin' );
		update_site_option(
			$cache_key,
			[
				'dot_org' => 'not in dot org',
				'timeout' => strtotime( '+12 hours' ),
			]
		);
	}

	/**
	 * Seed the error cache so api() returns false immediately without HTTP.
	 */
	private function seed_error_cache(): void {
		update_site_option(
			$this->api->get_cache_key( 'test-plugin_error' ),
			[
				'error_cache' => $this->mock_http_response( 403, [ 'message' => 'Rate limited' ] ),
				'timeout'     => strtotime( '+1 hour' ),
			]
		);
	}

	// -------------------------------------------------------------------------
	// get_remote_api_info()  via  get_remote_info()
	// -------------------------------------------------------------------------

	/**
	 * When the API returns an error, get_remote_info() returns false.
	 */
	public function test_get_remote_info_returns_false_when_api_fails(): void {
		$this->seed_error_cache();
		$result = $this->api->get_remote_info( 'test-plugin.php' );
		$this->assertFalse( $result );
	}

	/**
	 * When the API returns valid base64-encoded plugin headers, get_remote_info()
	 * returns true and populates type properties.
	 */
	public function test_get_remote_info_returns_true_with_valid_plugin_headers(): void {
		$this->seed_dot_org_cache();

		$plugin_header = implode(
			"\n",
			[
				'<?php',
				'/**',
				' * Plugin Name: Test Plugin',
				' * Plugin URI: https://example.com',
				' * Version: 1.0.0',
				' * Description: A test plugin for unit testing.',
				' * Author: Test Author',
				' * Author URI: https://example.com/author',
				' * License: GPL-3.0-or-later',
				' * Text Domain: test-plugin',
				' */',
			]
		);

		$api_body = wp_json_encode(
			[
				'content'  => base64_encode( $plugin_header ),
				'encoding' => 'base64',
			]
		);
		$this->intercept_http_with( $this->mock_http_response_raw( 200, $api_body ) );

		$result = $this->api->get_remote_info( 'test-plugin.php' );
		$this->assertTrue( $result );
	}

	// -------------------------------------------------------------------------
	// get_remote_api_repo_meta()  via  get_repo_meta()
	// -------------------------------------------------------------------------

	/**
	 * When api() returns false (error cache hit), get_repo_meta() hits the
	 * !$response branch and returns null — counted as complete.
	 */
	public function test_get_repo_meta_returns_false_when_api_fails(): void {
		$this->seed_error_cache();
		$result = $this->api->get_repo_meta();
		$this->assertNull( $result );
	}

	/**
	 * When the API returns a valid repository object, get_repo_meta() returns true.
	 */
	public function test_get_repo_meta_returns_true_with_valid_meta(): void {
		$this->intercept_http_with(
			$this->mock_http_response(
				200,
				[
					'name'        => 'test-plugin',
					'private'     => false,
					'pushed_at'   => '2024-06-01T12:00:00Z',
					'created_at'  => '2023-01-01T00:00:00Z',
					'watchers'    => 42,
					'forks'       => 7,
					'open_issues' => 3,
				]
			)
		);

		$result = $this->api->get_repo_meta();
		$this->assertTrue( $result );
	}

	// -------------------------------------------------------------------------
	// get_remote_api_assets()  via  get_repo_assets()
	// -------------------------------------------------------------------------

	/**
	 * When the API returns a non-200 for all asset paths, the response object
	 * has a 'message' property which sets $error=true; since it's not a WP_Error,
	 * get_repo_assets() caches a 'No assets found' placeholder and returns null.
	 */
	public function test_get_repo_assets_returns_false_when_api_fails(): void {
		$this->intercept_http_with(
			$this->mock_http_response( 404, [ 'message' => 'Not Found' ] )
		);

		$result = $this->api->get_repo_assets();
		$this->assertNull( $result );
	}

	/**
	 * When the assets directory contains files, get_repo_assets() returns true.
	 */
	public function test_get_repo_assets_returns_true_with_file_listing(): void {
		$asset_listing = [
			[
				'type'         => 'file',
				'name'         => 'screenshot-1.png',
				'path'         => 'assets/screenshot-1.png',
				'download_url' => 'https://raw.githubusercontent.com/test-owner/test-plugin/master/assets/screenshot-1.png',
			],
		];
		$this->intercept_http_with( $this->mock_http_response( 200, $asset_listing ) );

		$result = $this->api->get_repo_assets();
		$this->assertTrue( $result );
	}

	// -------------------------------------------------------------------------
	// get_api_release_assets()  via  get_release_assets()
	// -------------------------------------------------------------------------

	/**
	 * When wp_remote_get() returns a WP_Error (network failure), parse_release_asset()
	 * short-circuits on the WP_Error and get_release_assets() returns false.
	 */
	public function test_get_release_assets_returns_false_when_api_fails(): void {
		add_filter(
			'pre_http_request',
			fn() => new WP_Error( 'http_request_failed', 'Connection refused' ),
			10,
			3
		);

		$result = $this->api->get_release_assets();
		$this->assertFalse( $result );
	}

	/**
	 * When the API returns a valid releases array with slug-matched assets,
	 * get_release_assets() returns the parsed assets array.
	 */
	public function test_get_release_assets_returns_array_with_valid_releases(): void {
		$releases = [
			[
				'tag_name' => '1.0.0',
				'assets'   => [
					[
						'name'       => 'test-plugin-1.0.0.zip',
						'url'        => 'https://api.github.com/repos/test-owner/test-plugin/releases/assets/123',
						'created_at' => '2024-06-01T12:00:00Z',
					],
				],
			],
		];
		$this->intercept_http_with( $this->mock_http_response( 200, $releases ) );

		$result = $this->api->get_release_assets();
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'assets', $result );
		$this->assertArrayHasKey( '1.0.0', $result['assets'] );
	}

	// -------------------------------------------------------------------------
	// get_remote_api_contents()  via  get_repo_contents()
	// -------------------------------------------------------------------------

	/**
	 * When api() returns false (error cache hit), get_repo_contents() hits the
	 * !$response branch and returns null — counted as complete.
	 */
	public function test_get_repo_contents_returns_false_when_api_fails(): void {
		$this->seed_error_cache();
		$result = $this->api->get_repo_contents();
		$this->assertNull( $result );
	}

	/**
	 * When the API returns a directory listing, get_repo_contents() returns true
	 * and stores the parsed files/dirs structure in cache.
	 */
	public function test_get_repo_contents_returns_true_with_valid_listing(): void {
		$listing = [
			[ 'type' => 'file', 'name' => 'readme.txt', 'path' => 'readme.txt' ],
			[ 'type' => 'file', 'name' => 'test-plugin.php', 'path' => 'test-plugin.php' ],
			[ 'type' => 'dir',  'name' => 'src', 'path' => 'src' ],
		];
		$this->intercept_http_with( $this->mock_http_response( 200, $listing ) );

		$result = $this->api->get_repo_contents();
		$this->assertTrue( $result );

		$cache = $this->api->get_repo_cache( 'test-plugin' );
		$this->assertArrayHasKey( 'contents', $cache );
		$this->assertContains( 'readme.txt', $cache['contents']['files'] );
		$this->assertContains( 'src', $cache['contents']['dirs'] );
	}
}
