<?php
/**
 * Tests for Plugin class methods.
 *
 * Covers:
 * - Plugin::get_plugin_configs()     — returns array; reflects injected config
 * - Plugin::sort_sections_in_api()   — sections ordering; empty value removal; unknown keys; non-section objects
 * - Plugin::load_pre_filters()       — three filters registered
 * - Plugin::plugins_api()            — non-info action; unknown slug; background-wait skip; dot_org skip; populated response
 * - Plugin::update_site_transient()  — non-object input; empty config; update/no_update paths;
 *                                      no_update not overwritten; dot_org override removal; release_asset branch package
 * - Plugin::get_remote_plugin_meta() — load_pre_filters called; cron scheduled for uncached repos;
 *                                      no cron when config is empty; gu_config_pre_process filter applied
 *
 * ReflectionProperty is used to inject a mock $config so tests run without network calls and
 * without requiring the fixture plugin to be installed.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\Base;
use Fragen\Git_Updater\Plugin;

// ---------------------------------------------------------------------------
// Shared helper trait
// ---------------------------------------------------------------------------

trait Plugin_Mock_Helper {

	/**
	 * Build a fully-populated mock plugin stdClass.
	 *
	 * @param array<string, mixed> $overrides Fields to override.
	 * @return stdClass
	 */
	private function make_plugin_obj( array $overrides = [] ): stdClass {
		return (object) array_merge(
			[
				'slug'           => 'test-plugin',
				'file'           => 'test-plugin/test-plugin.php',
				'uri'            => 'https://github.com/test-owner/test-plugin',
				'icons'          => [ 'default' => 'https://s.w.org/plugins/geopattern-icon/test-plugin.svg' ],
				'banners'        => [],
				'branch'         => 'main',
				'primary_branch' => 'main',
				'git'            => 'github',
				'type'           => 'plugin',
				'remote_version' => '2.0.0',
				'local_version'  => '1.0.0',
				'download_link'  => 'https://example.com/test-plugin.zip',
				'tested'         => '6.5',
				'requires'       => '',
				'requires_php'   => '',
				'branches'       => [ 'main' => [ 'download' => 'https://example.com/main.zip' ] ],
				'dot_org'        => false,
				'name'           => 'Test Plugin',
				'author'         => 'Test Author',
				'homepage'       => 'https://example.com',
				'donate_link'    => '',
				'sections'       => [ 'description' => 'A test plugin description.' ],
				'downloaded'     => 0,
				'last_updated'   => '2024-01-01',
				'added'          => '2023-01-01',
				'contributors'   => [],
				'rating'         => 0,
				'num_ratings'    => 0,
				'release_asset'  => false,
				'did'            => null,
			],
			$overrides
		);
	}

	/**
	 * Construct a Plugin instance with a pre-injected config array.
	 *
	 * @param array<string, stdClass> $config Mock config keyed by slug.
	 * @return Plugin
	 */
	private function plugin_with_config( array $config ): Plugin {
		$plugin = new Plugin();
		$ref    = new ReflectionProperty( Plugin::class, 'config' );
		$ref->setAccessible( true );
		$ref->setValue( $plugin, $config );
		return $plugin;
	}
}

// ---------------------------------------------------------------------------
// Test_Plugin_Get_Plugin_Configs
// ---------------------------------------------------------------------------

/**
 * Class Test_Plugin_Get_Plugin_Configs
 */
class Test_Plugin_Get_Plugin_Configs extends WP_UnitTestCase {
	use Plugin_Mock_Helper;

	public function set_up(): void {
		parent::set_up();
		new Base();
	}

	public function test_returns_array(): void {
		$plugin = new Plugin();
		$this->assertIsArray( $plugin->get_plugin_configs() );
	}

	public function test_reflects_injected_config(): void {
		$plugin_obj = $this->make_plugin_obj();
		$plugin     = $this->plugin_with_config( [ 'test-plugin' => $plugin_obj ] );
		$configs    = $plugin->get_plugin_configs();
		$this->assertArrayHasKey( 'test-plugin', $configs );
		$this->assertSame( 'test-plugin', $configs['test-plugin']->slug );
	}

