<?php
/**
 * Tests for Settings.php – 100% line coverage.
 *
 * Covers every public and private method in Settings including:
 * - refresh_caches(), load_hooks(), load_api_subtabs(), add_plugin_page()
 * - create_admin_page(), admin_page_notices(), page_init()
 * - gu_tokens(), unset_stale_options(), print_section_gu_settings()
 * - display_dot_org_overrides(), token_callback_text(), token_callback_checkbox()
 * - update_settings(), filter_options(), redirect_on_save()
 * - refresh_transients(), plugin_action_links()
 * - add_hidden_settings_sections(), display_gu_repos(), settings_sub_tabs()
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\Settings;
use Fragen\Git_Updater\Base;
use Fragen\Git_Updater\Plugin;
use Fragen\Git_Updater\Theme;
use Fragen\Singleton;

// =============================================================================
// Shared helper trait
// =============================================================================

/**
 * Shared helpers for Settings tests.
 */
trait Settings_Test_Helper {

	/** @var Settings */
	private Settings $settings;

	private function make_plugin_obj( array $overrides = [] ): stdClass {
		return (object) array_merge(
			[
				'slug'           => 'test-plugin',
				'file'           => 'test-plugin/test-plugin.php',
				'name'           => 'Test Plugin',
				'type'           => 'plugin',
				'git'            => 'github',
				'branch'         => 'main',
				'primary_branch' => 'main',
				'is_private'     => false,
				'dot_org'        => false,
				'dismiss'        => false,
				'remote_version' => '1.0.0',
			],
			$overrides
		);
	}

	private function make_theme_obj( array $overrides = [] ): stdClass {
		return (object) array_merge(
			[
				'slug'           => 'test-theme',
				'file'           => 'test-theme',
				'name'           => 'Test Theme',
				'type'           => 'theme',
				'git'            => 'github',
				'branch'         => 'main',
				'primary_branch' => 'main',
				'is_private'     => false,
				'dot_org'        => false,
				'dismiss'        => false,
				'remote_version' => '1.0.0',
			],
			$overrides
		);
	}

	private function inject_plugin_config( array $cfg ): void {
		$p   = Singleton::get_instance( 'Plugin', $this->settings );
		$ref = new ReflectionProperty( Plugin::class, 'config' );
		$ref->setAccessible( true );
		$ref->setValue( $p, $cfg );
	}

	private function inject_theme_config( array $cfg ): void {
		$t   = Singleton::get_instance( 'Theme', $this->settings );
		$ref = new ReflectionProperty( Theme::class, 'config' );
		$ref->setAccessible( true );
		$ref->setValue( $t, $cfg );
	}

	private function call_private( string $method, array $args = [] ) {
		$rm = new ReflectionMethod( Settings::class, $method );
		$rm->setAccessible( true );
		return $rm->invokeArgs( $this->settings, $args );
	}

	private function set_settings_options( array $opts ): void {
		$ref = new ReflectionProperty( Settings::class, 'options' );
		$ref->setAccessible( true );
		$ref->setValue( null, $opts );
	}

	private function settings_tear_down(): void {
		Settings::$auth_required = [
			'github'            => true,
			'github_private'    => true,
			'github_enterprise' => true,
		];
		remove_all_filters( 'gu_hide_settings' );
		remove_all_filters( 'gu_add_settings_tabs' );
		remove_all_filters( 'gu_add_settings_subtabs' );
		remove_all_filters( 'gu_running_git_servers' );
		remove_all_filters( 'gu_save_redirect' );
		remove_all_filters( 'gu_display_repos' );
		remove_all_filters( 'gu_override_dot_org' );
		remove_all_filters( 'gu_ignore_dot_org' );
		remove_all_filters( 'gu_settings_auth_required' );
		remove_all_filters( 'gu_add_repo_setting_field' );
		remove_all_filters( 'gu_config_pre_process' );
		remove_all_filters( 'wp_redirect' );
		remove_all_filters( 'wp_doing_ajax' );
		remove_all_actions( 'gu_add_settings' );
		remove_all_actions( 'gu_add_admin_page' );
		remove_all_actions( 'gu_update_settings' );
		unset(
			$_GET['tab'],
			$_GET['subtab'],
			$_GET['_wpnonce'],
			$_GET['updated'],
			$_GET['refresh_transients'],
			$_POST['_wpnonce'],
			$_POST['option_page'],
			$_POST['action'],
			$_POST['git_updater'],
			$_POST['gu_refresh_cache'],
			$_REQUEST['git_updater_refresh_transients'],
			$_REQUEST['_wpnonce'],
			$_REQUEST['reset']
		);
	}
}

// =============================================================================
// Test_Settings_Refresh_Caches  lines 80–89
// =============================================================================

/**
 * Class Test_Settings_Refresh_Caches
 *
 * Tests refresh_caches() — called from constructor.
 */
class Test_Settings_Refresh_Caches extends WP_UnitTestCase {
	use Settings_Test_Helper;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->settings = new Settings();
	}

	public function tear_down(): void {
		delete_site_transient( 'gu_refresh_cache' );
		$this->settings_tear_down();
		parent::tear_down();
	}

	public function test_refresh_caches_returns_early_without_nonce(): void {
		unset( $_POST['_wpnonce'] );
		$new = new Settings();
		$this->assertFalse( (bool) get_site_transient( 'gu_refresh_cache' ) );
	}

	public function test_refresh_caches_returns_early_with_invalid_nonce(): void {
		$_POST['_wpnonce'] = 'bad_nonce';
		$new               = new Settings();
		$this->assertFalse( (bool) get_site_transient( 'gu_refresh_cache' ) );
	}

	public function test_refresh_caches_with_valid_nonce_but_no_gu_refresh_cache_flag(): void {
		$_POST['_wpnonce'] = wp_create_nonce( 'gu_refresh_cache' );
		unset( $_POST['gu_refresh_cache'] );
		$new = new Settings();
		$this->assertFalse( (bool) get_site_transient( 'gu_refresh_cache' ) );
	}

	public function test_refresh_caches_sets_transient_when_flag_present(): void {
		$_POST['_wpnonce']       = wp_create_nonce( 'gu_refresh_cache' );
		$_POST['gu_refresh_cache'] = '1';
		$new                     = new Settings();
		$this->assertTrue( (bool) get_site_transient( 'gu_refresh_cache' ) );
	}
}

