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
