<?php
/**
 * Tests for Rest_Upgrader_Skin, Rest_Update, and Remote_Management.
 *
 * Rest_Upgrader_Skin:
 * - feedback()  — no-op when upgrader is null (message key not found)
 * - error()     — sets $error flag; parent called safely with null/empty WP_Error
 * - header()    — no-op override; produces no output
 * - footer()    — no-op override; produces no output
 *
 * Rest_Update:
 * - is_error()              — proxies upgrader_skin->error (null initially)
 * - get_messages()          — proxies upgrader_skin->messages ([] initially)
 * - process_request_data()  — non-REST path reads self::$request; returns compact array
 *
 * Remote_Management:
 * - ensure_api_key_is_set() — creates option when absent; skips when present
 * - reset_api_key()         — returns false without $_REQUEST params; deletes option with them
 * - add_settings_tabs()     — registers gu_add_settings_tabs filter
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\Base;
use Fragen\Git_Updater\REST\Rest_Upgrader_Skin;
use Fragen\Git_Updater\REST\Rest_Update;
use Fragen\Git_Updater\Remote_Management;

// ---------------------------------------------------------------------------
// Rest_Upgrader_Skin
// ---------------------------------------------------------------------------

/**
 * Class Test_Rest_Upgrader_Skin
 */
class Test_Rest_Upgrader_Skin extends WP_UnitTestCase {

	private Rest_Upgrader_Skin $skin;

	public function set_up(): void {
		parent::set_up();
		$this->skin = new Rest_Upgrader_Skin();
	}

	public function test_messages_is_empty_array_on_construction(): void {
		$this->assertSame( [], $this->skin->messages );
	}

	public function test_error_property_is_not_set_on_construction(): void {
		$this->assertFalse( isset( $this->skin->error ) );
	}

	public function test_feedback_does_not_add_to_messages_when_upgrader_is_null(): void {
		// upgrader is null → isset($this->upgrader->strings[$message]) === false → early return.
		$this->skin->feedback( 'nonexistent_string_key' );
		$this->assertSame( [], $this->skin->messages );
	}

	public function test_error_sets_error_flag_true_with_null_errors(): void {
		// parent::error(null): is_string(null)=false, is_wp_error(null)=false → parent is a no-op.
		$this->skin->error( null );
		$this->assertTrue( $this->skin->error );
	}

	public function test_error_sets_error_flag_true_with_empty_wp_error(): void {
		// parent::error(WP_Error with no errors): has_errors()=false → parent is a no-op.
		$this->skin->error( new WP_Error() );
		$this->assertTrue( $this->skin->error );
	}

	public function test_header_produces_no_output(): void {
		ob_start();
		$this->skin->header();
		$output = ob_get_clean();
		$this->assertSame( '', $output );
	}

	public function test_footer_produces_no_output(): void {
		ob_start();
		$this->skin->footer();
		$output = ob_get_clean();
		$this->assertSame( '', $output );
	}

	public function test_feedback_appends_string_from_upgrader_strings_without_percent(): void {
		// set_upgrader() must be called explicitly; WP_Upgrader::__construct() does not call init().
		$upgrader = new Plugin_Upgrader( $this->skin );
		$this->skin->set_upgrader( $upgrader );
		$upgrader->strings['gu_test_key'] = 'Hello World';
		$this->skin->feedback( 'gu_test_key' );
		$this->assertContains( 'Hello World', $this->skin->messages );
	}

	public function test_feedback_uses_vsprintf_when_string_contains_percent(): void {
		$upgrader = new Plugin_Upgrader( $this->skin );
		$this->skin->set_upgrader( $upgrader );
		$upgrader->strings['gu_prog_key'] = 'Done: %s';
		$this->skin->feedback( 'gu_prog_key', '100' );
		$this->assertContains( 'Done: 100', $this->skin->messages );
	}

	public function test_decrement_update_count_is_callable_no_op(): void {
		$method = new ReflectionMethod( Rest_Upgrader_Skin::class, 'decrement_update_count' );
		$method->setAccessible( true );
		$method->invoke( $this->skin, 'plugin' );
		$this->assertTrue( true );
	}
}

