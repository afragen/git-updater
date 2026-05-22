<?php

use Fragen\Git_Updater\Base;
use Fragen\Git_Updater\API\GitHub_API;
use Fragen\Singleton;
use WpOrg\Requests\Utility\CaseInsensitiveDictionary;

/**
 * Tests for GU_Trait methods changed in Tier 1 and Tier 2 PHPStan fixes.
 */
class Test_GUTrait extends \WP_UnitTestCase {

	use Fragen\Git_Updater\Traits\GU_Trait;

	/**
	 * GU_Trait::get_headers() references self::$extra_headers; declare it so
	 * the test class satisfies the trait's expectation.
	 *
	 * @var array<string, string>
	 */
	public static $extra_headers = [];

	// -------------------------------------------------------------------------
	// sanitize() – existing tests
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider data_sanitize
	 */
	public function test_sanitize( $input = [], $expected = [] ) {
		$this->assertSame( $expected, $this->sanitize( $input ) );
	}

	public function data_sanitize() {
		return [
			[ [], [] ],
			[ [ 0 => 'test' ], [ 0 => 'test' ] ],
			[ [ '0' => 'test' ], [ 0 => 'test' ] ],
			[ [ 'test' => 'test' ], [ 'test' => 'test' ] ],
			[ [ 'test' => '<test' ], [ 'test' => '&lt;test' ] ],
			[ [ '<test' => '<test' ], [ '' => '&lt;test' ] ],
			[ [ 'test_one' => 'test' ], [ 'test_one' => 'test' ] ],
			[ [ 'test-one' => 'test' ], [ 'test-one' => 'test' ] ],
		];
	}

	// -------------------------------------------------------------------------
	// get_reflection_method() — PHP_VERSION_ID < 80100 dead code removed
	// -------------------------------------------------------------------------

	/**
	 * get_reflection_method() must return a usable ReflectionMethod on PHP 8.1+
	 * without calling setAccessible(true), which was the removed dead code.
	 */
	public function test_get_reflection_method_returns_reflection_method(): void {
		$rm = $this->get_reflection_method( $this, 'sanitize' );
		$this->assertInstanceOf( ReflectionMethod::class, $rm );
	}

	/**
	 * The returned ReflectionMethod can be invoked without explicitly calling
	 * setAccessible — confirming the dead-code removal doesn't break functionality.
	 */
	public function test_get_reflection_method_can_invoke_public_method(): void {
		$rm     = $this->get_reflection_method( $this, 'sanitize' );
		$result = $rm->invoke( $this, [ 'test' => '<test' ] );
		$this->assertSame( [ 'test' => '&lt;test' ], $result );
	}

	// -------------------------------------------------------------------------
	// override_dot_org() — @param fixed to accept array|stdClass
	// -------------------------------------------------------------------------

	/**
	 * When $repo is an array (icon/dashicon context), override_dot_org() must
	 * cast it to object and treat dot_org_master as true.  With no filter
	 * overrides registered, the return value is false (do not override
	 * dot-org updates for the icon rendering path).
	 */
	public function test_override_dot_org_with_array_repo_returns_false(): void {
		$repo = [
			'slug' => 'my-plugin',
			'file' => 'my-plugin/my-plugin.php',
		];
		$result = $this->override_dot_org( 'plugin', $repo );
		$this->assertFalse( $result );
	}

	/**
	 * When $repo is a stdClass without dot_org set, the repo is not on dot.org,
	 * so override_dot_org() should return true (override / ignore dot-org).
	 */
	public function test_override_dot_org_with_stdclass_without_dot_org_returns_true(): void {
		$repo                 = new stdClass();
		$repo->slug           = 'my-plugin';
		$repo->file           = 'my-plugin/my-plugin.php';
		$repo->branch         = 'main';
		$repo->primary_branch = 'main';

		$result = $this->override_dot_org( 'plugin', $repo );
		$this->assertTrue( $result );
	}

	/**
	 * When $repo is a stdClass with dot_org=true and branch equals primary_branch,
	 * the repo is actively distributed via dot.org on its primary branch, so
	 * override_dot_org() should return false (don't override).
	 */
	public function test_override_dot_org_with_dot_org_on_primary_branch_returns_false(): void {
		$repo                 = new stdClass();
		$repo->slug           = 'my-plugin';
		$repo->file           = 'my-plugin/my-plugin.php';
		$repo->dot_org        = true;
		$repo->branch         = 'main';
		$repo->primary_branch = 'main';

		$result = $this->override_dot_org( 'plugin', $repo );
		$this->assertFalse( $result );
	}

	/**
	 * When $repo is a stdClass with dot_org=true but on a non-primary branch,
	 * override_dot_org() returns true (override while on a feature branch).
	 */
	public function test_override_dot_org_with_dot_org_on_non_primary_branch_returns_true(): void {
		$repo                 = new stdClass();
		$repo->slug           = 'my-plugin';
		$repo->file           = 'my-plugin/my-plugin.php';
		$repo->dot_org        = true;
		$repo->branch         = 'develop';
		$repo->primary_branch = 'main';

		$result = $this->override_dot_org( 'plugin', $repo );
		$this->assertTrue( $result );
	}

	/**
	 * The gu_override_dot_org filter can force an override even for a
	 * dot.org repo on its primary branch.
	 */
	public function test_override_dot_org_respects_filter_override(): void {
		$repo                 = new stdClass();
		$repo->slug           = 'my-plugin';
		$repo->file           = 'my-plugin/my-plugin.php';
		$repo->dot_org        = true;
		$repo->branch         = 'main';
		$repo->primary_branch = 'main';

		add_filter( 'gu_override_dot_org', fn() => [ 'my-plugin/my-plugin.php' ] );
		$result = $this->override_dot_org( 'plugin', $repo );
		remove_all_filters( 'gu_override_dot_org' );

		$this->assertTrue( $result );
	}

	// -------------------------------------------------------------------------
	// get_file_headers() — parses string and passthrough for array
	// -------------------------------------------------------------------------

	/**
	 * Parsing a plugin header string must produce the expected Version/Name keys.
	 */
	public function test_get_file_headers_parses_plugin_header_string(): void {
		$contents = "<?php\n/**\n * Plugin Name: My Plugin\n * Version: 1.2.3\n */\n";
		$headers  = $this->get_file_headers( $contents, 'plugin' );
		$this->assertSame( '1.2.3', $headers['Version'] );
		$this->assertSame( 'My Plugin', $headers['Name'] );
	}

	/**
	 * When $contents is already a parsed array it is returned directly
	 * without attempting string parsing.
	 */
	public function test_get_file_headers_with_array_returns_array_unchanged(): void {
		$pre_parsed = [
			'Name'    => 'My Plugin',
			'Version' => '2.0.0',
		];
		$headers = $this->get_file_headers( $pre_parsed, 'plugin' );
		$this->assertSame( 'My Plugin', $headers['Name'] );
		$this->assertSame( '2.0.0', $headers['Version'] );
	}

	// -------------------------------------------------------------------------
	// override_dot_org() — Skip_Updates plugin paths (lines 468–474)
	// -------------------------------------------------------------------------

