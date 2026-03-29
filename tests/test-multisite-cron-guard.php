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
 * Class Test_Multisite_Cron_Guard
 *
 * Uses the GU_Trait directly and invokes GU_Upgrade's private method
 * via reflection (GU_Upgrade is final, so we cannot extend it).
 */
class Test_Multisite_Cron_Guard extends \WP_UnitTestCase {

	use Fragen\Git_Updater\Traits\GU_Trait;

	/**
	 * Clean up cron events and filters after each test.
	 */
	public function tear_down() {
		wp_clear_scheduled_hook( 'gu_delete_access_tokens' );
		wp_clear_scheduled_hook( 'gu_test_cron_action' );
		remove_all_filters( 'cron_request' );
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
	 * We verify by hooking into the 'cron_request' filter, which fires
	 * inside spawn_cron() when wp_cron() reaches the point of spawning
	 * the loopback request. In the test environment there is no HTTP
	 * server, so we short-circuit the request and just record that the
	 * code path was reached.
	 */
	public function test_delete_all_cached_data_calls_wp_cron_on_single_site() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single-site test — skipped under multisite.' );
		}

		$spawn_attempted = false;
		add_filter(
			'cron_request',
			function ( $cron_request ) use ( &$spawn_attempted ) {
				$spawn_attempted = true;
				// Return a blocking request with a very short timeout to
				// prevent an actual HTTP call in the test environment.
				$cron_request['args']['blocking'] = false;
				$cron_request['args']['timeout']  = 0.01;
				return $cron_request;
			},
			9999
		);

		// Schedule an event in the past so wp_cron() has something to process.
		wp_schedule_single_event( time() - 1, 'gu_test_cron_action' );

		// Clear the spawn-cron lock so wp_cron() doesn't bail early.
		delete_transient( 'doing_cron' );

		$result = $this->delete_all_cached_data();

		$this->assertTrue( $result, 'delete_all_cached_data() should return true.' );
		$this->assertTrue( $spawn_attempted, 'wp_cron() should attempt to spawn cron on single-site.' );

		// Clean up.
		wp_clear_scheduled_hook( 'gu_test_cron_action' );
		remove_all_filters( 'cron_request' );
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

		$spawn_attempted = false;
		add_filter(
			'cron_request',
			function ( $cron_request ) use ( &$spawn_attempted ) {
				$spawn_attempted = true;
				$cron_request['args']['blocking'] = false;
				$cron_request['args']['timeout']  = 0.01;
				return $cron_request;
			},
			9999
		);

		wp_schedule_single_event( time() - 1, 'gu_test_cron_action' );
		delete_transient( 'doing_cron' );

		$result = $this->delete_all_cached_data();

		$this->assertTrue( $result, 'delete_all_cached_data() should return true.' );
		$this->assertTrue( $spawn_attempted, 'wp_cron() should attempt to spawn cron on the main site.' );

		wp_clear_scheduled_hook( 'gu_test_cron_action' );
		remove_all_filters( 'cron_request' );
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

		$spawn_attempted = false;
		add_filter(
			'cron_request',
			function ( $cron_request ) use ( &$spawn_attempted ) {
				$spawn_attempted = true;
				$cron_request['args']['blocking'] = false;
				$cron_request['args']['timeout']  = 0.01;
				return $cron_request;
			},
			9999
		);

		wp_schedule_single_event( time() - 1, 'gu_test_cron_action' );
		delete_transient( 'doing_cron' );

		$result = $this->delete_all_cached_data();

		$this->assertTrue( $result, 'delete_all_cached_data() should still return true.' );
		$this->assertFalse( $spawn_attempted, 'wp_cron() should NOT attempt to spawn cron on a subsite.' );

		wp_clear_scheduled_hook( 'gu_test_cron_action' );
		remove_all_filters( 'cron_request' );
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