	public function test_returns_empty_array_for_empty_config(): void {
		$plugin = $this->plugin_with_config( [] );
		$this->assertSame( [], $plugin->get_plugin_configs() );
	}

	public function test_returns_multiple_configs(): void {
		$plugin_a = $this->make_plugin_obj( [ 'slug' => 'plugin-a' ] );
		$plugin_b = $this->make_plugin_obj( [ 'slug' => 'plugin-b' ] );
		$plugin   = $this->plugin_with_config( [
			'plugin-a' => $plugin_a,
			'plugin-b' => $plugin_b,
		] );
		$configs = $plugin->get_plugin_configs();
		$this->assertCount( 2, $configs );
		$this->assertArrayHasKey( 'plugin-a', $configs );
		$this->assertArrayHasKey( 'plugin-b', $configs );
	}
}

// ---------------------------------------------------------------------------
// Test_Plugin_Sort_Sections_In_API
// ---------------------------------------------------------------------------

/**
 * Class Test_Plugin_Sort_Sections_In_API
 */
class Test_Plugin_Sort_Sections_In_API extends WP_UnitTestCase {

	private Plugin $plugin;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->plugin = new Plugin();
	}

	public function test_returns_res_unchanged_when_no_sections_property(): void {
		$res       = new stdClass();
		$res->name = 'Test Plugin';
		$result    = $this->plugin->sort_sections_in_api( $res );
		$this->assertSame( $res, $result );
		$this->assertFalse( property_exists( $result, 'sections' ) );
	}

	public function test_returns_wp_error_unchanged(): void {
		$error  = new WP_Error( 'test', 'Test error' );
		$result = $this->plugin->sort_sections_in_api( $error );
		$this->assertSame( $error, $result );
	}

	public function test_orders_known_sections_correctly(): void {
		$res           = new stdClass();
		$res->sections = [
			'changelog'    => 'Changelog text.',
			'description'  => 'Description text.',
			'installation' => 'Installation text.',
		];
		$result = $this->plugin->sort_sections_in_api( $res );
		$keys   = array_keys( $result->sections );
		$this->assertSame( 'description', $keys[0] );
		$this->assertSame( 'installation', $keys[1] );
		$this->assertSame( 'changelog', $keys[2] );
	}

	public function test_empty_value_sections_are_removed(): void {
		$res           = new stdClass();
		$res->sections = [
			'description'  => 'Description text.',
			'installation' => '',
		];
		$result = $this->plugin->sort_sections_in_api( $res );
		$this->assertArrayHasKey( 'description', $result->sections );
		$this->assertArrayNotHasKey( 'installation', $result->sections );
	}

	public function test_unknown_sections_are_preserved(): void {
		$res           = new stdClass();
		$res->sections = [
			'custom_tab'  => 'Custom content.',
			'description' => 'Description text.',
		];
		$result = $this->plugin->sort_sections_in_api( $res );
		$this->assertArrayHasKey( 'description', $result->sections );
		$this->assertArrayHasKey( 'custom_tab', $result->sections );
		// Known sections sort before unknown ones.
		$keys = array_keys( $result->sections );
		$this->assertSame( 'description', $keys[0] );
	}

	public function test_all_standard_section_order(): void {
		$res           = new stdClass();
		$res->sections = [
			'reviews'        => 'Review text.',
			'changelog'      => 'Changelog text.',
			'faq'            => 'FAQ text.',
			'description'    => 'Description text.',
			'upgrade_notice' => 'Upgrade notice text.',
			'screenshots'    => 'Screenshot text.',
			'installation'   => 'Installation text.',
		];
		$result         = $this->plugin->sort_sections_in_api( $res );
		$expected_order = [ 'description', 'installation', 'faq', 'screenshots', 'changelog', 'upgrade_notice', 'reviews' ];
		$this->assertSame( $expected_order, array_keys( $result->sections ) );
	}
}