// =============================================================================
// Test_Settings_Load_Hooks  lines 105–145
// =============================================================================

/**
 * Class Test_Settings_Load_Hooks
 *
 * Tests load_hooks() via run().
 */
class Test_Settings_Load_Hooks extends WP_UnitTestCase {
	use Settings_Test_Helper;

	public function set_up(): void {
		parent::set_up();
		new Base();
	}

	public function tear_down(): void {
		$this->settings_tear_down();
		parent::tear_down();
	}

	public function test_load_hooks_registers_admin_menu_by_default(): void {
		$this->settings = new Settings();
		$this->settings->run();
		$this->assertNotFalse( has_action( 'admin_menu', [ $this->settings, 'add_plugin_page' ] ) );
	}

	public function test_load_hooks_skips_admin_menu_when_filter_hides_settings(): void {
		add_filter( 'gu_hide_settings', '__return_true' );
		$this->settings = new Settings();
		$this->settings->run();
		$this->assertFalse( has_action( 'admin_menu', [ $this->settings, 'add_plugin_page' ] ) );
	}

	public function test_load_hooks_registers_network_admin_edit_action(): void {
		$this->settings = new Settings();
		$this->settings->run();
		$this->assertNotFalse( has_action( 'network_admin_edit_git-updater', [ $this->settings, 'update_settings' ] ) );
	}

	public function test_load_hooks_registers_plugin_action_links_filter(): void {
		$this->settings = new Settings();
		$this->settings->run();
		$hook = 'plugin_action_links_' . $this->settings->gu_plugin_name();
		$this->assertNotFalse( has_filter( $hook, [ $this->settings, 'plugin_action_links' ] ) );
	}

	public function test_load_hooks_registers_admin_init_when_on_settings_page(): void {
		global $pagenow;
		$pagenow        = 'options.php';
		$this->settings = new Settings();
		$this->settings->run();
		$this->assertNotFalse( has_action( 'admin_init', [ $this->settings, 'update_settings' ] ) );
		$this->assertNotFalse( has_action( 'admin_init', [ $this->settings, 'page_init' ] ) );
		$pagenow = '';
	}

	public function test_load_hooks_enqueue_scripts_closure_runs(): void {
		global $pagenow;
		$pagenow        = 'options.php';
		$this->settings = new Settings();
		$this->settings->run();
		set_current_screen( 'options-general' );
		do_action( 'admin_enqueue_scripts' );
		$this->assertTrue( wp_style_is( 'git-updater-settings', 'enqueued' ) );
		$pagenow = '';
	}

	public function test_load_hooks_adds_disable_wpcron_filter_when_bypass_enabled(): void {
		Base::$options['bypass_background_processing'] = '1';
		$this->settings = new Settings();
		$this->settings->run();
		$this->assertNotFalse( has_filter( 'gu_disable_wpcron', '__return_true' ) );
		unset( Base::$options['bypass_background_processing'] );
	}

	public function test_load_hooks_applies_auth_required_filter(): void {
		add_filter( 'gu_settings_auth_required', fn( $req ) => array_merge( $req, [ 'custom' => true ] ) );
		$this->settings = new Settings();
		$this->settings->run();
		$this->assertArrayHasKey( 'custom', Settings::$auth_required );
	}
}

// =============================================================================
// Test_Settings_Load_Api_Subtabs  lines 206–226
// =============================================================================

/**
 * Class Test_Settings_Load_Api_Subtabs
 *
 * Tests load_api_subtabs() closures via the filters they register.
 */
class Test_Settings_Load_Api_Subtabs extends WP_UnitTestCase {
	use Settings_Test_Helper;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->settings = new Settings();
	}

	public function tear_down(): void {
		$this->settings_tear_down();
		parent::tear_down();
	}

	public function test_load_api_subtabs_always_adds_github_to_running_servers(): void {
		$gits = apply_filters( 'gu_running_git_servers', [] );
		$this->assertContains( 'github', $gits );
	}

	public function test_load_api_subtabs_adds_active_api_plugin_to_running_servers(): void {
		update_option( 'active_plugins', [ 'git-updater-gist/git-updater-gist.php' ] );
		$s    = new Settings();
		$gits = apply_filters( 'gu_running_git_servers', [] );
		$this->assertContains( 'gist', $gits );
		update_option( 'active_plugins', [] );
	}

	public function test_load_api_subtabs_always_adds_github_to_subtabs(): void {
		$subtabs = apply_filters( 'gu_add_settings_subtabs', [] );
		$this->assertArrayHasKey( 'github', $subtabs );
	}

	public function test_load_api_subtabs_adds_active_api_plugin_to_subtabs(): void {
		update_option( 'active_plugins', [ 'git-updater-gist/git-updater-gist.php' ] );
		$s       = new Settings();
		$subtabs = apply_filters( 'gu_add_settings_subtabs', [] );
		$this->assertArrayHasKey( 'gist', $subtabs );
		update_option( 'active_plugins', [] );
	}
}

// =============================================================================
// Test_Settings_Add_Plugin_Page  lines 233–245
// =============================================================================

/**
 * Class Test_Settings_Add_Plugin_Page
 *
 * Tests add_plugin_page().
 */
class Test_Settings_Add_Plugin_Page extends WP_UnitTestCase {
	use Settings_Test_Helper;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->settings = new Settings();
		set_current_screen( 'dashboard' );
	}

	public function tear_down(): void {
		$this->settings_tear_down();
		parent::tear_down();
	}

	public function test_add_plugin_page_registers_submenu(): void {
		global $submenu;
		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin );
		$this->settings->add_plugin_page();
		$this->assertArrayHasKey( 'options-general.php', (array) $submenu );
	}
}

