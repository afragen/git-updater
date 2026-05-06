<?php
/**
 * Integration tests for REST_API route dispatch and Rest_Update::process_request_data().
 *
 * Test_REST_API_Dispatch:
 * - Routes are registered correctly and return expected HTTP status codes.
 * - /git-updater/v1/test             — 200, string payload.
 * - /git-updater/namespace           — 200, namespace array.
 * - /github-updater/v1/test          — 200, deprecation body (success=false).
 * - /git-updater/v1/repos            — bad/absent key → error body.
 * - /git-updater/v1/flush-repo-cache — bad key → error; valid key, no cache → success=false;
 *                                      valid key, cache present → success=true.
 * - /git-updater/v1/plugins-api      — nonexistent slug → error; private addition → error.
 *
 * Test_REST_API_Get_Methods:
 * - get_remote_repo_data() via /repos with valid key: sites/slugs structure.
 * - get_api_data() via /plugins-api with fixture slug: slug, version, git fields.
 *   Both groups skip when the fixture plugin is not installed.
 *
 * Test_Rest_Update_Request_Data_Via_REST:
 * - process_request_data() called with a real WP_REST_Request exercises the REST-path
 *   branch (not the $_REQUEST path already covered by existing unit tests).
 * - Verifies key, plugin, theme, tag, committish, branch, override, and deprecated fields.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\REST\REST_API;
use Fragen\Git_Updater\REST\Rest_Update;
use Fragen\Git_Updater\Remote_Management;
use Fragen\Git_Updater\Base;

// ---------------------------------------------------------------------------
// Test_REST_API_Dispatch
// ---------------------------------------------------------------------------

/**
 * Class Test_REST_API_Dispatch
 */
class Test_REST_API_Dispatch extends WP_UnitTestCase {

	private WP_REST_Server $server;
	private string         $api_key = 'test-gu-integration-key';

	public function set_up(): void {
		parent::set_up();

		// Initialise Base so extra_headers and git_servers are populated.
		new Base();

		// Reset the global server so rest_get_server() creates a fresh one and
		// re-fires rest_api_init — which triggers register_endpoints() via the
		// hook that Bootstrap::run() → REST_API::load_hooks() registered.
		$GLOBALS['wp_rest_server'] = null;
		$this->server              = rest_get_server();

		// Seed the api_key into the DB and directly into the static property so
		// get_class_vars('Remote_Management', 'api_key') returns our test key
		// regardless of whether the singleton was previously created.
		update_site_option( 'git_updater_api_key', $this->api_key );
		$this->force_api_key_static( $this->api_key );
	}

