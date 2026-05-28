<?php
/**
 * Tests for Init.php – 100% line coverage.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\Init;
use Fragen\Git_Updater\Base;
use Fragen\Singleton;

// =============================================================================
// Shared helper trait
// =============================================================================

trait Init_Test_Helper {
	private function init_tear_down(): void {
		remove_all_actions( 'init' );
		remove_all_filters( 'upgrader_pre_download' );
		remove_all_filters( 'upgrader_source_selection' );
		remove_all_filters( 'plugin_row_meta' );
		remove_all_filters( 'theme_row_meta' );
		remove_all_filters( 'pre_unschedule_event' );
		remove_all_filters( 'http_request_args' );
		delete_site_option( 'git_updater' );
		wp_cache_delete( 'cron', 'options' );
		unset( $_POST['action'], $_POST['_nonce'] );
		wp_set_current_user( 0 );
	}

	private function get_base_from_init( Init $init ): Base {
		$rp = new ReflectionProperty( Init::class, 'base' );
		$rp->setAccessible( true );
		return $rp->getValue( $init );
	}
}

// =============================================================================
// __construct()
// =============================================================================

class Test_Init_Constructor extends WP_UnitTestCase {
	use Init_Test_Helper;

	public function tear_down(): void {
		$this->init_tear_down();
		parent::tear_down();
	}

	public function test_constructor_sets_base_instance(): void {
		$init = new Init();
		$base = $this->get_base_from_init( $init );
		$this->assertInstanceOf( Base::class, $base );
	}
}

// =============================================================================
// run() – non-heartbeat, non-WP-CLI path
// =============================================================================

class Test_Init_Run extends WP_UnitTestCase {
	use Init_Test_Helper;

	private Init $init;
	private Base $base;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->init = new Init();
		$this->base = $this->get_base_from_init( $this->init );
		$this->init->run();
	}

	public function tear_down(): void {
		$this->init_tear_down();
		parent::tear_down();
	}

	public function test_run_registers_init_load(): void {
		$this->assertNotFalse( has_action( 'init', [ $this->base, 'load' ] ) );
	}

	public function test_run_registers_init_background_update(): void {
		$this->assertNotFalse( has_action( 'init', [ $this->base, 'background_update' ] ) );
	}

	public function test_run_registers_init_set_options_filter(): void {
		$this->assertNotFalse( has_action( 'init', [ $this->base, 'set_options_filter' ] ) );
	}

	public function test_run_registers_upgrader_source_selection(): void {
		$this->assertNotFalse( has_filter( 'upgrader_source_selection', [ $this->base, 'upgrader_source_selection' ] ) );
	}

	public function test_run_registers_plugin_row_meta(): void {
		$this->assertNotFalse( has_filter( 'plugin_row_meta', [ $this->base, 'row_meta_icons' ] ) );
	}

	public function test_run_registers_theme_row_meta(): void {
		$this->assertNotFalse( has_filter( 'theme_row_meta', [ $this->base, 'row_meta_icons' ] ) );
	}

	public function test_run_registers_upgrader_pre_download(): void {
		$this->assertNotFalse( has_filter( 'upgrader_pre_download' ) );
	}

	public function test_run_registers_pre_unschedule_event(): void {
		$gu_upgrade = Singleton::get_instance( 'GU_Upgrade', $this->init );
		$this->assertNotFalse( has_filter( 'pre_unschedule_event', [ $gu_upgrade, 'pre_unschedule_event' ] ) );
	}
}

// =============================================================================
// load_hooks() – upgrader_pre_download closure body
// =============================================================================

class Test_Init_Load_Hooks_Closure extends WP_UnitTestCase {
	use Init_Test_Helper;

	private Init $init;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->init = new Init();
		$this->init->run();
	}

	public function tear_down(): void {
		$this->init_tear_down();
		parent::tear_down();
	}

	public function test_upgrader_pre_download_closure_returns_false(): void {
		$result = apply_filters( 'upgrader_pre_download', null, 'https://example.com/pkg.zip', new stdClass() );
		$this->assertFalse( $result );
	}

	public function test_upgrader_pre_download_closure_adds_http_request_args_filter(): void {
		apply_filters( 'upgrader_pre_download', null, 'https://example.com/pkg.zip', new stdClass() );
		$this->assertSame( 15, has_filter( 'http_request_args', [ $this->init, 'download_package' ] ) );
	}
}

// =============================================================================
// can_update()
// =============================================================================

class Test_Init_Can_Update extends WP_UnitTestCase {
	use Init_Test_Helper;

	private Init $init;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->init = new Init();
	}

	public function tear_down(): void {
		$this->init_tear_down();
		parent::tear_down();
	}

	public function test_can_update_returns_true_for_administrator(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
		$this->assertTrue( $this->init->can_update() );
	}

	public function test_can_update_returns_false_for_subscriber(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );
		$this->assertFalse( $this->init->can_update() );
	}
}