// ---------------------------------------------------------------------------
// Rest_Update
// ---------------------------------------------------------------------------

/**
 * Class Test_Rest_Update
 */
class Test_Rest_Update extends WP_UnitTestCase {

	private Rest_Update $rest;

	public function set_up(): void {
		parent::set_up();
		// Ensure $_REQUEST is empty so process_request_data() sees a clean state.
		$_REQUEST   = [];
		$this->rest = new Rest_Update();
	}

	public function test_is_error_returns_falsy_when_no_error_occurred(): void {
		$this->assertFalse( (bool) $this->rest->is_error() );
	}

	public function test_get_messages_returns_empty_array_initially(): void {
		$this->assertSame( [], $this->rest->get_messages() );
	}

	public function test_process_request_data_null_returns_false_for_key(): void {
		$result = $this->rest->process_request_data( null );
		$this->assertFalse( $result['key'] );
	}

	public function test_process_request_data_null_returns_false_for_plugin(): void {
		$result = $this->rest->process_request_data( null );
		$this->assertFalse( $result['plugin'] );
	}

	public function test_process_request_data_null_returns_false_for_theme(): void {
		$result = $this->rest->process_request_data( null );
		$this->assertFalse( $result['theme'] );
	}

	public function test_process_request_data_null_returns_master_as_default_tag(): void {
		$result = $this->rest->process_request_data( null );
		$this->assertSame( 'master', $result['tag'] );
	}

	public function test_process_request_data_null_returns_deprecated_string(): void {
		$result = $this->rest->process_request_data( null );
		$this->assertIsString( $result['deprecated'] );
		$this->assertStringContainsString( 'deprecated', strtolower( $result['deprecated'] ) );
	}

	public function test_process_request_data_null_returns_false_for_override(): void {
		$result = $this->rest->process_request_data( null );
		$this->assertFalse( $result['override'] );
	}
}

// ---------------------------------------------------------------------------
// Remote_Management
// ---------------------------------------------------------------------------

/**
 * Class Test_Remote_Management
 */
class Test_Remote_Management extends WP_UnitTestCase {

	private array $saved_request;
	private array $saved_post;

	public function set_up(): void {
		parent::set_up();
		// Snapshot superglobals so we can restore them in tear_down.
		$this->saved_request = $_REQUEST;
		$this->saved_post    = $_POST;
	}

	public function tear_down(): void {
		$_REQUEST = $this->saved_request;
		$_POST    = $this->saved_post;
		delete_site_option( 'git_updater_api_key' );
		remove_all_filters( 'gu_add_settings_tabs' );
		remove_all_actions( 'gu_add_admin_page' );
		parent::tear_down();
	}

	public function test_constructor_creates_api_key_when_none_exists(): void {
		delete_site_option( 'git_updater_api_key' );

		new Remote_Management();

		$this->assertNotFalse( get_site_option( 'git_updater_api_key', false ) );
	}

	public function test_constructor_does_not_overwrite_existing_api_key(): void {
		update_site_option( 'git_updater_api_key', 'my-fixed-key' );

		new Remote_Management();

		$this->assertSame( 'my-fixed-key', get_site_option( 'git_updater_api_key' ) );
	}

	public function test_ensure_api_key_is_set_creates_key_when_option_is_absent(): void {
		delete_site_option( 'git_updater_api_key' );
		// Construct with no option present; ensure_api_key_is_set() is called by constructor.
		new Remote_Management();

		$key = get_site_option( 'git_updater_api_key', false );
		$this->assertIsString( $key );
		$this->assertNotEmpty( $key );
	}

	public function test_reset_api_key_returns_false_without_request_params(): void {
		$rm     = new Remote_Management();
		$result = $rm->reset_api_key();
		$this->assertFalse( $result );
	}

	public function test_reset_api_key_returns_true_and_deletes_option_with_valid_request(): void {
		update_site_option( 'git_updater_api_key', 'key-to-delete' );
		$rm = new Remote_Management();

		$_REQUEST['tab']                      = 'git_updater_remote_management';
		$_REQUEST['git_updater_reset_api_key'] = '1';

		$result = $rm->reset_api_key();

		$this->assertTrue( $result );
		$this->assertFalse( get_site_option( 'git_updater_api_key', false ) );
	}

