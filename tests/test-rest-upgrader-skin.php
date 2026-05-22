<?php
/**
 * Tests for REST\Rest_Upgrader_Skin.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\REST\Rest_Upgrader_Skin;

class Test_Rest_Upgrader_Skin extends GU_Test_Case {

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

	public function test_feedback_appends_string_from_upgrader_strings_without_percent(): void {
		// set_upgrader() must be called explicitly; WP_Upgrader::__construct() does not call init().
		$upgrader = new Plugin_Upgrader( $this->skin );
		$this->skin->set_upgrader( $upgrader );
		$upgrader->strings['gu_test_key'] = 'Hello World';
		$this->skin->feedback( 'gu_test_key' );
		$this->assertContains( 'Hello World', $this->skin->messages );
	}

	public function test_feedback_uses_vsprintf_when_string_contains_percent(): void {
		$upgrader = new Plugin_Upgrader( $this->skin );
		$this->skin->set_upgrader( $upgrader );
		$upgrader->strings['gu_prog_key'] = 'Done: %s';
		$this->skin->feedback( 'gu_prog_key', '100' );
		$this->assertContains( 'Done: 100', $this->skin->messages );
	}

	public function test_decrement_update_count_is_callable_no_op(): void {
		$method = new ReflectionMethod( Rest_Upgrader_Skin::class, 'decrement_update_count' );
		$method->setAccessible( true );
		$method->invoke( $this->skin, 'plugin' );
		$this->assertTrue( true );
	}
}

// ---------------------------------------------------------------------------
// Rest_Update
// ---------------------------------------------------------------------------

/**
 * Class Test_Rest_Update
 */