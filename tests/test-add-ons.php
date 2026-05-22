<?php
/**
 * Tests for Add_Ons.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\Add_Ons;
use Fragen\Git_Updater\Base;

class Test_Add_Ons extends WP_UnitTestCase {

	private Add_Ons $addons;
	private string  $addons_cache_key;

	public function set_up(): void {
		parent::set_up();
		$this->addons           = new Add_Ons();
		$this->addons_cache_key = 'ghu-' . md5( 'gu_addon_api_results' );
	}

	public function tear_down(): void {
		delete_site_option( $this->addons_cache_key );
		remove_all_filters( 'gu_add_settings_tabs' );
		remove_all_actions( 'gu_add_admin_page' );
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// plugins_api()
	// -------------------------------------------------------------------------

	public function test_plugins_api_returns_result_unchanged_for_non_addon_slug(): void {
		$result   = (object) [ 'name' => 'Some Plugin' ];
		$args     = (object) [ 'slug' => 'not-an-addon' ];

		$returned = $this->addons->plugins_api( $result, 'plugin_information', $args );

		$this->assertSame( $result, $returned );
	}

	public function test_plugins_api_returns_cached_addon_data_as_object(): void {
		$addon_data = [ 'git-updater-gist' => [ 'name' => 'Git Updater Gist', 'slug' => 'git-updater-gist', 'version' => '1.0.0' ] ];
		update_site_option(
			$this->addons_cache_key,
			[
				'gu_addon_api_results' => $addon_data,
				'timeout'              => strtotime( '+7 days' ),
			]
		);

		$args   = (object) [ 'slug' => 'git-updater-gist' ];
		$result = $this->addons->plugins_api( false, 'plugin_information', $args );

		$this->assertIsObject( $result );
		$this->assertSame( 'Git Updater Gist', $result->name );
	}

	// -------------------------------------------------------------------------
	// add_settings_tabs()
	// -------------------------------------------------------------------------

	public function test_add_settings_tabs_registers_git_updater_addons_tab(): void {
		$this->addons->add_settings_tabs();

		$tabs = apply_filters( 'gu_add_settings_tabs', [] );

		$this->assertArrayHasKey( 'git_updater_addons', $tabs );
	}

	public function test_add_settings_tabs_preserves_existing_tabs(): void {
		$this->addons->add_settings_tabs();

		$tabs = apply_filters( 'gu_add_settings_tabs', [ 'existing_tab' => 'Existing' ] );

		$this->assertArrayHasKey( 'existing_tab', $tabs );
		$this->assertArrayHasKey( 'git_updater_addons', $tabs );
	}
}


class Test_Add_Ons_Load_Hooks extends WP_UnitTestCase {

	private Add_Ons $addons;

	public function set_up(): void {
		parent::set_up();
		$this->addons = new Add_Ons();
	}

	public function tear_down(): void {
		remove_action( 'admin_init', [ $this->addons, 'addons_page_init' ] );
		remove_action( 'install_plugins_pre_plugin-information', [ $this->addons, 'prevent_redirect_on_modal_activation' ] );
		remove_filter( 'plugins_api', [ $this->addons, 'plugins_api' ], 99 );
		remove_all_filters( 'gu_add_settings_tabs' );
		remove_all_actions( 'gu_add_admin_page' );
		parent::tear_down();
	}

	public function test_load_hooks_registers_admin_init_action(): void {
		$this->addons->load_hooks();
		$this->assertNotFalse( has_action( 'admin_init', [ $this->addons, 'addons_page_init' ] ) );
	}

	public function test_load_hooks_registers_plugin_information_action(): void {
		$this->addons->load_hooks();
		$this->assertNotFalse( has_action( 'install_plugins_pre_plugin-information', [ $this->addons, 'prevent_redirect_on_modal_activation' ] ) );
	}

	public function test_load_hooks_registers_plugins_api_filter(): void {
		$this->addons->load_hooks();
		$this->assertNotFalse( has_filter( 'plugins_api', [ $this->addons, 'plugins_api' ] ) );
	}

	public function test_load_hooks_registers_git_updater_addons_settings_tab(): void {
		$this->addons->load_hooks();
		$tabs = apply_filters( 'gu_add_settings_tabs', [] );
		$this->assertArrayHasKey( 'git_updater_addons', $tabs );
	}

	public function test_load_hooks_addons_tab_label_is_non_empty(): void {
		$this->addons->load_hooks();
		$tabs = apply_filters( 'gu_add_settings_tabs', [] );
		$this->assertNotEmpty( $tabs['git_updater_addons'] );
	}
}

// ---------------------------------------------------------------------------
// Add_Ons — get_addon_api_results
// ---------------------------------------------------------------------------

/**
 * Class Test_Add_Ons_Api_Results
 */

class Test_Add_Ons_Api_Results extends WP_UnitTestCase {

	private Add_Ons $addons;
	private string  $cache_key;

	public function set_up(): void {
		parent::set_up();
		$this->addons    = new Add_Ons();
		$this->cache_key = 'ghu-' . md5( 'gu_addon_api_results' );
	}

