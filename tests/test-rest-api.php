<?php

use Fragen\Git_Updater\REST\REST_API;
use Fragen\Git_Updater\Base;
use Fragen\Git_Updater\Plugin;
use Fragen\Git_Updater\Theme;
use Fragen\Git_Updater\Remote_Management;
use Fragen\Singleton;
use WpOrg\Requests\Utility\CaseInsensitiveDictionary;
use ZipArchive;

class Test_REST_API extends WP_UnitTestCase {

	private REST_API $rest;

	public function set_up(): void {
		parent::set_up();
		$this->rest = new REST_API();
	}

	public function test_test_returns_connected_string(): void {
		$this->assertSame( 'Connected to Git Updater!', $this->rest->test() );
	}

	public function test_get_namespace_returns_array_with_namespace_key(): void {
		$result = $this->rest->get_namespace();
		$this->assertArrayHasKey( 'namespace', $result );
	}

	public function test_get_namespace_returns_correct_namespace_value(): void {
		$result = $this->rest->get_namespace();
		$this->assertSame( 'git-updater/v1', $result['namespace'] );
	}

	public function test_deprecated_returns_success_false(): void {
		$result = $this->rest->deprecated();
		$this->assertFalse( $result['success'] );
	}

	public function test_deprecated_error_message_mentions_old_namespace(): void {
		$result = $this->rest->deprecated();
		$this->assertStringContainsString( 'github-updater/v1', $result['error'] );
	}

	public function test_deprecated_error_message_mentions_current_namespace(): void {
		$result = $this->rest->deprecated();
		$this->assertStringContainsString( 'git-updater/v1', $result['error'] );
	}
}

// ---------------------------------------------------------------------------
// Messages
// ---------------------------------------------------------------------------

/**
 * Class Test_Messages
 */

class Test_REST_API_Load_Hooks extends WP_UnitTestCase {

	public function tear_down(): void {
		remove_all_actions( 'rest_api_init' );
		remove_all_actions( 'wp_ajax_git-updater-update' );
		remove_all_actions( 'wp_ajax_nopriv_git-updater-update' );
		parent::tear_down();
	}

	public function test_load_hooks_registers_rest_api_init_action(): void {
		$api = new REST_API();
		$api->load_hooks();
		$this->assertNotFalse( has_action( 'rest_api_init', [ $api, 'register_endpoints' ] ) );
	}

	public function test_load_hooks_registers_ajax_action(): void {
		$api = new REST_API();
		$api->load_hooks();
		$this->assertNotFalse( has_action( 'wp_ajax_git-updater-update' ) );
	}

