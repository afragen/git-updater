<?php
/**
 * Full coverage tests for API_Common trait — paths not yet exercised by
 * test-api-common.php or test-api-common-extended.php:
 *
 * - get_remote_api_readme()     success path (Readme_Parser integration)
 * - get_api_release_asset()     fail + success (both paths; method is
 *                               commented-out in GitHub_API so called directly
 *                               via the public trait method)
 * - parse_release_asset()       dev-release branch (nightly/alpha/beta/RC tags)
 * - Cache-hit paths             for tag, changes, readme, repo_meta,
 *                               release_assets, contents (skips HTTP fetch)
 *
 * HTTP is mocked via pre_http_request throughout.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\API\GitHub_API;
use Fragen\Git_Updater\Base;

class Test_API_Common_Full extends WP_UnitTestCase {

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

	private function seed_main_cache( array $data ): void {
		update_site_option(
			$this->api->get_cache_key( 'test-plugin' ),
			array_merge( [ 'timeout' => strtotime( '+12 hours' ) ], $data )
		);
	}

	// -------------------------------------------------------------------------
	// get_remote_api_readme() — success path
	// -------------------------------------------------------------------------

	/**
	 * When the API returns base64-encoded readme.txt content, get_remote_readme()
	 * parses it via Readme_Parser, caches the result, and returns true.
	 */
	public function test_get_remote_readme_returns_true_with_valid_readme(): void {
		$readme_content = implode(
			"\n",
			[
				'=== Test Plugin ===',
				'Contributors: testauthor',
				'Tags: test, unit',
				'Requires at least: 5.0',
				'Tested up to: 6.4',
				'Stable tag: 1.0.0',
				'License: GPL-3.0-or-later',
				'',
				'Short description for the test plugin.',
				'',
				'== Description ==',
				'',
				'Full description of the test plugin.',
				'',
				'== Changelog ==',
				'',
				'= 1.0.0 =',
				'* Initial release',
			]
		);

		$api_body = wp_json_encode(
			[
				'content'  => base64_encode( $readme_content ),
				'encoding' => 'base64',
			]
		);
		$this->intercept_http_with( $this->mock_http_response_raw( 200, $api_body ) );

		$result = $this->api->get_remote_readme();
		$this->assertTrue( $result );

		$cache = $this->api->get_repo_cache( 'test-plugin' );
		$this->assertArrayHasKey( 'readme', $cache );
	}

	/**
	 * After a successful fetch, a second call hits the cache and returns true
	 * without making any HTTP request.
	 */
	public function test_get_remote_readme_returns_true_from_cache(): void {
		$this->seed_main_cache( [ 'readme' => [ 'sections' => [ 'description' => 'Cached readme.' ] ] ] );

		$result = $this->api->get_remote_readme();
		$this->assertTrue( $result );
	}

	// -------------------------------------------------------------------------
	// get_api_release_asset() — fail path
	// -------------------------------------------------------------------------

	/**
	 * When wp_remote_get() fails with a WP_Error, parse_release_asset()
	 * short-circuits and get_api_release_asset() returns false.
	 *
	 * get_release_asset() in GitHub_API has its body commented out; the
	 * public trait method is called directly here.
	 */
	public function test_get_api_release_asset_returns_false_on_wp_error(): void {
		add_filter(
			'pre_http_request',
			fn() => new WP_Error( 'http_request_failed', 'Connection refused' ),
			10,
			3
		);

		$result = $this->api->get_api_release_asset(
			'github',
			'/repos/test-owner/test-plugin/releases/latest'
		);
		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// get_api_release_asset() — success path
	// -------------------------------------------------------------------------

	/**
	 * When the API returns a single release object (the 'latest' endpoint),
	 * parse_release_asset() wraps it in an array, matches the slug-prefixed
	 * asset, and get_api_release_asset() returns the parsed assets array.
	 */
	public function test_get_api_release_asset_returns_array_with_valid_release(): void {
		add_filter( 'gu_always_fetch_update', '__return_true' );

		$release = [
			'tag_name' => '1.0.0',
			'assets'   => [
				[
					'name'       => 'test-plugin-1.0.0.zip',
					'url'        => 'https://api.github.com/repos/test-owner/test-plugin/releases/assets/123',
					'created_at' => '2024-06-01T12:00:00Z',
				],
			],
		];
		$this->intercept_http_with( $this->mock_http_response( 200, $release ) );

		$result = $this->api->get_api_release_asset(
			'github',
			'/repos/test-owner/test-plugin/releases/latest'
		);
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'assets', $result );
		$this->assertArrayHasKey( '1.0.0', $result['assets'] );
	}

	/**
	 * Once a release asset is cached, a second call returns from cache without
	 * an HTTP round-trip.
	 */
	public function test_get_api_release_asset_returns_from_cache(): void {
		add_filter( 'gu_always_fetch_update', '__return_true' );

		$cached = [
			'assets'         => [ '1.0.0' => 'https://example.com/release.zip' ],
			'created_at'     => [ '1.0.0' => '2024-06-01T12:00:00Z' ],
			'dev_assets'     => [],
			'dev_created_at' => [],
		];
		$this->seed_main_cache( [ 'release_asset' => $cached ] );

		$result = $this->api->get_api_release_asset(
			'github',
			'/repos/test-owner/test-plugin/releases/latest'
		);
		$this->assertIsArray( $result );
		$this->assertSame( $cached, $result );
	}

	// -------------------------------------------------------------------------
	// parse_release_asset() — dev-release branch via get_release_assets()
	// -------------------------------------------------------------------------

	/**
	 * A release whose tag_name matches the dev-release pattern (e.g. 1.0.0-beta1)
	 * is collected into dev_assets / dev_created_at rather than the stable bucket.
	 */
	public function test_get_release_assets_captures_dev_release_with_beta_tag(): void {
		add_filter( 'gu_always_fetch_update', '__return_true' );

		$releases = [
			[
				'tag_name' => '1.0.0-beta1',
				'assets'   => [
					[
						'name'       => 'test-plugin-1.0.0-beta1.zip',
						'url'        => 'https://api.github.com/repos/test-owner/test-plugin/releases/assets/456',
						'created_at' => '2024-05-01T00:00:00Z',
					],
				],
			],
		];
		$this->intercept_http_with( $this->mock_http_response( 200, $releases ) );

		$result = $this->api->get_release_assets();
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'dev_assets', $result );
		$this->assertArrayHasKey( '1.0.0-beta1', $result['dev_assets'] );
		$this->assertEmpty( $result['assets'] );
	}

	/**
	 * A release whose tag_name matches the nightly pattern is also captured
	 * as a dev release.
	 */
	public function test_get_release_assets_captures_dev_release_with_nightly_tag(): void {
		add_filter( 'gu_always_fetch_update', '__return_true' );

		$releases = [
			[
				'tag_name' => '2.0.0-nightly20240601',
				'assets'   => [
					[
						'name'       => 'test-plugin-2.0.0-nightly20240601.zip',
						'url'        => 'https://api.github.com/repos/test-owner/test-plugin/releases/assets/789',
						'created_at' => '2024-06-01T00:00:00Z',
					],
				],
			],
		];
		$this->intercept_http_with( $this->mock_http_response( 200, $releases ) );

		$result = $this->api->get_release_assets();
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( '2.0.0-nightly20240601', $result['dev_assets'] );
		$this->assertEmpty( $result['assets'] );
	}

	// -------------------------------------------------------------------------
	// Cache-hit paths for remaining methods
	// -------------------------------------------------------------------------

	/**
	 * When tags are already cached, get_remote_tag() reads from cache and
	 * returns true without making an HTTP request.
	 */
	public function test_get_remote_tag_returns_true_from_cache(): void {
		$this->seed_main_cache(
			[ 'tags' => [ '1.0.0' => 'https://example.com/archive/1.0.0.zip' ] ]
		);

		$result = $this->api->get_remote_tag();
		$this->assertTrue( $result );
	}

	/**
	 * When a parsed changelog is already cached, get_remote_changes() reads
	 * from cache and returns true without making an HTTP request.
	 */
	public function test_get_remote_changes_returns_true_from_cache(): void {
		$this->seed_main_cache(
			[ 'changes' => '<h2>Changelog</h2><p>Initial release.</p>' ]
		);

		$result = $this->api->get_remote_changes( 'CHANGES.md' );
		$this->assertTrue( $result );
	}

	/**
	 * When repo meta is already cached, get_repo_meta() reads from cache and
	 * returns true without making an HTTP request.
	 */
	public function test_get_repo_meta_returns_true_from_cache(): void {
		$this->seed_main_cache(
			[ 'meta' => [ 'last_updated' => '2024-01-01', 'watchers' => 10 ] ]
		);

		$result = $this->api->get_repo_meta();
		$this->assertTrue( $result );
	}

	/**
	 * When the contents listing is already cached, get_repo_contents() reads
	 * from cache and returns true without making an HTTP request.
	 */
	public function test_get_repo_contents_returns_true_from_cache(): void {
		$this->seed_main_cache(
			[
				'contents' => [
					'files' => [ 'readme.txt', 'test-plugin.php' ],
					'dirs'  => [ 'src' ],
				],
			]
		);

		$result = $this->api->get_repo_contents();
		$this->assertTrue( $result );
	}

	/**
	 * When release assets are already cached (and gu_always_fetch_update is
	 * true so exit_no_update does not short-circuit), get_release_assets()
	 * returns the cached array directly.
	 */
	public function test_get_release_assets_returns_array_from_cache(): void {
		add_filter( 'gu_always_fetch_update', '__return_true' );

		$cached = [
			'assets'         => [ '1.0.0' => 'https://example.com/release.zip' ],
			'created_at'     => [ '1.0.0' => '2024-06-01T12:00:00Z' ],
			'dev_assets'     => [],
			'dev_created_at' => [],
		];
		$this->seed_main_cache( [ 'release_assets' => $cached ] );

		$result = $this->api->get_release_assets();
		$this->assertIsArray( $result );
		$this->assertSame( $cached, $result );
	}
}
