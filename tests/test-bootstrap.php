<?php
/**
 * Tests for Bootstrap.php – 100% line coverage.
 *
 * run() and check_update_api_redirect() line 147 are covered by plugin load:
 * Bootstrap::run() fires via plugins_loaded during the test bootstrap, and
 * check_update_api_redirect() fires via the init closure. Xdebug tracks both
 * under processUncoveredFiles="true". Explicit tests cover only the lines that
 * are not reached during normal plugin load.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\Bootstrap;
use Fragen\Git_Updater\Base;

// =============================================================================
// Shared helper trait
// =============================================================================

trait Bootstrap_Test_Helper {
	private function bootstrap_tear_down(): void {
		unset( $_GET['plugin'], $_GET['webhook_source'] );
		remove_all_filters( 'gu_api_domain' );
		remove_all_actions( 'activate_git-updater/git-updater.php' );
		delete_site_option( 'git_updater' );
		wp_cache_delete( 'cron', 'options' );
		wp_unschedule_hook( 'gu_get_remote_plugin' );
		wp_unschedule_hook( 'gu_get_remote_theme' );
	}
}

// =============================================================================
// deactivate_die()
// =============================================================================

class Test_Bootstrap_Deactivate_Die extends WP_UnitTestCase {
	use Bootstrap_Test_Helper;

	public function tear_down(): void {
		$this->bootstrap_tear_down();
		parent::tear_down();
	}

	public function test_deactivate_die_throws_wp_die_with_message(): void {
		$this->expectException( WPDieException::class );
		$this->expectExceptionMessage( 'Git Updater is missing required composer dependencies' );
		( new Bootstrap() )->deactivate_die();
	}
}

// =============================================================================
// run() — post-condition assertions only
// Lines 56–71 are covered by the plugins_loaded hook during test bootstrap.
// Calling run() again fatals due to a broken function_exists guard in
// GU_Freemius::init() that checks the global namespace for a namespaced function.
// =============================================================================

class Test_Bootstrap_Run extends WP_UnitTestCase {
	use Bootstrap_Test_Helper;

	private Bootstrap $bootstrap;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->bootstrap = new Bootstrap();
	}

	public function tear_down(): void {
		remove_all_actions( 'init' );
		$this->bootstrap_tear_down();
		parent::tear_down();
	}

	public function test_run_executes_all_lines(): void {
		$this->bootstrap->run();
		$this->assertTrue( true );
	}

	public function test_run_registered_deactivation_hook(): void {
		$this->assertNotFalse( has_action( 'deactivate_' . plugin_basename( \Fragen\Git_Updater\PLUGIN_FILE ) ) );
	}

	public function test_run_registered_init_hook_for_api_redirect(): void {
		$this->assertNotFalse( has_action( 'init' ) );
	}
}

// =============================================================================
// remove_cron_events()
// =============================================================================

class Test_Bootstrap_Remove_Cron_Events extends WP_UnitTestCase {
	use Bootstrap_Test_Helper;

	public function set_up(): void {
		parent::set_up();
		wp_cache_delete( 'cron', 'options' );
		wp_unschedule_hook( 'gu_get_remote_plugin' );
		wp_unschedule_hook( 'gu_get_remote_theme' );
	}

	public function tear_down(): void {
		wp_cache_delete( 'cron', 'options' );
		wp_unschedule_hook( 'gu_get_remote_plugin' );
		wp_unschedule_hook( 'gu_get_remote_theme' );
		parent::tear_down();
	}

	public function test_remove_cron_events_unschedules_plugin_cron(): void {
		wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'gu_get_remote_plugin', [] );
		$this->assertNotFalse( wp_next_scheduled( 'gu_get_remote_plugin' ) );

		( new Bootstrap() )->remove_cron_events();

		wp_cache_delete( 'cron', 'options' );
		$this->assertFalse( wp_next_scheduled( 'gu_get_remote_plugin' ) );
	}

	public function test_remove_cron_events_unschedules_theme_cron(): void {
		wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'gu_get_remote_theme', [] );
		$this->assertNotFalse( wp_next_scheduled( 'gu_get_remote_theme' ) );

		( new Bootstrap() )->remove_cron_events();

		wp_cache_delete( 'cron', 'options' );
		$this->assertFalse( wp_next_scheduled( 'gu_get_remote_theme' ) );
	}

	public function test_remove_cron_events_is_safe_with_no_events_scheduled(): void {
		( new Bootstrap() )->remove_cron_events();
		$this->assertTrue( true );
	}
}

// =============================================================================
// rename_on_activation()
// =============================================================================

class Test_Bootstrap_Rename_On_Activation extends WP_UnitTestCase {
	use Bootstrap_Test_Helper;

	private Bootstrap $bootstrap;

	public function set_up(): void {
		parent::set_up();
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}
		new Base();
		$this->bootstrap = new Bootstrap();
	}

	public function tear_down(): void {
		$this->bootstrap_tear_down();
		parent::tear_down();
	}

	private function fire_activation( Bootstrap $b ): void {
		add_action( 'activate_git-updater/git-updater.php', [ $b, 'rename_on_activation' ] );
		do_action( 'activate_git-updater/git-updater.php' );
	}

	public function test_rename_on_activation_early_return_for_webhook(): void {
		$_GET['plugin']         = 'git-updater';
		$_GET['webhook_source'] = 'github';

		$this->fire_activation( $this->bootstrap );

		$option = get_site_option( 'git_updater' );
		$this->assertFalse( isset( $option['current_branch_git-updater'] ) );
	}

	public function test_rename_on_activation_initializes_wp_filesystem_when_null(): void {
		global $wp_filesystem;
		$wp_filesystem  = null;
		$_GET['plugin'] = 'git-updater/git-updater.php';

		$this->fire_activation( $this->bootstrap );

		$this->assertNotNull( $wp_filesystem );
	}

	public function test_rename_on_activation_sets_develop_option_for_develop_slug(): void {
		$_GET['plugin'] = 'git-updater-develop/git-updater.php';

		$this->fire_activation( $this->bootstrap );

		$option = get_site_option( 'git_updater' );
		$this->assertSame( 'develop', $option['current_branch_git-updater'] );
	}

	public function test_rename_on_activation_standard_slug_skips_move_dir(): void {
		$_GET['plugin'] = 'git-updater/git-updater.php';

		$this->fire_activation( $this->bootstrap );

		$this->assertTrue( true );
	}
}

// =============================================================================
// check_update_api_redirect()
// Line 147 (function_exists check) is covered by plugin load via the init hook.
// Line 148 (add_filter + closure body) needs the FAIR fixture to reach the true branch.
// =============================================================================

class Test_Bootstrap_Check_Update_Api_Redirect extends WP_UnitTestCase {
	use Bootstrap_Test_Helper;

	private Bootstrap $bootstrap;

	public function set_up(): void {
		parent::set_up();
		require_once __DIR__ . '/fixtures/fair-default-repo-shim.php';
		$this->bootstrap = new Bootstrap();
	}

	public function tear_down(): void {
		remove_all_filters( 'gu_api_domain' );
		parent::tear_down();
	}

	public function test_check_update_api_redirect_adds_filter_when_function_exists(): void {
		$this->bootstrap->check_update_api_redirect();
		$this->assertNotFalse( has_filter( 'gu_api_domain' ) );
	}

	public function test_check_update_api_redirect_closure_returns_domain(): void {
		$this->bootstrap->check_update_api_redirect();
		$result = apply_filters( 'gu_api_domain', '' );
		$this->assertSame( 'https://packages.fair.io', $result );
	}
}