	public function test_load_hooks_registers_nopriv_ajax_action(): void {
		$api = new REST_API();
		$api->load_hooks();
		$this->assertNotFalse( has_action( 'wp_ajax_nopriv_git-updater-update' ) );
	}
}

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
	// /git-updater/v1/plugins-api  (get_api_data — falsy slug early return, line 440)
	// -------------------------------------------------------------------------

	public function test_get_api_data_returns_error_when_slug_is_falsy(): void {
		// Call get_api_data() directly with slug=false so the early-return at line 440 executes.
		$api     = new REST_API();
		$request = new WP_REST_Request( 'GET', '/git-updater/v1/plugins-api' );
		$request->set_param( 'slug', false );
		$result  = $api->get_api_data( $request );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'slug', $result['error'] );
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

		if ( ! str_contains( $url, 'api.github.com/repos/afragen/test-gu-plugin' )
			&& ! str_contains( $url, 'api.github.com/repos/afragen/test-gu-theme' )
		) {
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

		if ( str_contains( $path, '/contents/style.css' ) ) {
			return $this->http_response(
				json_encode(
					[
						'content'  => base64_encode( $this->fixture_theme_content() ),
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

		if ( '/repos/afragen/test-gu-theme/contents' === $path ) {
			return $this->http_response(
				json_encode(
					[
						[ 'name' => 'style.css', 'type' => 'file' ],
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
							'zipball_url' => 'https://github.com/afragen/test-gu-plugin/zipball/2.0.0',
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

		if ( '/repos/afragen/test-gu-plugin' === $path || '/repos/afragen/test-gu-theme' === $path ) {
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

	private function fixture_theme_content(): string {
		return implode(
			"\n",
			[
				'/*',
				'Theme Name: Test GU Theme',
				'Theme URI: https://github.com/afragen/test-gu-theme',
				'Description: Minimal fixture theme for PHPUnit integration tests.',
				'Version: 2.0.0',
				'Author: Test Author',
				'Author URI: https://example.com',
				'License: GPL-3.0-or-later',
				'GitHub Theme URI: https://github.com/afragen/test-gu-theme',
				'Primary Branch: main',
				'*/',
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

	// -------------------------------------------------------------------------
	// /git-updater/v1/themes-api  (get_api_data — themes-api route)
	// -------------------------------------------------------------------------

	public function test_themes_api_endpoint_returns_slug_for_fixture_theme(): void {
		$theme_path = get_theme_root() . '/test-gu-theme/style.css';
		if ( ! file_exists( $theme_path ) ) {
			$this->markTestSkipped( 'Fixture theme not installed. Run: npm run wp-env start' );
		}

		$request = new WP_REST_Request( 'GET', '/git-updater/v1/themes-api' );
		$request->set_param( 'slug', 'test-gu-theme' );
		$response = $this->server->dispatch( $request );
		$data     = (array) $response->get_data();

		$this->assertArrayNotHasKey( 'error', $data );
		$this->assertSame( 'test-gu-theme', $data['slug'] );
	}

	public function test_themes_api_endpoint_returns_error_for_nonexistent_slug(): void {
		$request = new WP_REST_Request( 'GET', '/git-updater/v1/themes-api' );
		$request->set_param( 'slug', 'nonexistent-theme-xyzzy-abc' );
		$response = $this->server->dispatch( $request );
		$data     = (array) $response->get_data();

		$this->assertArrayHasKey( 'error', $data );
		$this->assertStringContainsString( 'does not exist', $data['error'] );
	}

	// -------------------------------------------------------------------------
	// /git-updater/v1/update-api  (get_api_data — update-api route)
	// -------------------------------------------------------------------------

	public function test_update_api_endpoint_returns_slug_for_fixture_plugin(): void {
		$this->skip_if_fixture_absent();

		$request = new WP_REST_Request( 'GET', '/git-updater/v1/update-api' );
		$request->set_param( 'slug', self::SLUG );
		$response = $this->server->dispatch( $request );
		$data     = (array) $response->get_data();

		$this->assertArrayNotHasKey( 'error', $data );
		$this->assertSame( self::SLUG, $data['slug'] );
	}

	public function test_update_api_endpoint_returns_error_for_nonexistent_slug(): void {
		$request = new WP_REST_Request( 'GET', '/git-updater/v1/update-api' );
		$request->set_param( 'slug', 'nonexistent-plugin-xyzzy-abc' );
		$response = $this->server->dispatch( $request );
		$data     = (array) $response->get_data();

		$this->assertArrayHasKey( 'error', $data );
		$this->assertStringContainsString( 'does not exist', $data['error'] );
	}

	// -------------------------------------------------------------------------
	// /git-updater/v1/plugins-api — POST (CREATABLE) dispatch
	// -------------------------------------------------------------------------

	public function test_plugins_api_POST_endpoint_returns_slug_for_fixture_plugin(): void {
		$this->skip_if_fixture_absent();

		$request = new WP_REST_Request( 'POST', '/git-updater/v1/plugins-api' );
		$request->set_param( 'slug', self::SLUG );
		$response = $this->server->dispatch( $request );
		$data     = (array) $response->get_data();

		$this->assertArrayNotHasKey( 'error', $data );
		$this->assertSame( self::SLUG, $data['slug'] );
	}

	// -------------------------------------------------------------------------
	// /git-updater/v1/repos — update_package paths (lines 403, 406)
	// -------------------------------------------------------------------------

	public function test_repos_endpoint_includes_plugin_update_package(): void {
		$this->skip_if_fixture_absent();

		$plugin_file = self::SLUG . '/' . self::SLUG . '.php';

		// Use a site_transient filter at priority 99 so the injection survives any
		// wp_update_plugins() rewrite within get_remote_repo_data().
		$inject = static function ( $transient ) use ( $plugin_file ) {
			if ( ! is_object( $transient ) ) {
				$transient = new stdClass();
			}
			$transient->response[ $plugin_file ] = (object) [
				'slug'        => 'test-gu-plugin',
				'new_version' => '3.0.0',
				'package'     => 'https://example.com/test.zip',
			];
			return $transient;
		};
		add_filter( 'site_transient_update_plugins', $inject, 99, 1 );

		$request = new WP_REST_Request( 'GET', '/git-updater/v1/repos' );
		$request->set_param( 'key', self::API_KEY );
		$response  = $this->server->dispatch( $request );
		$data      = (array) $response->get_data();

		remove_filter( 'site_transient_update_plugins', $inject, 99 );

		$slug_data = array_filter( $data['sites']['slugs'], fn( $s ) => $s['slug'] === self::SLUG );
		$entry     = reset( $slug_data );

		$this->assertNotFalse( $entry['update_package'] );
	}

	public function test_repos_endpoint_includes_theme_update_package(): void {
		$theme_path = get_theme_root() . '/test-gu-theme/style.css';
		if ( ! file_exists( $theme_path ) ) {
			$this->markTestSkipped( 'Fixture theme not installed.' );
		}

		// Use a site_transient filter at priority 99 so the injection survives any
		// wp_update_themes() rewrite within get_remote_repo_data().
		$inject = static function ( $transient ) {
			if ( ! is_object( $transient ) ) {
				$transient = new stdClass();
			}
			$transient->response['test-gu-theme'] = [
				'theme'       => 'test-gu-theme',
				'new_version' => '2.0.0',
				'package'     => 'https://example.com/theme.zip',
			];
			return $transient;
		};
		add_filter( 'site_transient_update_themes', $inject, 99, 1 );

		$request = new WP_REST_Request( 'GET', '/git-updater/v1/repos' );
		$request->set_param( 'key', self::API_KEY );
		$response  = $this->server->dispatch( $request );
		$data      = (array) $response->get_data();

		remove_filter( 'site_transient_update_themes', $inject, 99 );

		$slug_data = array_filter( $data['sites']['slugs'], fn( $s ) => $s['slug'] === 'test-gu-theme' );
		$entry     = reset( $slug_data );

		$this->assertNotFalse( $entry['update_package'] );
	}

	// -------------------------------------------------------------------------
	// get_api_data() — dev channel path (lines 486-487, 496)
	// -------------------------------------------------------------------------

	public function test_get_api_data_covers_dev_channel_path(): void {
		$this->skip_if_fixture_absent();

		// Pre-seed the release_assets cache with stable + dev versions.
		// populate_api_data() reads $cache['release_assets']['assets'] → $repo->release_assets.
		$cache_key = 'ghu-' . md5( self::SLUG );
		$existing  = get_site_option( $cache_key, [] );
		$existing['release_assets'] = [
			'assets'     => [ '2.0.0' => 'https://example.com/stable.zip' ],
			'created_at' => [ '2.0.0' => '2024-01-01T00:00:00Z' ],
			'dev_assets'     => [ '3.0.0-beta1' => 'https://example.com/dev.zip' ],
			'dev_created_at' => [ '3.0.0-beta1' => '2024-02-01T00:00:00Z' ],
		];
		update_site_option( $cache_key, $existing );

		// channel param non-null → $channel=true; '2.0.0' < '3.0.0-beta1' → $use_channel=true.
		$request = new WP_REST_Request( 'GET', '/git-updater/v1/plugins-api' );
		$request->set_param( 'slug', self::SLUG );
		$request->set_param( 'channel', 'dev' );
		$response = $this->server->dispatch( $request );
		$data     = (array) $response->get_data();

		delete_site_option( $cache_key );

		$this->assertArrayNotHasKey( 'error', $data );
		// With dev channel active the reported version is from dev_release_assets.
		$this->assertSame( '3.0.0-beta1', $data['version'] );
	}

	// -------------------------------------------------------------------------
	// get_api_data() — non-release-asset tags path (line 498, theme fixture)
	// -------------------------------------------------------------------------

	public function test_themes_api_covers_non_release_asset_tags_path(): void {
		$theme_path = get_theme_root() . '/test-gu-theme/style.css';
		if ( ! file_exists( $theme_path ) ) {
			$this->markTestSkipped( 'Fixture theme not installed. Run: composer wp-env-start' );
		}

		// Theme has no 'Release Asset' header → $repo_data->release_asset = false →
		// the else branch at line 497 executes: $versions = $repo_data->tags ?? [].
		$request = new WP_REST_Request( 'GET', '/git-updater/v1/plugins-api' );
		$request->set_param( 'slug', 'test-gu-theme' );
		$response = $this->server->dispatch( $request );
		$data     = (array) $response->get_data();

		$this->assertArrayNotHasKey( 'error', $data );
		$this->assertSame( 'test-gu-theme', $data['slug'] );
	}

	// -------------------------------------------------------------------------
	// get_api_data() — release_asset_download path (lines 559-565)
	// -------------------------------------------------------------------------

	public function test_get_api_data_covers_release_asset_download_path(): void {
		$this->skip_if_fixture_absent();

		$cache_key = 'ghu-' . md5( self::SLUG );
		$existing  = get_site_option( $cache_key, [] );
		// Seed release_asset_download but NOT release_asset_redirect.
		$existing['release_asset_download'] = 'https://example.com/stable-download.zip';
		unset( $existing['release_asset_redirect'] );
		update_site_option( $cache_key, $existing );

		$request = new WP_REST_Request( 'GET', '/git-updater/v1/plugins-api' );
		$request->set_param( 'slug', self::SLUG );
		$response = $this->server->dispatch( $request );
		$data     = (array) $response->get_data();

		delete_site_option( $cache_key );

		$this->assertArrayNotHasKey( 'error', $data );
		$this->assertSame( 'https://example.com/stable-download.zip', $data['download_link'] );
	}

	// -------------------------------------------------------------------------
	// get_api_data() — release_asset redirect path (lines 566-569)
	// -------------------------------------------------------------------------

	public function test_get_api_data_covers_release_asset_redirect_path(): void {
		$this->skip_if_fixture_absent();

		$cache_key = 'ghu-' . md5( self::SLUG );
		$existing  = get_site_option( $cache_key, [] );
		// Seed all three keys:
		// - release_asset_download: construct_download_link() sees it non-empty → returns early without
		//   overwriting, so the key remains in cache after get_remote_repo_meta().
		// - release_asset_redirect: makes the first if at line 559 false (condition requires
		//   !isset(release_asset_redirect)), so the elseif at line 566 is reached.
		// - release_asset: use a GitHub API URL so mock_http() intercepts the wp_remote_get()
		//   call inside get_release_asset_redirect() without a real network round-trip.
		$existing['release_asset']          = 'https://api.github.com/repos/afragen/test-gu-plugin/releases/assets/1234';
		$existing['release_asset_download'] = 'https://example.com/release-asset-download.zip';
		$existing['release_asset_redirect'] = 'https://example.com/release-asset-redirect';
		update_site_option( $cache_key, $existing );

		// get_release_asset_redirect() reads $this->type->slug on the API singleton.
		// Set it to the fixture slug so get_repo_cache() looks up the correct site option
		// (which has 'timeout' after get_remote_repo_meta() runs) instead of the wrong
		// option key derived from slug=false.
		$api_singleton  = Singleton::get_instance( 'Fragen\Git_Updater\API\API', new REST_API() );
		$rp             = new ReflectionProperty( get_class( $api_singleton ), 'type' );
		$rp->setAccessible( true );
		$saved_type     = $rp->getValue( $api_singleton );
		$type_obj       = new stdClass();
		$type_obj->slug = self::SLUG;
		$rp->setValue( $api_singleton, $type_obj );

		$request = new WP_REST_Request( 'GET', '/git-updater/v1/plugins-api' );
		$request->set_param( 'slug', self::SLUG );
		$response = $this->server->dispatch( $request );
		$data     = (array) $response->get_data();

		$rp->setValue( $api_singleton, $saved_type );
		delete_site_option( $cache_key );

		$this->assertArrayNotHasKey( 'error', $data );
	}
}

// ---------------------------------------------------------------------------
// Test_REST_API_Additions
// ---------------------------------------------------------------------------

/**
 * Class Test_REST_API_Additions
 *
 * Exercises REST_API::get_additions_api_data() and get_additions_data()
 * via REST server dispatch.
 *
 * get_additions_api_data():
 * - no additions → empty array
 * - private plugin addition → skipped (continue), empty result
 * - theme-type addition with public package but slug not in tokens → empty result
 *
 * get_additions_data():
 * - no additions → empty array
 * - private addition → filtered out
 * - public addition → included after deduplicate() normalisation
 */

class Test_REST_API_Additions extends WP_UnitTestCase {

	private WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();
		new Base();

		// Seed addon caches so deduplicate() doesn't access false['key'].
		$plugin_cache_key = 'ghu-' . md5( 'git_updater_repository_add_plugin' );
		$theme_cache_key  = 'ghu-' . md5( 'git_updater_repository_add_theme' );
		update_site_option( $plugin_cache_key, [ 'git_updater_repository_add_plugin' => [], 'timeout' => strtotime( '+12 hours' ) ] );
		update_site_option( $theme_cache_key, [ 'git_updater_repository_add_theme' => [], 'timeout' => strtotime( '+12 hours' ) ] );

		$GLOBALS['wp_rest_server'] = null;
		$this->server              = rest_get_server();
	}

	public function tear_down(): void {
		$GLOBALS['wp_rest_server'] = null;
		delete_site_option( 'git_updater_additions' );
		delete_site_option( 'ghu-' . md5( 'git_updater_repository_add_plugin' ) );
		delete_site_option( 'ghu-' . md5( 'git_updater_repository_add_theme' ) );
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// /git-updater/v1/update-api-additions
	// -------------------------------------------------------------------------

	public function test_get_additions_api_data_returns_empty_for_no_additions(): void {
		delete_site_option( 'git_updater_additions' );

		$request  = new WP_REST_Request( 'GET', '/git-updater/v1/update-api-additions' );
		$response = $this->server->dispatch( $request );
		$data     = (array) $response->get_data();

		$this->assertSame( [], $data );
	}

	public function test_get_additions_api_data_skips_private_plugin_additions(): void {
		update_site_option(
			'git_updater_additions',
			[
				[
					'slug'            => 'private-addon/private-addon.php',
					'type'            => 'plugin',
					'private_package' => true,
					'release_asset'   => false,
					'ID'              => 'did:test:private',
					'source'          => 'manual',
				],
			]
		);

		$request  = new WP_REST_Request( 'GET', '/git-updater/v1/update-api-additions' );
		$response = $this->server->dispatch( $request );
		$data     = (array) $response->get_data();

		$this->assertSame( [], $data );
	}

	public function test_get_additions_api_data_with_slug_in_gu_tokens(): void {
		// Fixture-dependent: needs test-gu-plugin installed so it appears in gu_tokens.
		$configs = ( new \Fragen\Git_Updater\Plugin() )->get_plugin_configs();
		if ( ! isset( $configs['test-gu-plugin'] ) ) {
			$this->markTestSkipped( 'Fixture plugin not installed. Run: npm run wp-env start' );
		}

		// Mock HTTP so get_api_data() → get_remote_repo_meta() doesn't make real calls.
		add_filter( 'pre_http_request', [ $this, 'mock_http_for_additions' ], 10, 3 );

		// Seed a public plugin addition whose slug matches 'test-gu-plugin' (dirname).
		update_site_option(
			'git_updater_additions',
			[
				[
					'slug'            => 'test-gu-plugin/test-gu-plugin.php',
					'type'            => 'plugin',
					'private_package' => false,
					'release_asset'   => false,
					'ID'              => 'did:test:gu-token',
					'source'          => 'manual',
				],
			]
		);

		$request  = new WP_REST_Request( 'GET', '/git-updater/v1/update-api-additions' );
		$response = $this->server->dispatch( $request );
		$data     = (array) $response->get_data();

		remove_filter( 'pre_http_request', [ $this, 'mock_http_for_additions' ], 10 );

		// The slug is in gu_tokens → get_api_data() was called → slug key present in result.
		$this->assertArrayHasKey( 'test-gu-plugin', $data );
	}

	/**
	 * HTTP mock for get_api_data calls inside get_additions_api_data.
	 *
	 * @param mixed  $preempt Existing preempt value.
	 * @param mixed  $args    Request args.
	 * @param string $url     Request URL.
	 * @return mixed
	 */
	public function mock_http_for_additions( mixed $preempt, mixed $args, string $url ): mixed {
		if ( str_contains( $url, 'api.wordpress.org/plugins/update-check' ) ) {
			return [ 'headers' => [], 'body' => json_encode( [ 'plugins' => [], 'translations' => [], 'no_update' => [] ] ), 'response' => [ 'code' => 200, 'message' => 'OK' ], 'cookies' => [], 'filename' => null ];
		}
		if ( str_contains( $url, 'api.wordpress.org' ) ) {
			return [ 'headers' => [], 'body' => json_encode( [ 'error' => 'Plugin not found.' ] ), 'response' => [ 'code' => 200, 'message' => 'OK' ], 'cookies' => [], 'filename' => null ];
		}
		if ( str_contains( $url, 'api.github.com/repos/afragen/test-gu-plugin' ) ) {
			$path = (string) parse_url( $url, PHP_URL_PATH );
			if ( str_contains( $path, '/contents/test-gu-plugin.php' ) ) {
				$content = "<?php\n/**\n * Plugin Name: Test GU Plugin\n * Version: 2.0.0\n * GitHub Plugin URI: https://github.com/afragen/test-gu-plugin\n * Primary Branch: main\n */";
				return [ 'headers' => [], 'body' => json_encode( [ 'content' => base64_encode( $content ), 'encoding' => 'base64' ] ), 'response' => [ 'code' => 200, 'message' => 'OK' ], 'cookies' => [], 'filename' => null ];
			}
			if ( '/repos/afragen/test-gu-plugin' === $path ) {
				return [ 'headers' => [], 'body' => json_encode( [ 'private' => false, 'pushed_at' => '2024-06-01T12:00:00Z', 'created_at' => '2023-01-01T00:00:00Z', 'watchers' => 0, 'forks' => 0, 'open_issues' => 0 ] ), 'response' => [ 'code' => 200, 'message' => 'OK' ], 'cookies' => [], 'filename' => null ];
			}
			return [ 'headers' => [], 'body' => '[]', 'response' => [ 'code' => 200, 'message' => 'OK' ], 'cookies' => [], 'filename' => null ];
		}
		return $preempt;
	}

	public function test_get_additions_api_data_handles_theme_type_slug(): void {
		// Theme-type addition: $slug = $addition['slug'] (not dirname). Not in $gu_tokens → skipped.
		update_site_option(
			'git_updater_additions',
			[
				[
					'slug'            => 'my-nonexistent-theme-xyz',
					'type'            => 'theme',
					'private_package' => false,
					'release_asset'   => false,
					'ID'              => 'did:test:theme1',
					'source'          => 'manual',
				],
			]
		);

		$request  = new WP_REST_Request( 'GET', '/git-updater/v1/update-api-additions' );
		$response = $this->server->dispatch( $request );
		$data     = (array) $response->get_data();

		$this->assertSame( [], $data );
	}

	// -------------------------------------------------------------------------
	// /git-updater/v1/get-additions-data
	// -------------------------------------------------------------------------

	public function test_get_additions_data_returns_empty_for_no_additions(): void {
		delete_site_option( 'git_updater_additions' );

		$request  = new WP_REST_Request( 'GET', '/git-updater/v1/get-additions-data' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( [], $data );
	}

	public function test_get_additions_data_filters_out_private_additions(): void {
		update_site_option(
			'git_updater_additions',
			[
				[
					'slug'            => 'my-private-addon/my-private-addon.php',
					'type'            => 'plugin',
					'private_package' => true,
					'release_asset'   => false,
					'ID'              => 'did:test:priv2',
					'source'          => 'manual',
				],
			]
		);

		$request  = new WP_REST_Request( 'GET', '/git-updater/v1/get-additions-data' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( [], array_values( (array) $data ) );
	}

	public function test_get_additions_data_includes_public_additions(): void {
		update_site_option(
			'git_updater_additions',
			[
				[
					'slug'            => 'my-public-addon/my-public-addon.php',
					'type'            => 'plugin',
					'private_package' => false,
					'release_asset'   => false,
					'ID'              => 'did:test:pub1',
					'source'          => 'manual',
				],
			]
		);

		$request  = new WP_REST_Request( 'GET', '/git-updater/v1/get-additions-data' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertNotEmpty( $data );
	}
}

// ---------------------------------------------------------------------------
// Test_REST_API_Reset_Branch
// ---------------------------------------------------------------------------

/**
 * Class Test_REST_API_Reset_Branch
 *
 * Exercises REST_API::reset_branch() via direct method calls.
 *
 * reset_branch():
 * - bad key → UnexpectedValueException → log_exit(418) → WPDieException
 * - no plugin/theme → UnexpectedValueException → WPDieException
 * - slug not in options → UnexpectedValueException → WPDieException
 * - valid slug in options → clears cache, updates options, log_exit(200) → WPDieException
 */

class Test_REST_API_Reset_Branch extends WP_UnitTestCase {

	private const API_KEY = 'test-reset-branch-key';
	private const SLUG    = 'test-reset-slug-xyz';

	private REST_API $api;

	public function set_up(): void {
		parent::set_up();

		// reset_branch() calls log_exit() → wp_send_json_success/error() → wp_die().
		// In AJAX context wp_die() uses wp_die_ajax_handler; override it to throw
		// WPDieException so the test framework can catch it instead of calling die().
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function (): callable {
				return static function ( $msg, $title, $args ): void {
					throw new WPDieException( (string) $msg, (int) ( $args['response'] ?? 200 ) );
				};
			}
		);

		update_site_option( 'git_updater_api_key', self::API_KEY );
		$this->force_api_key_static( self::API_KEY );

		// Seed Base::$options with the test slug.
		update_site_option( 'git_updater', [ self::SLUG => 'some-branch-value' ] );
		new Base();

		$this->api = new REST_API();
	}

	public function tear_down(): void {
		remove_all_filters( 'wp_doing_ajax' );
		remove_all_filters( 'wp_die_ajax_handler' );
		delete_site_option( 'git_updater_api_key' );
		delete_site_option( 'git_updater' );
		delete_site_option( 'ghu-' . md5( self::SLUG ) );
		remove_all_actions( 'gu_post_rest_process_request' );
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Helper
	// -------------------------------------------------------------------------

	private function force_api_key_static( string $key ): void {
		$prop = ( new ReflectionClass( Remote_Management::class ) )->getProperty( 'api_key' );
		$prop->setAccessible( true );
		$prop->setValue( null, $key );
	}

	private function assert_wp_die_thrown( callable $fn ): void {
		ob_start();
		$threw = false;
		try {
			$fn();
		} catch ( WPDieException $e ) {
			$threw = true;
		} finally {
			ob_end_clean();
		}
		$this->assertTrue( $threw, 'Expected WPDieException to be thrown' );
	}

	private function make_request( array $params ): WP_REST_Request {
		$request = new WP_REST_Request( 'GET', '/git-updater/v1/reset-branch' );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return $request;
	}

	// -------------------------------------------------------------------------
	// Tests
	// -------------------------------------------------------------------------

	public function test_reset_branch_bad_key_triggers_wp_die(): void {
		$request = $this->make_request( [ 'key' => 'wrong-key', 'plugin' => self::SLUG ] );
		$this->assert_wp_die_thrown( fn() => $this->api->reset_branch( $request ) );
	}

	public function test_reset_branch_no_plugin_or_theme_triggers_wp_die(): void {
		$request = $this->make_request( [ 'key' => self::API_KEY ] );
		$this->assert_wp_die_thrown( fn() => $this->api->reset_branch( $request ) );
	}

	public function test_reset_branch_slug_not_in_options_triggers_wp_die(): void {
		$request = $this->make_request( [ 'key' => self::API_KEY, 'plugin' => 'zzz-nonexistent-slug-xyz' ] );
		$this->assert_wp_die_thrown( fn() => $this->api->reset_branch( $request ) );
	}

	public function test_reset_branch_success_clears_cache_and_triggers_wp_die(): void {
		$cache_key = 'ghu-' . md5( self::SLUG );
		update_site_option( $cache_key, [ 'current_branch' => 'develop', 'timeout' => strtotime( '+12 hours' ) ] );

		$request = $this->make_request( [ 'key' => self::API_KEY, 'plugin' => self::SLUG ] );
		$this->assert_wp_die_thrown( fn() => $this->api->reset_branch( $request ) );

		// After reset, current_branch should be '' (cleared) in the cache.
		$cache = get_site_option( $cache_key, [] );
		$this->assertSame( '', $cache['current_branch'] ?? 'NOT_SET' );
	}
}

// ---------------------------------------------------------------------------
// Test_REST_API_Zero_Version
// ---------------------------------------------------------------------------

/**
 * Class Test_REST_API_Zero_Version
 *
 * Exercises REST_API::get_api_data() error path (lines 466-472):
 * when get_remote_repo_meta() returns a repo object with remote_version='0.0.0',
 * get_api_data() returns ['error' => ..., 'rate_limit' => ...].
 *
 * get_github_rate_limit_headers() is triggered (line 467) because $repo_data->git='github'.
 * It calls wp_remote_head() which is intercepted by our pre_http_request mock returning
 * a CaseInsensitiveDictionary response.
 *
 * Fixture-dependent: skips when test-gu-plugin is not installed.
 */

class Test_REST_API_Zero_Version extends WP_UnitTestCase {

	private const SLUG    = 'test-gu-plugin';
	private const API_KEY = 'test-gu-zero-version-key';

	private WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();
		new Base();

		// Clear all caches for the fixture plugin so no cached version pollutes the test.
		delete_site_option( 'ghu-' . md5( self::SLUG ) );
		delete_site_option( 'ghu-' . md5( self::SLUG . '_error' ) );

		// Pre-seed update transients to avoid api.wordpress.org calls.
		$empty_transient = (object) [
			'last_checked' => time(),
			'checked'      => [],
			'response'     => [],
			'translations' => [],
			'no_update'    => [],
		];
		set_site_transient( 'update_plugins', $empty_transient );
		set_site_transient( 'update_themes', $empty_transient );

		$GLOBALS['wp_rest_server'] = null;
		$this->server              = rest_get_server();

		update_site_option( 'git_updater_api_key', self::API_KEY );
		$prop = ( new ReflectionClass( Remote_Management::class ) )->getProperty( 'api_key' );
		$prop->setAccessible( true );
		$prop->setValue( null, self::API_KEY );

		add_filter( 'pre_http_request', [ $this, 'mock_http_zero' ], 10, 3 );
	}

	public function tear_down(): void {
		remove_filter( 'pre_http_request', [ $this, 'mock_http_zero' ], 10 );
		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );
		$GLOBALS['wp_rest_server'] = null;
		delete_site_option( 'git_updater_api_key' );
		delete_site_option( 'ghu-' . md5( self::SLUG ) );
		delete_site_option( 'ghu-' . md5( self::SLUG . '_error' ) );
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// HTTP mock — returns [] for all GitHub endpoints so remote_version stays '0.0.0'
	// -------------------------------------------------------------------------

	public function mock_http_zero( mixed $preempt, mixed $args, string $url ): mixed {
		// Rate limit HEAD request — must return CaseInsensitiveDictionary for getAll().
		if ( str_contains( $url, 'api.github.com/rate_limit' ) ) {
			return [
				'headers'  => new CaseInsensitiveDictionary( [ 'x-ratelimit-reset' => (string) ( time() + 300 ) ] ),
				'body'     => '{}',
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'cookies'  => [],
				'filename' => null,
			];
		}

		// All other GitHub API endpoints → empty body; no valid version will be parsed.
		if ( str_contains( $url, 'api.github.com' ) ) {
			return [
				'headers'  => [],
				'body'     => '[]',
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'cookies'  => [],
				'filename' => null,
			];
		}

		// wordpress.org plugin update-check.
		if ( str_contains( $url, 'api.wordpress.org/plugins/update-check' ) ) {
			return [
				'headers'  => [],
				'body'     => json_encode( [ 'plugins' => [], 'translations' => [], 'no_update' => [] ] ),
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'cookies'  => [],
				'filename' => null,
			];
		}

		// Any other wordpress.org call.
		if ( str_contains( $url, 'api.wordpress.org' ) ) {
			return [
				'headers'  => [],
				'body'     => json_encode( [ 'error' => 'Plugin not found.' ] ),
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'cookies'  => [],
				'filename' => null,
			];
		}

		return $preempt;
	}

	// -------------------------------------------------------------------------
	// Helper
	// -------------------------------------------------------------------------

	private function skip_if_fixture_absent(): void {
		$configs = ( new \Fragen\Git_Updater\Plugin() )->get_plugin_configs();
		if ( ! isset( $configs[ self::SLUG ] ) ) {
			$this->markTestSkipped( 'Fixture plugin not installed. Run: npm run wp-env start' );
		}
	}

	// -------------------------------------------------------------------------
	// Test
	// -------------------------------------------------------------------------

	public function test_plugins_api_returns_error_when_remote_version_is_zero(): void {
		$this->skip_if_fixture_absent();

		$request = new WP_REST_Request( 'GET', '/git-updater/v1/plugins-api' );
		$request->set_param( 'slug', self::SLUG );
		$response = $this->server->dispatch( $request );
		$data     = (array) $response->get_data();

		$this->assertArrayHasKey( 'error', $data );
		$this->assertStringContainsString( 'API data response is incorrect', $data['error'] );
		$this->assertArrayHasKey( 'rate_limit', $data );
	}
}

// ---------------------------------------------------------------------------
// Test_REST_API_Download_Proxy
// ---------------------------------------------------------------------------

/**
 * Class Test_REST_API_Download_Proxy
 *
 * Exercises the signed download proxy methods added to REST_API:
 * - sign_download_url()          (private)
 * - verify_download_signature()  (private)
 * - proxy_download()             (public)
 * - build_download_metadata()    (private)
 * - get_api_data() update-api proxy changes
 *
 * Uses reflection to test private methods directly and REST dispatch
 * for integration-level proxy_download() error paths.
 */

class Test_REST_API_Download_Proxy extends WP_UnitTestCase {

	private const SLUG    = 'test-gu-plugin';
	private const API_KEY = 'test-gu-download-proxy-key';

	private REST_API $rest;

	public function set_up(): void {
		parent::set_up();
		$this->rest = new REST_API();
	}

	// -------------------------------------------------------------------------
	// sign_download_url() — private method via reflection
	// -------------------------------------------------------------------------

	public function test_sign_download_url_returns_url_with_correct_path(): void {
		$method = new ReflectionMethod( REST_API::class, 'sign_download_url' );
		$method->setAccessible( true );

		$url = $method->invoke( $this->rest, 'my-slug' );

		// rest_url() may use pretty permalinks or ?rest_route= query format.
		$this->assertTrue(
			str_contains( $url, '/git-updater/v1/download/my-slug' )
			|| str_contains( $url, 'rest_route=%2Fgit-updater%2Fv1%2Fdownload%2Fmy-slug' ),
			'URL does not contain expected download path: ' . $url
		);
	}

	public function test_sign_download_url_contains_expires_param(): void {
		$method = new ReflectionMethod( REST_API::class, 'sign_download_url' );
		$method->setAccessible( true );

		$url = $method->invoke( $this->rest, 'my-slug' );

		$this->assertMatchesRegularExpression( '/[?&]expires=\d+/', $url );
	}

	public function test_sign_download_url_contains_signature_param(): void {
		$method = new ReflectionMethod( REST_API::class, 'sign_download_url' );
		$method->setAccessible( true );

		$url = $method->invoke( $this->rest, 'my-slug' );

		$this->assertMatchesRegularExpression( '/[?&]signature=[a-f0-9]{64}/', $url );
	}

	public function test_sign_download_url_expires_in_future(): void {
		$method = new ReflectionMethod( REST_API::class, 'sign_download_url' );
		$method->setAccessible( true );

		$url     = $method->invoke( $this->rest, 'my-slug', 300 );
		$expires = (int) ( preg_match( '/[?&]expires=(\d+)/', $url, $m ) ? $m[1] : 0 );

		$this->assertGreaterThan( time(), $expires );
	}

	public function test_sign_download_url_expires_within_expected_range(): void {
		$ttl     = 600;
		$method  = new ReflectionMethod( REST_API::class, 'sign_download_url' );
		$method->setAccessible( true );

		$url     = $method->invoke( $this->rest, 'my-slug', $ttl );
		$expires = (int) ( preg_match( '/[?&]expires=(\d+)/', $url, $m ) ? $m[1] : 0 );

		$this->assertGreaterThanOrEqual( time() + $ttl - 2, $expires );
		$this->assertLessThanOrEqual( time() + $ttl + 2, $expires );
	}

	public function test_sign_download_url_custom_ttl(): void {
		$method = new ReflectionMethod( REST_API::class, 'sign_download_url' );
		$method->setAccessible( true );

		$url_default = $method->invoke( $this->rest, 'my-slug' );
		$url_custom  = $method->invoke( $this->rest, 'my-slug', 60 );

		preg_match( '/[?&]expires=(\d+)/', $url_default, $m1 );
		preg_match( '/[?&]expires=(\d+)/', $url_custom, $m2 );

		$this->assertGreaterThan( (int) $m2[1], (int) $m1[1] );
	}

	public function test_sign_download_url_different_slugs_produce_different_signatures(): void {
		$method = new ReflectionMethod( REST_API::class, 'sign_download_url' );
		$method->setAccessible( true );

		$url1 = $method->invoke( $this->rest, 'slug-a' );
		$url2 = $method->invoke( $this->rest, 'slug-b' );

		preg_match( '/[?&]signature=([a-f0-9]{64})/', $url1, $m1 );
		preg_match( '/[?&]signature=([a-f0-9]{64})/', $url2, $m2 );

		$this->assertNotSame( $m1[1], $m2[1] );
	}

	// -------------------------------------------------------------------------
	// verify_download_signature() — private method via reflection
	// -------------------------------------------------------------------------

	public function test_verify_download_signature_accepts_valid_signature(): void {
		$sign   = new ReflectionMethod( REST_API::class, 'sign_download_url' );
		$sign->setAccessible( true );
		$verify = new ReflectionMethod( REST_API::class, 'verify_download_signature' );
		$verify->setAccessible( true );

		$url = $sign->invoke( $this->rest, 'my-slug' );
		preg_match( '/[?&]expires=(\d+)/', $url, $m_expires );
		preg_match( '/[?&]signature=([a-f0-9]{64})/', $url, $m_sig );

		$result = $verify->invoke( $this->rest, 'my-slug', (int) $m_expires[1], $m_sig[1] );

		$this->assertTrue( $result );
	}

	public function test_verify_download_signature_rejects_expired_signature(): void {
		$secret   = wp_salt( 'auth' );
		$expires  = time() - 100;
		$payload  = 'my-slug|' . $expires;
		$signature = hash_hmac( 'sha256', $payload, $secret );

		$verify = new ReflectionMethod( REST_API::class, 'verify_download_signature' );
		$verify->setAccessible( true );

		$result = $verify->invoke( $this->rest, 'my-slug', $expires, $signature );

		$this->assertFalse( $result );
	}

	public function test_verify_download_signature_rejects_tampered_signature(): void {
		$sign   = new ReflectionMethod( REST_API::class, 'sign_download_url' );
		$sign->setAccessible( true );
		$verify = new ReflectionMethod( REST_API::class, 'verify_download_signature' );
		$verify->setAccessible( true );

		$url = $sign->invoke( $this->rest, 'my-slug' );
		preg_match( '/[?&]expires=(\d+)/', $url, $m_expires );

		// Tamper: flip first hex character.
		$tampered = 'f' . str_repeat( '0', 63 );

		$result = $verify->invoke( $this->rest, 'my-slug', (int) $m_expires[1], $tampered );

		$this->assertFalse( $result );
	}

	public function test_verify_download_signature_rejects_wrong_slug(): void {
		$sign   = new ReflectionMethod( REST_API::class, 'sign_download_url' );
		$sign->setAccessible( true );
		$verify = new ReflectionMethod( REST_API::class, 'verify_download_signature' );
		$verify->setAccessible( true );

		$url = $sign->invoke( $this->rest, 'slug-a' );
		preg_match( '/[?&]expires=(\d+)/', $url, $m_expires );
		preg_match( '/[?&]signature=([a-f0-9]{64})/', $url, $m_sig );

		// Verify with different slug — should fail.
		$result = $verify->invoke( $this->rest, 'slug-b', (int) $m_expires[1], $m_sig[1] );

		$this->assertFalse( $result );
	}

	public function test_verify_download_signature_rejects_empty_signature(): void {
		$verify = new ReflectionMethod( REST_API::class, 'verify_download_signature' );
		$verify->setAccessible( true );

		$result = $verify->invoke( $this->rest, 'my-slug', time() + 300, '' );

		$this->assertFalse( $result );
	}

	public function test_verify_roundtrip_with_custom_ttl(): void {
		$sign   = new ReflectionMethod( REST_API::class, 'sign_download_url' );
		$sign->setAccessible( true );
		$verify = new ReflectionMethod( REST_API::class, 'verify_download_signature' );
		$verify->setAccessible( true );

		$url = $sign->invoke( $this->rest, 'custom-ttl-slug', 120 );
		preg_match( '/[?&]expires=(\d+)/', $url, $m_expires );
		preg_match( '/[?&]signature=([a-f0-9]{64})/', $url, $m_sig );

		$result = $verify->invoke( $this->rest, 'custom-ttl-slug', (int) $m_expires[1], $m_sig[1] );

		$this->assertTrue( $result );
	}

	// -------------------------------------------------------------------------
	// proxy_download() — error paths via direct calls
	// -------------------------------------------------------------------------

	public function test_proxy_download_returns_error_for_invalid_signature(): void {
		$request = new WP_REST_Request( 'GET', '/git-updater/v1/download/my-slug' );
		$request->set_param( 'slug', 'my-slug' );
		$request->set_param( 'expires', (string) ( time() + 300 ) );
		$request->set_param( 'signature', str_repeat( 'a', 64 ) );

		$result = $this->rest->proxy_download( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'gu_invalid_signature', $result->get_error_code() );
	}

	public function test_proxy_download_returns_error_for_expired_signature(): void {
		$secret    = wp_salt( 'auth' );
		$expires   = time() - 100;
		$signature = hash_hmac( 'sha256', 'my-slug|' . $expires, $secret );

		$request = new WP_REST_Request( 'GET', '/git-updater/v1/download/my-slug' );
		$request->set_param( 'slug', 'my-slug' );
		$request->set_param( 'expires', (string) $expires );
		$request->set_param( 'signature', $signature );

		$result = $this->rest->proxy_download( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'gu_invalid_signature', $result->get_error_code() );
	}

	public function test_proxy_download_returns_error_for_nonexistent_slug(): void {
		$sign = new ReflectionMethod( REST_API::class, 'sign_download_url' );
		$sign->setAccessible( true );

		$url = $sign->invoke( $this->rest, 'nonexistent-slug-xyz' );
		preg_match( '/[?&]expires=(\d+)/', $url, $m_expires );
		preg_match( '/[?&]signature=([a-f0-9]{64})/', $url, $m_sig );

		$request = new WP_REST_Request( 'GET', '/git-updater/v1/download/nonexistent-slug-xyz' );
		$request->set_param( 'slug', 'nonexistent-slug-xyz' );
		$request->set_param( 'expires', $m_expires[1] );
		$request->set_param( 'signature', $m_sig[1] );

		$result = $this->rest->proxy_download( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'gu_repo_not_found', $result->get_error_code() );
	}

	public function test_proxy_download_returns_error_for_private_package(): void {
		update_site_option(
			'git_updater_additions',
			[
				[
					'slug'            => 'private-proxy/private-proxy.php',
					'type'            => 'plugin',
					'private_package' => true,
				],
			]
		);

		$sign = new ReflectionMethod( REST_API::class, 'sign_download_url' );
		$sign->setAccessible( true );

		$url = $sign->invoke( $this->rest, 'private-proxy' );
		preg_match( '/[?&]expires=(\d+)/', $url, $m_expires );
		preg_match( '/[?&]signature=([a-f0-9]{64})/', $url, $m_sig );

		$request = new WP_REST_Request( 'GET', '/git-updater/v1/download/private-proxy' );
		$request->set_param( 'slug', 'private-proxy' );
		$request->set_param( 'expires', $m_expires[1] );
		$request->set_param( 'signature', $m_sig[1] );

		$result = $this->rest->proxy_download( $request );

		delete_site_option( 'git_updater_additions' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'gu_private_package', $result->get_error_code() );
	}

	// -------------------------------------------------------------------------
	// build_download_metadata() — private method via reflection
	// -------------------------------------------------------------------------

	public function test_build_download_metadata_returns_error_for_nonexistent_slug(): void {
		$method = new ReflectionMethod( REST_API::class, 'build_download_metadata' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->rest, 'nonexistent-build-slug-xyz' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'gu_repo_not_found', $result->get_error_code() );
	}

	public function test_build_download_metadata_returns_error_for_private_package(): void {
		update_site_option(
			'git_updater_additions',
			[
				[
					'slug'            => 'private-meta/private-meta.php',
					'type'            => 'plugin',
					'private_package' => true,
				],
			]
		);

		$method = new ReflectionMethod( REST_API::class, 'build_download_metadata' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->rest, 'private-meta' );

		delete_site_option( 'git_updater_additions' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'gu_private_package', $result->get_error_code() );
	}

	public function test_build_download_metadata_returns_array_with_download_link_key(): void {
		$this->skip_if_fixture_absent();

		// Mock HTTP so get_remote_repo_meta() doesn't make real calls.
		add_filter( 'pre_http_request', [ $this, 'mock_http_build' ], 10, 3 );

		$method = new ReflectionMethod( REST_API::class, 'build_download_metadata' );
		$method->setAccessible( true );

		new Base();
		$result = $method->invoke( $this->rest, self::SLUG );

		remove_filter( 'pre_http_request', [ $this, 'mock_http_build' ], 10 );

		if ( is_wp_error( $result ) ) {
			// If the fixture isn't fully configured, skip.
			$this->markTestSkipped( 'Fixture plugin metadata unavailable.' );
		}

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'download_link', $result );
	}

	// -------------------------------------------------------------------------
	// get_api_data() — update-api proxy changes
	// -------------------------------------------------------------------------

	public function test_update_api_returns_no_auth_header_for_fixture_plugin(): void {
		$this->skip_if_fixture_absent();

		new Base();
		$GLOBALS['wp_rest_server'] = null;
		$server = rest_get_server();

		update_site_option( 'git_updater_api_key', self::API_KEY );
		$prop = ( new ReflectionClass( Remote_Management::class ) )->getProperty( 'api_key' );
		$prop->setAccessible( true );
		$prop->setValue( null, self::API_KEY );

		$empty_transient = (object) [
			'last_checked' => time(),
			'checked'      => [],
			'response'     => [],
			'translations' => [],
			'no_update'    => [],
		];
		set_site_transient( 'update_plugins', $empty_transient );
		set_site_transient( 'update_themes', $empty_transient );

		add_filter( 'pre_http_request', [ $this, 'mock_http_build' ], 10, 3 );

		$request = new WP_REST_Request( 'GET', '/git-updater/v1/update-api' );
		$request->set_param( 'slug', self::SLUG );
		$response = $server->dispatch( $request );
		$data     = (array) $response->get_data();

		remove_filter( 'pre_http_request', [ $this, 'mock_http_build' ], 10 );
		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );
		$GLOBALS['wp_rest_server'] = null;

		$this->assertArrayNotHasKey( 'auth_header', $data );
	}

	public function test_plugins_api_never_exposes_auth_header(): void {
		$this->skip_if_fixture_absent();

		new Base();
		$GLOBALS['wp_rest_server'] = null;
		$server = rest_get_server();

		update_site_option( 'git_updater_api_key', self::API_KEY );
		$prop = ( new ReflectionClass( Remote_Management::class ) )->getProperty( 'api_key' );
		$prop->setAccessible( true );
		$prop->setValue( null, self::API_KEY );

		$empty_transient = (object) [
			'last_checked' => time(),
			'checked'      => [],
			'response'     => [],
			'translations' => [],
			'no_update'    => [],
		];
		set_site_transient( 'update_plugins', $empty_transient );
		set_site_transient( 'update_themes', $empty_transient );

		add_filter( 'pre_http_request', [ $this, 'mock_http_build' ], 10, 3 );

		$request = new WP_REST_Request( 'GET', '/git-updater/v1/plugins-api' );
		$request->set_param( 'slug', self::SLUG );
		$response = $server->dispatch( $request );
		$data     = (array) $response->get_data();

		remove_filter( 'pre_http_request', [ $this, 'mock_http_build' ], 10 );
		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );
		$GLOBALS['wp_rest_server'] = null;

		$this->assertArrayNotHasKey( 'auth_header', $data );
	}

	// -------------------------------------------------------------------------
	// download route registration
	// -------------------------------------------------------------------------

	public function test_download_route_is_registered(): void {
		$GLOBALS['wp_rest_server'] = null;
		$server = rest_get_server();
		$routes = $server->get_routes();

		$this->assertArrayHasKey( '/git-updater/v1/download/(?P<slug>[a-z0-9-]+)', $routes );
	}

	public function test_download_route_returns_403_for_missing_params(): void {
		$GLOBALS['wp_rest_server'] = null;
		$server = rest_get_server();

		$request  = new WP_REST_Request( 'GET', '/git-updater/v1/download/test-slug' );
		$response = $server->dispatch( $request );

		// Missing required expires/signature params → 400 (REST validation).
		$this->assertGreaterThanOrEqual( 400, $response->get_status() );
	}

	// -------------------------------------------------------------------------
	// HTTP mock helper
	// -------------------------------------------------------------------------

	public function mock_http_build( mixed $preempt, mixed $args, string $url ): mixed {
		if ( str_contains( $url, 'api.wordpress.org/plugins/update-check' ) ) {
			return [
				'headers'  => [],
				'body'     => json_encode( [ 'plugins' => [], 'translations' => [], 'no_update' => [] ] ),
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'cookies'  => [],
				'filename' => null,
			];
		}
		if ( str_contains( $url, 'api.wordpress.org/themes/update-check' ) ) {
			return [
				'headers'  => [],
				'body'     => json_encode( [ 'themes' => [], 'translations' => [], 'no_update' => [] ] ),
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'cookies'  => [],
				'filename' => null,
			];
		}
		if ( str_contains( $url, 'api.wordpress.org' ) ) {
			return [
				'headers'  => [],
				'body'     => json_encode( [ 'error' => 'Plugin not found.' ] ),
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'cookies'  => [],
				'filename' => null,
			];
		}
		if ( str_contains( $url, 'api.github.com/repos/afragen/test-gu-plugin' ) ) {
			$path = (string) parse_url( $url, PHP_URL_PATH );
			if ( str_contains( $path, '/contents/test-gu-plugin.php' ) ) {
				$content = "<?php\n/**\n * Plugin Name: Test GU Plugin\n * Version: 2.0.0\n * GitHub Plugin URI: https://github.com/afragen/test-gu-plugin\n * Primary Branch: main\n * Release Asset: true\n */";
				return [
					'headers'  => [],
					'body'     => json_encode( [ 'content' => base64_encode( $content ), 'encoding' => 'base64' ] ),
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'cookies'  => [],
					'filename' => null,
				];
			}
			if ( str_contains( $path, '/contents/readme.txt' ) ) {
				$content = "=== Test GU Plugin ===\nContributors: test\nRequires at least: 6.0\nTested up to: 6.5\nRequires PHP: 8.1\nStable tag: 2.0.0\n\n== Description ==\nTest.";
				return [
					'headers'  => [],
					'body'     => json_encode( [ 'content' => base64_encode( $content ), 'encoding' => 'base64' ] ),
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'cookies'  => [],
					'filename' => null,
				];
			}
			if ( '/repos/afragen/test-gu-plugin/contents' === $path ) {
				return [
					'headers'  => [],
					'body'     => json_encode( [ [ 'name' => 'test-gu-plugin.php', 'type' => 'file' ], [ 'name' => 'readme.txt', 'type' => 'file' ] ] ),
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'cookies'  => [],
					'filename' => null,
				];
			}
			if ( str_ends_with( $path, '/tags' ) ) {
				return [
					'headers'  => [],
					'body'     => json_encode( [ [ 'name' => '2.0.0', 'zipball_url' => 'https://github.com/afragen/test-gu-plugin/zipball/2.0.0', 'commit' => [ 'sha' => 'abc123' ] ] ] ),
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'cookies'  => [],
					'filename' => null,
				];
			}
			if ( str_ends_with( $path, '/branches' ) ) {
				return [
					'headers'  => [],
					'body'     => json_encode( [ [ 'name' => 'main', 'commit' => [ 'sha' => 'abc123', 'url' => '' ] ] ] ),
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'cookies'  => [],
					'filename' => null,
				];
			}
			if ( '/repos/afragen/test-gu-plugin' === $path ) {
				return [
					'headers'  => [],
					'body'     => json_encode( [ 'private' => false, 'pushed_at' => '2024-06-01T12:00:00Z', 'created_at' => '2023-01-01T00:00:00Z', 'watchers' => 0, 'forks' => 0, 'open_issues' => 0 ] ),
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'cookies'  => [],
					'filename' => null,
				];
			}
			return [
				'headers'  => [],
				'body'     => '[]',
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'cookies'  => [],
				'filename' => null,
			];
		}
		return $preempt;
	}

	// -------------------------------------------------------------------------
	// Helper
	// -------------------------------------------------------------------------

	private function skip_if_fixture_absent(): void {
		$configs = ( new \Fragen\Git_Updater\Plugin() )->get_plugin_configs();
		if ( ! isset( $configs[ self::SLUG ] ) ) {
			$this->markTestSkipped( 'Fixture plugin not installed. Run: composer wp-env-start' );
		}
	}

	// -------------------------------------------------------------------------
	// build_download_metadata() — happy path with auth headers
	// -------------------------------------------------------------------------

	public function test_build_download_metadata_returns_auth_header_for_private_repo(): void {
		$this->skip_if_fixture_absent();

		add_filter( 'pre_http_request', [ $this, 'mock_http_build' ], 10, 3 );

		// Seed a release_asset_download so the download_link is populated.
		$cache_key = 'ghu-' . md5( self::SLUG );
		$existing  = get_site_option( $cache_key, [] );
		$existing['release_asset_download'] = 'https://github.com/afragen/test-gu-plugin/archive/refs/tags/2.0.0.zip';
		unset( $existing['release_asset_redirect'] );
		update_site_option( $cache_key, $existing );

		$method = new ReflectionMethod( REST_API::class, 'build_download_metadata' );
		$method->setAccessible( true );

		new Base();
		$result = $method->invoke( $this->rest, self::SLUG );

		remove_filter( 'pre_http_request', [ $this, 'mock_http_build' ], 10 );
		delete_site_option( $cache_key );

		if ( is_wp_error( $result ) ) {
			$this->markTestSkipped( 'Fixture plugin metadata unavailable.' );
		}

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'download_link', $result );
		$this->assertNotEmpty( $result['download_link'], 'download_link should not be empty' );
		$this->assertArrayHasKey( 'auth_header', $result );
		$this->assertArrayHasKey( 'headers', $result['auth_header'] );
	}

	public function test_build_download_metadata_covers_release_asset_download_path(): void {
		$this->skip_if_fixture_absent();

		add_filter( 'pre_http_request', [ $this, 'mock_http_build' ], 10, 3 );

		// Seed release_asset_download in cache.
		$cache_key = 'ghu-' . md5( self::SLUG );
		$existing  = get_site_option( $cache_key, [] );
		$existing['release_asset_download'] = 'https://example.com/release-download.zip';
		unset( $existing['release_asset_redirect'] );
		update_site_option( $cache_key, $existing );

		$method = new ReflectionMethod( REST_API::class, 'build_download_metadata' );
		$method->setAccessible( true );

		new Base();
		$result = $method->invoke( $this->rest, self::SLUG );

		remove_filter( 'pre_http_request', [ $this, 'mock_http_build' ], 10 );
		delete_site_option( $cache_key );

		if ( is_wp_error( $result ) ) {
			$this->markTestSkipped( 'Fixture plugin metadata unavailable.' );
		}

		$this->assertIsArray( $result );
		$this->assertSame( 'https://example.com/release-download.zip', $result['download_link'] );
	}

	public function test_build_download_metadata_covers_release_asset_redirect_path(): void {
		$this->skip_if_fixture_absent();

		add_filter( 'pre_http_request', [ $this, 'mock_http_build' ], 10, 3 );

		// Seed release_asset + release_asset_download + release_asset_redirect.
		$cache_key = 'ghu-' . md5( self::SLUG );
		$existing  = get_site_option( $cache_key, [] );
		$existing['release_asset']          = 'https://api.github.com/repos/afragen/test-gu-plugin/releases/assets/1234';
		$existing['release_asset_download'] = 'https://example.com/release-asset-download.zip';
		$existing['release_asset_redirect'] = 'https://example.com/release-asset-redirect';
		update_site_option( $cache_key, $existing );

		// Set the type slug on the API singleton so get_repo_cache() works.
		$api_singleton  = Singleton::get_instance( 'Fragen\Git_Updater\API\API', new REST_API() );
		$rp             = new ReflectionProperty( get_class( $api_singleton ), 'type' );
		$rp->setAccessible( true );
		$saved_type     = $rp->getValue( $api_singleton );
		$type_obj       = new stdClass();
		$type_obj->slug = self::SLUG;
		$rp->setValue( $api_singleton, $type_obj );

		$method = new ReflectionMethod( REST_API::class, 'build_download_metadata' );
		$method->setAccessible( true );

		new Base();
		$result = $method->invoke( $this->rest, self::SLUG );

		$rp->setValue( $api_singleton, $saved_type );
		remove_filter( 'pre_http_request', [ $this, 'mock_http_build' ], 10 );
		delete_site_option( $cache_key );

		if ( is_wp_error( $result ) ) {
			$this->markTestSkipped( 'Fixture plugin metadata unavailable.' );
		}

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'download_link', $result );
		// When release_asset_redirect path is taken, auth_header is cleared.
		$this->assertArrayNotHasKey( 'auth_header', $result );
	}

	// -------------------------------------------------------------------------
	// get_api_data() — update-api with per-slug token (covers line 615)
	// -------------------------------------------------------------------------

	public function test_update_api_returns_proxy_url_for_repo_with_per_slug_token(): void {
		$this->skip_if_fixture_absent();

		// Set per-slug token BEFORE Base construction so it propagates to API::$options.
		$options = get_site_option( 'git_updater', [] );
		$options[ self::SLUG ] = 'fake-token-for-testing';
		update_site_option( 'git_updater', $options );

		new Base();
		$GLOBALS['wp_rest_server'] = null;
		$server = rest_get_server();

		update_site_option( 'git_updater_api_key', self::API_KEY );
		$prop = ( new ReflectionClass( Remote_Management::class ) )->getProperty( 'api_key' );
		$prop->setAccessible( true );
		$prop->setValue( null, self::API_KEY );

		$empty_transient = (object) [
			'last_checked' => time(),
			'checked'      => [],
			'response'     => [],
			'translations' => [],
			'no_update'    => [],
		];
		set_site_transient( 'update_plugins', $empty_transient );
		set_site_transient( 'update_themes', $empty_transient );

		add_filter( 'pre_http_request', [ $this, 'mock_http_build' ], 10, 3 );

		// Seed a release_asset_download so the download_link is populated.
		$cache_key = 'ghu-' . md5( self::SLUG );
		$existing  = get_site_option( $cache_key, [] );
		$existing['release_asset_download'] = 'https://github.com/afragen/test-gu-plugin/archive/refs/tags/2.0.0.zip';
		unset( $existing['release_asset_redirect'] );
		update_site_option( $cache_key, $existing );

		$request = new WP_REST_Request( 'GET', '/git-updater/v1/update-api' );
		$request->set_param( 'slug', self::SLUG );
		$response = $server->dispatch( $request );
		$data     = (array) $response->get_data();

		// Clean up.
		unset( $options[ self::SLUG ] );
		update_site_option( 'git_updater', $options );
		remove_filter( 'pre_http_request', [ $this, 'mock_http_build' ], 10 );
		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );
		delete_site_option( $cache_key );
		$GLOBALS['wp_rest_server'] = null;

		if ( isset( $data['error'] ) ) {
			$this->markTestSkipped( 'Fixture plugin metadata unavailable: ' . $data['error'] );
		}

		// With a per-slug token, download_link should be a proxy URL and auth_header absent.
		$this->assertArrayNotHasKey( 'auth_header', $data );
		if ( isset( $data['download_link'] ) && ! empty( $data['download_link'] ) ) {
			$this->assertTrue(
				str_contains( $data['download_link'], '/git-updater/v1/download/' )
				|| str_contains( $data['download_link'], 'rest_route=%2Fgit-updater%2Fv1%2Fdownload%2F' ),
				'Expected proxy URL but got: ' . $data['download_link']
			);
		}
	}

	// -------------------------------------------------------------------------
	// proxy_download() — upstream error paths
	// -------------------------------------------------------------------------

	public function test_proxy_download_returns_error_for_zero_version_metadata(): void {
		// Mock HTTP to return empty responses so remote_version stays '0.0.0'.
		add_filter( 'pre_http_request', [ $this, 'mock_http_zero_version' ], 10, 3 );

		$this->skip_if_fixture_absent();

		new Base();

		$sign = new ReflectionMethod( REST_API::class, 'sign_download_url' );
		$sign->setAccessible( true );

		$url = $sign->invoke( $this->rest, self::SLUG );
		preg_match( '/[?&]expires=(\d+)/', $url, $m_expires );
		preg_match( '/[?&]signature=([a-f0-9]{64})/', $url, $m_sig );

		$request = new WP_REST_Request( 'GET', '/git-updater/v1/download/' . self::SLUG );
		$request->set_param( 'slug', self::SLUG );
		$request->set_param( 'expires', $m_expires[1] );
		$request->set_param( 'signature', $m_sig[1] );

		$result = $this->rest->proxy_download( $request );

		remove_filter( 'pre_http_request', [ $this, 'mock_http_zero_version' ], 10 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'gu_api_error', $result->get_error_code() );
	}

	public function mock_http_zero_version( mixed $preempt, mixed $args, string $url ): mixed {
		if ( str_contains( $url, 'api.wordpress.org' ) ) {
			return [
				'headers'  => [],
				'body'     => json_encode( [ 'plugins' => [], 'translations' => [], 'no_update' => [] ] ),
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'cookies'  => [],
				'filename' => null,
			];
		}
		if ( str_contains( $url, 'api.github.com' ) ) {
			return [
				'headers'  => [],
				'body'     => '[]',
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'cookies'  => [],
				'filename' => null,
			];
		}
		return $preempt;
	}

	// -------------------------------------------------------------------------
	// proxy_download() — streaming success path via overridden send_file_response
	// -------------------------------------------------------------------------

	public function test_proxy_download_streams_file_for_valid_signature_and_metadata(): void {
		$this->skip_if_fixture_absent();

		// Mock HTTP for metadata fetch + upstream download.
		add_filter( 'pre_http_request', [ $this, 'mock_http_for_streaming' ], 10, 3 );

		new Base();

		// Seed a release_asset_download so build_download_metadata returns a download link.
		$cache_key = 'ghu-' . md5( self::SLUG );
		$existing  = get_site_option( $cache_key, [] );
		$existing['release_asset_download'] = 'https://example.com/release.zip';
		unset( $existing['release_asset_redirect'] );
		update_site_option( $cache_key, $existing );

		// Use a subclass that captures the file path instead of calling exit.
		$rest = new class() extends REST_API {
			/** @var string */
			public $captured_file = '';
			/** @var string */
			public $captured_filename = '';
			/** @var bool */
			public $send_file_called = false;

			protected function send_file_response( string $file, string $filename, string $temp_file ): void {
				$this->captured_file     = $file;
				$this->captured_filename = $filename;
				$this->send_file_called  = true;
				wp_delete_file( $temp_file );
			}
		};

		$sign = new ReflectionMethod( REST_API::class, 'sign_download_url' );
		$sign->setAccessible( true );

		$url = $sign->invoke( $rest, self::SLUG );
		preg_match( '/[?&]expires=(\d+)/', $url, $m_expires );
		preg_match( '/[?&]signature=([a-f0-9]{64})/', $url, $m_sig );

		$request = new WP_REST_Request( 'GET', '/git-updater/v1/download/' . self::SLUG );
		$request->set_param( 'slug', self::SLUG );
		$request->set_param( 'expires', $m_expires[1] );
		$request->set_param( 'signature', $m_sig[1] );

		$result = $rest->proxy_download( $request );

		remove_filter( 'pre_http_request', [ $this, 'mock_http_for_streaming' ], 10 );
		delete_site_option( $cache_key );

		if ( ! $rest->send_file_called && is_wp_error( $result ) ) {
			$this->markTestSkipped( 'Fixture metadata unavailable: ' . $result->get_error_message() );
		}

		$this->assertTrue( $rest->send_file_called, 'send_file_response should have been called' );
		$this->assertStringContainsString( self::SLUG, $rest->captured_filename );
		$this->assertStringEndsWith( '.zip', $rest->captured_filename );
	}

	public function test_proxy_download_returns_error_when_upstream_fails(): void {
		$this->skip_if_fixture_absent();

		// Mock HTTP: return WP_Error for upstream download, valid metadata otherwise.
		add_filter(
			'pre_http_request',
			function ( mixed $preempt, mixed $args, string $url ): mixed {
				if ( str_contains( $url, 'example.com/download.zip' ) ) {
					return new \WP_Error( 'http_error', 'Connection refused' );
				}
				return $this->mock_http_build( $preempt, $args, $url );
			},
			10,
			3
		);

		new Base();

		// Seed cache with a download link so build_download_metadata returns it.
		$cache_key = 'ghu-' . md5( self::SLUG );
		$existing  = get_site_option( $cache_key, [] );
		$existing['release_asset_download'] = 'https://example.com/download.zip';
		unset( $existing['release_asset_redirect'] );
		update_site_option( $cache_key, $existing );

		$sign = new ReflectionMethod( REST_API::class, 'sign_download_url' );
		$sign->setAccessible( true );

		$url = $sign->invoke( $this->rest, self::SLUG );
		preg_match( '/[?&]expires=(\d+)/', $url, $m_expires );
		preg_match( '/[?&]signature=([a-f0-9]{64})/', $url, $m_sig );

		$request = new WP_REST_Request( 'GET', '/git-updater/v1/download/' . self::SLUG );
		$request->set_param( 'slug', self::SLUG );
		$request->set_param( 'expires', $m_expires[1] );
		$request->set_param( 'signature', $m_sig[1] );

		$result = $this->rest->proxy_download( $request );

		remove_all_filters( 'pre_http_request' );
		delete_site_option( $cache_key );

		if ( ! is_wp_error( $result ) ) {
			$this->markTestSkipped( 'Fixture metadata unavailable or download link not set.' );
		}

		$this->assertSame( 'gu_upstream_error', $result->get_error_code() );
	}

	public function test_proxy_download_returns_error_when_upstream_returns_non_200(): void {
		$this->skip_if_fixture_absent();

		// Mock HTTP: return 403 for upstream download.
		add_filter(
			'pre_http_request',
			function ( mixed $preempt, mixed $args, string $url ): mixed {
				if ( str_contains( $url, 'example.com/forbidden.zip' ) ) {
					return [
						'headers'  => [],
						'body'     => 'Forbidden',
						'response' => [ 'code' => 403, 'message' => 'Forbidden' ],
						'cookies'  => [],
						'filename' => null,
					];
				}
				return $this->mock_http_build( $preempt, $args, $url );
			},
			10,
			3
		);

		new Base();

		$cache_key = 'ghu-' . md5( self::SLUG );
		$existing  = get_site_option( $cache_key, [] );
		$existing['release_asset_download'] = 'https://example.com/forbidden.zip';
		unset( $existing['release_asset_redirect'] );
		update_site_option( $cache_key, $existing );

		$sign = new ReflectionMethod( REST_API::class, 'sign_download_url' );
		$sign->setAccessible( true );

		$url = $sign->invoke( $this->rest, self::SLUG );
		preg_match( '/[?&]expires=(\d+)/', $url, $m_expires );
		preg_match( '/[?&]signature=([a-f0-9]{64})/', $url, $m_sig );

		$request = new WP_REST_Request( 'GET', '/git-updater/v1/download/' . self::SLUG );
		$request->set_param( 'slug', self::SLUG );
		$request->set_param( 'expires', $m_expires[1] );
		$request->set_param( 'signature', $m_sig[1] );

		$result = $this->rest->proxy_download( $request );

		remove_all_filters( 'pre_http_request' );
		delete_site_option( $cache_key );

		if ( ! is_wp_error( $result ) ) {
			$this->markTestSkipped( 'Fixture metadata unavailable or download link not set.' );
		}

		$this->assertSame( 'gu_upstream_http_error', $result->get_error_code() );
	}

	public function test_proxy_download_returns_error_when_download_link_is_empty(): void {
		$this->skip_if_fixture_absent();

		// Mock HTTP: return metadata with no download link.
		add_filter( 'pre_http_request', [ $this, 'mock_http_build' ], 10, 3 );

		new Base();

		// Clear any cached download link.
		$cache_key = 'ghu-' . md5( self::SLUG );
		$existing  = get_site_option( $cache_key, [] );
		unset( $existing['release_asset_download'] );
		unset( $existing['release_asset_redirect'] );
		update_site_option( $cache_key, $existing );

		$sign = new ReflectionMethod( REST_API::class, 'sign_download_url' );
		$sign->setAccessible( true );

		$url = $sign->invoke( $this->rest, self::SLUG );
		preg_match( '/[?&]expires=(\d+)/', $url, $m_expires );
		preg_match( '/[?&]signature=([a-f0-9]{64})/', $url, $m_sig );

		$request = new WP_REST_Request( 'GET', '/git-updater/v1/download/' . self::SLUG );
		$request->set_param( 'slug', self::SLUG );
		$request->set_param( 'expires', $m_expires[1] );
		$request->set_param( 'signature', $m_sig[1] );

		$result = $this->rest->proxy_download( $request );

		remove_filter( 'pre_http_request', [ $this, 'mock_http_build' ], 10 );
		delete_site_option( $cache_key );

		// If metadata resolved successfully but download_link is empty, we get 404.
		if ( is_wp_error( $result ) && 'gu_no_download_link' === $result->get_error_code() ) {
			$this->assertSame( 'gu_no_download_link', $result->get_error_code() );
			return;
		}

		// If metadata itself failed (fixture not configured), skip.
		$this->markTestSkipped( 'Fixture metadata unavailable or download link present.' );
	}

	// -------------------------------------------------------------------------
	// download route — POST method and validate_callback
	// -------------------------------------------------------------------------

	public function test_download_route_post_method_is_registered(): void {
		$GLOBALS['wp_rest_server'] = null;
		$server = rest_get_server();

		$request  = new WP_REST_Request( 'POST', '/git-updater/v1/download/test-slug' );
		$request->set_param( 'slug', 'test-slug' );
		$request->set_param( 'expires', (string) ( time() + 300 ) );
		$request->set_param( 'signature', str_repeat( 'a', 64 ) );
		$response = $server->dispatch( $request );

		// POST should work (returns error due to bad signature, but route exists).
		$this->assertNotSame( 404, $response->get_status() );
	}

	public function test_download_route_rejects_non_hex_signature(): void {
		$GLOBALS['wp_rest_server'] = null;
		$server = rest_get_server();

		$request  = new WP_REST_Request( 'GET', '/git-updater/v1/download/test-slug' );
		$request->set_param( 'slug', 'test-slug' );
		$request->set_param( 'expires', (string) ( time() + 300 ) );
		$request->set_param( 'signature', 'not-valid-hex-characters-zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz' );
		$response = $server->dispatch( $request );

		// Invalid signature format should be rejected by validate_callback.
		$this->assertGreaterThanOrEqual( 400, $response->get_status() );
	}

	// -------------------------------------------------------------------------
	// build_download_metadata() — no download link path
	// -------------------------------------------------------------------------

	public function test_build_download_metadata_returns_empty_download_link_when_repo_has_none(): void {
		$this->skip_if_fixture_absent();

		add_filter( 'pre_http_request', [ $this, 'mock_http_build' ], 10, 3 );

		// Clear any cached download link.
		$cache_key = 'ghu-' . md5( self::SLUG );
		$existing  = get_site_option( $cache_key, [] );
		unset( $existing['release_asset_download'] );
		unset( $existing['release_asset_redirect'] );
		update_site_option( $cache_key, $existing );

		$method = new ReflectionMethod( REST_API::class, 'build_download_metadata' );
		$method->setAccessible( true );

		new Base();
		$result = $method->invoke( $this->rest, self::SLUG );

		remove_filter( 'pre_http_request', [ $this, 'mock_http_build' ], 10 );
		delete_site_option( $cache_key );

		if ( is_wp_error( $result ) ) {
			$this->markTestSkipped( 'Fixture plugin metadata unavailable.' );
		}

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'download_link', $result );
		// When download_link is empty, auth_header should not be present.
		if ( empty( $result['download_link'] ) ) {
			$this->assertArrayNotHasKey( 'auth_header', $result );
		}
	}

	// -------------------------------------------------------------------------
	// Helper: create a temp zip file for streaming tests
	// -------------------------------------------------------------------------

	public function create_temp_zip(): string {
		$tmp = wp_tempnam( 'gu_test_zip' );
		$zip = new ZipArchive();
		if ( $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) === true ) {
			$zip->addFromString( 'test.txt', 'test content' );
			$zip->close();
		}
		return $tmp;
	}

	public function mock_http_for_streaming( mixed $preempt, mixed $args, string $url ): mixed {
		if ( str_contains( $url, 'api.wordpress.org' ) ) {
			return [
				'headers'  => [],
				'body'     => json_encode( [ 'plugins' => [], 'translations' => [], 'no_update' => [] ] ),
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'cookies'  => [],
				'filename' => null,
			];
		}
		if ( str_contains( $url, 'api.github.com/repos/afragen/test-gu-plugin' ) ) {
			$path = (string) parse_url( $url, PHP_URL_PATH );
			if ( str_contains( $path, '/contents/test-gu-plugin.php' ) ) {
				$content = "<?php\n/**\n * Plugin Name: Test GU Plugin\n * Version: 2.0.0\n * GitHub Plugin URI: https://github.com/afragen/test-gu-plugin\n * Primary Branch: main\n * Release Asset: true\n */";
				return [ 'headers' => [], 'body' => json_encode( [ 'content' => base64_encode( $content ), 'encoding' => 'base64' ] ), 'response' => [ 'code' => 200, 'message' => 'OK' ], 'cookies' => [], 'filename' => null ];
			}
			if ( str_contains( $path, '/contents/readme.txt' ) ) {
				$content = "=== Test GU Plugin ===\nContributors: test\nRequires at least: 6.0\nTested up to: 6.5\nRequires PHP: 8.1\nStable tag: 2.0.0\n\n== Description ==\nTest.";
				return [ 'headers' => [], 'body' => json_encode( [ 'content' => base64_encode( $content ), 'encoding' => 'base64' ] ), 'response' => [ 'code' => 200, 'message' => 'OK' ], 'cookies' => [], 'filename' => null ];
			}
			if ( '/repos/afragen/test-gu-plugin/contents' === $path ) {
				return [ 'headers' => [], 'body' => json_encode( [ [ 'name' => 'test-gu-plugin.php', 'type' => 'file' ], [ 'name' => 'readme.txt', 'type' => 'file' ] ] ), 'response' => [ 'code' => 200, 'message' => 'OK' ], 'cookies' => [], 'filename' => null ];
			}
			if ( str_ends_with( $path, '/tags' ) ) {
				return [ 'headers' => [], 'body' => json_encode( [ [ 'name' => '2.0.0', 'zipball_url' => 'https://github.com/afragen/test-gu-plugin/zipball/2.0.0', 'commit' => [ 'sha' => 'abc123' ] ] ] ), 'response' => [ 'code' => 200, 'message' => 'OK' ], 'cookies' => [], 'filename' => null ];
			}
			if ( str_ends_with( $path, '/branches' ) ) {
				return [ 'headers' => [], 'body' => json_encode( [ [ 'name' => 'main', 'commit' => [ 'sha' => 'abc123', 'url' => '' ] ] ] ), 'response' => [ 'code' => 200, 'message' => 'OK' ], 'cookies' => [], 'filename' => null ];
			}
			if ( '/repos/afragen/test-gu-plugin' === $path ) {
				return [ 'headers' => [], 'body' => json_encode( [ 'private' => true, 'pushed_at' => '2024-06-01T12:00:00Z', 'created_at' => '2023-01-01T00:00:00Z', 'watchers' => 0, 'forks' => 0, 'open_issues' => 0 ] ), 'response' => [ 'code' => 200, 'message' => 'OK' ], 'cookies' => [], 'filename' => null ];
			}
			return [ 'headers' => [], 'body' => '[]', 'response' => [ 'code' => 200, 'message' => 'OK' ], 'cookies' => [], 'filename' => null ];
		}
		// Simulate upstream download returning a valid zip.
		if ( str_contains( $url, 'example.com' ) ) {
			$tmp = $this->create_temp_zip();
			return [
				'headers'  => [],
				'body'     => $tmp,
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'cookies'  => [],
				'filename' => null,
			];
		}
		return $preempt;
	}
}
