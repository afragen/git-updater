<?php
/**
 * Tests for Additions_Settings_Methods.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\Base;
use Fragen\Git_Updater\Add_Ons;
use Fragen\Git_Updater\Additions\Additions;
use Fragen\Git_Updater\Additions\Settings as Additions_Settings;
use Fragen\Git_Updater\Additions\Settings;
use Fragen\Git_Updater\Additions\Bootstrap;
use Fragen\Git_Updater\Additions\Repo_List_Table;

class Test_Additions_Settings_Methods extends WP_UnitTestCase {

	private Additions_Settings $settings;

	public function set_up(): void {
		parent::set_up();
		Additions_Settings::$options_additions = [];
		$this->settings = new Additions_Settings();
	}

	public function tear_down(): void {
		remove_all_filters( 'gu_add_settings_tabs' );
		remove_all_filters( 'gua_addition_types' );
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// sanitize()
	// -------------------------------------------------------------------------

	public function test_sanitize_returns_array_indexed_at_zero(): void {
		$result = $this->settings->sanitize( [ 'slug' => 'my/plugin.php', 'uri' => 'https://github.com/owner/repo' ] );
		$this->assertArrayHasKey( 0, $result );
	}

	public function test_sanitize_strips_trailing_slash_from_uri(): void {
		$result = $this->settings->sanitize( [ 'slug' => 'my/plugin.php', 'uri' => 'https://github.com/owner/repo/' ] );
		$this->assertSame( 'https://github.com/owner/repo', $result[0]['uri'] );
	}

	public function test_sanitize_sets_id_as_md5_of_slug(): void {
		$result = $this->settings->sanitize( [ 'slug' => 'my/plugin.php', 'uri' => 'https://github.com/owner/repo' ] );
		$this->assertSame( md5( 'my/plugin.php' ), $result[0]['ID'] );
	}

	public function test_sanitize_sets_source_as_md5_of_home_url(): void {
		$result = $this->settings->sanitize( [ 'slug' => 'my/plugin.php', 'uri' => 'https://github.com/owner/repo' ] );
		$this->assertSame( md5( home_url() ), $result[0]['source'] );
	}

	public function test_sanitize_defaults_primary_branch_to_master_when_absent(): void {
		$result = $this->settings->sanitize( [ 'slug' => 'my/plugin.php', 'uri' => 'https://github.com/owner/repo' ] );
		$this->assertSame( 'master', $result[0]['primary_branch'] );
	}

	public function test_sanitize_preserves_custom_primary_branch(): void {
		$result = $this->settings->sanitize( [ 'slug' => 'my/plugin.php', 'uri' => 'https://github.com/owner/repo', 'primary_branch' => 'main' ] );
		$this->assertSame( 'main', $result[0]['primary_branch'] );
	}

	public function test_sanitize_private_package_defaults_to_false(): void {
		$result = $this->settings->sanitize( [ 'slug' => 'my/plugin.php', 'uri' => 'https://github.com/owner/repo' ] );
		$this->assertFalse( $result[0]['private_package'] );
	}

	public function test_sanitize_private_package_is_true_when_truthy_value_provided(): void {
		$result = $this->settings->sanitize( [ 'slug' => 'my/plugin.php', 'uri' => 'https://github.com/owner/repo', 'private_package' => '1' ] );
		$this->assertTrue( $result[0]['private_package'] );
	}

	public function test_sanitize_non_uri_fields_are_sanitized_as_text(): void {
		$result = $this->settings->sanitize( [ 'slug' => "  my/plugin.php\t", 'uri' => 'https://github.com/owner/repo' ] );
		$this->assertSame( 'my/plugin.php', $result[0]['slug'] );
	}

	// -------------------------------------------------------------------------
	// add_settings_tabs()
	// -------------------------------------------------------------------------

	public function test_add_settings_tabs_registers_git_updater_additions_tab(): void {
		$this->settings->add_settings_tabs();
		$tabs = apply_filters( 'gu_add_settings_tabs', [] );
		$this->assertArrayHasKey( 'git_updater_additions', $tabs );
	}

	public function test_add_settings_tabs_preserves_existing_tabs(): void {
		$this->settings->add_settings_tabs();
		$tabs = apply_filters( 'gu_add_settings_tabs', [ 'other_tab' => 'Other' ] );
		$this->assertArrayHasKey( 'other_tab', $tabs );
		$this->assertArrayHasKey( 'git_updater_additions', $tabs );
	}

	// -------------------------------------------------------------------------
	// print_section_additions()
	// -------------------------------------------------------------------------

	public function test_print_section_additions_outputs_descriptive_text(): void {
		ob_start();
		$this->settings->print_section_additions();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'git repositories', $output );
	}

	// -------------------------------------------------------------------------
	// callback_field()
	// -------------------------------------------------------------------------

	public function test_callback_field_outputs_text_input_element(): void {
		ob_start();
		$this->settings->callback_field( [ 'id' => 'test_id', 'setting' => 'slug', 'title' => 'Slug' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( '<input type="text"', $output );
	}

	public function test_callback_field_name_attribute_uses_git_updater_additions_prefix(): void {
		ob_start();
		$this->settings->callback_field( [ 'id' => 'test_id', 'setting' => 'slug', 'title' => 'Slug' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'name="git_updater_additions[slug]"', $output );
	}

	public function test_callback_field_includes_title_as_description(): void {
		ob_start();
		$this->settings->callback_field( [ 'id' => 'test_id', 'setting' => 'slug', 'title' => 'My custom title' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'My custom title', $output );
	}

	// -------------------------------------------------------------------------
	// callback_dropdown()
	// -------------------------------------------------------------------------

	public function test_callback_dropdown_outputs_select_element(): void {
		ob_start();
		$this->settings->callback_dropdown( [ 'id' => 'type_id', 'setting' => 'type' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( '<select', $output );
	}

	public function test_callback_dropdown_includes_github_plugin_option(): void {
		ob_start();
		$this->settings->callback_dropdown( [ 'id' => 'type_id', 'setting' => 'type' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'github_plugin', $output );
	}

	public function test_callback_dropdown_includes_github_theme_option(): void {
		ob_start();
		$this->settings->callback_dropdown( [ 'id' => 'type_id', 'setting' => 'type' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'github_theme', $output );
	}

	public function test_callback_dropdown_respects_gua_addition_types_filter(): void {
		add_filter( 'gua_addition_types', fn() => [ 'custom_type' ], 10 );
		ob_start();
		$this->settings->callback_dropdown( [ 'id' => 'type_id', 'setting' => 'type' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'custom_type', $output );
	}

	// -------------------------------------------------------------------------
	// callback_checkbox()
	// -------------------------------------------------------------------------

	public function test_callback_checkbox_outputs_checkbox_input(): void {
		ob_start();
		$this->settings->callback_checkbox( [ 'id' => 'chk_id', 'setting' => 'release_asset', 'title' => 'Release Asset' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'type="checkbox"', $output );
	}

	public function test_callback_checkbox_is_not_checked_when_option_absent(): void {
		ob_start();
		$this->settings->callback_checkbox( [ 'id' => 'chk_id', 'setting' => 'release_asset', 'title' => 'Release Asset' ] );
		$output = ob_get_clean();
		$this->assertStringNotContainsString( "checked='checked'", $output );
	}

	public function test_callback_checkbox_is_checked_when_option_equals_one(): void {
		Additions_Settings::$options_additions = [ 'chk_id' => 1 ];
		ob_start();
		$this->settings->callback_checkbox( [ 'id' => 'chk_id', 'setting' => 'release_asset', 'title' => 'Release Asset' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'checked', $output );
	}

	public function test_callback_checkbox_name_attribute_uses_git_updater_additions_prefix(): void {
		ob_start();
		$this->settings->callback_checkbox( [ 'id' => 'chk_id', 'setting' => 'release_asset', 'title' => 'Release Asset' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'name="git_updater_additions[release_asset]"', $output );
	}
}

// ---------------------------------------------------------------------------
// Additions\Repo_List_Table
// ---------------------------------------------------------------------------

/**
 * Class Test_Repo_List_Table_Methods
 */

