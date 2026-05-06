<?php
/**
 * Integration tests for Plugin config discovery and Base::get_remote_repo_meta().
 *
 * REQUIRES the fixture plugin to be mounted in the wp-env container.
 * The fixture is listed in .wp-env.json:
 *   "plugins": [".", "tests/fixtures/plugins/test-gu-plugin"]
 *
 * After editing .wp-env.json, restart the environment so Docker picks up the change:
 *   npm run wp-env start
 *
 * Test_Plugin_Config_Discovery:
 * - Plugin::get_plugin_configs() discovers the fixture plugin by its GitHub Plugin URI
 *   header and populates the correct git, owner, type, slug, and primary_branch fields.
 *   These tests skip automatically when the fixture is not installed.
 *
 * Test_Plugin_Meta_HTTP_Mock:
 * - Base::get_remote_repo_meta() is exercised end-to-end without live HTTP.
 *   The pre_http_request filter intercepts all outbound calls and returns canned
 *   responses for each GitHub API endpoint and the WordPress.org plugins API.
 * - Asserts that remote_version is populated from the mocked file-contents response.
 *   These tests skip automatically when the fixture is not installed.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\Base;
use Fragen\Git_Updater\Plugin;

// ---------------------------------------------------------------------------
// Test_Plugin_Config_Discovery
// ---------------------------------------------------------------------------

/**
 * Class Test_Plugin_Config_Discovery
 */
class Test_Plugin_Config_Discovery extends WP_UnitTestCase {

	private const SLUG = 'test-gu-plugin';

	/** @var array<string, \stdClass> */
	private array $configs;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->configs = ( new Plugin() )->get_plugin_configs();

		if ( ! isset( $this->configs[ self::SLUG ] ) ) {
			$this->markTestSkipped(
				'Fixture plugin not installed. Run: npm run wp-env start'
			);
		}
	}

	public function test_fixture_plugin_is_in_plugin_configs(): void {
		$this->assertArrayHasKey( self::SLUG, $this->configs );
	}

	public function test_fixture_plugin_git_is_github(): void {
		$this->assertSame( 'github', $this->configs[ self::SLUG ]->git );
	}

	public function test_fixture_plugin_owner_is_afragen(): void {
		$this->assertSame( 'afragen', $this->configs[ self::SLUG ]->owner );
	}

	public function test_fixture_plugin_slug_matches(): void {
		$this->assertSame( self::SLUG, $this->configs[ self::SLUG ]->slug );
	}

	public function test_fixture_plugin_type_is_plugin(): void {
		$this->assertSame( 'plugin', $this->configs[ self::SLUG ]->type );
	}

	public function test_fixture_plugin_primary_branch_is_main(): void {
		$this->assertSame( 'main', $this->configs[ self::SLUG ]->primary_branch );
	}
}

// ---------------------------------------------------------------------------
// Test_Plugin_Meta_HTTP_Mock
// ---------------------------------------------------------------------------

/**
 * Class Test_Plugin_Meta_HTTP_Mock
 *
 * Exercises Base::get_remote_repo_meta() using pre_http_request to intercept
 * every outbound HTTP call and return canned responses.
 *
 * Response strategy:
 *  - /contents/test-gu-plugin.php  → base64-encoded plugin headers (Version 2.0.0)
 *  - /contents (root listing)      → single-file array so readme/changelog loops
 *                                    skip HTTP and set "not found" gracefully
 *  - /tags                         → minimal one-tag array
 *  - /branches                     → minimal one-branch array
 *  - /repos/afragen/test-gu-plugin → repo meta object
 *  - api.wordpress.org             → error body (plugin not on .org)
 *  - anything else                 → empty JSON array [] (valid 200, no error cache)
 */
class Test_Plugin_Meta_HTTP_Mock extends WP_UnitTestCase {

	private const SLUG = 'test-gu-plugin';

	private string    $cache_key;
	private \stdClass $config;

	public function set_up(): void {
		parent::set_up();
		new Base();

		$this->cache_key = 'ghu-' . md5( self::SLUG );

		// Start with a clean cache so nothing is skipped from a previous run.
		delete_site_option( $this->cache_key );
		delete_site_option( $this->cache_key . '_error' );

		$configs = ( new Plugin() )->get_plugin_configs();
		if ( ! isset( $configs[ self::SLUG ] ) ) {
			$this->markTestSkipped(
				'Fixture plugin not installed. Run: npm run wp-env start'
			);
		}
		$this->config = $configs[ self::SLUG ];

		add_filter( 'pre_http_request', [ $this, 'mock_http' ], 10, 3 );
	}