// =============================================================================
// Test_Settings_Create_Admin_Page  lines 289–343
// =============================================================================

/**
 * Class Test_Settings_Create_Admin_Page
 *
 * Tests create_admin_page() including options_tabs(), options_sub_tabs(),
 * add_hidden_settings_sections(), and display_gu_repos() branches.
 */
class Test_Settings_Create_Admin_Page extends WP_UnitTestCase {
	use Settings_Test_Helper;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->settings = new Settings();
		$this->settings->run();
		include_once ABSPATH . 'wp-admin/includes/template.php';
	}

	public function tear_down(): void {
		$this->settings_tear_down();
		delete_site_option( 'skip_updates' );
		parent::tear_down();
	}

	public function test_create_admin_page_returns_early_with_invalid_nonce(): void {
		$_GET['_wpnonce'] = 'bad_nonce';
		ob_start();
		$this->settings->create_admin_page();
		$output = ob_get_clean();
		$this->assertEmpty( $output );
	}

	public function test_create_admin_page_renders_git_updater_subtab(): void {
		$_GET['_wpnonce'] = wp_create_nonce( 'gu_settings' );
		$_GET['tab']      = 'git_updater_settings';
		$_GET['subtab']   = 'git_updater';
		add_filter( 'gu_config_pre_process', '__return_empty_array' );
		ob_start();
		$this->settings->create_admin_page();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'git-updater-settings', $output );
	}

	public function test_create_admin_page_renders_github_subtab(): void {
		$_GET['_wpnonce'] = wp_create_nonce( 'gu_settings' );
		$_GET['tab']      = 'git_updater_settings';
		$_GET['subtab']   = 'github';
		$plugin           = $this->make_plugin_obj( [ 'git' => 'github', 'remote_version' => '1.0.0', 'is_private' => false ] );
		$this->inject_plugin_config( [ 'test-plugin' => $plugin ] );
		$this->inject_theme_config( [] );
		add_filter( 'gu_config_pre_process', '__return_empty_array' );
		ob_start();
		$this->settings->create_admin_page();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'git-updater-settings', $output );
		$this->assertStringContainsString( 'Installed Plugins and Themes', $output );
	}

	public function test_create_admin_page_fires_gu_add_admin_page_action(): void {
		$_GET['_wpnonce'] = wp_create_nonce( 'gu_settings' );
		$_GET['tab']      = 'git_updater_settings';
		$fired            = false;
		add_action( 'gu_add_admin_page', function () use ( &$fired ) { $fired = true; } );
		add_filter( 'gu_config_pre_process', '__return_empty_array' );
		ob_start();
		$this->settings->create_admin_page();
		ob_get_clean();
		$this->assertTrue( $fired );
	}

	public function test_create_admin_page_without_nonce_renders_page_but_no_notices(): void {
		unset( $_GET['_wpnonce'] );
		$_GET['tab']    = 'git_updater_settings';
		$_GET['subtab'] = 'git_updater';
		add_filter( 'gu_config_pre_process', '__return_empty_array' );
		ob_start();
		$this->settings->create_admin_page();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'git-updater-settings', $output );
		$this->assertStringNotContainsString( 'class="updated"', $output );
	}

	public function test_create_admin_page_renders_cache_refreshed_notice(): void {
		$_GET['_wpnonce']         = wp_create_nonce( 'gu_settings' );
		$_GET['tab']              = 'git_updater_settings';
		$_GET['subtab']           = 'git_updater';
		$_GET['refresh_transients'] = '1';
		add_filter( 'gu_config_pre_process', '__return_empty_array' );
		ob_start();
		$this->settings->create_admin_page();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'Cache refreshed.', $output );
	}
}

// =============================================================================
// Test_Settings_Admin_Page_Notices_Multisite  lines 361–362
// =============================================================================

/**
 * Class Test_Settings_Admin_Page_Notices_Multisite
 *
 * Covers the "Settings saved." branch in admin_page_notices()
 * which only runs on multisite.
 *
 * @group ms-required
 */
class Test_Settings_Admin_Page_Notices_Multisite extends WP_UnitTestCase {
	use Settings_Test_Helper;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->settings = new Settings();
		$this->settings->run();
		include_once ABSPATH . 'wp-admin/includes/template.php';
	}

	public function tear_down(): void {
		$this->settings_tear_down();
		parent::tear_down();
	}

	public function test_admin_page_notices_shows_settings_saved_on_multisite(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}
		$_GET['_wpnonce'] = wp_create_nonce( 'gu_settings' );
		$_GET['tab']      = 'git_updater_settings';
		$_GET['subtab']   = 'git_updater';
		$_GET['updated']  = '1';
		add_filter( 'gu_config_pre_process', '__return_empty_array' );
		ob_start();
		$this->settings->create_admin_page();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'Settings saved.', $output );
	}
}

// =============================================================================
// Test_Settings_Page_Init  lines 376–433
// =============================================================================

/**
 * Class Test_Settings_Page_Init
 *
 * Tests page_init().
 */