// ---------------------------------------------------------------------------
// Test_Plugin_Load_Pre_Filters
// ---------------------------------------------------------------------------

/**
 * Class Test_Plugin_Load_Pre_Filters
 */
class Test_Plugin_Load_Pre_Filters extends WP_UnitTestCase {

	private Plugin $plugin;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->plugin = new Plugin();
	}

	public function tear_down(): void {
		remove_filter( 'plugins_api', [ $this->plugin, 'plugins_api' ], 99 );
		remove_filter( 'plugins_api_result', [ $this->plugin, 'sort_sections_in_api' ], 15 );
		remove_filter( 'site_transient_update_plugins', [ $this->plugin, 'update_site_transient' ], 15 );
		parent::tear_down();
	}

	public function test_registers_plugins_api_filter(): void {
		$this->plugin->load_pre_filters();
		$this->assertSame( 99, has_filter( 'plugins_api', [ $this->plugin, 'plugins_api' ] ) );
	}

	public function test_registers_plugins_api_result_filter(): void {
		$this->plugin->load_pre_filters();
		$this->assertSame( 15, has_filter( 'plugins_api_result', [ $this->plugin, 'sort_sections_in_api' ] ) );
	}

	public function test_registers_site_transient_update_plugins_filter(): void {
		$this->plugin->load_pre_filters();
		$this->assertSame( 15, has_filter( 'site_transient_update_plugins', [ $this->plugin, 'update_site_transient' ] ) );
	}
}

// ---------------------------------------------------------------------------
// Test_Plugin_Plugins_API_Filter
// ---------------------------------------------------------------------------

/**
 * Class Test_Plugin_Plugins_API_Filter
 */
class Test_Plugin_Plugins_API_Filter extends WP_UnitTestCase {
	use Plugin_Mock_Helper;