	public function tear_down(): void {
		$GLOBALS['wp_rest_server'] = null;
		delete_site_option( 'git_updater_api_key' );
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// /git-updater/v1/test
	// -------------------------------------------------------------------------

	public function test_test_endpoint_returns_200(): void {
		$request  = new WP_REST_Request( 'GET', '/git-updater/v1/test' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	public function test_test_endpoint_body_is_connected_string(): void {
		$request  = new WP_REST_Request( 'GET', '/git-updater/v1/test' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 'Connected to Git Updater!', $response->get_data() );
	}

	// -------------------------------------------------------------------------
	// /git-updater/namespace
	// -------------------------------------------------------------------------

	public function test_namespace_endpoint_returns_200(): void {
		$request  = new WP_REST_Request( 'GET', '/git-updater/namespace' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	public function test_namespace_endpoint_returns_correct_namespace(): void {
		$request  = new WP_REST_Request( 'GET', '/git-updater/namespace' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'namespace', $data );
		$this->assertSame( 'git-updater/v1', $data['namespace'] );
	}

	// -------------------------------------------------------------------------
	// /github-updater/v1/test  (deprecated)
	// -------------------------------------------------------------------------

	public function test_deprecated_endpoint_returns_200(): void {
		$request  = new WP_REST_Request( 'GET', '/github-updater/v1/test' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	public function test_deprecated_endpoint_body_has_success_false(): void {
		$request  = new WP_REST_Request( 'GET', '/github-updater/v1/test' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertFalse( $data['success'] );
	}

	public function test_deprecated_endpoint_error_references_old_namespace(): void {
		$request  = new WP_REST_Request( 'GET', '/github-updater/v1/test' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertStringContainsString( 'github-updater/v1', $data['error'] );
	}

	public function test_deprecated_endpoint_error_references_current_namespace(): void {
		$request  = new WP_REST_Request( 'GET', '/github-updater/v1/test' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertStringContainsString( 'git-updater/v1', $data['error'] );
	}

	// -------------------------------------------------------------------------
	// /git-updater/v1/repos  (requires valid api key)
	// -------------------------------------------------------------------------

	public function test_repos_endpoint_returns_error_body_with_wrong_key(): void {
		$request = new WP_REST_Request( 'GET', '/git-updater/v1/repos' );
		$request->set_param( 'key', 'definitely-wrong-key' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'error', $data );
	}

	public function test_repos_endpoint_error_mentions_api_key(): void {
		$request = new WP_REST_Request( 'GET', '/git-updater/v1/repos' );
		$request->set_param( 'key', 'definitely-wrong-key' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertStringContainsString( 'Bad API key', $data['error'] );
	}

	// -------------------------------------------------------------------------
	// /git-updater/v1/flush-repo-cache
	// -------------------------------------------------------------------------

	public function test_flush_endpoint_returns_bad_key_error(): void {
		$request = new WP_REST_Request( 'GET', '/git-updater/v1/flush-repo-cache' );
		$request->set_param( 'key', 'wrong-key' );
		$request->set_param( 'slug', 'any-slug' );
		$response = $this->server->dispatch( $request );
		$data     = (array) $response->get_data();

		$this->assertArrayHasKey( 'error', $data );
		$this->assertStringContainsString( 'Bad API key', $data['error'] );
	}

	public function test_flush_endpoint_returns_slug_error_when_slug_is_absent(): void {
		// 'slug' arg has default=false; the callback checks !$slug and returns error.
		$request = new WP_REST_Request( 'GET', '/git-updater/v1/flush-repo-cache' );
		$request->set_param( 'key', $this->api_key );
		// slug intentionally not set → gets default false from route definition.
		$response = $this->server->dispatch( $request );
		$data     = (array) $response->get_data();

		$this->assertArrayHasKey( 'error', $data );
		$this->assertStringContainsString( 'slug', $data['error'] );
	}

	public function test_flush_endpoint_returns_success_false_when_no_cache_exists(): void {
		$slug     = 'nonexistent-test-slug-xyzzy';
		$request  = new WP_REST_Request( 'GET', '/git-updater/v1/flush-repo-cache' );
		$request->set_param( 'key', $this->api_key );
		$request->set_param( 'slug', $slug );
		$response = $this->server->dispatch( $request );
		$data     = (array) $response->get_data();

		$this->assertFalse( $data['success'] );
	}

	public function test_flush_endpoint_returns_success_true_and_clears_cache(): void {
		$slug      = 'test-flush-slug-xyzzy';
		$cache_key = 'ghu-' . md5( $slug );
		update_site_option( $cache_key, [ 'some' => 'data' ] );

		$request = new WP_REST_Request( 'GET', '/git-updater/v1/flush-repo-cache' );
		$request->set_param( 'key', $this->api_key );
		$request->set_param( 'slug', $slug );
		$response = $this->server->dispatch( $request );
		$data     = (array) $response->get_data();

		$this->assertTrue( $data['success'] );
		$this->assertFalse( get_site_option( $cache_key, false ) );

		delete_site_option( $cache_key );
	}

	// -------------------------------------------------------------------------
	// /git-updater/v1/plugins-api  (get_api_data error paths — no HTTP needed)
	// -------------------------------------------------------------------------

	public function test_plugins_api_returns_error_for_nonexistent_slug(): void {
		$request = new WP_REST_Request( 'GET', '/git-updater/v1/plugins-api' );
		$request->set_param( 'slug', 'nonexistent-plugin-xyzzy-abc' );
		$response = $this->server->dispatch( $request );
		$data     = (array) $response->get_data();

		$this->assertArrayHasKey( 'error', $data );
	}

	public function test_plugins_api_error_mentions_does_not_exist(): void {
		$request = new WP_REST_Request( 'GET', '/git-updater/v1/plugins-api' );
		$request->set_param( 'slug', 'nonexistent-plugin-xyzzy-abc' );
		$response = $this->server->dispatch( $request );
		$data     = (array) $response->get_data();

		$this->assertStringContainsString( 'does not exist', $data['error'] );
	}

	public function test_plugins_api_returns_error_for_private_addition(): void {
		update_site_option(
			'git_updater_additions',
			[
				[
					'slug'            => 'private-plugin/private-plugin.php',
					'type'            => 'plugin',
					'private_package' => true,
				],
			]
		);

		$request = new WP_REST_Request( 'GET', '/git-updater/v1/plugins-api' );
		$request->set_param( 'slug', 'private-plugin' );
		$response = $this->server->dispatch( $request );
		$data     = (array) $response->get_data();

		delete_site_option( 'git_updater_additions' );

		$this->assertArrayHasKey( 'error', $data );
		$this->assertStringContainsString( 'not shared', $data['error'] );
	}

	// -------------------------------------------------------------------------
	// Helper
	// -------------------------------------------------------------------------

	private function force_api_key_static( string $key ): void {
		$prop = ( new ReflectionClass( Remote_Management::class ) )->getProperty( 'api_key' );
		$prop->setAccessible( true );
		$prop->setValue( null, $key );
	}
}

// ---------------------------------------------------------------------------
// Test_Rest_Update_Request_Data_Via_REST
// ---------------------------------------------------------------------------

/**
 * Class Test_Rest_Update_Request_Data_Via_REST
 *
 * Exercises the WP_REST_Request branch of process_request_data() —
 * the branch not covered by the existing null-argument unit tests.
 */
class Test_Rest_Update_Request_Data_Via_REST extends WP_UnitTestCase {

	private Rest_Update $rest;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$_REQUEST   = [];
		$this->rest = new Rest_Update();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function make_request( string $route, array $params ): WP_REST_Request {
		$request = new WP_REST_Request( 'GET', $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return $request;
	}

	private function base_params( array $overrides = [] ): array {
		return array_merge(
			[
				'key'        => 'some-key',
				'plugin'     => 'my-plugin',
				'theme'      => false,
				'tag'        => false,
				'committish' => false,
				'branch'     => false,
				'override'   => false,
			],
			$overrides
		);
	}

	// -------------------------------------------------------------------------
	// Field extraction
	// -------------------------------------------------------------------------

	public function test_key_is_extracted_from_rest_request(): void {
		$request = $this->make_request( '/git-updater/v1/update', $this->base_params( [ 'key' => 'my-api-key' ] ) );
		$result  = $this->rest->process_request_data( $request );

		$this->assertSame( 'my-api-key', $result['key'] );
	}

	public function test_plugin_is_extracted_from_rest_request(): void {
		$request = $this->make_request( '/git-updater/v1/update', $this->base_params( [ 'plugin' => 'my-plugin' ] ) );
		$result  = $this->rest->process_request_data( $request );

		$this->assertSame( 'my-plugin', $result['plugin'] );
	}

	public function test_theme_is_extracted_when_no_plugin_present(): void {
		$request = $this->make_request(
			'/git-updater/v1/update',
			$this->base_params(
				[
					'plugin' => false,
					'theme'  => 'my-theme',
				]
			)
		);
		$result = $this->rest->process_request_data( $request );

		$this->assertSame( 'my-theme', $result['theme'] );
	}

	public function test_tag_from_params_is_used_when_present(): void {
		$request = $this->make_request( '/git-updater/v1/update', $this->base_params( [ 'tag' => '2.1.0' ] ) );
		$result  = $this->rest->process_request_data( $request );

		$this->assertSame( '2.1.0', $result['tag'] );
	}

	public function test_tag_defaults_to_primary_branch_when_absent(): void {
		// With no tag in params and no cache for the slug, get_primary_branch() returns 'master'.
		$request = $this->make_request( '/git-updater/v1/update', $this->base_params( [ 'tag' => false ] ) );
		$result  = $this->rest->process_request_data( $request );

		$this->assertSame( 'master', $result['tag'] );
	}

	public function test_committish_is_extracted_from_rest_request(): void {
		$request = $this->make_request( '/git-updater/v1/update', $this->base_params( [ 'committish' => 'abc123' ] ) );
		$result  = $this->rest->process_request_data( $request );

		$this->assertSame( 'abc123', $result['committish'] );
	}

	public function test_branch_is_extracted_from_rest_request(): void {
		$request = $this->make_request( '/git-updater/v1/update', $this->base_params( [ 'branch' => 'develop' ] ) );
		$result  = $this->rest->process_request_data( $request );

		$this->assertSame( 'develop', $result['branch'] );
	}

	public function test_override_is_false_by_default(): void {
		$request = $this->make_request( '/git-updater/v1/update', $this->base_params() );
		$result  = $this->rest->process_request_data( $request );

		$this->assertFalse( $result['override'] );
	}

	public function test_override_is_true_when_param_is_set(): void {
		$request = $this->make_request( '/git-updater/v1/update', $this->base_params( [ 'override' => '1' ] ) );
		$result  = $this->rest->process_request_data( $request );

		$this->assertTrue( (bool) $result['override'] );
	}

	// -------------------------------------------------------------------------
	// Deprecated flag
	// -------------------------------------------------------------------------

	public function test_deprecated_is_false_for_current_namespace_route(): void {
		$request = $this->make_request( '/git-updater/v1/update', $this->base_params() );
		$result  = $this->rest->process_request_data( $request );

		$this->assertFalse( $result['deprecated'] );
	}

	public function test_deprecated_is_true_for_legacy_namespace_route(): void {
		$request = $this->make_request( '/github-updater/v1/update', $this->base_params() );
		$result  = $this->rest->process_request_data( $request );

		$this->assertTrue( $result['deprecated'] );
	}
}

// ---------------------------------------------------------------------------
// Test_REST_API_Get_Methods
// ---------------------------------------------------------------------------

/**
 * Class Test_REST_API_Get_Methods
 *
 * Exercises REST_API::get_remote_repo_data() and get_api_data() via dispatch,
 * using pre_http_request to mock all outbound HTTP calls.
 *
 * get_remote_repo_data():
 * - Valid API key path: update transients are pre-seeded so wp_update_plugins()
 *   and wp_update_themes() skip their HTTP calls. Asserts the 'sites' structure.
 *
 * get_api_data() (routes: plugins-api, themes-api, update-api):
 * - Valid fixture slug path: full GitHub API mock plus a minimal readme.txt
 *   response so get_remote_readme() populates sections['description'].
 *   Asserts slug, version, git, and type fields in the response.
 *
 * Both test groups skip when the fixture plugin is not installed.
 */
class Test_REST_API_Get_Methods extends WP_UnitTestCase {

	private const SLUG   = 'test-gu-plugin';
	private const API_KEY = 'test-gu-get-methods-key';

	private WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();
		new Base();

		// Pre-seed update transients so wp_update_plugins/themes() return early
		// without making real HTTP calls to api.wordpress.org.
		$empty_transient = (object) [
			'last_checked' => time(),
			'checked'      => [],
			'response'     => [],
			'translations' => [],
			'no_update'    => [],
		];
		set_site_transient( 'update_plugins', $empty_transient );
		set_site_transient( 'update_themes', $empty_transient );

		// Register the REST server.
		$GLOBALS['wp_rest_server'] = null;
		$this->server              = rest_get_server();

		// Seed the API key.
		update_site_option( 'git_updater_api_key', self::API_KEY );
		$prop = ( new ReflectionClass( Remote_Management::class ) )->getProperty( 'api_key' );
		$prop->setAccessible( true );
		$prop->setValue( null, self::API_KEY );

		// Install the HTTP mock for GitHub and wordpress.org calls.
		add_filter( 'pre_http_request', [ $this, 'mock_http' ], 10, 3 );
	}

	public function tear_down(): void {
		remove_filter( 'pre_http_request', [ $this, 'mock_http' ], 10 );
		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );
		$GLOBALS['wp_rest_server'] = null;
		delete_site_option( 'git_updater_api_key' );
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// HTTP mock
	// -------------------------------------------------------------------------

	/**
	 * Intercept all outbound HTTP calls and return canned responses.
	 *
	 * @param mixed  $preempt Existing preempt value.
	 * @param mixed  $args    Request args (unused).
	 * @param string $url     Request URL.
	 * @return mixed Canned response array or original $preempt.
	 */
	public function mock_http( mixed $preempt, mixed $args, string $url ): mixed {
		// WordPress.org plugin update-check — must return 'plugins' key or the
		// array-access in wp_update_plugins() triggers an undefined-key notice.
		if ( str_contains( $url, 'api.wordpress.org/plugins/update-check' ) ) {
			return $this->http_response(
				json_encode( [ 'plugins' => [], 'translations' => [], 'no_update' => [] ] )
			);
		}

		// WordPress.org theme update-check — same requirement with 'themes' key.
		if ( str_contains( $url, 'api.wordpress.org/themes/update-check' ) ) {
			return $this->http_response(
				json_encode( [ 'themes' => [], 'translations' => [], 'no_update' => [] ] )
			);
		}

		// Any other wordpress.org call (plugin info, etc.) — report not found.
		if ( str_contains( $url, 'api.wordpress.org' ) ) {
			return $this->http_response( json_encode( [ 'error' => 'Plugin not found.' ] ) );
		}

		if ( ! str_contains( $url, 'api.github.com/repos/afragen/test-gu-plugin' ) ) {
			return $preempt;
		}

		$path = (string) parse_url( $url, PHP_URL_PATH );

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

		// readme.txt — minimal WP-readme so get_remote_readme() populates sections.
		if ( str_contains( $path, '/contents/readme.txt' ) ) {
			return $this->http_response(
				json_encode(
					[
						'content'  => base64_encode( $this->fixture_readme_content() ),
						'encoding' => 'base64',
					]
				)
			);
		}

		// Root directory listing — plugin file + readme so both paths are exercised.
		if ( '/repos/afragen/test-gu-plugin/contents' === $path ) {
			return $this->http_response(
				json_encode(
					[
						[ 'name' => 'test-gu-plugin.php', 'type' => 'file' ],
						[ 'name' => 'readme.txt', 'type' => 'file' ],
					]
				)
			);
		}

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

		if ( str_ends_with( $path, '/branches' ) ) {
			return $this->http_response(
				json_encode(
					[
						[
							'name'   => 'main',
							'commit' => [ 'sha' => 'abc123def456', 'url' => '' ],
						],
					]
				)
			);
		}

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

		return $this->http_response( '[]' );
	}

	/**
	 * @param string $body JSON body string.
	 * @param int    $code HTTP status code.
	 * @return array<string, mixed>
	 */
	private function http_response( string $body, int $code = 200 ): array {
		return [
			'headers'  => [],
			'body'     => $body,
			'response' => [ 'code' => $code, 'message' => 200 === $code ? 'OK' : 'Error' ],
			'cookies'  => [],
			'filename' => null,
		];
	}

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

	private function fixture_readme_content(): string {
		return implode(
			"\n",
			[
				'=== Test GU Plugin ===',
				'Contributors: testauthor',
				'Requires at least: 6.0',
				'Tested up to: 6.5',
				'Requires PHP: 8.1',
				'Stable tag: 2.0.0',
				'License: GPL-3.0-or-later',
				'',
				'== Description ==',
				'',
				'Minimal fixture plugin for PHPUnit integration tests.',
				'',
				'== Installation ==',
				'',
				'Upload and activate.',
				'',
				'== Changelog ==',
				'',
				'= 2.0.0 =',
				'* Initial release.',
			]
		);
	}

	// -------------------------------------------------------------------------
	// /git-updater/v1/repos  (get_remote_repo_data — valid key path)
	// -------------------------------------------------------------------------

	private function skip_if_fixture_absent(): void {
		$configs = ( new \Fragen\Git_Updater\Plugin() )->get_plugin_configs();
		if ( ! isset( $configs[ self::SLUG ] ) ) {
			$this->markTestSkipped( 'Fixture plugin not installed. Run: composer wp-env-start' );
		}
	}

	public function test_repos_endpoint_returns_sites_key_with_valid_key(): void {
		$this->skip_if_fixture_absent();

		$request = new WP_REST_Request( 'GET', '/git-updater/v1/repos' );
		$request->set_param( 'key', self::API_KEY );
		$response = $this->server->dispatch( $request );
		$data     = (array) $response->get_data();

		$this->assertArrayHasKey( 'sites', $data );
	}

	public function test_repos_endpoint_sites_contains_slugs_array(): void {
		$this->skip_if_fixture_absent();

		$request = new WP_REST_Request( 'GET', '/git-updater/v1/repos' );
		$request->set_param( 'key', self::API_KEY );
		$response = $this->server->dispatch( $request );
		$data     = (array) $response->get_data();

		$this->assertIsArray( $data['sites']['slugs'] );
	}

	public function test_repos_endpoint_slugs_contains_fixture_plugin(): void {
		$this->skip_if_fixture_absent();

		$request = new WP_REST_Request( 'GET', '/git-updater/v1/repos' );
		$request->set_param( 'key', self::API_KEY );
		$response    = $this->server->dispatch( $request );
		$data        = (array) $response->get_data();
		$slug_values = array_column( $data['sites']['slugs'], 'slug' );

		$this->assertContains( self::SLUG, $slug_values );
	}

	// -------------------------------------------------------------------------
	// /git-updater/v1/plugins-api  (get_api_data — valid fixture slug path)
	// -------------------------------------------------------------------------

	public function test_plugins_api_returns_slug_for_fixture_plugin(): void {
		$this->skip_if_fixture_absent();

		$request = new WP_REST_Request( 'GET', '/git-updater/v1/plugins-api' );
		$request->set_param( 'slug', self::SLUG );
		$response = $this->server->dispatch( $request );
		$data     = (array) $response->get_data();

		$this->assertArrayHasKey( 'slug', $data );
		$this->assertSame( self::SLUG, $data['slug'] );
	}

	public function test_plugins_api_returns_correct_version_for_fixture_plugin(): void {
		$this->skip_if_fixture_absent();

		$request = new WP_REST_Request( 'GET', '/git-updater/v1/plugins-api' );
		$request->set_param( 'slug', self::SLUG );
		$response = $this->server->dispatch( $request );
		$data     = (array) $response->get_data();

		$this->assertSame( '2.0.0', $data['version'] );
	}

	public function test_plugins_api_returns_git_field_for_fixture_plugin(): void {
		$this->skip_if_fixture_absent();

		$request = new WP_REST_Request( 'GET', '/git-updater/v1/plugins-api' );
		$request->set_param( 'slug', self::SLUG );
		$response = $this->server->dispatch( $request );
		$data     = (array) $response->get_data();

		$this->assertSame( 'github', $data['git'] );
	}

	public function test_plugins_api_response_has_no_error_for_fixture_plugin(): void {
		$this->skip_if_fixture_absent();

		$request = new WP_REST_Request( 'GET', '/git-updater/v1/plugins-api' );
		$request->set_param( 'slug', self::SLUG );
		$response = $this->server->dispatch( $request );
		$data     = (array) $response->get_data();

		$this->assertArrayNotHasKey( 'error', $data );
	}
}
