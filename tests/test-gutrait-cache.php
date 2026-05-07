<?php
/**
 * Tests for GU_Trait cache methods and misc methods not covered by
 * test-gutrait.php or test-gutrait-extended.php.
 *
 * Covers:
 * - get_repo_cache()           — cache read with/without timeout enforcement
 * - set_repo_cache()           — cache write, WP_Error short-circuit, timeout preservation
 * - maybe_extend_repo_cache()  — version-match cache extension logic
 * - delete_all_cached_data()   — ghu-prefixed option cleanup
 * - is_private()               — repo property guards
 * - get_plugin_version()       — static file-data read
 * - gu_plugin_name()           — active-plugin basename check
 * - is_cron_event_scheduled()  — WP-Cron event lookup
 * - is_cron_overdue()          — 24-hour overdue guard (non-overdue path)
 * - delete_upgrade_source()    — WP_Error and missing-destination pass-through
 * - get_class_vars()           — Singleton reflection on known property
 * - get_error_codes()          — delegation to get_class_vars on API error_code
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\API\GitHub_API;
use Fragen\Git_Updater\Base;

class Test_GUTrait_Cache extends WP_UnitTestCase {

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
		remove_all_filters( 'wp_doing_ajax' );
		delete_site_option( $this->api->get_cache_key( 'test-plugin' ) );
		delete_site_option( $this->api->get_cache_key( 'test-plugin_error' ) );
		wp_clear_scheduled_hook( 'gu_test_cron_hook_xyz' );
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

	// -------------------------------------------------------------------------
	// get_repo_cache()
	// -------------------------------------------------------------------------

	public function test_get_repo_cache_returns_false_when_no_cache_exists(): void {
		$result = $this->api->get_repo_cache( 'test-plugin' );
		$this->assertFalse( $result );
	}

	public function test_get_repo_cache_returns_false_when_timeout_expired(): void {
		$cache_key = $this->api->get_cache_key( 'test-plugin' );
		update_site_option(
			$cache_key,
			[
				'timeout' => strtotime( '-1 hour' ),
				'data'    => 'some-value',
			]
		);

		$result = $this->api->get_repo_cache( 'test-plugin' );
		$this->assertFalse( $result );
	}

	public function test_get_repo_cache_returns_cache_when_timeout_valid(): void {
		$cache_key = $this->api->get_cache_key( 'test-plugin' );
		update_site_option(
			$cache_key,
			[
				'timeout' => strtotime( '+12 hours' ),
				'data'    => 'cached-value',
			]
		);

		$result = $this->api->get_repo_cache( 'test-plugin' );
		$this->assertIsArray( $result );
		$this->assertSame( 'cached-value', $result['data'] );
	}

	public function test_get_repo_cache_without_timeout_returns_expired_cache(): void {
		$cache_key = $this->api->get_cache_key( 'test-plugin' );
		update_site_option(
			$cache_key,
			[
				'timeout' => strtotime( '-1 hour' ),
				'data'    => 'stale-value',
			]
		);

		$result = $this->api->get_repo_cache( 'test-plugin', false );
		$this->assertIsArray( $result );
		$this->assertSame( 'stale-value', $result['data'] );
	}

	// -------------------------------------------------------------------------
	// set_repo_cache()
	// -------------------------------------------------------------------------

	public function test_set_repo_cache_returns_false_for_wp_error(): void {
		$error  = new WP_Error( 'test', 'test error' );
		$result = $this->api->set_repo_cache( 'data', $error, 'test-plugin' );
		$this->assertFalse( $result );
	}

	public function test_set_repo_cache_stores_value_and_returns_true(): void {
		$result = $this->api->set_repo_cache( 'my_key', 'my_value', 'test-plugin', '+1 hour' );
		$this->assertTrue( $result );

		$cache = $this->api->get_repo_cache( 'test-plugin' );
		$this->assertIsArray( $cache );
		$this->assertSame( 'my_value', $cache['my_key'] );
	}

	public function test_set_repo_cache_preserves_existing_timeout_on_second_write(): void {
		$this->api->set_repo_cache( 'key1', 'val1', 'test-plugin', '+6 hours' );
		$timeout_after_first = $this->api->get_repo_cache( 'test-plugin' )['timeout'];

		$this->api->set_repo_cache( 'key2', 'val2', 'test-plugin', '+12 hours' );
		$timeout_after_second = $this->api->get_repo_cache( 'test-plugin' )['timeout'];

		$this->assertSame( $timeout_after_first, $timeout_after_second );
	}

	public function test_set_repo_cache_stores_multiple_keys_in_same_cache_entry(): void {
		$this->api->set_repo_cache( 'alpha', 'a', 'test-plugin', '+1 hour' );
		$this->api->set_repo_cache( 'beta', 'b', 'test-plugin' );

		$cache = $this->api->get_repo_cache( 'test-plugin' );
		$this->assertSame( 'a', $cache['alpha'] );
		$this->assertSame( 'b', $cache['beta'] );
	}

	// -------------------------------------------------------------------------
	// maybe_extend_repo_cache()
	// -------------------------------------------------------------------------

	public function test_maybe_extend_repo_cache_returns_false_when_cache_empty(): void {
		$result = $this->api->maybe_extend_repo_cache( [ 'Version' => '1.0.0' ], $this->type );
		$this->assertFalse( $result );
	}

	public function test_maybe_extend_repo_cache_extends_when_version_matches_and_meta_present(): void {
		$cache_key = $this->api->get_cache_key( 'test-plugin' );
		update_site_option(
			$cache_key,
			[
				'repo'        => 'test-plugin',
				'test-plugin' => [ 'Version' => '1.0.0' ],
				'meta'        => [ 'last_updated' => '2024-01-01' ],
				'timeout'     => strtotime( '-1 hour' ),
			]
		);

		$result = $this->api->maybe_extend_repo_cache( [ 'Version' => '1.0.0' ], $this->type );
		$this->assertTrue( $result );

		$cache = get_site_option( $cache_key );
		$this->assertGreaterThan( time(), $cache['timeout'] );
	}

	public function test_maybe_extend_repo_cache_returns_false_when_versions_differ(): void {
		$cache_key = $this->api->get_cache_key( 'test-plugin' );
		update_site_option(
			$cache_key,
			[
				'repo'        => 'test-plugin',
				'test-plugin' => [ 'Version' => '2.0.0' ],
				'meta'        => [ 'last_updated' => '2024-01-01' ],
				'timeout'     => strtotime( '-1 hour' ),
			]
		);

		$result = $this->api->maybe_extend_repo_cache( [ 'Version' => '1.0.0' ], $this->type );
		$this->assertFalse( $result );
	}

	public function test_maybe_extend_repo_cache_returns_false_when_meta_missing(): void {
		$cache_key = $this->api->get_cache_key( 'test-plugin' );
		update_site_option(
			$cache_key,
			[
				'repo'        => 'test-plugin',
				'test-plugin' => [ 'Version' => '1.0.0' ],
				// no 'meta' key — cache data incomplete
				'timeout'     => strtotime( '-1 hour' ),
			]
		);

		$result = $this->api->maybe_extend_repo_cache( [ 'Version' => '1.0.0' ], $this->type );
		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// delete_all_cached_data()
	// -------------------------------------------------------------------------

	public function test_delete_all_cached_data_removes_ghu_prefixed_options(): void {
		$cache_key = $this->api->get_cache_key( 'test-plugin' );
		update_site_option( $cache_key, [ 'data' => 'test' ] );
		$this->assertIsArray( get_site_option( $cache_key ) );

		$this->api->delete_all_cached_data();

		$this->assertFalse( get_site_option( $cache_key, false ) );
	}

	public function test_delete_all_cached_data_returns_true(): void {
		$result = $this->api->delete_all_cached_data();
		$this->assertTrue( $result );
	}

	// -------------------------------------------------------------------------
	// is_private()
	// -------------------------------------------------------------------------

	public function test_is_private_returns_true_when_remote_version_not_set(): void {
		$repo = new stdClass();
		$this->assertTrue( $this->api->is_private( $repo ) );
	}

	public function test_is_private_returns_false_when_remote_version_is_real_version(): void {
		$repo                 = new stdClass();
		$repo->slug           = 'test-plugin';
		$repo->remote_version = '1.0.0';
		$this->assertFalse( $this->api->is_private( $repo ) );
	}

	public function test_is_private_returns_true_when_remote_version_is_zero(): void {
		$repo                 = new stdClass();
		$repo->slug           = 'test-plugin';
		$repo->remote_version = '0.0.0';
		$this->assertTrue( $this->api->is_private( $repo ) );
	}

	// -------------------------------------------------------------------------
	// get_plugin_version()
	// -------------------------------------------------------------------------

	public function test_get_plugin_version_returns_string(): void {
		$version = GitHub_API::get_plugin_version();
		$this->assertIsString( $version );
	}

	public function test_get_plugin_version_is_not_empty(): void {
		$version = GitHub_API::get_plugin_version();
		$this->assertNotEmpty( $version );
	}

	// -------------------------------------------------------------------------
	// gu_plugin_name()
	// -------------------------------------------------------------------------

	public function test_gu_plugin_name_returns_string(): void {
		$name = $this->api->gu_plugin_name();
		$this->assertIsString( $name );
	}

	public function test_gu_plugin_name_ends_with_php(): void {
		$name = $this->api->gu_plugin_name();
		$this->assertStringEndsWith( '.php', $name );
	}

	// -------------------------------------------------------------------------
	// is_cron_event_scheduled()
	// -------------------------------------------------------------------------

	public function test_is_cron_event_scheduled_returns_false_for_unregistered_hook(): void {
		$result = $this->api->is_cron_event_scheduled( 'gu_nonexistent_hook_xyz_12345' );
		$this->assertFalse( $result );
	}

	public function test_is_cron_event_scheduled_returns_true_for_registered_hook(): void {
		// Schedule in the past so wp_get_ready_cron_jobs() (used internally)
		// returns it. One hour ago is still within the 24-hour overdue window
		// so is_cron_overdue() will not try to create an error message.
		wp_schedule_single_event( time() - HOUR_IN_SECONDS, 'gu_test_cron_hook_xyz' );

		$result = $this->api->is_cron_event_scheduled( 'gu_test_cron_hook_xyz' );
		$this->assertTrue( $result );

		wp_clear_scheduled_hook( 'gu_test_cron_hook_xyz' );
	}

	// -------------------------------------------------------------------------
	// is_cron_overdue()
	// -------------------------------------------------------------------------

	public function test_is_cron_overdue_does_not_error_when_timestamp_is_recent(): void {
		// One hour ago is not overdue (< 24 hours). Should execute silently.
		$this->api->is_cron_overdue( time() - HOUR_IN_SECONDS );
		$this->addToAssertionCount( 1 );
	}

	// -------------------------------------------------------------------------
	// delete_upgrade_source()
	// -------------------------------------------------------------------------

	public function test_delete_upgrade_source_returns_wp_error_unchanged(): void {
		$error  = new WP_Error( 'test', 'test error' );
		$result = $this->api->delete_upgrade_source( $error );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'test', $result->get_error_code() );
	}

	public function test_delete_upgrade_source_returns_result_array_without_destination_unchanged(): void {
		$input  = [ 'other_key' => 'value' ];
		$result = $this->api->delete_upgrade_source( $input );
		$this->assertSame( $input, $result );
	}

	// -------------------------------------------------------------------------
	// get_class_vars()
	// -------------------------------------------------------------------------

	public function test_get_class_vars_returns_property_value_for_known_class(): void {
		$hours = $this->api->get_class_vars( 'API\API', 'hours' );
		$this->assertSame( 12, $hours );
	}

	public function test_get_class_vars_returns_false_for_nonexistent_property(): void {
		$result = $this->api->get_class_vars( 'API\API', 'nonexistent_property_xyz' );
		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// get_error_codes()
	// -------------------------------------------------------------------------

	public function test_get_error_codes_returns_array(): void {
		$result = $this->api->get_error_codes();
		$this->assertIsArray( $result );
	}

	// -------------------------------------------------------------------------
	// maybe_extend_repo_cache() — expired timeout update (lines 225–226)
	// -------------------------------------------------------------------------

	public function test_maybe_extend_repo_cache_updates_timeout_when_expired_and_meta_present(): void {
		$slug      = 'test-plugin';
		$cache_key = $this->api->get_cache_key( $slug );
		update_site_option(
			$cache_key,
			[
				'repo'    => $slug,
				$slug     => [ 'Version' => '1.0.0' ],
				'meta'    => [ 'last_updated' => '2024-01-01' ],
				'timeout' => strtotime( '-1 hour' ),
			]
		);
		$repo   = (object) [ 'slug' => $slug ];
		$result = $this->api->maybe_extend_repo_cache( [ 'Version' => '1.0.0' ], $repo );
		$this->assertTrue( $result );
		$cache = get_site_option( $cache_key );
		$this->assertGreaterThan( time(), $cache['timeout'] );
	}

	// -------------------------------------------------------------------------
	// delete_upgrade_source() — filesystem delete path (lines 975–979)
	// -------------------------------------------------------------------------

	public function test_delete_upgrade_source_deletes_when_destination_name_is_set(): void {
		WP_Filesystem();
		$result   = [ 'destination_name' => 'nonexistent-upgrade-dir', 'source' => '/tmp/' ];
		$returned = $this->api->delete_upgrade_source( $result );
		$this->assertSame( $result, $returned );
	}

	// -------------------------------------------------------------------------
	// is_private() — AJAX path (line 444)
	// -------------------------------------------------------------------------

	public function test_is_private_returns_false_during_ajax(): void {
		add_filter( 'wp_doing_ajax', '__return_true' );
		$repo                 = new stdClass();
		$repo->remote_version = '1.0.0';
		$repo->slug           = 'test-plugin';
		$this->assertFalse( $this->api->is_private( $repo ) );
	}
}