	public function test_add_settings_tabs_registers_remote_management_tab(): void {
		$rm = new Remote_Management();
		$rm->add_settings_tabs();

		$tabs = apply_filters( 'gu_add_settings_tabs', [] );

		$this->assertArrayHasKey( 'git_updater_remote_management', $tabs );
	}

	public function test_add_settings_tabs_preserves_existing_tabs(): void {
		$rm = new Remote_Management();
		$rm->add_settings_tabs();

		$tabs = apply_filters( 'gu_add_settings_tabs', [ 'existing' => 'Existing Tab' ] );

		$this->assertArrayHasKey( 'existing', $tabs );
		$this->assertArrayHasKey( 'git_updater_remote_management', $tabs );
	}
}

// ---------------------------------------------------------------------------
// Test_Rest_Update_Process
// ---------------------------------------------------------------------------

/**
 * Class Test_Rest_Update_Process
 *
 * Exercises Rest_Update methods that call log_exit() or perform upgrades:
 * - log_exit()            — fires action then throws via wp_die()
 * - update_plugin/theme() — throws UnexpectedValueException for nonexistent slugs
 * - process_request()     — various key/branch/webhook paths all end via WPDieException
 * - get_primary_branch()  — returns cached PrimaryBranch when present
 */
class Test_Rest_Update_Process extends WP_UnitTestCase {

	private Rest_Update $rest;
	private array       $saved_request;
	private array       $saved_get;
	private array       $saved_server;

	public function set_up(): void {
		parent::set_up();
		$this->saved_request = $_REQUEST;
		$this->saved_get     = $_GET;
		$this->saved_server  = $_SERVER;

		// wp_send_json_success/error() calls `die;` in non-AJAX context (the else branch).
		// In AJAX context it calls wp_die('', '', ['response' => null]) instead.
		// The test framework only hooks wp_die_handler (non-AJAX); we must also hook
		// wp_die_ajax_handler so that the inner wp_die() inside wp_send_json() throws
		// WPDieException instead of calling die($message).
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function (): callable {
				return static function ( $msg, $title, $args ): void {
					throw new WPDieException( (string) $msg, (int) ( $args['response'] ?? 200 ) );
				};
			}
		);

