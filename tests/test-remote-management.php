<?php
/**
 * Tests for Remote_Management.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\Remote_Management;
use Fragen\Git_Updater\Base;

class Test_Remote_Management extends GU_Test_Case {

	private array $saved_request;
	private array $saved_post;
	private array $saved_get;

	public function set_up(): void {
		parent::set_up();
		if ( ! function_exists( 'submit_button' ) ) {
			require_once ABSPATH . 'wp-admin/includes/template.php';
		}
		$this->saved_request = $_REQUEST;
		$this->saved_post    = $_POST;
		$this->saved_get     = $_GET;
	}

	public function tear_down(): void {
		$_REQUEST = $this->saved_request;
		$_POST    = $this->saved_post;
		$_GET     = $this->saved_get;
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

	public function test_init_registers_settings_section(): void {
		update_site_option( 'git_updater_api_key', 'test-key-init' );
		$rm = new Remote_Management();
		$rm->init();

		$tabs = apply_filters( 'gu_add_settings_tabs', [] );
		$this->assertArrayHasKey( 'git_updater_remote_management', $tabs );
		$this->assertNotFalse( has_action( 'gu_add_admin_page' ) );

		global $wp_settings_sections;
		$this->assertTrue( isset( $wp_settings_sections['git_updater_remote_settings'] ) );
	}

	public function test_add_admin_page_outputs_form_html_for_matching_tab(): void {
		update_site_option( 'git_updater_api_key', 'test-key-admin' );
		$rm = new Remote_Management();

		ob_start();
		$rm->add_admin_page( 'git_updater_remote_management', admin_url( 'admin.php' ) );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'no-sub-tabs', $output );
		$this->assertStringContainsString( 'git_updater_reset_api_key', $output );
		$this->assertStringNotContainsString( 'updated', $output );
	}

	public function test_admin_page_notices_shows_reset_message_when_reset_is_one(): void {
		update_site_option( 'git_updater_api_key', 'test-key-notice' );
		$rm          = new Remote_Management();
		$_GET['reset'] = '1';

		ob_start();
		$rm->add_admin_page( 'git_updater_remote_management', admin_url( 'admin.php' ) );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'updated', $output );
		$this->assertStringContainsString( 'REST API key reset', $output );
	}

	public function test_add_settings_tabs_action_closure_calls_add_admin_page(): void {
		update_site_option( 'git_updater_api_key', 'test-key-closure' );
		$rm = new Remote_Management();
		$rm->add_settings_tabs();

		ob_start();
		do_action( 'gu_add_admin_page', 'git_updater_remote_management', admin_url( 'admin.php' ) );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'no-sub-tabs', $output );
	}

	public function test_print_section_remote_management_with_empty_api_key(): void {
		delete_site_option( 'git_updater_api_key' );
		$rm = new Remote_Management();

		ob_start();
		$rm->print_section_remote_management();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'wp-json', $output );
		$this->assertStringContainsString( 'git-updater/v1', $output );
		$this->assertStringContainsString( 'update/', $output );
		$this->assertStringContainsString( 'reset-branch/', $output );
	}

	public function test_print_section_remote_management_embeds_api_key_in_endpoints(): void {
		update_site_option( 'git_updater_api_key', 'known-test-key' );
		$rm = new Remote_Management();

		ob_start();
		$rm->print_section_remote_management();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'known-test-key', $output );
		$this->assertStringContainsString( 'git-updater/v1', $output );
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