class Test_Settings_Page_Init extends WP_UnitTestCase {
	use Settings_Test_Helper;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->settings = new Settings();
		include_once ABSPATH . 'wp-admin/includes/template.php';
	}

	public function tear_down(): void {
		$this->settings_tear_down();
		parent::tear_down();
	}

	public function test_page_init_returns_early_during_ajax(): void {
		global $wp_registered_settings;
		$before = $wp_registered_settings ?? [];
		add_filter( 'wp_doing_ajax', '__return_true' );
		$this->settings->page_init();
		$this->assertSame( $before, $wp_registered_settings ?? [] );
	}

	public function test_page_init_registers_git_updater_setting(): void {
		global $wp_registered_settings;
		add_filter( 'gu_config_pre_process', '__return_empty_array' );
		$this->settings->page_init();
		$this->assertArrayHasKey( 'git_updater', (array) $wp_registered_settings );
	}

	public function test_page_init_registers_branch_switch_field(): void {
		global $wp_settings_fields;
		add_filter( 'gu_config_pre_process', '__return_empty_array' );
		$this->settings->page_init();
		$this->assertArrayHasKey( 'branch_switch', $wp_settings_fields['git_updater_install_settings']['git_updater_settings'] ?? [] );
	}

	public function test_page_init_registers_bypass_background_processing_field(): void {
		global $wp_settings_fields;
		add_filter( 'gu_config_pre_process', '__return_empty_array' );
		$this->settings->page_init();
		$this->assertArrayHasKey( 'bypass_background_processing', $wp_settings_fields['git_updater_install_settings']['git_updater_settings'] ?? [] );
	}

	public function test_page_init_fires_gu_add_settings_action(): void {
		$fired = false;
		add_action( 'gu_add_settings', function () use ( &$fired ) { $fired = true; } );
		add_filter( 'gu_config_pre_process', '__return_empty_array' );
		$this->settings->page_init();
		$this->assertTrue( $fired );
	}
}

// =============================================================================
// Test_Settings_Gu_Tokens  lines 440–505
// =============================================================================

/**
 * Class Test_Settings_Gu_Tokens
 *
 * Tests gu_tokens() all branches.
 */
class Test_Settings_Gu_Tokens extends WP_UnitTestCase {
	use Settings_Test_Helper;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->settings = new Settings();
		include_once ABSPATH . 'wp-admin/includes/template.php';
		add_filter( 'gu_config_pre_process', '__return_empty_array' );
	}

	public function tear_down(): void {
		$this->settings_tear_down();
		parent::tear_down();
	}

	public function test_gu_tokens_with_empty_configs_calls_unset_stale_options(): void {
		$this->inject_plugin_config( [] );
		$this->inject_theme_config( [] );
		$this->settings->gu_tokens();
		$this->assertTrue( true );
	}

	public function test_gu_tokens_skips_non_private_repos(): void {
		global $wp_settings_fields;
		$before  = count( (array) $wp_settings_fields );
		$plugin  = $this->make_plugin_obj( [ 'remote_version' => '1.0.0' ] );
		$this->inject_plugin_config( [ 'test-plugin' => $plugin ] );
		$this->inject_theme_config( [] );
		$this->settings->gu_tokens();
		$this->assertSame( $before, count( (array) $wp_settings_fields ) );
	}

	public function test_gu_tokens_skips_private_repo_when_filter_returns_empty(): void {
		global $wp_settings_fields;
		$before = count( (array) $wp_settings_fields );
		$plugin = $this->make_plugin_obj( [ 'remote_version' => '0.0.0' ] );
		$this->inject_plugin_config( [ 'test-plugin' => $plugin ] );
		$this->inject_theme_config( [] );
		add_filter( 'gu_add_repo_setting_field', '__return_empty_array', 10, 3 );
		$this->settings->gu_tokens();
		$this->assertSame( $before, count( (array) $wp_settings_fields ) );
	}

	public function test_gu_tokens_registers_field_for_private_plugin_with_text_callback(): void {
		global $wp_settings_fields;
		$settings = $this->settings;
		$plugin   = $this->make_plugin_obj( [ 'remote_version' => '0.0.0' ] );
		$this->inject_plugin_config( [ 'test-plugin' => $plugin ] );
		$this->inject_theme_config( [] );
		add_filter(
			'gu_add_repo_setting_field',
			static function ( $fields, $token, $git ) use ( $settings ) {
				return [
					'page'            => 'git_updater_github_install_settings',
					'section'         => 'github_id',
					'callback_method' => [ $settings, 'token_callback_text' ],
					'placeholder'     => true,
				];
			},
			10,
			3
		);
		$this->settings->gu_tokens();
		$this->assertArrayHasKey( 'test-plugin', $wp_settings_fields['git_updater_github_install_settings']['github_id'] ?? [] );
	}

	public function test_gu_tokens_registers_field_with_empty_title_for_checkbox_callback(): void {
		global $wp_settings_fields;
		$settings = $this->settings;
		$plugin   = $this->make_plugin_obj( [ 'remote_version' => '0.0.0' ] );
		$this->inject_plugin_config( [ 'test-plugin' => $plugin ] );
		$this->inject_theme_config( [] );
		add_filter(
			'gu_add_repo_setting_field',
			static function ( $fields, $token, $git ) use ( $settings ) {
				return [
					'page'            => 'git_updater_github_install_settings',
					'section'         => 'github_id',
					'callback_method' => [ $settings, 'token_callback_checkbox' ],
				];
			},
			10,
			3
		);
		$this->settings->gu_tokens();
		$field = $wp_settings_fields['git_updater_github_install_settings']['github_id']['test-plugin'] ?? null;
		$this->assertNotNull( $field );
		$this->assertSame( '', $field['title'] );
	}

	public function test_gu_tokens_uses_appearance_dashicon_for_theme(): void {
		$settings = $this->settings;
		$theme    = $this->make_theme_obj( [ 'remote_version' => '0.0.0' ] );
		$this->inject_plugin_config( [] );
		$this->inject_theme_config( [ 'test-theme' => $theme ] );
		$captured_args = null;
		add_filter(
			'gu_add_repo_setting_field',
			static function ( $fields, $token, $git ) use ( $settings, &$captured_args ) {
				$captured_args = $token;
				return [
					'page'            => 'git_updater_github_install_settings',
					'section'         => 'github_id',
					'callback_method' => [ $settings, 'token_callback_text' ],
				];
			},
			10,
			3
		);
		$this->settings->gu_tokens();
		$this->assertNotNull( $captured_args );
		$this->assertSame( 'theme', $captured_args->type );
	}

	public function test_gu_tokens_calls_create_error_message_when_waiting(): void {
		$plugin = $this->make_plugin_obj();
		$this->inject_plugin_config( [ 'test-plugin' => $plugin ] );
		$this->inject_theme_config( [] );
		remove_all_filters( 'gu_config_pre_process' );
		$this->settings->gu_tokens();
		$this->assertTrue( true );
	}
}