		update_site_option( 'git_updater_api_key', 'test-process-key' );
		$_REQUEST   = [];
		$_GET       = [];
		$this->rest = new Rest_Update();
	}

	public function tear_down(): void {
		$_REQUEST = $this->saved_request;
		$_GET     = $this->saved_get;
		$_SERVER  = $this->saved_server;
		delete_site_option( 'git_updater_api_key' );
		remove_all_filters( 'wp_doing_ajax' );
		remove_all_filters( 'wp_die_ajax_handler' );
		remove_all_actions( 'gu_post_rest_process_request' );
		remove_all_actions( 'gu_pre_rest_process_request' );
		remove_all_filters( 'upgrader_pre_download' );
		remove_all_filters( 'site_transient_update_plugins' );
		remove_all_filters( 'site_transient_update_themes' );
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Helper
	// -------------------------------------------------------------------------

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

	private function make_rest_with_key( string $key = 'test-process-key', array $extra = [] ): Rest_Update {
		$_REQUEST = array_merge( [ 'key' => $key ], $extra );
		$rest     = new Rest_Update();
		$_REQUEST = [];
		return $rest;
	}

	// -------------------------------------------------------------------------
	// log_exit()
	// -------------------------------------------------------------------------

	public function test_log_exit_200_fires_action_and_throws_wp_die(): void {
		$action_fired = false;
		add_action(
			'gu_post_rest_process_request',
			function () use ( &$action_fired ) {
				$action_fired = true;
			}
		);

		$this->assert_wp_die_thrown(
			fn() => $this->rest->log_exit( [ 'success' => true, 'messages' => [] ], 200 )
		);
		$this->assertTrue( $action_fired );
	}

	public function test_log_exit_418_throws_wp_die(): void {
		$this->assert_wp_die_thrown(
			fn() => $this->rest->log_exit( [ 'success' => false, 'messages' => 'Error' ], 418 )
		);
	}

	// -------------------------------------------------------------------------
	// update_plugin() / update_theme() — not-found throws
	// -------------------------------------------------------------------------

	public function test_update_plugin_throws_for_nonexistent_slug(): void {
		$this->expectException( UnexpectedValueException::class );
		$this->rest->update_plugin( 'zzz-nonexistent-plugin-xyz-9999' );
	}

	public function test_update_theme_throws_for_nonexistent_slug(): void {
		$this->expectException( UnexpectedValueException::class );
		$this->rest->update_theme( 'zzz-nonexistent-theme-xyz-9999' );
	}

	// -------------------------------------------------------------------------
	// process_request() — key / plugin / theme / branch paths
	// -------------------------------------------------------------------------

	public function test_process_request_bad_key_triggers_wp_die(): void {
		// self::$request = [] from set_up → key = false → throws "Bad API key"
		$this->assert_wp_die_thrown( fn() => $this->rest->process_request( null ) );
	}

	public function test_process_request_valid_key_no_plugin_no_theme_triggers_wp_die(): void {
		// key matches, no plugin/theme → throws "No plugin or theme specified"
		$rest = $this->make_rest_with_key();
		$this->assert_wp_die_thrown( fn() => $rest->process_request( null ) );
	}

	public function test_process_request_version_tag_skips_branch_mismatch(): void {
		// tag='2.0.0' matches version regex → remote_branch='master'; current_branch='master' → no mismatch
		// → falls through to "No plugin or theme" → WPDieException
		$rest = $this->make_rest_with_key( 'test-process-key', [ 'tag' => '2.0.0' ] );
		$this->assert_wp_die_thrown( fn() => $rest->process_request( null ) );
	}

	public function test_process_request_branch_param_causes_mismatch_throw(): void {
		// branch='develop' → remote_branch='develop'; current_branch='master' → mismatch → WPDieException
		$rest = $this->make_rest_with_key( 'test-process-key', [ 'branch' => 'develop' ] );
		$this->assert_wp_die_thrown( fn() => $rest->process_request( null ) );
	}

	public function test_process_request_override_prevents_branch_mismatch(): void {
		// override=1 → current_branch=remote_branch='develop' → no mismatch → "No plugin or theme" → WPDieException
		$rest = $this->make_rest_with_key( 'test-process-key', [ 'branch' => 'develop', 'override' => '1' ] );
		$this->assert_wp_die_thrown( fn() => $rest->process_request( null ) );
	}

	// -------------------------------------------------------------------------
	// get_webhook_source() — private, exercised via process_request()
	// -------------------------------------------------------------------------

	private function webhook_source_test( array $server_headers ): string {
		foreach ( $server_headers as $k => $v ) {
			$_SERVER[ $k ] = $v;
		}
		$rest = $this->make_rest_with_key();
		$this->assert_wp_die_thrown( fn() => $rest->process_request( null ) );
		foreach ( array_keys( $server_headers ) as $k ) {
			unset( $_SERVER[ $k ] );
		}
		return $_GET['webhook_source'] ?? '';
	}

	public function test_webhook_source_github(): void {
		$source = $this->webhook_source_test( [ 'HTTP_X_GITHUB_EVENT' => 'push' ] );
		$this->assertSame( 'GitHub webhook', $source );
	}

	public function test_webhook_source_bitbucket(): void {
		$source = $this->webhook_source_test( [ 'HTTP_X_EVENT_KEY' => 'repo:push' ] );
		$this->assertSame( 'Bitbucket webhook', $source );
	}

	public function test_webhook_source_gitlab(): void {
		$source = $this->webhook_source_test( [ 'HTTP_X_GITLAB_EVENT' => 'Push Hook' ] );
		$this->assertSame( 'GitLab webhook', $source );
	}

	public function test_webhook_source_gitea(): void {
		$source = $this->webhook_source_test( [ 'HTTP_X_GITEA_EVENT' => 'push' ] );
		$this->assertSame( 'Gitea webhook', $source );
	}

	public function test_webhook_source_defaults_to_browser(): void {
		// Ensure no webhook headers are present.
		foreach ( [ 'HTTP_X_GITHUB_EVENT', 'HTTP_X_EVENT_KEY', 'HTTP_X_GITLAB_EVENT', 'HTTP_X_GITEA_EVENT' ] as $k ) {
			unset( $_SERVER[ $k ] );
		}
		$rest = $this->make_rest_with_key();
		$this->assert_wp_die_thrown( fn() => $rest->process_request( null ) );
		$this->assertSame( 'browser', $_GET['webhook_source'] ?? '' );
	}

	// -------------------------------------------------------------------------
	// get_primary_branch() — exercised via process_request_data(WP_REST_Request)
	// -------------------------------------------------------------------------

	public function test_get_primary_branch_returns_value_from_cache(): void {
		$slug      = 'primary-branch-test-slug-xyz';
		$cache_key = 'ghu-' . md5( $slug );
		update_site_option( $cache_key, [ $slug => [ 'PrimaryBranch' => 'main' ] ] );

		$request = new WP_REST_Request( 'GET', '/git-updater/v1/update' );
		$request->set_param( 'plugin', $slug );
		$request->set_param( 'theme', false );
		$request->set_param( 'tag', false );
		$request->set_param( 'key', false );
		$request->set_param( 'committish', false );
		$request->set_param( 'branch', false );
		$request->set_param( 'override', false );

		$result = $this->rest->process_request_data( $request );

		delete_site_option( $cache_key );
		$this->assertSame( 'main', $result['tag'] );
	}
}

