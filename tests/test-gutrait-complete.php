<?php
/**
 * Complete GU_Trait test coverage for methods not covered elsewhere.
 *
 * Covers:
 * - is_heartbeat()
 * - load_options() / modify_options()
 * - populate_api_data()
 * - get_running_git_servers()
 * - waiting_for_background_update()
 * - get_repo_parts()
 * - get_repo_slugs()
 * - get_repo_requirements()
 * - get_github_rate_limit_headers()
 * - can_update_repo() filter paths
 * - set_repo_cache() filter path
 * - is_cron_overdue() overdue path
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\API\GitHub_API;
use Fragen\Git_Updater\Base;
use Fragen\Singleton;
use WpOrg\Requests\Utility\CaseInsensitiveDictionary;

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
}
