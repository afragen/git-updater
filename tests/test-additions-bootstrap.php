<?php
/**
 * Tests for Bootstrap.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\Base;
use Fragen\Git_Updater\Add_Ons;
use Fragen\Git_Updater\Additions\Additions;
use Fragen\Git_Updater\Additions\Settings;
use Fragen\Git_Updater\Additions\Bootstrap;
use Fragen\Git_Updater\Additions\Repo_List_Table;

class Test_Bootstrap extends WP_UnitTestCase {

	public function tear_down(): void {
		remove_all_actions( 'gu_update_settings' );
		remove_all_actions( 'init' );
		remove_all_actions( 'gu_add_admin_page' );
		parent::tear_down();
	}

	public function test_run_registers_gu_update_settings_action(): void {
		( new Bootstrap() )->run();
		$this->assertNotFalse( has_action( 'gu_update_settings' ) );
	}

	public function test_run_registers_init_action(): void {
		( new Bootstrap() )->run();
		$this->assertNotFalse( has_action( 'init' ) );
	}

	public function test_run_registers_gu_add_admin_page_action(): void {
		( new Bootstrap() )->run();
		$this->assertNotFalse( has_action( 'gu_add_admin_page' ) );
	}
}

// ---------------------------------------------------------------------------
// Settings::load_hooks()
// ---------------------------------------------------------------------------