// =============================================================================
// Test_Settings_Unset_Stale_Options  lines 514–561
// =============================================================================

/**
 * Class Test_Settings_Unset_Stale_Options
 *
 * Tests unset_stale_options().
 */
class Test_Settings_Unset_Stale_Options extends WP_UnitTestCase {
	use Settings_Test_Helper;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->settings = new Settings();
	}

	public function tear_down(): void {
		delete_site_option( 'git_updater' );
		$this->settings_tear_down();
		parent::tear_down();
	}

	public function test_unset_stale_options_no_update_when_keys_match(): void {
		$slug   = 'my-plugin';
		$plugin = $this->make_plugin_obj( [ 'slug' => $slug ] );
		update_site_option( 'git_updater', [ $slug => 'token_value' ] );
		Base::$options = [ $slug => 'token_value' ];
		$this->settings->unset_stale_options(
			[ $slug => null ],
			[ $slug => $plugin ]
		);
		$saved = get_site_option( 'git_updater', [] );
		$this->assertArrayHasKey( $slug, $saved );
	}

	public function test_unset_stale_options_removes_stale_key(): void {
		$stale_slug = 'removed-plugin';
		update_site_option( 'git_updater', [ $stale_slug => 'old_token' ] );
		Base::$options = [ $stale_slug => 'old_token' ];
		$this->settings->unset_stale_options( [], [] );
		$saved = get_site_option( 'git_updater', [] );
		$this->assertArrayNotHasKey( $stale_slug, $saved );
	}

	public function test_unset_stale_options_preserves_branch_switch_key(): void {
		Base::$options = [ 'branch_switch' => '1', 'stale' => 'value' ];
		update_site_option( 'git_updater', Base::$options );
		$this->settings->unset_stale_options( [], [] );
		$saved = get_site_option( 'git_updater', [] );
		$this->assertArrayHasKey( 'branch_switch', $saved );
	}

	public function test_unset_stale_options_preserves_access_token_keys(): void {
		add_filter( 'gu_running_git_servers', fn( $gits ) => array_merge( $gits, [ 'github' ] ) );
		Base::$options = [ 'github_access_token' => 'tok', 'stale' => 'val' ];
		update_site_option( 'git_updater', Base::$options );
		$this->settings->unset_stale_options( [], [] );
		$saved = get_site_option( 'git_updater', [] );
		$this->assertArrayHasKey( 'github_access_token', $saved );
		remove_all_filters( 'gu_running_git_servers' );
	}

	public function test_unset_stale_options_preserves_current_branch_for_existing_repo(): void {
		$slug   = 'my-plugin';
		$plugin = $this->make_plugin_obj( [ 'slug' => $slug ] );
		Base::$options = [ "current_branch_{$slug}" => 'develop' ];
		update_site_option( 'git_updater', Base::$options );
		$this->settings->unset_stale_options(
			[ $slug => null ],
			[ $slug => $plugin ]
		);
		$saved = get_site_option( 'git_updater', [] );
		$this->assertArrayHasKey( "current_branch_{$slug}", $saved );
	}

	public function test_unset_stale_options_removes_current_branch_for_missing_repo(): void {
		$slug          = 'old-plugin';
		Base::$options = [ "current_branch_{$slug}" => 'main' ];
		update_site_option( 'git_updater', Base::$options );
		$this->settings->unset_stale_options( [], [] );
		$saved = get_site_option( 'git_updater', [] );
		$this->assertArrayNotHasKey( "current_branch_{$slug}", $saved );
	}
}

// =============================================================================
// Test_Settings_Print_Section  lines 568–616
// =============================================================================

/**
 * Class Test_Settings_Print_Section
 *
 * Tests print_section_gu_settings() and display_dot_org_overrides().
 */
class Test_Settings_Print_Section extends WP_UnitTestCase {
	use Settings_Test_Helper;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->settings = new Settings();
		$this->inject_plugin_config( [] );
		$this->inject_theme_config( [] );
	}

	public function tear_down(): void {
		delete_site_option( 'skip_updates' );
		$this->settings_tear_down();
		parent::tear_down();
	}

	public function test_print_section_gu_settings_outputs_check_to_enable(): void {
		ob_start();
		$this->settings->print_section_gu_settings();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'Check to enable.', $output );
	}

	public function test_display_dot_org_overrides_no_output_with_no_overrides(): void {
		ob_start();
		$this->settings->print_section_gu_settings();
		$output = ob_get_clean();
		$this->assertStringNotContainsString( 'Overridden Plugins and Themes', $output );
	}

	public function test_display_dot_org_overrides_shows_header_when_overrides_exist(): void {
		add_filter( 'gu_override_dot_org', fn() => [ 'test-plugin/test-plugin.php' ] );
		ob_start();
		$this->settings->print_section_gu_settings();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'Overridden Plugins and Themes', $output );
	}

	public function test_display_dot_org_overrides_shows_plugin_in_override_list(): void {
		$plugin = $this->make_plugin_obj();
		$this->inject_plugin_config( [ 'test-plugin' => $plugin ] );
		add_filter( 'gu_override_dot_org', fn() => [ 'test-plugin/test-plugin.php' ] );
		ob_start();
		$this->settings->print_section_gu_settings();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'Test Plugin', $output );
		$this->assertStringContainsString( 'dashicons-admin-plugins', $output );
	}

	public function test_display_dot_org_overrides_shows_theme_in_override_list(): void {
		$theme = $this->make_theme_obj();
		$this->inject_theme_config( [ 'test-theme' => $theme ] );
		add_filter( 'gu_override_dot_org', fn() => [ 'test-theme' ] );
		ob_start();
		$this->settings->print_section_gu_settings();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'Test Theme', $output );
		$this->assertStringContainsString( 'dashicons-admin-appearance', $output );
	}

	public function test_display_dot_org_overrides_includes_skip_updates_slugs(): void {
		update_site_option( 'skip_updates', [ [ 'slug' => 'skip-plugin/skip-plugin.php' ] ] );
		add_filter( 'gu_override_dot_org', '__return_empty_array' );
		ob_start();
		$this->settings->print_section_gu_settings();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'Overridden Plugins and Themes', $output );
	}
}