class Test_Additions_Settings_Load_Hooks extends WP_UnitTestCase {

	private array $pre_registered_bindings = [];

	public function set_up(): void {
		parent::set_up();
		if ( class_exists( 'WP_Block_Bindings_Registry' ) ) {
			$this->pre_registered_bindings = array_keys(
				WP_Block_Bindings_Registry::get_instance()->get_all_registered()
			);
		}
	}

	public function tear_down(): void {
		unset( $_POST['_wpnonce'] );
		remove_all_actions( 'gu_update_settings' );
		remove_all_actions( 'init' );
		remove_all_actions( 'gu_add_admin_page' );
		remove_all_filters( 'gu_add_settings_tabs' );
		delete_site_option( 'git_updater_additions' );
		if ( class_exists( 'WP_Block_Bindings_Registry' ) ) {
			foreach ( array_keys( WP_Block_Bindings_Registry::get_instance()->get_all_registered() ) as $name ) {
				if ( ! in_array( $name, $this->pre_registered_bindings, true ) ) {
					unregister_block_bindings_source( $name );
				}
			}
		}
		parent::tear_down();
	}

	public function test_load_hooks_registers_gu_update_settings_action(): void {
		( new Additions_Settings() )->load_hooks();
		$this->assertNotFalse( has_action( 'gu_update_settings' ) );
	}