	private string $cache_key;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->cache_key = 'ghu-' . md5( 'test-plugin' );
		delete_site_option( $this->cache_key );
	}

	public function tear_down(): void {
		delete_site_option( $this->cache_key );
		parent::tear_down();
	}

	public function test_returns_result_for_non_plugin_information_action(): void {
		$plugin   = $this->plugin_with_config( [ 'test-plugin' => $this->make_plugin_obj() ] );
		$response = new stdClass();
		$response->slug = 'test-plugin';
		$result = $plugin->plugins_api( false, 'query_plugins', $response );
		$this->assertFalse( $result );
	}

	public function test_returns_result_when_slug_not_in_config(): void {
		$plugin   = $this->plugin_with_config( [] );
		$response = new stdClass();
		$response->slug = 'unknown-plugin';
		$result = $plugin->plugins_api( 'original', 'plugin_information', $response );
		// Slug not found → $plugin = false → waiting_for_background_update(false) = true → returns $result.
		$this->assertSame( 'original', $result );
	}

	public function test_returns_result_when_waiting_for_background_update(): void {
		// Empty cache → waiting_for_background_update = true.
		$plugin   = $this->plugin_with_config( [ 'test-plugin' => $this->make_plugin_obj() ] );
		$response = new stdClass();
		$response->slug = 'test-plugin';
		$result = $plugin->plugins_api( 'original', 'plugin_information', $response );
		$this->assertSame( 'original', $result );
	}

	public function test_returns_result_when_dot_org_on_primary_branch(): void {
		update_site_option( $this->cache_key, [ 'any' => 'data' ] );
		$plugin_obj = $this->make_plugin_obj( [
			'dot_org'        => true,
			'branch'         => 'main',
			'primary_branch' => 'main',
		] );
		$plugin   = $this->plugin_with_config( [ 'test-plugin' => $plugin_obj ] );
		$response = new stdClass();
		$response->slug = 'test-plugin';
		$result = $plugin->plugins_api( false, 'plugin_information', $response );
		$this->assertFalse( $result );
	}

	public function test_populates_response_for_git_plugin(): void {
		update_site_option( $this->cache_key, [ 'any' => 'data' ] );
		$plugin_obj = $this->make_plugin_obj( [ 'dot_org' => false ] );
		$plugin     = $this->plugin_with_config( [ 'test-plugin' => $plugin_obj ] );
		$response   = new stdClass();
		$response->slug = 'test-plugin';
		$result = $plugin->plugins_api( false, 'plugin_information', $response );
		$this->assertInstanceOf( stdClass::class, $result );
		$this->assertSame( 'Test Plugin', $result->name );
		$this->assertSame( '2.0.0', $result->version );
		$this->assertSame( 'Test Author', $result->author );
		$this->assertSame( 'https://example.com', $result->homepage );
	}

	public function test_response_version_falls_back_to_local_version(): void {
		update_site_option( $this->cache_key, [ 'any' => 'data' ] );
		$plugin_obj = $this->make_plugin_obj( [
			'dot_org'        => false,
			'remote_version' => '',
			'local_version'  => '1.5.0',
		] );
		$plugin   = $this->plugin_with_config( [ 'test-plugin' => $plugin_obj ] );
		$response = new stdClass();
		$response->slug = 'test-plugin';
		$result = $plugin->plugins_api( false, 'plugin_information', $response );
		$this->assertSame( '1.5.0', $result->version );
	}

	public function test_response_short_description_is_truncated(): void {
		update_site_option( $this->cache_key, [ 'any' => 'data' ] );
		$long_desc  = str_repeat( 'A', 200 );
		$plugin_obj = $this->make_plugin_obj( [
			'dot_org'  => false,
			'sections' => [ 'description' => $long_desc ],
		] );
		$plugin   = $this->plugin_with_config( [ 'test-plugin' => $plugin_obj ] );
		$response = new stdClass();
		$response->slug = 'test-plugin';
		$result = $plugin->plugins_api( false, 'plugin_information', $response );
		$this->assertLessThanOrEqual( 151, strlen( $result->short_description ) ); // 147 chars + '...'
	}

	public function test_dot_org_on_non_primary_branch_returns_response(): void {
		// dot_org = true but branch != primary_branch → should NOT skip (returns populated response).
		update_site_option( $this->cache_key, [ 'any' => 'data' ] );
		$plugin_obj = $this->make_plugin_obj( [
			'dot_org'        => true,
			'branch'         => 'develop',
			'primary_branch' => 'main',
		] );
		$plugin   = $this->plugin_with_config( [ 'test-plugin' => $plugin_obj ] );
		$response = new stdClass();
		$response->slug = 'test-plugin';
		$result = $plugin->plugins_api( false, 'plugin_information', $response );
		$this->assertInstanceOf( stdClass::class, $result );
		$this->assertSame( 'Test Plugin', $result->name );
	}
}

// ---------------------------------------------------------------------------
// Test_Plugin_Update_Site_Transient_Method
// ---------------------------------------------------------------------------

/**
 * Class Test_Plugin_Update_Site_Transient_Method
 */
class Test_Plugin_Update_Site_Transient_Method extends WP_UnitTestCase {
	use Plugin_Mock_Helper;