	public function tear_down(): void {
		delete_site_option( $this->cache_key );
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	private function mock_http( int $code, array $body = [ 'name' => 'Addon' ] ): void {
		add_filter(
			'pre_http_request',
			fn() => [
				'response' => [ 'code' => $code, 'message' => 200 === $code ? 'OK' : 'Error' ],
				'body'     => json_encode( $body ),
				'headers'  => [],
			],
			10,
			3
		);
	}

	public function test_get_addon_api_results_returns_cached_data_without_http(): void {
		$data = [ 'git-updater-gist' => [ 'name' => 'Git Updater Gist' ] ];
		update_site_option(
			$this->cache_key,
			[ 'gu_addon_api_results' => $data, 'timeout' => strtotime( '+7 days' ) ]
		);

		$result = $this->addons->get_addon_api_results();

		$this->assertSame( $data, $result );
	}

	public function test_get_addon_api_results_returns_all_four_addons_when_all_succeed(): void {
		$this->mock_http( 200 );

		$result = $this->addons->get_addon_api_results();

		$this->assertCount( 4, $result );
	}

	public function test_get_addon_api_results_result_keys_match_addon_slugs(): void {
		$this->mock_http( 200 );

		$result = $this->addons->get_addon_api_results();

		$this->assertArrayHasKey( 'git-updater-gist', $result );
		$this->assertArrayHasKey( 'git-updater-bitbucket', $result );
		$this->assertArrayHasKey( 'git-updater-gitlab', $result );
		$this->assertArrayHasKey( 'git-updater-gitea', $result );
	}

	public function test_get_addon_api_results_skips_addon_on_non_200_response(): void {
		$n = 0;
		add_filter(
			'pre_http_request',
			function () use ( &$n ) {
				++$n;
				return [
					'response' => [ 'code' => 1 === $n ? 404 : 200, 'message' => 'OK' ],
					'body'     => json_encode( [ 'name' => 'Addon' ] ),
					'headers'  => [],
				];
			},
			10,
			3
		);

		$result = $this->addons->get_addon_api_results();

		$this->assertCount( 3, $result );
	}

	public function test_get_addon_api_results_skips_addon_when_body_contains_error_key(): void {
		$this->mock_http( 200, [ 'error' => 'Plugin not found' ] );

		$result = $this->addons->get_addon_api_results();

		$this->assertEmpty( $result );
	}

	public function test_get_addon_api_results_writes_cache_when_all_addons_succeed(): void {
		$this->mock_http( 200 );

		$this->addons->get_addon_api_results();

		$cache = get_site_option( $this->cache_key );
		$this->assertIsArray( $cache );
		$this->assertArrayHasKey( 'gu_addon_api_results', $cache );
	}

	public function test_get_addon_api_results_does_not_write_cache_for_partial_results(): void {
		$n = 0;
		add_filter(
			'pre_http_request',
			function () use ( &$n ) {
				++$n;
				return [
					'response' => [ 'code' => 1 === $n ? 404 : 200, 'message' => 'OK' ],
					'body'     => json_encode( [ 'name' => 'Addon' ] ),
					'headers'  => [],
				];
			},
			10,
			3
		);

		$this->addons->get_addon_api_results();

		$this->assertFalse( get_site_option( $this->cache_key, false ) );
	}

	public function test_plugins_api_returns_original_result_for_addon_slug_with_no_cached_data(): void {
		// All HTTP calls fail so get_addon_api_results() returns [].
		$this->mock_http( 404 );

		$original = (object) [ 'name' => 'Original' ];
		$args     = (object) [ 'slug' => 'git-updater-gist' ];

		$returned = $this->addons->plugins_api( $original, 'plugin_information', $args );

		$this->assertSame( $original, $returned );
	}

	public function test_plugins_api_returns_result_object_when_slug_found_in_api_results(): void {
		$data = [ 'git-updater-gist' => [ 'name' => 'Git Updater Gist', 'slug' => 'git-updater-gist' ] ];
		update_site_option(
			$this->cache_key,
			[ 'gu_addon_api_results' => $data, 'timeout' => strtotime( '+7 days' ) ]
		);

		$args   = (object) [ 'slug' => 'git-updater-gist' ];
		$result = $this->addons->plugins_api( new stdClass(), 'plugin_information', $args );

		$this->assertSame( 'Git Updater Gist', $result->name );
	}
}

// ---------------------------------------------------------------------------
// Additions — add_headers
// ---------------------------------------------------------------------------

/**
 * Class Test_Additions_Add_Headers
 */

class Test_Add_Ons_Admin_Page_And_Init extends WP_UnitTestCase {

	private Add_Ons $addons;

	public function set_up(): void {
		parent::set_up();
		$this->addons = new Add_Ons();
	}