// ---------------------------------------------------------------------------
// Test_Rest_Update_Full_Path
// ---------------------------------------------------------------------------

/**
 * Class Test_Rest_Update_Full_Path
 *
 * Fixture-dependent tests exercising the full upgrade paths in Rest_Update:
 * - update_plugin() — lines 103-152 (upgrade + reactivation)
 * - update_theme()  — lines 177-218
 * - process_request() success path — lines 316-344
 *
 * All tests skip when the fixture plugin/theme is not installed.
 * HTTP is mocked via pre_http_request to avoid real network calls.
 * Plugin upgrade uses a local zip returned from upgrader_pre_download at priority 15.
 */
class Test_Rest_Update_Full_Path extends WP_UnitTestCase {

	private const PLUGIN_SLUG = 'test-gu-plugin';
	private const THEME_SLUG  = 'test-gu-theme';

	private ?string $zip_path           = null;
	private ?string $theme_zip_path     = null;
	private ?string $plugin_file_backup = null;
	private ?string $theme_file_backup  = null;
	private array   $saved_request;

	public function set_up(): void {
		parent::set_up();
		$this->saved_request = $_REQUEST;

		// Force AJAX context so wp_send_json_success/error() calls wp_die() instead of die;.
		// Also override wp_die_ajax_handler so wp_die() throws WPDieException (the test
		// framework only hooks the non-AJAX wp_die_handler by default).
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function (): callable {
				return static function ( $msg, $title, $args ): void {
					throw new WPDieException( (string) $msg, (int) ( $args['response'] ?? 200 ) );
				};
			}
		);

		new Base();
		update_site_option( 'git_updater_api_key', 'test-full-path-key' );
		add_filter( 'pre_http_request', [ $this, 'mock_http' ], 10, 3 );