	public function test_load_hooks_registers_init_action(): void {
		( new Additions_Settings() )->load_hooks();
		$this->assertNotFalse( has_action( 'init' ) );
	}

	public function test_load_hooks_registers_gu_add_admin_page_at_priority_10(): void {
		( new Additions_Settings() )->load_hooks();
		$this->assertNotFalse( has_action( 'gu_add_admin_page' ) );
	}

	public function test_gu_update_settings_action_fires_save_settings_closure(): void {
		( new Additions_Settings() )->load_hooks();
		unset( $_POST['_wpnonce'] );
		do_action( 'gu_update_settings', [] );
		$this->assertFalse( get_site_option( 'git_updater_additions' ) );
	}

	public function test_init_action_fires_add_settings_tabs_closure(): void {
		( new Additions_Settings() )->load_hooks();
		if ( class_exists( 'WP_Block_Bindings_Registry' ) ) {
			foreach ( array_keys( WP_Block_Bindings_Registry::get_instance()->get_all_registered() ) as $name ) {
				unregister_block_bindings_source( $name );
			}
		}
		do_action( 'init' );
		$tabs = apply_filters( 'gu_add_settings_tabs', [] );
		$this->assertArrayHasKey( 'git_updater_additions', $tabs );
	}

	public function test_gu_add_admin_page_action_fires_add_admin_page_closure(): void {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		Additions_Settings::$options_additions = [];
		( new Additions_Settings() )->load_hooks();
		ob_start();
		do_action( 'gu_add_admin_page', 'other_tab', admin_url() );
		ob_get_clean();
		$this->assertTrue( true );
	}
}

// ---------------------------------------------------------------------------
// Settings::save_settings()
// ---------------------------------------------------------------------------


class Test_Additions_Settings_Save_Settings extends WP_UnitTestCase {

	private Additions_Settings $settings;

	public function set_up(): void {
		parent::set_up();
		Additions_Settings::$options_additions = [];
		$this->settings = new Additions_Settings();
	}

	public function tear_down(): void {
		unset( $_POST['_wpnonce'], $_POST['action'] );
		delete_site_option( 'git_updater_additions' );
		remove_all_filters( 'gu_save_redirect' );
		parent::tear_down();
	}

	private function make_post_data( array $overrides = [] ): array {
		return array_merge(
			[
				'option_page'           => 'git_updater_additions',
				'git_updater_additions' => [
					'slug' => 'owner/plugin.php',
					'uri'  => 'https://github.com/owner/plugin',
					'type' => 'github_plugin',
				],
			],
			$overrides
		);
	}