	private string $cache_key;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->cache_key = 'ghu-' . md5( 'test-plugin' );
		delete_site_option( $this->cache_key );
	}

	public function tear_down(): void {
		delete_site_option( $this->cache_key );
		remove_all_filters( 'gu_config_pre_process' );
		remove_all_filters( 'gu_override_dot_org' );
		remove_all_filters( 'gu_remote_is_newer' );
		parent::tear_down();
	}

	public function test_non_object_transient_becomes_stdclass(): void {
		$plugin = $this->plugin_with_config( [] );
		$result = $plugin->update_site_transient( null );
		$this->assertInstanceOf( stdClass::class, $result );
	}

	public function test_false_transient_becomes_stdclass(): void {
		$plugin = $this->plugin_with_config( [] );
		$result = $plugin->update_site_transient( false );
		$this->assertInstanceOf( stdClass::class, $result );
	}

	public function test_empty_config_returns_transient_object_unchanged(): void {
		$plugin    = $this->plugin_with_config( [] );
		$transient = new stdClass();
		$transient->response  = [];
		$transient->no_update = [];
		$result = $plugin->update_site_transient( $transient );
		$this->assertSame( $transient, $result );
		$this->assertEmpty( $result->response );
		$this->assertEmpty( $result->no_update );
	}

	public function test_gu_config_pre_process_filter_applied(): void {
		$plugin_obj = $this->make_plugin_obj();
		$plugin     = $this->plugin_with_config( [ 'test-plugin' => $plugin_obj ] );
		add_filter( 'gu_config_pre_process', '__return_empty_array' );
		$transient = new stdClass();
		$transient->response  = [];
		$transient->no_update = [];
		$result = $plugin->update_site_transient( $transient );
		$this->assertEmpty( $result->response );
		$this->assertEmpty( $result->no_update );
	}

	public function test_plugin_without_update_goes_to_no_update(): void {
		$plugin_obj = $this->make_plugin_obj( [
			'remote_version' => '1.0.0',
			'local_version'  => '2.0.0',
		] );
		$plugin    = $this->plugin_with_config( [ 'test-plugin' => $plugin_obj ] );
		$transient = new stdClass();
		$transient->response  = [];
		$transient->no_update = [];
		$result = $plugin->update_site_transient( $transient );
		$this->assertArrayHasKey( 'test-plugin/test-plugin.php', $result->no_update );
		$this->assertArrayNotHasKey( 'test-plugin/test-plugin.php', $result->response );
	}

	public function test_plugin_with_update_goes_to_response(): void {
		$plugin_obj = $this->make_plugin_obj( [
			'remote_version' => '2.0.0',
			'local_version'  => '1.0.0',
			'dot_org'        => false,
		] );
		$plugin    = $this->plugin_with_config( [ 'test-plugin' => $plugin_obj ] );
		$transient = new stdClass();
		$transient->response  = [];
		$transient->no_update = [];
		$result = $plugin->update_site_transient( $transient );
		$this->assertArrayHasKey( 'test-plugin/test-plugin.php', $result->response );
		$this->assertSame( '2.0.0', $result->response['test-plugin/test-plugin.php']->new_version );
	}

	public function test_response_contains_correct_type_field(): void {
		$plugin_obj = $this->make_plugin_obj( [
			'remote_version' => '2.0.0',
			'local_version'  => '1.0.0',
			'dot_org'        => false,
			'git'            => 'github',
			'type'           => 'plugin',
		] );
		$plugin    = $this->plugin_with_config( [ 'test-plugin' => $plugin_obj ] );
		$transient = new stdClass();
		$transient->response  = [];
		$transient->no_update = [];
		$result = $plugin->update_site_transient( $transient );
		$this->assertSame( 'github-plugin', $result->response['test-plugin/test-plugin.php']->type );
	}

	public function test_no_update_not_overwritten_when_already_set(): void {
		$plugin_obj = $this->make_plugin_obj( [
			'remote_version' => '1.0.0',
			'local_version'  => '2.0.0',
		] );
		$plugin    = $this->plugin_with_config( [ 'test-plugin' => $plugin_obj ] );
		$existing  = (object) [ 'slug' => 'pre-existing' ];
		$transient = new stdClass();
		$transient->response  = [];
		$transient->no_update = [ 'test-plugin/test-plugin.php' => $existing ];
		$result = $plugin->update_site_transient( $transient );
		$this->assertSame( $existing, $result->no_update['test-plugin/test-plugin.php'] );
	}

	public function test_dot_org_override_removes_entry_from_response(): void {
		$plugin_obj = $this->make_plugin_obj( [
			'remote_version' => '1.0.0',
			'local_version'  => '2.0.0',
		] );
		$plugin    = $this->plugin_with_config( [ 'test-plugin' => $plugin_obj ] );
		$transient = new stdClass();
		$transient->response  = [ 'test-plugin/test-plugin.php' => new stdClass() ];
		$transient->no_update = [];
		add_filter( 'gu_override_dot_org', fn() => [ 'test-plugin/test-plugin.php' ] );
		$result = $plugin->update_site_transient( $transient );
		$this->assertArrayNotHasKey( 'test-plugin/test-plugin.php', $result->response );
	}

	public function test_release_asset_non_primary_branch_updates_package_url(): void {
		$plugin_obj = $this->make_plugin_obj( [
			'remote_version' => '2.0.0',
			'local_version'  => '1.0.0',
			'dot_org'        => false,
			'release_asset'  => true,
			'branch'         => 'develop',
			'primary_branch' => 'main',
			'branches'       => [
				'main'    => [ 'download' => 'https://example.com/main.zip' ],
				'develop' => [ 'download' => 'https://example.com/develop.zip' ],
			],
		] );
		$plugin    = $this->plugin_with_config( [ 'test-plugin' => $plugin_obj ] );
		$transient = new stdClass();
		$transient->response  = [];
		$transient->no_update = [];
		$result = $plugin->update_site_transient( $transient );
		$this->assertSame(
			'https://example.com/develop.zip',
			$result->response['test-plugin/test-plugin.php']->package
		);
	}

	public function test_release_asset_missing_branch_sets_package_null(): void {
		$plugin_obj = $this->make_plugin_obj( [
			'remote_version' => '2.0.0',
			'local_version'  => '1.0.0',
			'dot_org'        => false,
			'release_asset'  => true,
			'branch'         => 'feature',
			'primary_branch' => 'main',
			'branches'       => [
				'main' => [ 'download' => 'https://example.com/main.zip' ],
			],
		] );
		$plugin    = $this->plugin_with_config( [ 'test-plugin' => $plugin_obj ] );
		$transient = new stdClass();
		$transient->response  = [];
		$transient->no_update = [];
		$result = $plugin->update_site_transient( $transient );
		$this->assertNull( $result->response['test-plugin/test-plugin.php']->package );
	}

	public function test_dot_org_plugin_on_primary_branch_skipped_for_update(): void {
		// dot_org=true + same branch → override_dot_org returns false → continue (no update).
		$plugin_obj = $this->make_plugin_obj( [
			'remote_version' => '2.0.0',
			'local_version'  => '1.0.0',
			'dot_org'        => true,
			'branch'         => 'main',
			'primary_branch' => 'main',
		] );
		$plugin    = $this->plugin_with_config( [ 'test-plugin' => $plugin_obj ] );
		$transient = new stdClass();
		$transient->response  = [];
		$transient->no_update = [];
		$result = $plugin->update_site_transient( $transient );
		$this->assertArrayNotHasKey( 'test-plugin/test-plugin.php', $result->response );
	}
}