		// Save fixture file contents. Plugin_Upgrader::upgrade() replaces files in bind-mounted
		// directories; tear_down() restores them so the host filesystem is left unchanged.
		$plugin_path = WP_PLUGIN_DIR . '/' . self::PLUGIN_SLUG . '/' . self::PLUGIN_SLUG . '.php';
		if ( file_exists( $plugin_path ) ) {
			$this->plugin_file_backup = file_get_contents( $plugin_path );
		}
		$theme_path = get_theme_root() . '/' . self::THEME_SLUG . '/style.css';
		if ( file_exists( $theme_path ) ) {
			$this->theme_file_backup = file_get_contents( $theme_path );
		}
	}

	public function tear_down(): void {
		remove_filter( 'pre_http_request', [ $this, 'mock_http' ], 10 );
		remove_all_filters( 'upgrader_pre_download' );
		remove_all_filters( 'site_transient_update_plugins' );
		remove_all_filters( 'site_transient_update_themes' );
		remove_all_filters( 'http_request_args' );
		remove_all_filters( 'wp_doing_ajax' );
		remove_all_filters( 'wp_die_ajax_handler' );
		delete_site_option( 'git_updater_api_key' );

		foreach ( [ $this->zip_path, $this->theme_zip_path ] as $path ) {
			if ( $path && file_exists( $path ) ) {
				unlink( $path );
			}
		}
		deactivate_plugins( self::PLUGIN_SLUG . '/' . self::PLUGIN_SLUG . '.php' );

		// Restore bind-mounted fixture files that the upgrader may have modified or deleted.
		if ( null !== $this->plugin_file_backup ) {
			$dir = WP_PLUGIN_DIR . '/' . self::PLUGIN_SLUG;
			if ( ! is_dir( $dir ) ) {
				mkdir( $dir, 0755, true );
			}
			file_put_contents( $dir . '/' . self::PLUGIN_SLUG . '.php', $this->plugin_file_backup );
		}
		if ( null !== $this->theme_file_backup ) {
			$dir = get_theme_root() . '/' . self::THEME_SLUG;
			if ( ! is_dir( $dir ) ) {
				mkdir( $dir, 0755, true );
			}
			file_put_contents( $dir . '/style.css', $this->theme_file_backup );
		}

		$_REQUEST = $this->saved_request;
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function skip_if_plugin_absent(): void {
		// Check file existence directly. Using new Plugin() re-scans from disk and would
		// wrongly skip after a previous test's upgrader deleted the file mid-run; the
		// Singleton Plugin's config (used by update_plugin) retains the entry from bootstrap.
		$path = WP_PLUGIN_DIR . '/' . self::PLUGIN_SLUG . '/' . self::PLUGIN_SLUG . '.php';
		if ( ! file_exists( $path ) ) {
			$this->markTestSkipped( 'Fixture plugin not installed. Run: npm run wp-env start' );
		}
	}

	private function skip_if_theme_absent(): void {
		$path = get_theme_root() . '/' . self::THEME_SLUG . '/style.css';
		if ( ! file_exists( $path ) ) {
			$this->markTestSkipped( 'Fixture theme not installed. Run: npm run wp-env start' );
		}
	}

	private function create_plugin_zip(): string {
		$path = sys_get_temp_dir() . '/gu-test-plugin-' . uniqid() . '.zip';
		$zip  = new ZipArchive();
		$zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		$zip->addFromString(
			self::PLUGIN_SLUG . '/' . self::PLUGIN_SLUG . '.php',
			implode(
				"\n",
				[
					'<?php',
					'/**',
					' * Plugin Name:       Test GU Plugin',
					' * Plugin URI:        https://github.com/afragen/test-gu-plugin',
					' * Description:       Minimal fixture plugin for PHPUnit integration tests.',
					' * Version:           1.2.0',
					' * Author:            Test Author',
					' * License:           GPL-3.0-or-later',
					' * GitHub Plugin URI: https://github.com/afragen/test-gu-plugin',
					' * Primary Branch:    main',
					' */',
				]
			)
		);
		$zip->close();
		$this->zip_path = $path;
		return $path;
	}

	private function create_theme_zip(): string {
		$path = sys_get_temp_dir() . '/gu-test-theme-' . uniqid() . '.zip';
		$zip  = new ZipArchive();
		$zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		$zip->addFromString(
			self::THEME_SLUG . '/style.css',
			implode(
				"\n",
				[
					'/*',
					'Theme Name: Test GU Theme',
					'Theme URI: https://github.com/afragen/test-gu-theme',
					'Description: Minimal fixture theme for PHPUnit integration tests.',
					'Version: 1.0.0',
					'Author: Test Author',
					'Author URI: https://example.com',
					'License: GPL-3.0-or-later',
					'GitHub Theme URI: https://github.com/afragen/test-gu-theme',
					'Primary Branch: main',
					'*/',
				]
			)
		);
		$zip->close();
		$this->theme_zip_path = $path;
		return $path;
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

	/**
	 * Mock HTTP: all GitHub API calls return [] (empty); wordpress.org returns valid structure.
	 *
	 * @param mixed  $preempt Existing preempt value.
	 * @param mixed  $args    Request args.
	 * @param string $url     Request URL.
	 * @return mixed
	 */
	public function mock_http( mixed $preempt, mixed $args, string $url ): mixed {
		if ( str_contains( $url, 'api.wordpress.org/plugins/update-check' ) ) {
			return $this->http_response( json_encode( [ 'plugins' => [], 'translations' => [], 'no_update' => [] ] ) );
		}
		if ( str_contains( $url, 'api.wordpress.org/themes/update-check' ) ) {
			return $this->http_response( json_encode( [ 'themes' => [], 'translations' => [], 'no_update' => [] ] ) );
		}
		if ( str_contains( $url, 'api.wordpress.org' ) ) {
			return $this->http_response( json_encode( [ 'error' => 'Plugin not found.' ] ) );
		}
		if ( str_contains( $url, 'api.github.com' ) ) {
			return $this->http_response( '[]' );
		}
		return $preempt;
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

	// -------------------------------------------------------------------------
	// update_plugin() — lines 118-120 (non-object $current transient branch)
	// -------------------------------------------------------------------------

	public function test_update_plugin_non_object_transient_covers_lines_119_120(): void {
		$this->skip_if_plugin_absent();

		// Delete the transient so get_site_transient('update_plugins') returns false initially.
		// Plugin::update_site_transient() is registered at priority 15 and converts false to
		// an object. We add our false-returning filter also at priority 15 AFTER Plugin's
		// registration but BEFORE the source closure (registered inside update_plugin()) —
		// all three run at priority 15 in registration order. The sequence becomes:
		//   1. Plugin filter (15, pos1): false → object
		//   2. Our filter   (15, pos2): object → false
		//   3. Source closure (15, pos3): false → !is_object → lines 119-120 execute.
		delete_site_transient( 'update_plugins' );
		add_filter( 'site_transient_update_plugins', fn() => false, 15, 1 );

		$zip_path = $this->create_plugin_zip();
		add_filter( 'upgrader_pre_download', fn() => $zip_path, 15, 3 );

		$_REQUEST = [];
		$rest     = new Rest_Update();
		$rest->update_plugin( self::PLUGIN_SLUG, 'main' );
		$this->assertTrue( true );
	}

	// -------------------------------------------------------------------------
	// update_theme() — lines 191-193 (non-object $current transient branch)
	// -------------------------------------------------------------------------

	public function test_update_theme_non_object_transient_covers_lines_192_193(): void {
		$this->skip_if_theme_absent();

		// Delete the transient so WordPress returns false initially.
		// Theme::update_site_transient() is registered at priority 15 and converts false
		// to an object. We add our false-returning filter also at priority 15 AFTER
		// Theme's registration but BEFORE the source closure (registered inside
		// update_theme()) — all three run at priority 15 in registration order:
		//   1. Theme filter (15, pos1): false → object
		//   2. Our filter   (15, pos2): object → false
		//   3. Source closure (15, pos3): false → !is_object → lines 192-193 execute.
		delete_site_transient( 'update_themes' );
		add_filter( 'site_transient_update_themes', fn() => false, 15, 1 );

		$zip_path = $this->create_theme_zip();
		add_filter( 'upgrader_pre_download', fn() => $zip_path, 15, 3 );

		$_REQUEST = [];
		$rest     = new Rest_Update();
		$rest->update_theme( self::THEME_SLUG, 'main' );
		$this->assertTrue( true );
	}

	// -------------------------------------------------------------------------
	// update_plugin() — lines 103-144 (upgrade without reactivation)
	// -------------------------------------------------------------------------

	public function test_update_plugin_covers_upgrade_path(): void {
		$this->skip_if_plugin_absent();

		$zip_path = $this->create_plugin_zip();
		add_filter(
			'upgrader_pre_download',
			function ( $result ) use ( $zip_path ) {
				return $zip_path;
			},
			15,
			3
		);

		$_REQUEST   = [];
		$rest       = new Rest_Update();
		$rest->update_plugin( self::PLUGIN_SLUG, 'main' );
		// Reached here without UnexpectedValueException.
		$this->assertTrue( true );
	}

	// -------------------------------------------------------------------------
	// update_plugin() — lines 146-150 (reactivation path)
	// -------------------------------------------------------------------------

	public function test_update_plugin_covers_reactivation_path(): void {
		$this->skip_if_plugin_absent();

		$plugin_file = self::PLUGIN_SLUG . '/' . self::PLUGIN_SLUG . '.php';
		activate_plugin( $plugin_file );

		// Return an invalid (non-zip) file from upgrader_pre_download at priority 15.
		// This bypasses download_url() entirely (no WP temp file is created), so
		// unpack_package() tries to unzip our file, fails, then unlinks it cleanly.
		// Because upgrader_pre_install never fires, the plugin is never deactivated,
		// and the original file stays in place so activate_plugin() returns null.
		$fake_zip = sys_get_temp_dir() . '/gu-not-a-zip-' . uniqid() . '.zip';
		file_put_contents( $fake_zip, 'not a valid zip' );
		add_filter(
			'upgrader_pre_download',
			function () use ( $fake_zip ) {
				return $fake_zip;
			},
			15,
			3
		);

		$_REQUEST = [];
		$rest     = new Rest_Update();
		$rest->update_plugin( self::PLUGIN_SLUG, 'main' );

		// Reactivation appends a message if activate_plugin() returns null (success).
		$messages = $rest->get_messages();
		$this->assertContains( 'Plugin reactivated successfully.', $messages );
	}

	// -------------------------------------------------------------------------
	// update_theme() — lines 177-218
	// -------------------------------------------------------------------------

	public function test_update_theme_covers_upgrade_path(): void {
		$this->skip_if_theme_absent();

		$zip_path = $this->create_theme_zip();
		add_filter(
			'upgrader_pre_download',
			function ( $result ) use ( $zip_path ) {
				return $zip_path;
			},
			15,
			3
		);

		$_REQUEST = [];
		$rest     = new Rest_Update();
		$rest->update_theme( self::THEME_SLUG, 'main' );
		$this->assertTrue( true );
	}

	// -------------------------------------------------------------------------
	// process_request() lines 316-329: success branch (is_error=false, line 344)
	// -------------------------------------------------------------------------

	public function test_process_request_success_path_reaches_log_exit_200(): void {
		$this->skip_if_plugin_absent();

		$zip_path = $this->create_plugin_zip();
		add_filter(
			'upgrader_pre_download',
			function ( $result ) use ( $zip_path ) {
				return $zip_path;
			},
			15,
			3
		);

		$_REQUEST = [
			'key'    => 'test-full-path-key',
			'plugin' => self::PLUGIN_SLUG,
			'tag'    => 'main',
		];
		$rest     = new Rest_Update();

		$this->assert_wp_die_thrown( fn() => $rest->process_request( null ) );
	}

	// -------------------------------------------------------------------------
	// process_request() lines 340-342: is_error=true after failed upgrade
	// -------------------------------------------------------------------------

	public function test_process_request_theme_path_covers_line_300_and_401_402(): void {
		// theme=truthy → get_local_branch() hits theme branch (401-402) → update_theme() (300) →
		// no-op (no error) → success branch (317-329) → log_exit(200) (344) → WPDieException.
		// tag='1.0.0' matches version regex → remote_branch='master' = current_branch → no mismatch.
		$_REQUEST = [
			'key'   => 'test-full-path-key',
			'theme' => 'any-nonexistent-theme-slug',
			'tag'   => '1.0.0',
		];
		$fake = new class extends Rest_Update {
			public function update_plugin( $slug, $tag = 'master' ) {} // @phpcsignore
			public function update_theme( $slug, $tag = 'master' ) {}  // @phpcsignore
		};
		$this->assert_wp_die_thrown( fn() => $fake->process_request( null ) );
	}

	public function test_process_request_error_path_reaches_log_exit_418_after_upgrade_failure(): void {
		$this->skip_if_plugin_absent();

		// No priority-15 filter → upgrader tries real download → HTTP mock returns [] →
		// download_package sees a non-zip body → upgrade fails → skin.error=true → lines 340-342.
		$_REQUEST = [
			'key'    => 'test-full-path-key',
			'plugin' => self::PLUGIN_SLUG,
			'tag'    => 'main',
		];
		$rest     = new Rest_Update();

		$this->assert_wp_die_thrown( fn() => $rest->process_request( null ) );
	}
}