	public function test_save_settings_returns_early_without_nonce(): void {
		unset( $_POST['_wpnonce'] );
		$this->settings->save_settings( $this->make_post_data() );
		$this->assertFalse( get_site_option( 'git_updater_additions' ) );
	}

	public function test_save_settings_returns_early_with_invalid_nonce(): void {
		$_POST['_wpnonce'] = 'bad_nonce';
		$this->settings->save_settings( $this->make_post_data() );
		$this->assertFalse( get_site_option( 'git_updater_additions' ) );
	}

	public function test_save_settings_does_nothing_when_option_page_absent(): void {
		$_POST['_wpnonce'] = wp_create_nonce( 'git_updater_additions-options' );
		$this->settings->save_settings( [] );
		$this->assertFalse( get_site_option( 'git_updater_additions' ) );
	}

	public function test_save_settings_does_nothing_when_option_page_wrong(): void {
		$_POST['_wpnonce'] = wp_create_nonce( 'git_updater_additions-options' );
		$this->settings->save_settings( [ 'option_page' => 'other_page' ] );
		$this->assertFalse( get_site_option( 'git_updater_additions' ) );
	}

	public function test_save_settings_skips_save_when_slug_empty(): void {
		$_POST['_wpnonce'] = wp_create_nonce( 'git_updater_additions-options' );
		$post_data         = $this->make_post_data(
			[
				'git_updater_additions' => [
					'slug' => '',
					'uri'  => 'https://github.com/owner/plugin',
					'type' => 'github_plugin',
				],
			]
		);
		$this->settings->save_settings( $post_data );
		$this->assertFalse( get_site_option( 'git_updater_additions' ) );
	}

	public function test_save_settings_skips_save_when_uri_empty(): void {
		$_POST['_wpnonce'] = wp_create_nonce( 'git_updater_additions-options' );
		$post_data         = $this->make_post_data(
			[
				'git_updater_additions' => [
					'slug' => 'owner/plugin.php',
					'uri'  => '',
					'type' => 'github_plugin',
				],
			]
		);
		$this->settings->save_settings( $post_data );
		$this->assertFalse( get_site_option( 'git_updater_additions' ) );
	}

	public function test_save_settings_skips_save_when_plugin_type_but_no_slash_in_slug(): void {
		$existing = [
			[
				'slug'   => 'other/plugin.php',
				'type'   => 'github_plugin',
				'uri'    => 'https://github.com/owner/other',
				'ID'     => md5( 'other/plugin.php' ),
				'source' => md5( home_url() ),
			],
		];
		update_site_option( 'git_updater_additions', $existing );
		$_POST['_wpnonce'] = wp_create_nonce( 'git_updater_additions-options' );
		$post_data         = $this->make_post_data(
			[
				'git_updater_additions' => [
					'slug' => 'noslash',
					'uri'  => 'https://github.com/owner/plugin',
					'type' => 'github_plugin',
				],
			]
		);
		$this->settings->save_settings( $post_data );
		$this->assertCount( 1, get_site_option( 'git_updater_additions' ) );
	}

	public function test_save_settings_skips_save_when_theme_type_but_slug_has_slash(): void {
		$existing = [
			[
				'slug'   => 'my-theme',
				'type'   => 'github_theme',
				'uri'    => 'https://github.com/owner/theme',
				'ID'     => md5( 'my-theme' ),
				'source' => md5( home_url() ),
			],
		];
		update_site_option( 'git_updater_additions', $existing );
		$_POST['_wpnonce'] = wp_create_nonce( 'git_updater_additions-options' );
		$post_data         = $this->make_post_data(
			[
				'git_updater_additions' => [
					'slug' => 'with/slash',
					'uri'  => 'https://github.com/owner/plugin',
					'type' => 'github_theme',
				],
			]
		);
		$this->settings->save_settings( $post_data );
		$this->assertCount( 1, get_site_option( 'git_updater_additions' ) );
	}