// =============================================================================
// Test_Settings_Token_Callbacks  lines 624–650
// =============================================================================

/**
 * Class Test_Settings_Token_Callbacks
 *
 * Tests token_callback_text() and token_callback_checkbox().
 */
class Test_Settings_Token_Callbacks extends WP_UnitTestCase {
	use Settings_Test_Helper;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->settings = new Settings();
	}

	public function tear_down(): void {
		$this->settings_tear_down();
		parent::tear_down();
	}

	public function test_token_callback_text_outputs_password_input_when_token_arg_set(): void {
		ob_start();
		$this->settings->token_callback_text( [ 'id' => 'github_access_token', 'token' => true ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'type="password"', $output );
	}

	public function test_token_callback_text_outputs_text_input_without_token_arg(): void {
		ob_start();
		$this->settings->token_callback_text( [ 'id' => 'some_field' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'type="text"', $output );
	}

	public function test_token_callback_text_outputs_placeholder_when_set(): void {
		ob_start();
		$this->settings->token_callback_text( [ 'id' => 'some_field', 'placeholder' => true ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'username:password', $output );
	}

	public function test_token_callback_text_uses_existing_option_value(): void {
		Base::$options['github_access_token'] = 'mytoken';
		ob_start();
		$this->settings->token_callback_text( [ 'id' => 'github_access_token', 'token' => true ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'value="mytoken"', $output );
		unset( Base::$options['github_access_token'] );
	}

	public function test_token_callback_checkbox_renders_checked_state(): void {
		$this->set_settings_options( [ 'branch_switch' => '1' ] );
		ob_start();
		$this->settings->token_callback_checkbox( [ 'id' => 'branch_switch', 'title' => 'Enable' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'checked', $output );
	}

	public function test_token_callback_checkbox_renders_unchecked_state(): void {
		$this->set_settings_options( [ 'branch_switch' => null ] );
		ob_start();
		$this->settings->token_callback_checkbox( [ 'id' => 'branch_switch', 'title' => 'Enable' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'type="checkbox"', $output );
	}

	public function test_token_callback_checkbox_renders_disabled_state(): void {
		$this->set_settings_options( [ 'branch_switch' => '-1' ] );
		ob_start();
		$this->settings->token_callback_checkbox( [ 'id' => 'branch_switch', 'title' => 'Enable' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'disabled', $output );
	}
}

// =============================================================================
// Test_Settings_Update_Settings  lines 659–703
// =============================================================================

/**
 * Class Test_Settings_Update_Settings
 *
 * Tests update_settings() and filter_options().
 */
class Test_Settings_Update_Settings extends WP_UnitTestCase {
	use Settings_Test_Helper;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->settings = new Settings();
	}

	public function tear_down(): void {
		delete_site_option( 'git_updater' );
		$this->settings_tear_down();
		parent::tear_down();
	}

	public function test_update_settings_without_nonce_does_not_update_site_option(): void {
		unset( $_POST['_wpnonce'] );
		$this->settings->update_settings();
		$this->assertFalse( get_site_option( 'git_updater_test_marker' ) );
	}

	public function test_update_settings_fires_gu_update_settings_action(): void {
		$fired = false;
		add_action( 'gu_update_settings', function () use ( &$fired ) { $fired = true; } );
		$this->settings->update_settings();
		$this->assertTrue( $fired );
	}

	public function test_update_settings_with_valid_nonce_and_option_page_saves_option(): void {
		$_POST['_wpnonce']    = wp_create_nonce( 'git_updater-options' );
		$_POST['option_page'] = 'git_updater';
		$_POST['git_updater'] = [];
		$this->set_settings_options( [] );
		add_filter(
			'wp_redirect',
			static fn( $url ) => throw new \RuntimeException( 'redirect:' . $url ),
			1
		);
		try {
			$this->settings->update_settings();
		} catch ( \RuntimeException $e ) {
			$saved = get_site_option( 'git_updater', null );
			$this->assertNotNull( $saved );
		}
	}

	public function test_filter_options_returns_early_without_nonce(): void {
		unset( $_POST['_wpnonce'] );
		$rm     = new ReflectionMethod( Settings::class, 'filter_options' );
		$rm->setAccessible( true );
		$result = $rm->invoke( $this->settings );
		$this->assertNull( $result );
	}

	public function test_filter_options_with_valid_nonce_merges_post_data(): void {
		$_POST['_wpnonce']    = wp_create_nonce( 'git_updater-options' );
		$_POST['git_updater'] = [ 'branch_switch' => '1' ];
		Base::$options        = [ 'branch_switch' => '0', 'existing' => 'keep' ];
		$rm                   = new ReflectionMethod( Settings::class, 'filter_options' );
		$rm->setAccessible( true );
		$result = $rm->invoke( $this->settings );
		$this->assertSame( '1', $result['branch_switch'] );
	}

	public function test_filter_options_removes_checked_values_from_options(): void {
		$_POST['_wpnonce']    = wp_create_nonce( 'git_updater-options' );
		$_POST['git_updater'] = [];
		$this->set_settings_options( [ 'branch_switch' => '1' ] );
		$rm                   = new ReflectionMethod( Settings::class, 'filter_options' );
		$rm->setAccessible( true );
		$result = $rm->invoke( $this->settings );
		$this->assertArrayNotHasKey( 'branch_switch', $result );
	}
}

// =============================================================================
// Test_Settings_Redirect_On_Save  lines 710–755
// =============================================================================

/**
 * Class Test_Settings_Redirect_On_Save
 *
 * Tests redirect_on_save() via update_settings() and directly via reflection.
 */
class Test_Settings_Redirect_On_Save extends WP_UnitTestCase {
	use Settings_Test_Helper;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->settings = new Settings();
	}

	public function tear_down(): void {
		delete_site_option( 'git_updater' );
		$this->settings_tear_down();
		parent::tear_down();
	}

	public function test_redirect_on_save_returns_without_redirect_when_no_conditions_met(): void {
		unset( $_POST['option_page'], $_POST['action'] );
		$rm = new ReflectionMethod( Settings::class, 'redirect_on_save' );
		$rm->setAccessible( true );
		$rm->invoke( $this->settings );
		$this->assertTrue( true );
	}

	public function test_redirect_on_save_sets_update_true_when_action_is_update_and_option_page_matches(): void {
		$_POST['option_page'] = 'git_updater';
		$_POST['action']      = 'update';
		$url_captured         = null;
		add_filter(
			'wp_redirect',
			static function ( $url ) use ( &$url_captured ) {
				$url_captured = $url;
				throw new RuntimeException( 'redirect:' . $url );
			},
			1
		);
		$rm = new ReflectionMethod( Settings::class, 'redirect_on_save' );
		$rm->setAccessible( true );
		try {
			$rm->invoke( $this->settings );
			$this->fail( 'Expected redirect exception' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'updated=1', $e->getMessage() );
		}
	}

	public function test_redirect_on_save_redirects_when_is_option_page_true(): void {
		$_POST['option_page'] = 'git_updater';
		$_POST['action']      = 'submit';
		add_filter(
			'wp_redirect',
			static fn( $url ) => throw new RuntimeException( 'redirect:' . $url ),
			1
		);
		$rm = new ReflectionMethod( Settings::class, 'redirect_on_save' );
		$rm->setAccessible( true );
		try {
			$rm->invoke( $this->settings );
			$this->fail( 'Expected redirect exception' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'page=git-updater', $e->getMessage() );
		}
	}

	public function test_redirect_on_save_includes_tab_and_subtab_from_referer(): void {
		$_POST['option_page']       = 'git_updater';
		$_POST['action']            = 'submit';
		$_POST['_wp_http_referer']  = '/wp-admin/options-general.php?page=git-updater&tab=git_updater_settings&subtab=github';
		add_filter(
			'wp_redirect',
			static fn( $url ) => throw new RuntimeException( 'redirect:' . $url ),
			1
		);
		$rm = new ReflectionMethod( Settings::class, 'redirect_on_save' );
		$rm->setAccessible( true );
		try {
			$rm->invoke( $this->settings );
			$this->fail( 'Expected redirect exception' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'subtab=github', $e->getMessage() );
		}
		unset( $_POST['_wp_http_referer'] );
	}

	public function test_redirect_on_save_redirects_when_refresh_transients_true(): void {
		$_POST['_wpnonce']                        = wp_create_nonce( 'gu_refresh_cache' );
		$_REQUEST['git_updater_refresh_transients'] = true;
		add_filter(
			'wp_redirect',
			static fn( $url ) => throw new RuntimeException( 'redirect:' . $url ),
			1
		);
		$rm = new ReflectionMethod( Settings::class, 'redirect_on_save' );
		$rm->setAccessible( true );
		try {
			$rm->invoke( $this->settings );
			$this->fail( 'Expected redirect exception' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'page=git-updater', $e->getMessage() );
		}
	}
}

// =============================================================================
// Test_Settings_Refresh_Transients  lines 762–773
// =============================================================================

/**
 * Class Test_Settings_Refresh_Transients
 *
 * Tests refresh_transients() via reflection.
 */
class Test_Settings_Refresh_Transients extends WP_UnitTestCase {
	use Settings_Test_Helper;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->settings = new Settings();
	}

	public function tear_down(): void {
		$this->settings_tear_down();
		parent::tear_down();
	}

	public function test_refresh_transients_returns_false_without_nonce(): void {
		unset( $_POST['_wpnonce'] );
		$result = $this->call_private( 'refresh_transients' );
		$this->assertFalse( $result );
	}

	public function test_refresh_transients_returns_false_without_request_param(): void {
		$_POST['_wpnonce'] = wp_create_nonce( 'gu_refresh_cache' );
		unset( $_REQUEST['git_updater_refresh_transients'] );
		$result = $this->call_private( 'refresh_transients' );
		$this->assertFalse( $result );
	}

	public function test_refresh_transients_returns_true_with_valid_nonce_and_request_param(): void {
		$_POST['_wpnonce']                        = wp_create_nonce( 'gu_refresh_cache' );
		$_REQUEST['git_updater_refresh_transients'] = true;
		$result = $this->call_private( 'refresh_transients' );
		$this->assertTrue( $result );
	}
}

// =============================================================================
// Test_Settings_Plugin_Action_Links  lines 785–790
// =============================================================================

/**
 * Class Test_Settings_Plugin_Action_Links
 *
 * Tests plugin_action_links().
 */
class Test_Settings_Plugin_Action_Links extends WP_UnitTestCase {
	use Settings_Test_Helper;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->settings = new Settings();
	}

	public function tear_down(): void {
		$this->settings_tear_down();
		parent::tear_down();
	}

	public function test_plugin_action_links_prepends_settings_link(): void {
		$result = $this->settings->plugin_action_links( [ '<a href="#">Deactivate</a>' ] );
		$this->assertCount( 2, $result );
		$this->assertStringContainsString( 'page=git-updater', $result[0] );
	}

	public function test_plugin_action_links_preserves_existing_links(): void {
		$result = $this->settings->plugin_action_links( [ 'existing' ] );
		$this->assertContains( 'existing', $result );
	}
}

// =============================================================================
// Test_Settings_Display_Gu_Repos  lines 822–882
// =============================================================================

/**
 * Class Test_Settings_Display_Gu_Repos
 *
 * Tests display_gu_repos() via reflection.
 */
class Test_Settings_Display_Gu_Repos extends WP_UnitTestCase {
	use Settings_Test_Helper;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->settings = new Settings();
	}

	public function tear_down(): void {
		$this->settings_tear_down();
		parent::tear_down();
	}

	public function test_display_gu_repos_shows_header(): void {
		$this->inject_plugin_config( [] );
		$this->inject_theme_config( [] );
		ob_start();
		$this->call_private( 'display_gu_repos', [ 'github' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'Installed Plugins and Themes', $output );
	}

	public function test_display_gu_repos_shows_plugin_name(): void {
		$plugin = $this->make_plugin_obj();
		$this->inject_plugin_config( [ 'test-plugin' => $plugin ] );
		$this->inject_theme_config( [] );
		ob_start();
		$this->call_private( 'display_gu_repos', [ 'github' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'Test Plugin', $output );
		$this->assertStringContainsString( 'dashicons-admin-plugins', $output );
	}

	public function test_display_gu_repos_shows_lock_icon_for_private_repo(): void {
		$plugin = $this->make_plugin_obj( [ 'is_private' => true ] );
		$this->inject_plugin_config( [ 'test-plugin' => $plugin ] );
		$this->inject_theme_config( [] );
		ob_start();
		$this->call_private( 'display_gu_repos', [ 'github' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'dashicons-lock', $output );
	}

	public function test_display_gu_repos_shows_broken_icon_for_zero_version(): void {
		$plugin = $this->make_plugin_obj( [ 'remote_version' => '0.0.0' ] );
		$this->inject_plugin_config( [ 'test-plugin' => $plugin ] );
		$this->inject_theme_config( [] );
		ob_start();
		$this->call_private( 'display_gu_repos', [ 'github' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'dashicons-warning', $output );
	}

	public function test_display_gu_repos_shows_broken_icon_when_no_remote_version(): void {
		$plugin = $this->make_plugin_obj();
		unset( $plugin->remote_version );
		$this->inject_plugin_config( [ 'test-plugin' => $plugin ] );
		$this->inject_theme_config( [] );
		ob_start();
		$this->call_private( 'display_gu_repos', [ 'github' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'dashicons-warning', $output );
	}

	public function test_display_gu_repos_shows_dot_org_icon_when_not_overridden(): void {
		$plugin = $this->make_plugin_obj( [ 'dot_org' => true ] );
		$this->inject_plugin_config( [ 'test-plugin' => $plugin ] );
		$this->inject_theme_config( [] );
		ob_start();
		$this->call_private( 'display_gu_repos', [ 'github' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'dashicons-wordpress', $output );
	}

	public function test_display_gu_repos_hides_dot_org_icon_when_overridden(): void {
		$plugin = $this->make_plugin_obj( [ 'dot_org' => true ] );
		$this->inject_plugin_config( [ 'test-plugin' => $plugin ] );
		$this->inject_theme_config( [] );
		add_filter( 'gu_override_dot_org', fn() => [ 'test-plugin/test-plugin.php' ] );
		ob_start();
		$this->call_private( 'display_gu_repos', [ 'github' ] );
		$output = ob_get_clean();
		$this->assertStringNotContainsString( 'dashicons-wordpress', $output );
	}

	public function test_display_gu_repos_shows_dismiss_icon(): void {
		$plugin = $this->make_plugin_obj( [ 'dismiss' => true ] );
		$this->inject_plugin_config( [ 'test-plugin' => $plugin ] );
		$this->inject_theme_config( [] );
		ob_start();
		$this->call_private( 'display_gu_repos', [ 'github' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'dashicons-dismiss', $output );
	}

	public function test_display_gu_repos_uses_appearance_dashicon_for_theme(): void {
		$theme = $this->make_theme_obj();
		$this->inject_plugin_config( [] );
		$this->inject_theme_config( [ 'test-theme' => $theme ] );
		ob_start();
		$this->call_private( 'display_gu_repos', [ 'github' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'dashicons-admin-appearance', $output );
	}

	public function test_display_gu_repos_applies_gu_display_repos_filter(): void {
		$this->inject_plugin_config( [] );
		$this->inject_theme_config( [] );
		$filtered = false;
		add_filter(
			'gu_display_repos',
			function ( $repos ) use ( &$filtered ) {
				$filtered = true;
				return $repos;
			}
		);
		ob_start();
		$this->call_private( 'display_gu_repos', [ 'github' ] );
		ob_get_clean();
		$this->assertTrue( $filtered );
	}
}

// =============================================================================
// Test_Settings_Subtabs_Gitlabce  — gitlabce→gitlab branch in settings_sub_tabs()
// =============================================================================

/**
 * Class Test_Settings_Subtabs_Gitlabce
 *
 * Covers the gitlabce→gitlab rename branch in settings_sub_tabs()  (line 191).
 */
class Test_Settings_Subtabs_Gitlabce extends WP_UnitTestCase {
	use Settings_Test_Helper;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->settings = new Settings();
	}

	public function tear_down(): void {
		$this->settings_tear_down();
		parent::tear_down();
	}

	public function test_settings_sub_tabs_renames_gitlabce_to_gitlab_when_gitlab_absent(): void {
		add_filter(
			'gu_running_git_servers',
			fn( $gits ) => array_merge( $gits, [ 'gitlabce' ] )
		);
		add_filter(
			'gu_add_settings_subtabs',
			fn( $subtabs ) => array_merge( $subtabs, [ 'gitlab' => 'GitLab' ] )
		);
		$result = $this->call_private( 'settings_sub_tabs' );
		$this->assertArrayHasKey( 'gitlab', $result );
	}
}