	public function tear_down(): void {
		global $wp_settings_sections, $wp_settings_fields;
		unset( $wp_settings_sections['git_updater_addons_settings'] );
		unset( $wp_settings_fields['git_updater_addons_settings'] );
		wp_dequeue_script( 'ajax-activate' );
		wp_deregister_script( 'ajax-activate' );
		remove_all_filters( 'gu_add_settings_tabs' );
		remove_all_actions( 'gu_add_admin_page' );
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	public function test_add_admin_page_matching_tab_enqueues_plugin_install_script(): void {
		$this->addons->add_admin_page( 'git_updater_addons' );
		// ajax-activate is newly registered+enqueued by add_admin_page — reliable signal that the body ran.
		$this->assertTrue( wp_script_is( 'ajax-activate', 'registered' ) );
	}

	public function test_gu_add_admin_page_action_closure_invokes_add_admin_page(): void {
		// Fires the closure registered on 'gu_add_admin_page' by add_settings_tabs() — covers line 70.
		// Pass two args: Additions\Settings also registers on this action with accepted_args=2.
		$this->addons->add_settings_tabs();
		do_action( 'gu_add_admin_page', 'git_updater_addons', admin_url() );
		$this->assertTrue( wp_script_is( 'ajax-activate', 'registered' ) );
	}

	public function test_addons_page_init_registers_setting(): void {
		$this->addons->addons_page_init();
		$settings = get_registered_settings();
		$this->assertArrayHasKey( 'git_updater_addons_settings', $settings );
	}

	public function test_addons_page_init_registers_settings_section(): void {
		global $wp_settings_sections;
		$this->addons->addons_page_init();
		$this->assertArrayHasKey( 'addons', $wp_settings_sections['git_updater_addons_settings'] ?? [] );
	}
}

// ---------------------------------------------------------------------------
// Add_Ons — prevent_redirect_on_modal_activation
// ---------------------------------------------------------------------------

/**
 * Class Test_Add_Ons_Modal_Prevention
 */

class Test_Add_Ons_Modal_Prevention extends WP_UnitTestCase {

	private Add_Ons $addons;

	public function set_up(): void {
		parent::set_up();
		$this->addons = new Add_Ons();
		wp_dequeue_script( 'ajax-activate' );
		wp_deregister_script( 'ajax-activate' );
	}

	public function tear_down(): void {
		unset( $_GET['plugin'] );
		wp_dequeue_script( 'ajax-activate' );
		wp_deregister_script( 'ajax-activate' );
		parent::tear_down();
	}

	public function test_prevent_redirect_enqueues_ajax_activate_for_addon_slug(): void {
		$_GET['plugin'] = 'git-updater-gist';
		$this->addons->prevent_redirect_on_modal_activation();
		$this->assertTrue( wp_script_is( 'ajax-activate', 'registered' ) );
	}

	public function test_prevent_redirect_does_nothing_when_plugin_not_in_get(): void {
		unset( $_GET['plugin'] );
		$this->addons->prevent_redirect_on_modal_activation();
		$this->assertFalse( wp_script_is( 'ajax-activate', 'registered' ) );
	}
}

// ---------------------------------------------------------------------------
// Add_Ons — insert_cards
// ---------------------------------------------------------------------------

/**
 * Class Test_Add_Ons_Insert_Cards
 */

class Test_Add_Ons_Insert_Cards extends WP_UnitTestCase {

	private Add_Ons $addons;
	private string  $cache_key;

	public function set_up(): void {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/template.php';
		$this->addons    = new Add_Ons();
		$this->cache_key = 'ghu-' . md5( 'gu_addon_api_results' );
	}

	public function tear_down(): void {
		delete_site_option( $this->cache_key );
		remove_all_filters( 'pre_http_request' );
		$GLOBALS['current_screen'] = null;
		parent::tear_down();
	}

	private function make_addon_item( string $name, string $slug ): array {
		return [
			'name'              => $name,
			'slug'              => $slug,
			'version'           => '1.0.0',
			'short_description' => '',
			'author'            => '',
			'author_profile'    => '',
			'rating'            => 0,
			'num_ratings'       => 0,
			'active_installs'   => 0,
			'downloaded'        => 0,
			'last_updated'      => '',
			'requires'          => '',
			'requires_php'      => '',
			'tested'            => '',
			'homepage'          => '',
			'group'             => '',
			'icons'             => [ 'default' => '' ],
			'action_links'      => [],
			'banners'           => [ 'default' => '', 'high' => '' ],
			'donate_link'       => '',
			'compatibility'     => [],
		];
	}

	public function test_insert_cards_outputs_form_with_plugin_install_table(): void {
		$data = [
			'git-updater-gist'      => $this->make_addon_item( 'Git Updater Gist',      'git-updater-gist' ),
			'git-updater-bitbucket' => $this->make_addon_item( 'Git Updater Bitbucket', 'git-updater-bitbucket' ),
			'git-updater-gitlab'    => $this->make_addon_item( 'Git Updater GitLab',    'git-updater-gitlab' ),
			'git-updater-gitea'     => $this->make_addon_item( 'Git Updater Gitea',     'git-updater-gitea' ),
		];
		update_site_option(
			$this->cache_key,
			[ 'gu_addon_api_results' => $data, 'timeout' => strtotime( '+7 days' ) ]
		);

		set_current_screen( 'plugin-install' );

		ob_start();
		$this->addons->insert_cards();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<form', $output );
		$this->assertStringContainsString( 'git-updater-addons', $output );
	}
}