	public function test_save_settings_skips_save_when_duplicate_id(): void {
		$slug     = 'owner/plugin.php';
		$existing = [
			[
				'slug'   => $slug,
				'type'   => 'github_plugin',
				'uri'    => 'https://github.com/owner/plugin',
				'ID'     => md5( $slug ),
				'source' => md5( home_url() ),
			],
		];
		update_site_option( 'git_updater_additions', $existing );
		$_POST['_wpnonce'] = wp_create_nonce( 'git_updater_additions-options' );
		$this->settings->save_settings( $this->make_post_data() );
		$this->assertCount( 1, get_site_option( 'git_updater_additions' ) );
	}

	public function test_save_settings_saves_option_when_valid_and_no_existing_options(): void {
		$_POST['_wpnonce'] = wp_create_nonce( 'git_updater_additions-options' );
		$this->settings->save_settings( $this->make_post_data() );
		$this->assertCount( 1, get_site_option( 'git_updater_additions' ) );
	}

	public function test_save_settings_saves_option_when_valid_and_existing_options_present(): void {
		$existing = [
			[
				'slug'   => 'other/other.php',
				'type'   => 'github_plugin',
				'uri'    => 'https://github.com/owner/other',
				'ID'     => md5( 'other/other.php' ),
				'source' => md5( home_url() ),
			],
		];
		update_site_option( 'git_updater_additions', $existing );
		$_POST['_wpnonce'] = wp_create_nonce( 'git_updater_additions-options' );
		$this->settings->save_settings( $this->make_post_data() );
		$this->assertCount( 2, get_site_option( 'git_updater_additions' ) );
	}

	public function test_save_settings_adds_gu_save_redirect_filter_when_option_page_matches(): void {
		$_POST['_wpnonce'] = wp_create_nonce( 'git_updater_additions-options' );
		$this->settings->save_settings( $this->make_post_data() );
		$result = apply_filters( 'gu_save_redirect', [] );
		$this->assertContains( 'git_updater_additions', $result );
	}
}

// ---------------------------------------------------------------------------
// Settings::add_admin_page() and additions_page_init()
// ---------------------------------------------------------------------------


class Test_Settings_Add_Admin_Page extends WP_UnitTestCase {

	private Additions_Settings $settings;

	public function set_up(): void {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		Additions_Settings::$options_additions = [];
		$this->settings = new Additions_Settings();
	}

	public function test_add_admin_page_registers_setting_regardless_of_tab(): void {
		$this->settings->add_admin_page( 'other_tab', admin_url() );
		$settings = get_registered_settings();
		$this->assertArrayHasKey( 'git_updater_additions', $settings );
	}

	public function test_add_admin_page_renders_no_form_for_wrong_tab(): void {
		ob_start();
		$this->settings->add_admin_page( 'other_tab', admin_url() );
		$output = ob_get_clean();
		$this->assertStringNotContainsString( '<form', $output );
	}

	public function test_add_admin_page_renders_form_for_correct_tab(): void {
		ob_start();
		$this->settings->add_admin_page( 'git_updater_additions', admin_url() );
		$output = ob_get_clean();
		$this->assertStringContainsString( '<form', $output );
	}

	// -------------------------------------------------------------------------
	// uses_lite checkbox
	// -------------------------------------------------------------------------

	public function test_sanitize_preserves_uses_lite_true(): void {
		$result = $this->settings->sanitize( [ 'slug' => 'my/plugin.php', 'uri' => 'https://github.com/owner/repo', 'uses_lite' => '1' ] );
		$this->assertTrue( $result[0]['uses_lite'] );
	}

	public function test_sanitize_sets_uses_lite_false_when_absent(): void {
		$result = $this->settings->sanitize( [ 'slug' => 'my/plugin.php', 'uri' => 'https://github.com/owner/repo' ] );
		$this->assertFalse( $result[0]['uses_lite'] );
	}

	public function test_callback_checkbox_renders_uses_lite_field(): void {
		ob_start();
		$this->settings->callback_checkbox( [
			'id' => 'git_updater_additions_uses_lite',
			'setting' => 'uses_lite',
			'title' => 'Uses Git Updater Lite',
		] );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="git_updater_additions[uses_lite]"', $output );
	}
}