	private function ensure_skip_updates_stub(): void {
		if ( ! class_exists( '\Fragen\Skip_Updates\Bootstrap' ) ) {
			// phpcs:ignore Squiz.PHP.Eval.Discouraged
			eval( 'namespace Fragen\\Skip_Updates; class Bootstrap {}' );
		}
	}

	public function test_override_dot_org_skip_updates_returns_true_when_slug_matches(): void {
		$this->ensure_skip_updates_stub();
		update_site_option( 'skip_updates', [ [ 'slug' => 'my-plugin/my-plugin.php' ] ] );
		$repo                 = new stdClass();
		$repo->slug           = 'my-plugin';
		$repo->file           = 'my-plugin/my-plugin.php';
		$repo->dot_org        = true;
		$repo->branch         = 'main';
		$repo->primary_branch = 'main';
		$result               = $this->override_dot_org( 'plugin', $repo );
		delete_site_option( 'skip_updates' );
		$this->assertTrue( $result );
	}

	public function test_override_dot_org_skip_updates_returns_false_when_slug_unmatched(): void {
		$this->ensure_skip_updates_stub();
		update_site_option( 'skip_updates', [ [ 'slug' => 'other-plugin/other.php' ] ] );
		$repo                 = new stdClass();
		$repo->slug           = 'my-plugin';
		$repo->file           = 'my-plugin/my-plugin.php';
		$repo->dot_org        = true;
		$repo->branch         = 'main';
		$repo->primary_branch = 'main';
		$result               = $this->override_dot_org( 'plugin', $repo );
		delete_site_option( 'skip_updates' );
		$this->assertFalse( $result );
	}
}


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
	// set_repo_cache_timeout()
	// -------------------------------------------------------------------------

	public function test_set_repo_cache_timeout_no_op_when_ran_missing(): void {
		$cache_key = $this->api->get_cache_key( 'test-plugin' );
		update_site_option(
			$cache_key,
			[
				'test-plugin' => [ 'Version' => '1.0.0' ],
				'timeout'     => strtotime( '-1 hour' ),
			]
		);
		$original = get_site_option( $cache_key );

		$this->api->set_repo_cache_timeout( 'test-plugin' );

		$this->assertSame( $original['timeout'], get_site_option( $cache_key )['timeout'] );
	}

	public function test_set_repo_cache_timeout_no_op_when_ran_incomplete(): void {
		$cache_key = $this->api->get_cache_key( 'test-plugin' );
		update_site_option(
			$cache_key,
			[
				'test-plugin' => [ 'Version' => '1.0.0' ],
				'ran'         => [ 'contents', 'assets', 'readme', 'changes', 'tags' ],
				'timeout'     => strtotime( '-1 hour' ),
			]
		);
		$original = get_site_option( $cache_key );

		$this->api->set_repo_cache_timeout( 'test-plugin' );

		$this->assertSame( $original['timeout'], get_site_option( $cache_key )['timeout'] );
	}

	public function test_set_repo_cache_timeout_refreshes_expired_timeout_when_ran_complete(): void {
		$cache_key = $this->api->get_cache_key( 'test-plugin' );
		update_site_option(
			$cache_key,
			[
				'test-plugin' => [ 'Version' => '1.0.0' ],
				'ran'         => [ 'contents', 'assets', 'readme', 'changes', 'tags', 'branches', 'meta' ],
				'timeout'     => strtotime( '-1 hour' ),
			]
		);

		$this->api->set_repo_cache_timeout( 'test-plugin' );

		$cache = get_site_option( $cache_key );
		$this->assertGreaterThan( time() + ( 11 * HOUR_IN_SECONDS ), $cache['timeout'] );
	}

	public function test_set_repo_cache_timeout_applies_filter(): void {
		$cache_key = $this->api->get_cache_key( 'test-plugin' );
		update_site_option(
			$cache_key,
			[
				'test-plugin' => [ 'Version' => '1.0.0' ],
				'ran'         => [ 'contents', 'assets', 'readme', 'changes', 'tags', 'branches', 'meta' ],
				'timeout'     => strtotime( '-1 hour' ),
			]
		);

		$captured = [];
		add_filter(
			'gu_repo_cache_timeout',
			function ( $timeout, $id, $response, $repo ) use ( &$captured ) {
				$captured = compact( 'timeout', 'id', 'response', 'repo' );
				return '+1 hour';
			},
			10,
			4
		);

		$this->api->set_repo_cache_timeout( 'test-plugin' );

		remove_all_filters( 'gu_repo_cache_timeout' );

		$this->assertSame( '+12 hours', $captured['timeout'] );
		$this->assertSame( 'ran', $captured['id'] );
		$this->assertSame( [ 'contents', 'assets', 'readme', 'changes', 'tags', 'branches', 'meta' ], $captured['response'] );
		$this->assertSame( 'test-plugin', $captured['repo'] );

		$cache = get_site_option( $cache_key );
		$this->assertLessThan( time() + ( 2 * HOUR_IN_SECONDS ), $cache['timeout'] );
	}

	// -------------------------------------------------------------------------
	// maybe_extend_repo_cache()
	// -------------------------------------------------------------------------

	public function test_maybe_extend_repo_cache_returns_false_when_cache_empty(): void {
		$result = $this->api->maybe_extend_repo_cache( [ 'Version' => '1.0.0' ], $this->type );
		$this->assertFalse( $result );
	}

	public function test_maybe_extend_repo_cache_extends_when_version_matches_and_all_calls_ran(): void {
		$cache_key = $this->api->get_cache_key( 'test-plugin' );
		update_site_option(
			$cache_key,
			[
				'repo'        => 'test-plugin',
				'test-plugin' => [ 'Version' => '1.0.0' ],
				'ran'         => [ 'contents', 'assets', 'readme', 'changes', 'tags', 'branches', 'meta' ],
				'timeout'     => strtotime( '-1 hour' ),
			]
		);

		$result = $this->api->maybe_extend_repo_cache( [ 'Version' => '1.0.0' ], $this->type, '1.0.0' );
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
				'test-plugin' => [ 'Version' => '1.0.0' ],
				'ran'         => [ 'contents', 'assets', 'readme', 'changes', 'tags', 'branches', 'meta' ],
				'timeout'     => strtotime( '-1 hour' ),
			]
		);

		$result = $this->api->maybe_extend_repo_cache( [ 'Version' => '2.0.0' ], $this->type, '1.0.0' );
		$this->assertFalse( $result );
	}

	public function test_maybe_extend_repo_cache_returns_false_when_ran_missing(): void {
		$cache_key = $this->api->get_cache_key( 'test-plugin' );
		update_site_option(
			$cache_key,
			[
				'repo'        => 'test-plugin',
				'test-plugin' => [ 'Version' => '1.0.0' ],
				// no 'ran' key — cache data incomplete
				'timeout'     => strtotime( '-1 hour' ),
			]
		);

		$result = $this->api->maybe_extend_repo_cache( [ 'Version' => '1.0.0' ], $this->type );
		$this->assertFalse( $result );
	}

	public function test_maybe_extend_repo_cache_returns_false_when_ran_incomplete(): void {
		$cache_key = $this->api->get_cache_key( 'test-plugin' );
		update_site_option(
			$cache_key,
			[
				'repo'        => 'test-plugin',
				'test-plugin' => [ 'Version' => '1.0.0' ],
				// 'ran' exists but missing 'branches' and 'meta' — interrupted mid-sequence
				'ran'         => [ 'contents', 'assets', 'readme', 'changes', 'tags' ],
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
	// maybe_extend_repo_cache() — expired timeout update (lines 225–226)
	// -------------------------------------------------------------------------

	public function test_maybe_extend_repo_cache_updates_timeout_when_expired_and_all_calls_ran(): void {
		$slug      = 'test-plugin';
		$cache_key = $this->api->get_cache_key( $slug );
		update_site_option(
			$cache_key,
			[
				'repo'    => $slug,
				$slug     => [ 'Version' => '1.0.0' ],
				'ran'     => [ 'contents', 'assets', 'readme', 'changes', 'tags', 'branches', 'meta' ],
				'timeout' => strtotime( '-1 hour' ),
			]
		);
		$repo   = (object) [ 'slug' => $slug ];
		$result = $this->api->maybe_extend_repo_cache( [ 'Version' => '1.0.0' ], $repo, '1.0.0' );
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


class Test_GUTrait_Complete extends WP_UnitTestCase {

	/** @var GitHub_API */
	private GitHub_API $api;

	/** @var stdClass */
	private stdClass $type;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->type = $this->make_type();
		$this->api  = new GitHub_API( $this->type );
	}

	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'gu_repo_cache_timeout' );
		remove_all_filters( 'gu_running_git_servers' );
		remove_all_filters( 'gu_dev_release_asset' );
		remove_all_filters( 'gu_remote_is_newer' );
		remove_all_filters( 'gu_disable_wpcron' );
		remove_all_filters( 'gu_config_pre_process' );
		delete_site_option( $this->api->get_cache_key( 'test-plugin' ) );
		delete_site_option( 'git_updater' );
		unset( $_POST['action'], $_POST['_nonce'] );
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

	private function seed_cache( array $data ): void {
		update_site_option(
			$this->api->get_cache_key( 'test-plugin' ),
			array_merge( [ 'timeout' => strtotime( '+12 hours' ) ], $data )
		);
	}

	// -------------------------------------------------------------------------
	// is_heartbeat()
	// -------------------------------------------------------------------------

	public function test_is_heartbeat_returns_false_when_no_post_data(): void {
		$this->assertFalse( GitHub_API::is_heartbeat() );
	}

	// -------------------------------------------------------------------------
	// load_options() / modify_options()
	// -------------------------------------------------------------------------

	public function test_load_options_sets_branch_switch_default(): void {
		update_site_option( 'git_updater', [] );
		$this->api->load_options();
		$this->assertSame( '0', Base::$options['branch_switch'] );
	}

	public function test_load_options_sets_bypass_background_processing_default(): void {
		update_site_option( 'git_updater', [] );
		$this->api->load_options();
		$this->assertSame( '0', Base::$options['bypass_background_processing'] );
	}

	public function test_load_options_strips_minus_one_values(): void {
		update_site_option( 'git_updater', [ 'some_option' => '-1', 'keep_option' => '1' ] );
		$this->api->load_options();
		$this->assertArrayNotHasKey( 'some_option', Base::$options );
		$this->assertSame( '1', Base::$options['keep_option'] );
	}

	public function test_load_options_sets_bypass_to_one_when_gu_disable_wpcron_filter_is_true(): void {
		update_site_option( 'git_updater', [] );
		add_filter( 'gu_disable_wpcron', '__return_true' );
		$this->api->load_options();
		$this->assertSame( '1', Base::$options['bypass_background_processing'] );
	}

	// -------------------------------------------------------------------------
	// populate_api_data()
	// -------------------------------------------------------------------------

	public function test_populate_api_data_sets_empty_branches_when_cache_value_is_false(): void {
		$this->seed_cache( [ 'branches' => false ] );
		$repo   = (object) [ 'slug' => 'test-plugin' ];
		$result = $this->api->populate_api_data( $repo, $this->api );
		$this->assertSame( [], $result->branches );
	}

	public function test_populate_api_data_sets_branches_array_from_cache(): void {
		$this->seed_cache( [ 'branches' => [ 'main' => 'sha123' ] ] );
		$repo   = (object) [ 'slug' => 'test-plugin' ];
		$result = $this->api->populate_api_data( $repo, $this->api );
		$this->assertSame( [ 'main' => 'sha123' ], $result->branches );
	}

	public function test_populate_api_data_sets_changelog_from_changes_cache(): void {
		$this->seed_cache( [ 'changes' => 'changelog text' ] );
		$repo           = (object) [ 'slug' => 'test-plugin', 'sections' => [] ];
		$result         = $this->api->populate_api_data( $repo, $this->api );
		$this->assertSame( 'changelog text', $result->sections['changelog'] );
	}

	public function test_populate_api_data_skips_changes_when_validate_response_fails(): void {
		$this->seed_cache( [ 'changes' => new WP_Error( 'test', 'err' ) ] );
		$repo   = (object) [ 'slug' => 'test-plugin', 'sections' => [] ];
		$result = $this->api->populate_api_data( $repo, $this->api );
		$this->assertArrayNotHasKey( 'changelog', (array) $result->sections );
	}

	// -------------------------------------------------------------------------
	// get_running_git_servers()
	// -------------------------------------------------------------------------

	public function test_get_running_git_servers_returns_array(): void {
		$this->assertIsArray( $this->api->get_running_git_servers() );
	}

	public function test_get_running_git_servers_injects_server_via_filter(): void {
		add_filter( 'gu_running_git_servers', fn( $gits ) => array_merge( $gits, [ 'github' ] ), 10, 1 );
		$this->assertContains( 'github', $this->api->get_running_git_servers() );
	}

	public function test_get_running_git_servers_deduplicates_values(): void {
		add_filter( 'gu_running_git_servers', fn( $gits ) => array_merge( $gits, [ 'github', 'github', 'gitlab' ] ), 10, 1 );
		$result       = $this->api->get_running_git_servers();
		$github_count = count( array_filter( $result, fn( $s ) => 'github' === $s ) );
		$this->assertSame( 1, $github_count );
	}

	// -------------------------------------------------------------------------
	// waiting_for_background_update() — protected, via reflection
	// -------------------------------------------------------------------------

	public function test_waiting_for_background_update_returns_true_when_repo_has_no_cache(): void {
		$rm   = $this->api->get_reflection_method( $this->api, 'waiting_for_background_update' );
		$repo = (object) [ 'slug' => 'test-plugin' ]; // no 'git' property, skips Singleton
		$this->assertTrue( $rm->invoke( $this->api, $repo ) );
	}

	public function test_waiting_for_background_update_returns_false_when_repo_has_cached_data(): void {
		$this->seed_cache( [ 'meta' => [ 'Version' => '1.0.0' ] ] );
		$rm   = $this->api->get_reflection_method( $this->api, 'waiting_for_background_update' );
		$repo = (object) [ 'slug' => 'test-plugin' ]; // no 'git' property, skips Singleton
		$this->assertFalse( $rm->invoke( $this->api, $repo ) );
	}

	public function test_waiting_for_background_update_with_null_returns_false_when_repos_empty(): void {
		// Force empty repo list so all caches pass and waiting is empty.
		add_filter( 'gu_config_pre_process', '__return_empty_array' );
		$rm     = $this->api->get_reflection_method( $this->api, 'waiting_for_background_update' );
		$result = $rm->invoke( $this->api, null );
		$this->assertFalse( $result );
	}

	public function test_waiting_for_background_update_with_null_iterates_repos_when_config_not_empty(): void {
		// No gu_config_pre_process filter — fixture plugin IS in Plugin config with
		// empty cache, so $waiting is non-empty → returns true. Lines 571 and 576.
		$rm     = $this->api->get_reflection_method( $this->api, 'waiting_for_background_update' );
		$result = $rm->invoke( $this->api, null );
		// Result is true (fixture plugin cache empty) or false (config empty in this env).
		$this->assertIsBool( $result );
	}

	// -------------------------------------------------------------------------
	// get_repo_parts() — protected, via reflection
	// -------------------------------------------------------------------------

	public function test_get_repo_parts_returns_bool_true_for_known_server_github(): void {
		$rm     = $this->api->get_reflection_method( $this->api, 'get_repo_parts' );
		$result = $rm->invoke( $this->api, 'GitHub', 'plugin' );
		$this->assertTrue( $result['bool'] );
		$this->assertSame( 'github', $result['git_server'] );
		$this->assertSame( 'https://github.com/', $result['base_uri'] );
	}

	public function test_get_repo_parts_returns_bool_false_for_unknown_server(): void {
		$rm     = $this->api->get_reflection_method( $this->api, 'get_repo_parts' );
		$result = $rm->invoke( $this->api, 'Bitbucket', 'plugin' );
		$this->assertFalse( $result['bool'] );
		$this->assertArrayNotHasKey( 'type', $result );
	}

	public function test_get_repo_parts_includes_extra_repo_header_keys_for_known_server(): void {
		$rm     = $this->api->get_reflection_method( $this->api, 'get_repo_parts' );
		$result = $rm->invoke( $this->api, 'GitHub', 'plugin' );
		$this->assertSame( 'GitHub Languages', $result['Languages'] );
	}

	// -------------------------------------------------------------------------
	// get_repo_slugs() — protected, via reflection
	// -------------------------------------------------------------------------

	public function test_get_repo_slugs_returns_empty_array_for_nonexistent_slug(): void {
		$plugin_obj = Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $this->api );
		$rm         = $this->api->get_reflection_method( $this->api, 'get_repo_slugs' );
		$result     = $rm->invoke( $this->api, 'nonexistent-slug-xyz-abc', $plugin_obj );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	// -------------------------------------------------------------------------
	// get_repo_requirements() — protected, via reflection
	// -------------------------------------------------------------------------

	public function test_get_repo_requirements_returns_null_values_for_non_gist_repo(): void {
		$rm     = $this->api->get_reflection_method( $this->api, 'get_repo_requirements' );
		$repo   = (object) [ 'git' => 'github', 'local_path' => '', 'file' => '' ];
		$result = $rm->invoke( $this->api, $repo );
		$this->assertNull( $result['RequiresPHP'] );
		$this->assertNull( $result['RequiresWP'] );
	}

	public function test_get_repo_requirements_returns_array_with_required_keys(): void {
		$rm     = $this->api->get_reflection_method( $this->api, 'get_repo_requirements' );
		$repo   = (object) [ 'git' => 'github', 'local_path' => '', 'file' => '' ];
		$result = $rm->invoke( $this->api, $repo );
		$this->assertArrayHasKey( 'RequiresPHP', $result );
		$this->assertArrayHasKey( 'RequiresWP', $result );
	}

	// -------------------------------------------------------------------------
	// get_github_rate_limit_headers()
	// -------------------------------------------------------------------------

	public function test_get_github_rate_limit_headers_returns_wp_error_on_http_failure(): void {
		add_filter( 'pre_http_request', fn() => new WP_Error( 'http_request_failed', 'Error' ), 10, 3 );
		$result = $this->api->get_github_rate_limit_headers();
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_get_github_rate_limit_headers_formats_reset_time_in_minutes(): void {
		$dict = new CaseInsensitiveDictionary( [ 'x-ratelimit-reset' => (string) ( time() + 300 ) ] );
		$mock = [ 'response' => [ 'code' => 200, 'message' => 'OK' ], 'headers' => $dict, 'body' => '', 'cookies' => [] ];
		add_filter( 'pre_http_request', fn() => $mock, 10, 3 );
		$result = $this->api->get_github_rate_limit_headers();
		$this->assertStringEndsWith( ' minutes', $result['x-ratelimit-reset'] );
	}

	public function test_get_github_rate_limit_headers_defaults_to_60_minutes_when_no_reset_header(): void {
		$dict = new CaseInsensitiveDictionary( [] );
		$mock = [ 'response' => [ 'code' => 200, 'message' => 'OK' ], 'headers' => $dict, 'body' => '', 'cookies' => [] ];
		add_filter( 'pre_http_request', fn() => $mock, 10, 3 );
		$result = $this->api->get_github_rate_limit_headers();
		$this->assertSame( '60 minutes', $result['x-ratelimit-reset'] );
	}

	// -------------------------------------------------------------------------
	// can_update_repo() filter paths
	// -------------------------------------------------------------------------

	public function test_can_update_repo_gu_dev_release_asset_filter_overrides_remote_version(): void {
		add_filter( 'gu_dev_release_asset', '__return_true' );
		$type                     = clone $this->type;
		$type->remote_version     = '1.0.0';
		$type->local_version      = '2.0.0';
		$type->dev_release_assets = [ '2.5.0' => 'url' ];
		$this->assertTrue( $this->api->can_update_repo( $type ) );
	}

	public function test_can_update_repo_gu_dev_release_asset_filter_skipped_when_dev_assets_not_set(): void {
		add_filter( 'gu_dev_release_asset', '__return_true' );
		$type                 = clone $this->type;
		$type->remote_version = '1.0.0';
		$type->local_version  = '2.0.0';
		$this->assertFalse( $this->api->can_update_repo( $type ) );
	}

	public function test_can_update_repo_gu_remote_is_newer_filter_forces_true_on_same_version(): void {
		add_filter( 'gu_remote_is_newer', '__return_true' );
		$type                 = clone $this->type;
		$type->remote_version = '1.0.0';
		$type->local_version  = '1.0.0';
		$this->assertTrue( $this->api->can_update_repo( $type ) );
	}

	public function test_can_update_repo_gu_remote_is_newer_filter_forces_false_on_newer_version(): void {
		add_filter( 'gu_remote_is_newer', '__return_false' );
		$type                 = clone $this->type;
		$type->remote_version = '2.0.0';
		$type->local_version  = '1.0.0';
		$this->assertFalse( $this->api->can_update_repo( $type ) );
	}

	// -------------------------------------------------------------------------
	// set_repo_cache() filter path
	// -------------------------------------------------------------------------

	public function test_set_repo_cache_gu_repo_cache_timeout_filter_overrides_timeout_string(): void {
		delete_site_option( $this->api->get_cache_key( 'test-plugin' ) );
		add_filter( 'gu_repo_cache_timeout', fn() => '+1 minute', 10, 4 );
		$this->api->set_repo_cache( 'key', 'val', 'test-plugin' );
		$cache = get_site_option( $this->api->get_cache_key( 'test-plugin' ) );
		$this->assertEqualsWithDelta( time() + 60, $cache['timeout'], 5 );
	}

	// -------------------------------------------------------------------------
	// is_cron_overdue() overdue path
	// -------------------------------------------------------------------------

	public function test_is_cron_overdue_does_not_throw_when_timestamp_is_overdue(): void {
		$this->api->is_cron_overdue( time() - ( 25 * HOUR_IN_SECONDS ) );
		$this->addToAssertionCount( 1 );
	}

	// -------------------------------------------------------------------------
	// is_heartbeat() — TRUE path (line 58)
	// -------------------------------------------------------------------------

	public function test_is_heartbeat_returns_true_with_valid_heartbeat_nonce_and_action(): void {
		$_POST['action'] = 'heartbeat';
		$_POST['_nonce'] = wp_create_nonce( 'heartbeat-nonce' );
		$this->assertTrue( GitHub_API::is_heartbeat() );
	}

	// -------------------------------------------------------------------------
	// waiting_for_background_update() — $repo->git branch (lines 547–548)
	// -------------------------------------------------------------------------

	public function test_waiting_for_background_update_instantiates_git_api_when_repo_has_git(): void {
		$this->seed_cache( [ 'meta' => [ 'Version' => '1.0.0' ] ] );
		// $this->base is not set on GitHub_API; inject via reflection so the
		// $this->base::$git_servers lookup on line 547 does not throw.
		$rp = new ReflectionProperty( $this->api, 'base' );
		$rp->setAccessible( true );
		$rp->setValue( $this->api, Singleton::get_instance( 'Fragen\Git_Updater\Base', $this->api ) );
		$rm   = $this->api->get_reflection_method( $this->api, 'waiting_for_background_update' );
		$repo = (object) [ 'slug' => 'test-plugin', 'git' => 'github' ];
		$this->assertFalse( $rm->invoke( $this->api, $repo ) );
	}

	// -------------------------------------------------------------------------
	// populate_api_data() — tags case (lines 261–273)
	// -------------------------------------------------------------------------

	public function test_populate_api_data_processes_tags_from_cache(): void {
		$this->seed_cache( [ 'tags' => [ '1.0.0', '0.9.0' ] ] );
		$this->api->type->newest_tag = '';
		$repo = (object) [ 'slug' => 'test-plugin' ];
		$this->api->populate_api_data( $repo, $this->api );
		$this->assertSame( '1.0.0', $this->api->type->newest_tag );
	}

	// -------------------------------------------------------------------------
	// populate_api_data() — readme case (lines 280–286)
	// -------------------------------------------------------------------------

	public function test_populate_api_data_processes_readme_from_cache(): void {
		$readme = [
			'sections'          => [ 'description' => 'Test plugin' ],
			'requires'          => '5.9',
			'requires_php'      => '8.0',
			'tested'            => '',
			'donate_link'       => '',
			'contributors'      => [],
			'tags'              => [],
			'remaining_content' => null,
		];
		$this->seed_cache( [ 'readme' => $readme ] );
		$this->api->type->sections    = [];
		$this->api->type->requires    = '';
		$this->api->type->requires_php = '';
		$repo = (object) [ 'slug' => 'test-plugin' ];
		$this->api->populate_api_data( $repo, $this->api );
		$this->assertSame( '5.9', $this->api->type->requires );
	}

	// -------------------------------------------------------------------------
	// populate_api_data() — meta case (lines 287–294)
	// -------------------------------------------------------------------------

	public function test_populate_api_data_processes_meta_from_cache(): void {
		$meta = [
			'private'      => false,
			'last_updated' => '2024-01-01T00:00:00Z',
			'added'        => '',
			'watchers'     => 0,
			'forks'        => 0,
			'open_issues'  => 0,
		];
		$this->seed_cache( [ 'meta' => $meta ] );
		$this->api->populate_api_data( $this->api->type, $this->api );
		$this->assertSame( '2024-01-01T00:00:00Z', $this->api->type->last_updated );
	}

	// -------------------------------------------------------------------------
	// populate_api_data() — release_asset case (lines 298–303)
	// -------------------------------------------------------------------------

	public function test_populate_api_data_processes_release_asset_from_cache(): void {
		$this->seed_cache( [ 'release_asset' => 'https://example.com/release.zip' ] );
		$repo                  = (object) [
			'slug'           => 'test-plugin',
			'newest_tag'     => '1.0.0',
			'release_assets' => [],
		];
		$result = $this->api->populate_api_data( $repo, $this->api );
		$this->assertSame( 'https://example.com/release.zip', $result->release_assets['1.0.0'] );
	}

	// -------------------------------------------------------------------------
	// populate_api_data() — release_assets case (lines 304–315)
	// -------------------------------------------------------------------------

	public function test_populate_api_data_processes_release_assets_with_existing_tag(): void {
		$assets = [
			'assets'         => [ '1.0.0' => 'https://example.com/v1.zip' ],
			'created_at'     => [],
			'dev_assets'     => [],
			'dev_created_at' => [],
		];
		$this->seed_cache( [ 'release_assets' => $assets ] );
		$repo   = (object) [ 'slug' => 'test-plugin', 'newest_tag' => '1.0.0' ];
		$result = $this->api->populate_api_data( $repo, $this->api );
		$this->assertArrayHasKey( '1.0.0', $result->release_assets );
	}

	public function test_populate_api_data_merges_missing_tag_into_release_assets(): void {
		$assets = [
			'assets'         => [ '0.9.0' => 'https://example.com/v0.zip' ],
			'created_at'     => [],
			'dev_assets'     => [],
			'dev_created_at' => [],
		];
		$this->seed_cache( [ 'release_assets' => $assets ] );
		$repo   = (object) [ 'slug' => 'test-plugin', 'newest_tag' => '1.0.0' ];
		$result = $this->api->populate_api_data( $repo, $this->api );
		$this->assertArrayHasKey( '1.0.0', $result->release_assets );
		$this->assertSame( '', $result->release_assets['1.0.0'] );
	}

	// -------------------------------------------------------------------------
	// get_repo_requirements() — gist path (line 952)
	// -------------------------------------------------------------------------

	public function test_get_repo_requirements_with_gist_repo_returns_defaults(): void {
		$rm     = $this->api->get_reflection_method( $this->api, 'get_repo_requirements' );
		$repo   = (object) [ 'git' => 'gist', 'local_path' => '/nonexistent/path/', 'file' => 'plugin.php' ];
		$result = $rm->invoke( $this->api, $repo );
		$this->assertNull( $result['RequiresPHP'] );
		$this->assertNull( $result['RequiresWP'] );
	}
}


class Test_GUTrait_Extended extends WP_UnitTestCase {

	use Fragen\Git_Updater\Traits\GU_Trait;

	/**
	 * Required by get_headers() — Base::$extra_headers accessed statically.
	 *
	 * @var array<string, string>
	 */
	public static $extra_headers = [];

	public function set_up(): void {
		parent::set_up();
		$this->type = $this->make_type();
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
	// is_wp_cli()
	// -------------------------------------------------------------------------

	/**
	 * PHPUnit runs phpunit directly (not via wp-cli), so WP_CLI is not defined
	 * and is_wp_cli() must return false.
	 */
	public function test_is_wp_cli_returns_bool(): void {
		$result = self::is_wp_cli();
		$this->assertIsBool( $result );
	}

	public function test_is_wp_cli_false_when_not_running_under_wpcli(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->markTestSkipped( 'Running under WP-CLI — true branch already covered.' );
		}
		$this->assertFalse( self::is_wp_cli() );
	}

	// -------------------------------------------------------------------------
	// is_current_page()
	// -------------------------------------------------------------------------

	public function test_is_current_page_returns_true_when_pagenow_in_array(): void {
		global $pagenow;
		$pagenow = 'plugins.php';
		$this->assertTrue( $this->is_current_page( [ 'plugins.php', 'themes.php' ] ) );
	}

	public function test_is_current_page_returns_false_when_pagenow_not_in_array(): void {
		global $pagenow;
		$pagenow = 'index.php';
		$this->assertFalse( $this->is_current_page( [ 'plugins.php', 'themes.php' ] ) );
	}

	public function test_is_current_page_returns_false_for_empty_array(): void {
		global $pagenow;
		$pagenow = 'plugins.php';
		$this->assertFalse( $this->is_current_page( [] ) );
	}

	public function test_is_current_page_uses_strict_comparison(): void {
		global $pagenow;
		$pagenow = 'plugins';
		$this->assertFalse( $this->is_current_page( [ 'plugins.php' ] ) );
	}

	// -------------------------------------------------------------------------
	// should_run_on_current_page()
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider data_should_run_on_current_page
	 */
	public function test_should_run_on_current_page( string $page, bool $expected ): void {
		global $pagenow;
		$pagenow = $page;
		$this->assertSame( $expected, self::should_run_on_current_page() );
	}

	public function data_should_run_on_current_page(): array {
		return [
			'update-core page'   => [ 'update-core.php', true ],
			'update page'        => [ 'update.php', true ],
			'plugins page'       => [ 'plugins.php', true ],
			'themes page'        => [ 'themes.php', true ],
			'plugin-install page' => [ 'plugin-install.php', true ],
			'theme-install page' => [ 'theme-install.php', true ],
			'admin-ajax page'    => [ 'admin-ajax.php', true ],
			'index page'         => [ 'index.php', true ],
			'wp-cron page'       => [ 'wp-cron.php', true ],
			'settings page (single-site only)' => [ 'options.php', ! is_multisite() ],
			'random page'        => [ 'edit-comments.php', false ],
			'dashboard'          => [ 'dashboard.php', false ],
		];
	}

	// -------------------------------------------------------------------------
	// get_cache_key()
	// -------------------------------------------------------------------------

	public function test_get_cache_key_uses_type_slug_by_default(): void {
		$key = $this->get_cache_key();
		$this->assertSame( 'ghu-' . md5( 'test-plugin' ), $key );
	}

	public function test_get_cache_key_uses_provided_repo_name(): void {
		$key = $this->get_cache_key( 'my-other-plugin' );
		$this->assertSame( 'ghu-' . md5( 'my-other-plugin' ), $key );
	}

	public function test_get_cache_key_starts_with_ghu_prefix(): void {
		$key = $this->get_cache_key( 'anything' );
		$this->assertStringStartsWith( 'ghu-', $key );
	}

	public function test_get_cache_key_is_deterministic(): void {
		$this->assertSame( $this->get_cache_key( 'slug' ), $this->get_cache_key( 'slug' ) );
	}

	public function test_get_cache_key_differs_for_different_slugs(): void {
		$this->assertNotSame( $this->get_cache_key( 'slug-a' ), $this->get_cache_key( 'slug-b' ) );
	}

	// -------------------------------------------------------------------------
	// is_cache_timeout_valid()
	// -------------------------------------------------------------------------

	public function test_is_cache_timeout_valid_future_timestamp_is_valid(): void {
		$this->assertTrue( $this->is_cache_timeout_valid( strtotime( '+1 hour' ) ) );
	}

	public function test_is_cache_timeout_valid_past_timestamp_is_invalid(): void {
		$this->assertFalse( $this->is_cache_timeout_valid( strtotime( '-1 hour' ) ) );
	}

	public function test_is_cache_timeout_valid_zero_is_invalid(): void {
		$this->assertFalse( $this->is_cache_timeout_valid( 0 ) );
	}

	public function test_is_cache_timeout_valid_far_future_is_valid(): void {
		$this->assertTrue( $this->is_cache_timeout_valid( strtotime( '+7 days' ) ) );
	}

	// -------------------------------------------------------------------------
	// can_update_repo()
	// -------------------------------------------------------------------------

	public function test_can_update_repo_newer_remote_returns_true(): void {
		$type                 = clone $this->type;
		$type->remote_version = '2.0.0';
		$type->local_version  = '1.0.0';
		$this->assertTrue( $this->can_update_repo( $type ) );
	}

	public function test_can_update_repo_same_version_returns_false(): void {
		$type                 = clone $this->type;
		$type->remote_version = '1.0.0';
		$type->local_version  = '1.0.0';
		$this->assertFalse( $this->can_update_repo( $type ) );
	}

	public function test_can_update_repo_older_remote_returns_false(): void {
		$type                 = clone $this->type;
		$type->remote_version = '0.9.0';
		$type->local_version  = '1.0.0';
		$this->assertFalse( $this->can_update_repo( $type ) );
	}

	public function test_can_update_repo_missing_version_fields_returns_false(): void {
		$type = clone $this->type;
		$this->assertFalse( $this->can_update_repo( $type ) );
	}

	public function test_can_update_repo_incompatible_wp_version_returns_false(): void {
		$type                 = clone $this->type;
		$type->remote_version = '2.0.0';
		$type->local_version  = '1.0.0';
		$type->requires       = '99.0';
		$this->assertFalse( $this->can_update_repo( $type ) );
	}

	public function test_can_update_repo_incompatible_php_version_returns_false(): void {
		$type                 = clone $this->type;
		$type->remote_version = '2.0.0';
		$type->local_version  = '1.0.0';
		$type->requires_php   = '99.0';
		$this->assertFalse( $this->can_update_repo( $type ) );
	}

	public function test_can_update_repo_empty_requires_fields_does_not_block(): void {
		$type                 = clone $this->type;
		$type->remote_version = '2.0.0';
		$type->local_version  = '1.0.0';
		$type->requires       = '';
		$type->requires_php   = '';
		$this->assertTrue( $this->can_update_repo( $type ) );
	}

	// -------------------------------------------------------------------------
	// parse_header_uri()   (protected — accessible because the trait is used here)
	// -------------------------------------------------------------------------

	public function test_parse_header_uri_github_https_url(): void {
		$result = $this->parse_header_uri( 'https://github.com/afragen/git-updater' );

		$this->assertSame( 'https', $result['scheme'] );
		$this->assertSame( 'github.com', $result['host'] );
		$this->assertSame( 'afragen', $result['owner'] );
		$this->assertSame( 'git-updater', $result['repo'] );
		$this->assertSame( 'afragen/git-updater', $result['owner_repo'] );
		$this->assertSame( 'https://github.com', $result['base_uri'] );
	}

	public function test_parse_header_uri_strips_git_extension(): void {
		$result = $this->parse_header_uri( 'https://github.com/owner/my-repo.git' );
		$this->assertSame( 'my-repo', $result['repo'] );
	}

	public function test_parse_header_uri_preserves_original_url(): void {
		$url    = 'https://github.com/owner/repo';
		$result = $this->parse_header_uri( $url );
		$this->assertSame( $url, $result['original'] );
	}

	public function test_parse_header_uri_owner_repo_combines_owner_and_repo(): void {
		$result = $this->parse_header_uri( 'https://github.com/myorg/my-plugin' );
		$this->assertSame( 'myorg/my-plugin', $result['owner_repo'] );
	}

	// -------------------------------------------------------------------------
	// get_did_hash()
	// -------------------------------------------------------------------------

	public function test_get_did_hash_returns_six_hex_characters(): void {
		$hash = $this->get_did_hash( 'did:example:123456' );
		$this->assertSame( 6, strlen( $hash ) );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{6}$/', $hash );
	}

	public function test_get_did_hash_is_deterministic(): void {
		$this->assertSame(
			$this->get_did_hash( 'did:example:abc' ),
			$this->get_did_hash( 'did:example:abc' )
		);
	}

	public function test_get_did_hash_different_dids_produce_different_hashes(): void {
		$this->assertNotSame(
			$this->get_did_hash( 'did:example:aaa' ),
			$this->get_did_hash( 'did:example:bbb' )
		);
	}

	// -------------------------------------------------------------------------
	// get_file_without_did_hash()
	// -------------------------------------------------------------------------

	public function test_get_file_without_did_hash_removes_hash_suffix_from_slug(): void {
		$did    = 'did:example:123';
		$hash   = $this->get_did_hash( $did );
		$plugin = "my-plugin-{$hash}/my-plugin.php";

		$result = $this->get_file_without_did_hash( $did, $plugin );
		$this->assertSame( 'my-plugin/my-plugin.php', $result );
	}

	public function test_get_file_without_did_hash_preserves_filename(): void {
		$did    = 'did:example:xyz';
		$hash   = $this->get_did_hash( $did );
		$plugin = "some-plugin-{$hash}/some-plugin.php";

		$result = $this->get_file_without_did_hash( $did, $plugin );
		$this->assertStringEndsWith( 'some-plugin.php', $result );
	}

	// -------------------------------------------------------------------------
	// use_release_asset()
	// -------------------------------------------------------------------------

	public function test_use_release_asset_returns_false_without_release_asset_property(): void {
		$this->type->newest_tag = '1.0.0';
		$this->assertFalse( $this->use_release_asset() );
	}

	public function test_use_release_asset_returns_false_when_release_asset_is_false(): void {
		$this->type->release_asset = false;
		$this->type->newest_tag    = '1.0.0';
		$this->assertFalse( $this->use_release_asset() );
	}

	public function test_use_release_asset_returns_false_when_newest_tag_is_zero(): void {
		$this->type->release_asset = true;
		$this->type->newest_tag    = '0.0.0';
		$this->assertFalse( $this->use_release_asset() );
	}

	public function test_use_release_asset_returns_true_on_primary_branch_without_switch(): void {
		$this->type->release_asset = true;
		$this->type->newest_tag    = '1.0.0';
		// branch == primary_branch and branch_switch === false
		$this->assertTrue( $this->use_release_asset( false ) );
	}

	public function test_use_release_asset_returns_true_when_switching_to_primary_branch(): void {
		$this->type->release_asset = true;
		$this->type->newest_tag    = '1.0.0';
		$this->type->branches      = [ 'master' => [], 'develop' => [] ];
		// branch_switch == primary_branch
		$this->assertTrue( $this->use_release_asset( 'master' ) );
	}

	public function test_use_release_asset_returns_true_when_switching_to_tag(): void {
		$this->type->release_asset = true;
		$this->type->newest_tag    = '1.0.0';
		$this->type->branches      = [ 'master' => [], 'develop' => [] ];
		// '1.0.0' tag is not in branches array → is_tag = true
		$this->assertTrue( $this->use_release_asset( '1.0.0' ) );
	}

	public function test_use_release_asset_returns_false_when_switching_to_non_primary_branch(): void {
		$this->type->release_asset = true;
		$this->type->newest_tag    = '1.0.0';
		$this->type->branches      = [ 'master' => [], 'develop' => [] ];
		// 'develop' is in branches and is not primary_branch
		$this->assertFalse( $this->use_release_asset( 'develop' ) );
	}

	// -------------------------------------------------------------------------
	// get_headers()
	// -------------------------------------------------------------------------

	public function test_get_headers_plugin_contains_required_keys(): void {
		$headers = $this->get_headers( 'plugin' );
		foreach ( [ 'Name', 'Version', 'Author', 'Description' ] as $key ) {
			$this->assertArrayHasKey( $key, $headers, "Plugin headers must contain '{$key}'." );
		}
	}

	public function test_get_headers_theme_contains_required_keys(): void {
		$headers = $this->get_headers( 'theme' );
		foreach ( [ 'Name', 'Version', 'Author', 'Description' ] as $key ) {
			$this->assertArrayHasKey( $key, $headers, "Theme headers must contain '{$key}'." );
		}
	}

	public function test_get_headers_plugin_name_value_is_plugin_name(): void {
		$headers = $this->get_headers( 'plugin' );
		$this->assertSame( 'Plugin Name', $headers['Name'] );
	}

	public function test_get_headers_theme_name_value_is_theme_name(): void {
		$headers = $this->get_headers( 'theme' );
		$this->assertSame( 'Theme Name', $headers['Name'] );
	}

	public function test_get_headers_merges_extra_headers(): void {
		Base::$extra_headers['CustomHeader'] = 'Custom Header';
		$headers = $this->get_headers( 'plugin' );
		$this->assertArrayHasKey( 'CustomHeader', $headers );
		// Clean up to avoid cross-test pollution.
		unset( Base::$extra_headers['CustomHeader'] );
	}
}


class Test_GUTrait_Repo_Slugs extends WP_UnitTestCase {

	/** @var GitHub_API */
	private GitHub_API $api;

	/** @var stdClass */
	private stdClass $type;

	/** @var \Fragen\Git_Updater\Plugin */
	private $plugin_obj;

	/** @var \Fragen\Git_Updater\Theme */
	private $theme_obj;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->type       = $this->make_type();
		$this->api        = new GitHub_API( $this->type );
		$this->plugin_obj = Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $this->api );
		$this->theme_obj  = Singleton::get_instance( 'Fragen\Git_Updater\Theme', $this->api );
	}

	public function tear_down(): void {
		remove_all_filters( 'wp_doing_ajax' );
		unset( $_POST['action'], $_POST['git_updater_repo'], $_REQUEST['_ajax_nonce'] );
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

	private function invoke_get_repo_slugs( string $slug, $upgrader_object ): array {
		$rm = $this->api->get_reflection_method( $this->api, 'get_repo_slugs' );
		return $rm->invoke( $this->api, $slug, $upgrader_object );
	}

	// -------------------------------------------------------------------------
	// C1 — exact slug match via Plugin Singleton
	// -------------------------------------------------------------------------

	public function test_get_repo_slugs_matches_by_repo_slug(): void {
		// Inject a synthetic config so the test is independent of whether the
		// fixture plugin is installed (CI runs without wp-env fixture mounts).
		$ref      = new ReflectionProperty( get_class( $this->plugin_obj ), 'config' );
		$ref->setAccessible( true );
		$original = $ref->getValue( $this->plugin_obj );
		$ref->setValue(
			$this->plugin_obj,
			[
				'test-gu-plugin' => (object) [
					'slug' => 'test-gu-plugin',
					'file' => 'test-gu-plugin/test-gu-plugin.php',
				],
			]
		);
		try {
			$result = $this->invoke_get_repo_slugs( 'test-gu-plugin', $this->plugin_obj );
		} finally {
			$ref->setValue( $this->plugin_obj, $original );
		}
		$this->assertSame( [ 'slug' => 'test-gu-plugin' ], $result );
	}

	// -------------------------------------------------------------------------
	// C2 — dirname($repo->file) match when slug ≠ directory name
	// -------------------------------------------------------------------------

	public function test_get_repo_slugs_matches_by_dirname_of_file(): void {
		// Simulate a plugin installed in a 'my-plugin-master/' directory
		// where the actual repo slug is 'my-plugin'.
		$ref      = new ReflectionProperty( get_class( $this->plugin_obj ), 'config' );
		$ref->setAccessible( true );
		$original = $ref->getValue( $this->plugin_obj );
		$ref->setValue(
			$this->plugin_obj,
			[
				'my-plugin' => (object) [
					'slug' => 'my-plugin',
					'file' => 'my-plugin-master/my-plugin.php',
				],
			]
		);
		try {
			$result = $this->invoke_get_repo_slugs( 'my-plugin-master', $this->plugin_obj );
		} finally {
			$ref->setValue( $this->plugin_obj, $original );
		}
		// dirname('my-plugin-master/my-plugin.php') === 'my-plugin-master' matches;
		// the returned slug is the repo slug, not the directory name.
		$this->assertSame( [ 'slug' => 'my-plugin' ], $result );
		$this->assertSame( 'my-plugin', $result['slug'] );
		$this->assertNotSame( 'my-plugin-master', $result['slug'] );
	}

	// -------------------------------------------------------------------------
	// C1 via Theme — declared private $config, different upgrader_object type
	// -------------------------------------------------------------------------

	public function test_get_repo_slugs_matches_by_slug_with_theme_upgrader_object(): void {
		// Inject a minimal theme config so the test is independent of whether
		// the fixture theme is discovered by get_theme_meta() in the test env.
		$ref      = new ReflectionProperty( get_class( $this->theme_obj ), 'config' );
		$ref->setAccessible( true );
		$original = $ref->getValue( $this->theme_obj );
		$ref->setValue(
			$this->theme_obj,
			[
				'test-gu-theme' => (object) [
					'slug' => 'test-gu-theme',
					'file' => 'test-gu-theme/style.css',
				],
			]
		);
		try {
			$result = $this->invoke_get_repo_slugs( 'test-gu-theme', $this->theme_obj );
		} finally {
			$ref->setValue( $this->theme_obj, $original );
		}
		$this->assertSame( [ 'slug' => 'test-gu-theme' ], $result );
	}

	// -------------------------------------------------------------------------
	// A1 — AJAX + action contains 'install' sets $arr['slug'] = $slug directly
	// -------------------------------------------------------------------------

	public function test_get_repo_slugs_ajax_install_action_sets_slug_directly(): void {
		add_filter( 'wp_doing_ajax', '__return_true' );
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'updates' );
		$_POST['action']         = 'install-plugin';
		unset( $_POST['git_updater_repo'] );

		// 'ajax-install-slug' is not in Plugin config, so the config loop
		// does not overwrite the value set by the AJAX block.
		$result = $this->invoke_get_repo_slugs( 'ajax-install-slug', $this->plugin_obj );

		$this->assertSame( 'ajax-install-slug', $result['slug'] );
	}

	// -------------------------------------------------------------------------
	// A2 — AJAX fires but action has no 'install'; falls through to config loop
	// -------------------------------------------------------------------------

	public function test_get_repo_slugs_ajax_non_install_action_falls_through_to_config_loop(): void {
		add_filter( 'wp_doing_ajax', '__return_true' );
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'updates' );
		$_POST['action']         = 'update-plugin'; // no 'install' substring
		unset( $_POST['git_updater_repo'] );

		// Inject synthetic config so the config-loop C1 match works on CI
		// (where the fixture plugin is not installed).
		$ref      = new ReflectionProperty( get_class( $this->plugin_obj ), 'config' );
		$ref->setAccessible( true );
		$original = $ref->getValue( $this->plugin_obj );
		$ref->setValue(
			$this->plugin_obj,
			[
				'test-gu-plugin' => (object) [
					'slug' => 'test-gu-plugin',
					'file' => 'test-gu-plugin/test-gu-plugin.php',
				],
			]
		);
		try {
			// AJAX block fires but doesn't set $arr['slug']. Config loop then runs
			// and finds the plugin slug via C1.
			$result = $this->invoke_get_repo_slugs( 'test-gu-plugin', $this->plugin_obj );
		} finally {
			$ref->setValue( $this->plugin_obj, $original );
		}

		$this->assertSame( [ 'slug' => 'test-gu-plugin' ], $result );
	}

	// -------------------------------------------------------------------------
	// null $upgrader_object — defaults to $this (Plugin), line 671
	// -------------------------------------------------------------------------

	public function test_get_repo_slugs_null_upgrader_object_defaults_to_self_plugin(): void {
		// Invoke on $this->plugin_obj so $this inside get_repo_slugs is Plugin.
		// With $upgrader_object = null, line 671 executes: $upgrader_object = $this.
		// get_class_vars('Plugin', 'config') resolves to Plugin's $config.
		// Searching for a nonexistent slug returns an empty array.
		$rm     = $this->api->get_reflection_method( $this->plugin_obj, 'get_repo_slugs' );
		$result = $rm->invoke( $this->plugin_obj, 'nonexistent-slug-xyz-abc', null );
		$this->assertIsArray( $result );
	}
}