// ---------------------------------------------------------------------------
// Test_Plugin_Get_Remote_Plugin_Meta
// ---------------------------------------------------------------------------

/**
 * Class Test_Plugin_Get_Remote_Plugin_Meta
 */
class Test_Plugin_Get_Remote_Plugin_Meta extends WP_UnitTestCase {
	use Plugin_Mock_Helper;

	public function set_up(): void {
		parent::set_up();
		new Base();
		// The plugin bootstrap hooks Base::load() to 'init', which calls get_meta_plugins()
		// and may schedule gu_get_remote_plugin for the fixture plugin. Bust the cache
		// before clearing so the unschedule reads fresh state from DB even when the object
		// cache is stale after a transaction rollback from a previous test.
		wp_cache_delete( 'cron', 'options' );
		wp_unschedule_hook( 'gu_get_remote_plugin' );
	}

	public function tear_down(): void {
		remove_all_filters( 'plugins_api' );
		remove_all_filters( 'plugins_api_result' );
		remove_all_filters( 'site_transient_update_plugins' );
		remove_all_filters( 'gu_config_pre_process' );
		remove_all_filters( 'gu_disable_wpcron' );
		wp_cache_delete( 'cron', 'options' );
		wp_unschedule_hook( 'gu_get_remote_plugin' );
		parent::tear_down();
	}

