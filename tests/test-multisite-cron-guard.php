<?php
/**
 * Test multisite cron guard logic.
 *
 * Tests for:
 * - GU_Upgrade::schedule_access_token_cleanup() — early return on subsites.
 * - GU_Trait::delete_all_cached_data() — wp_cron() skipped on subsites.
 *
 * Run with WP_TESTS_MULTISITE=true to exercise the multisite code paths.
 * In single-site mode, the guards pass through and behavior is unchanged.
 *
 * @package Git_Updater
 */

/**
 * Helper class to expose the private schedule_access_token_cleanup() method.
 *
 * GU_Upgrade is declared final, so we use a thin wrapper that delegates
 * to a reflection call. This avoids modifying production code visibility.
 */
class Test_Multisite_Cron_Guard extends \WP_UnitTestCase {

	use Fragen\Git_Updater\Traits\GU_Trait;

	/**
	 * Clean up cron events after each test.
	 */
	public function tear_down() {
		wp_clear_scheduled_hook( 'gu_delete_access_tokens' );
		parent::tear_down();
	}

	/*
	|--------------------------------------------------------------------------
	| GU_Trait::delete_all_cached_data() — wp_cron() guard
	|--------------------------------------------------------------------------
	*/

	/**
	 * On a single site, delete_all_cached_data() should call wp_cron().
	 *
	 * We verify indirectly: wp_cron() processes due cron events, so we
	 * schedule a test action, call delete_all_cached_data(), and check
	 * whether the action fired.
	 */
	public function test_delete_all_cached_data_calls_wp_cron_on_single_site() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single-site test — skipped under multisite.' );
		}

		$fired = false;
		add_action(
			'gu_test_cron_action',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		// Schedule an event in the past so wp_cron() will execute it.
		wp_schedule_single_event( time() - 1, 'gu_test_cron_action' );

		$result = $this->delete_all_cached_data();

		$this->assertTrue( $result, 'delete_all_cached_data() should return true.' );
		$this->assertTrue( $fired, 'wp_cron() should fire on single-site, executing due events.' );

		// Clean up.
		wp_clear_scheduled_hook( 'gu_test_cron_action' );
	}

	/**
	 * On the main site of a multisite network, delete_all_cached_data()
	 * should call wp_cron().
	 *
	 * @group multisite
	 */
	public function test_delete_all_cached_data_calls_wp_cron_on_main_site() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test — skipped under single-site.' );
		}

		// Ensure we're on the main site.
		switch_to_blog( get_main_site_id() );

		$fired = false;
		add_action(
			'gu_test_cron_action',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		wp_schedule_single_event( time() - 1, 'gu_test_cron_action' );

		$result = $this->delete_all_cached_data();

		$this->assertTrue( $result, 'delete_all_cached_data() should return true.' );
		$this->assertTrue( $fired, 'wp_cron() should fire on the main site of a multisite network.' );

		wp_clear_scheduled_hook( 'gu_test_cron_action' );
		restore_current_blog();
	}

	/**
	 * On a subsite of a multisite network, delete_all_cached_data()
	 * should NOT call wp_cron().
	 *
	 * @group multisite
	 */
	public function test_delete_all_cached_data_skips_wp_cron_on_subsite() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test — skipped under single-site.' );
		}

		// Create a subsite and switch to it.
		$blog_id = self::factory()->blog->create();
		switch_to_blog( $blog_id );

		$this->assertFalse( is_main_site(), 'Should be on a subsite.' );

		$fired = false;
		add_action(
			'gu_test_cron_action',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		wp_schedule_single_event( time() - 1, 'gu_test_cron_action' );

		$result = $this->delete_all_cached_data();

		$this->assertTrue( $result, 'delete_all_cached_data() should still return true.' );
		$this->assertFalse( $fired, 'wp_cron() should NOT fire on a subsite.' );

		wp_clear_scheduled_hook( 'gu_test_cron_action' );
		restore_current_blog();
	}

	/*
	|--------------------------------------------------------------------------
	| GU_Upgrade::schedule_access_token_cleanup() — multisite guard
	|--------------------------------------------------------------------------
	|
	| schedule_access_token_cleanup() is private, so we test it indirectly
	| by checking whether the cron event gets scheduled after construction
	| scenarios. We use reflection to invoke the private method.
	*/

	/**
	 * Get a GU_Upgrade instance and invoke schedule_access_token_cleanup().
	 *
	 * @return void
	 */
	private function invoke_schedule_access_token_cleanup() {
		$upgrade    = new Fragen\Git_Updater\GU_Upgrade();
		$reflection = new ReflectionMethod( $upgrade, 'schedule_access_token_cleanup' );
		$reflection->setAccessible( true );
		$reflection->invoke( $upgrade );
	}

	/**
	 * On a single site, schedule_access_token_cleanup() should schedule
	 * the gu_delete_access_tokens cron event.
	 */
	public function test_schedule_access_token_cleanup_schedules_on_single_site() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single-site test — skipped under multisite.' );
		}

		// Ensure no pre-existing event.
		wp_clear_scheduled_hook( 'gu_delete_access_tokens' );
		$this->assertFalse( wp_next_scheduled( 'gu_delete_access_tokens' ), 'Precondition: no event scheduled.' );

		$this->invoke_schedule_access_token_cleanup();

		$this->assertNotFalse(
			wp_next_scheduled( 'gu_delete_access_tokens' ),
			'gu_delete_access_tokens should be scheduled on single-site.'
		);
	}

	/**
	 * On the main site of a multisite network, schedule_access_token_cleanup()
	 * should schedule the cron event.
	 *
	 * @group multisite
	 */
	public function test_schedule_access_token_cleanup_schedules_on_main_site() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test — skipped under single-site.' );
		}

		switch_to_blog( get_main_site_id() );
		wp_clear_scheduled_hook( 'gu_delete_access_tokens' );

		$this->invoke_schedule_access_token_cleanup();

		$this->assertNotFalse(
			wp_next_scheduled( 'gu_delete_access_tokens' ),
			'gu_delete_access_tokens should be scheduled on the main site.'
		);

		restore_current_blog();
	}

	/**
	 * On a subsite of a multisite network, schedule_access_token_cleanup()
	 * should NOT schedule the cron event.
	 *
	 * @group multisite
	 */
	public function test_schedule_access_token_cleanup_skips_on_subsite() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test — skipped under single-site.' );
		}

		$blog_id = self::factory()->blog->create();
		switch_to_blog( $blog_id );

		$this->assertFalse( is_main_site(), 'Should be on a subsite.' );

		wp_clear_scheduled_hook( 'gu_delete_access_tokens' );
		$this->assertFalse( wp_next_scheduled( 'gu_delete_access_tokens' ), 'Precondition: no event scheduled.' );

		$this->invoke_schedule_access_token_cleanup();

		$this->assertFalse(
			wp_next_scheduled( 'gu_delete_access_tokens' ),
			'gu_delete_access_tokens should NOT be scheduled on a subsite.'
		);

		restore_current_blog();
	}
}
