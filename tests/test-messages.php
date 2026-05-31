<?php
/**
 * Tests for Messages.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\Messages;
use Fragen\Git_Updater\Base;

class Test_Messages extends WP_UnitTestCase {

	private Messages $messages;

	public function set_up(): void {
		parent::set_up();
		$this->messages = new Messages();
	}

	public function tear_down(): void {
		global $pagenow;
		$pagenow = '';
		unset( $_GET['_wpnonce'], $_GET['page'] );
		set_current_screen( 'front' );
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'network_admin_notices' );
		Messages::$error_message = '';
		parent::tear_down();
	}

	public function test_create_error_message_returns_false_when_pagenow_is_empty(): void {
		global $pagenow;
		$pagenow = '';

		$this->assertFalse( $this->messages->create_error_message() );
	}

	public function test_create_error_message_returns_false_on_settings_page_without_nonce(): void {
		global $pagenow;
		$pagenow = 'options-general.php';

		$this->assertFalse( $this->messages->create_error_message() );
	}

	public function test_create_error_message_returns_true_on_update_core_page(): void {
		global $pagenow;
		$pagenow = 'update-core.php';

		// update-core.php is in $update_pages — nonce is not required to pass the guard.
		$this->assertTrue( $this->messages->create_error_message() );
	}

	public function test_create_error_message_returns_true_on_plugins_page(): void {
		global $pagenow;
		$pagenow = 'plugins.php';

		$this->assertTrue( $this->messages->create_error_message() );
	}

	public function test_create_error_message_returns_true_on_settings_page_with_valid_nonce_and_page(): void {
		global $pagenow;
		$pagenow             = 'options-general.php';
		$_GET['_wpnonce']    = wp_create_nonce( 'gu_settings' );
		$_GET['page']        = 'git-updater';

		$this->assertTrue( $this->messages->create_error_message() );
	}

	public function test_create_error_message_wp_error_registers_show_wp_error_action(): void {
		global $pagenow;
		$pagenow = 'update-core.php';
		set_current_screen( 'update-core' );

		$error  = new WP_Error( 'test_code', 'Something went wrong' );
		$result = $this->messages->create_error_message( $error );

		$hook = is_multisite() ? 'network_admin_notices' : 'admin_notices';
		$this->assertTrue( $result );
		$this->assertSame( 'Something went wrong', Messages::$error_message );
		$this->assertNotFalse( has_action( $hook, [ $this->messages, 'show_wp_error' ] ) );
	}

	public function test_create_error_message_get_license_registers_action(): void {
		global $pagenow;
		$pagenow = 'update-core.php';
		set_current_screen( 'update-core' );

		$result = $this->messages->create_error_message( 'get_license' );

		$hook = is_multisite() ? 'network_admin_notices' : 'admin_notices';
		$this->assertTrue( $result );
		$this->assertNotFalse( has_action( $hook, [ $this->messages, 'get_license' ] ) );
	}

	public function test_create_error_message_waiting_registers_action(): void {
		global $pagenow;
		$pagenow = 'update-core.php';
		set_current_screen( 'update-core' );

		$result = $this->messages->create_error_message( 'waiting' );

		$hook = is_multisite() ? 'network_admin_notices' : 'admin_notices';
		$this->assertTrue( $result );
		$this->assertNotFalse( has_action( $hook, [ $this->messages, 'waiting' ] ) );
	}

	public function test_show_wp_error_outputs_error_div(): void {
		Messages::$error_message = 'Test error output';

		ob_start();
		$this->messages->show_wp_error();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-error', $output );
		$this->assertStringContainsString( 'Test error output', $output );
	}

	public function test_waiting_outputs_info_div(): void {
		ob_start();
		$this->messages->waiting();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-info', $output );
		$this->assertStringContainsString( 'WP-Cron', $output );
	}

	public function test_get_license_returns_early_when_user_is_paying(): void {
		$orig_fs          = $GLOBALS['gu_fs'] ?? null;
		$GLOBALS['gu_fs'] = new class {
			public function is_not_paying(): bool {
				return false;
			}
		};

		ob_start();
		$this->messages->get_license();
		$output = ob_get_clean();

		$GLOBALS['gu_fs'] = $orig_fs;

		$this->assertSame( '', $output );
	}

	public function test_get_license_outputs_html_when_not_paying_and_notice_active(): void {
		ob_start();
		$this->messages->get_license();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-info', $output );
		$this->assertStringContainsString( 'Purchase from Store', $output );
	}

	public function test_create_error_message_git_type_does_not_register_action(): void {
		global $pagenow;
		$pagenow = 'update-core.php';
		set_current_screen( 'update-core' );

		$result = $this->messages->create_error_message( 'git' );

		$this->assertTrue( $result );
		$this->assertFalse( has_action( 'admin_notices', [ $this->messages, 'waiting' ] ) );
	}
}

// ---------------------------------------------------------------------------
// Language_Pack_API
// ---------------------------------------------------------------------------

/**
 * Class Test_Language_Pack_API
 */