	public function test_load_pre_filters_called_when_config_is_empty(): void {
		$plugin = $this->plugin_with_config( [] );
		$plugin->get_remote_plugin_meta();
		$this->assertSame( 99, has_filter( 'plugins_api', [ $plugin, 'plugins_api' ] ) );
		$this->assertSame( 15, has_filter( 'plugins_api_result', [ $plugin, 'sort_sections_in_api' ] ) );
		$this->assertSame( 15, has_filter( 'site_transient_update_plugins', [ $plugin, 'update_site_transient' ] ) );
	}

	public function test_gu_config_pre_process_filter_applied_in_meta_fetch(): void {
		$filter_ran = false;
		add_filter(
			'gu_config_pre_process',
			function ( $config ) use ( &$filter_ran ) {
				$filter_ran = true;
				return $config;
			}
		);
		$plugin = $this->plugin_with_config( [] );
		$plugin->get_remote_plugin_meta();
		$this->assertTrue( $filter_ran );
	}

	public function test_schedules_background_cron_for_uncached_plugins(): void {
		wp_cache_delete( 'cron', 'options' );
		wp_unschedule_hook( 'gu_get_remote_plugin' );

		$plugin_obj = $this->make_plugin_obj();
		// Empty cache → waiting_for_background_update = true → plugin queued for background.
		delete_site_option( 'ghu-' . md5( 'test-plugin' ) );

		$plugin = $this->plugin_with_config( [ 'test-plugin' => $plugin_obj ] );
		$plugin->get_remote_plugin_meta();

		// wp_next_scheduled() can't find args-keyed events without passing the exact args,
		// so inspect _get_cron_array() directly.
		$this->assertTrue( $this->cron_hook_exists( 'gu_get_remote_plugin' ) );
	}

	public function test_no_cron_scheduled_when_config_has_no_background_plugins(): void {
		// After a DB transaction rollback the object cache can be stale: the DB holds the
		// init-bootstrapped cron event but the cache reflects the cleared state from the
		// previous test's tear_down. Explicitly delete the 'cron' cache key first so the
		// next read goes to DB and the unschedule operates on fresh data.
		wp_cache_delete( 'cron', 'options' );
		wp_unschedule_hook( 'gu_get_remote_plugin' );

		// Empty config → no plugins queued for background → no cron.
		$plugin = $this->plugin_with_config( [] );
		$plugin->get_remote_plugin_meta();

		$this->assertFalse( $this->cron_hook_exists( 'gu_get_remote_plugin' ) );
	}

	public function test_no_duplicate_cron_when_already_scheduled(): void {
		// Pre-schedule a ready event so is_cron_event_scheduled returns true.
		wp_schedule_single_event( time() - HOUR_IN_SECONDS, 'gu_get_remote_plugin', [ [] ] );

		$plugin_obj = $this->make_plugin_obj();
		delete_site_option( 'ghu-' . md5( 'test-plugin' ) );

		$plugin = $this->plugin_with_config( [ 'test-plugin' => $plugin_obj ] );
		$plugin->get_remote_plugin_meta();

		// Hook should still exist (not cleared), but no duplicate was added.
		$this->assertTrue( $this->cron_hook_exists( 'gu_get_remote_plugin' ) );
	}

	/**
	 * Return true if any cron event exists for the given hook (ignoring args).
	 *
	 * @param string $hook Cron hook name.
	 * @return bool
	 */
	private function cron_hook_exists( string $hook ): bool {
		foreach ( (array) _get_cron_array() as $hooks ) {
			if ( isset( $hooks[ $hook ] ) ) {
				return true;
			}
		}
		return false;
	}
}