	public function tear_down(): void {
		remove_filter( 'pre_http_request', [ $this, 'mock_http' ], 10 );
		delete_site_option( $this->cache_key );
		delete_site_option( $this->cache_key . '_error' );
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// HTTP mock
	// -------------------------------------------------------------------------

	/**
	 * Intercept wp_remote_get() calls and return canned responses.
	 *
	 * @param mixed  $preempt Existing preempt value.
	 * @param mixed  $args    Request args (unused).
	 * @param string $url     Request URL.
	 * @return mixed Canned HTTP response array, or original $preempt for unhandled URLs.
	 */
	public function mock_http( mixed $preempt, mixed $args, string $url ): mixed {
		// WordPress.org plugin API — report "not found".
		if ( str_contains( $url, 'api.wordpress.org' ) ) {
			return $this->http_response( json_encode( [ 'error' => 'Plugin not found.' ] ) );
		}

		// Only intercept calls for our fixture repo.
		if ( ! str_contains( $url, 'api.github.com/repos/afragen/test-gu-plugin' ) ) {
			return $preempt;
		}

		$path = (string) parse_url( $url, PHP_URL_PATH );

		// Plugin PHP file contents — contains the plugin headers.
		if ( str_contains( $path, '/contents/test-gu-plugin.php' ) ) {
			return $this->http_response(
				json_encode(
					[
						'content'  => base64_encode( $this->fixture_plugin_content() ),
						'encoding' => 'base64',
					]
				)
			);
		}

		// Root directory listing — only our plugin file, so readme/changelog
		// loops find nothing and skip their HTTP calls.
		if ( '/repos/afragen/test-gu-plugin/contents' === $path ) {
			return $this->http_response(
				json_encode(
					[
						[ 'name' => 'test-gu-plugin.php', 'type' => 'file' ],
					]
				)
			);
		}

		// Tags.
		if ( str_ends_with( $path, '/tags' ) ) {
			return $this->http_response(
				json_encode(
					[
						[
							'name'        => '2.0.0',
							'zipball_url' => '',
							'commit'      => [ 'sha' => 'abc123def456' ],
						],
					]
				)
			);
		}

		// Branches.
		if ( str_ends_with( $path, '/branches' ) ) {
			return $this->http_response(
				json_encode(
					[
						[
							'name'   => 'main',
							'commit' => [
								'sha' => 'abc123def456',
								'url' => '',
							],
						],
					]
				)
			);
		}

		// Repository meta (exact path — no sub-resource).
		if ( '/repos/afragen/test-gu-plugin' === $path ) {
			return $this->http_response(
				json_encode(
					[
						'private'     => false,
						'pushed_at'   => '2024-06-01T12:00:00Z',
						'created_at'  => '2023-01-01T00:00:00Z',
						'watchers'    => 0,
						'forks'       => 0,
						'open_issues' => 0,
					]
				)
			);
		}

		// Any other path for this repo (assets, languages, etc.) — return an
		// empty JSON array.  A 200 with [] means: no error cache is set, and
		// validate_response([]) → empty → the calling getter returns false
		// gracefully.
		return $this->http_response( '[]' );
	}

	/**
	 * Build a minimal WP HTTP response array.
	 *
	 * @param string $body JSON body string.
	 * @param int    $code HTTP status code.
	 * @return array<string, mixed>
	 */
	private function http_response( string $body, int $code = 200 ): array {
		return [
			'headers'  => [],
			'body'     => $body,
			'response' => [
				'code'    => $code,
				'message' => 200 === $code ? 'OK' : 'Error',
			],
			'cookies'  => [],
			'filename' => null,
		];
	}

	/**
	 * Return the plugin file content that the mocked GitHub API will serve.
	 *
	 * These headers are what get_file_headers() parses to populate the
	 * repo object (Version → remote_version, etc.).
	 *
	 * @return string
	 */
	private function fixture_plugin_content(): string {
		return implode(
			"\n",
			[
				'<?php',
				'/**',
				' * Plugin Name:       Test GU Plugin',
				' * Plugin URI:        https://github.com/afragen/test-gu-plugin',
				' * Description:       Minimal fixture plugin for PHPUnit integration tests.',
				' * Version:           2.0.0',
				' * Author:            Test Author',
				' * License:           GPL-3.0-or-later',
				' * GitHub Plugin URI: https://github.com/afragen/test-gu-plugin',
				' * Primary Branch:    main',
				' */',
			]
		);
	}

	// -------------------------------------------------------------------------
	// Tests
	// -------------------------------------------------------------------------

	public function test_get_remote_repo_meta_returns_truthy(): void {
		$result = ( new Base() )->get_remote_repo_meta( $this->config );

		$this->assertNotFalse( $result );
	}

	public function test_get_remote_repo_meta_sets_remote_version_away_from_default(): void {
		( new Base() )->get_remote_repo_meta( $this->config );

		$this->assertNotSame( '0.0.0', $this->config->remote_version );
	}

	public function test_get_remote_repo_meta_sets_correct_remote_version(): void {
		( new Base() )->get_remote_repo_meta( $this->config );

		$this->assertSame( '2.0.0', $this->config->remote_version );
	}
}
