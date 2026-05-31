<?php
/**
 * Tests for GU_Upgrade.
 *
 * Covers:
 * - convert_ghu_options_to_gu_options() — migrate legacy github_updater site option
 * - pre_unschedule_event()              — passthrough filter; acts only on the
 *                                         gu_delete_access_tokens hook
 * - run()                              — db version check and upgrade
 * - flush_tokens()                     — strips access tokens from options
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\GU_Upgrade;

class Test_GU_Upgrade extends WP_UnitTestCase {

	private GU_Upgrade $upgrade;
	private array $original_options;

	private function get_db_version(): string {
		$rp = new \ReflectionProperty( GU_Upgrade::class, 'db_version' );
		$rp->setAccessible( true );
		return $rp->getValue( $this->upgrade );
	}

	public function set_up(): void {
		parent::set_up();
		$this->upgrade          = new GU_Upgrade();
		$this->original_options = \Fragen\Git_Updater\Base::$options ?? [];
	}

	public function tear_down(): void {
		delete_site_option( 'github_updater' );
		delete_site_option( 'git_updater' );
		\Fragen\Git_Updater\Base::$options = $this->original_options;
		wp_cache_delete( 'cron', 'options' );
		wp_clear_scheduled_hook( 'gu_delete_access_tokens' );
		remove_action( 'gu_delete_access_tokens', [ $this->upgrade, 'flush_tokens' ] );
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// convert_ghu_options_to_gu_options()
	// -------------------------------------------------------------------------

	public function test_convert_copies_github_updater_options_to_git_updater(): void {
		$options = [ 'github_access_token' => 'abc123', 'db_version' => '9.0.0' ];
		update_site_option( 'github_updater', $options );

		$this->upgrade->convert_ghu_options_to_gu_options();

		$this->assertSame( $options, get_site_option( 'git_updater' ) );
	}

	public function test_convert_deletes_legacy_github_updater_option(): void {
		update_site_option( 'github_updater', [ 'token' => 'xyz' ] );

		$this->upgrade->convert_ghu_options_to_gu_options();

		$this->assertFalse( get_site_option( 'github_updater', false ) );
	}

	public function test_convert_does_not_overwrite_git_updater_when_source_absent(): void {
		delete_site_option( 'github_updater' );
		update_site_option( 'git_updater', [ 'existing' => 'data' ] );

		$this->upgrade->convert_ghu_options_to_gu_options();

		$this->assertSame( [ 'existing' => 'data' ], get_site_option( 'git_updater' ) );
	}

	// -------------------------------------------------------------------------
	// pre_unschedule_event()
	// -------------------------------------------------------------------------

	public function test_pre_unschedule_returns_pre_unchanged_for_unrelated_hook(): void {
		$result = $this->upgrade->pre_unschedule_event( null, time(), 'some_other_hook' );
		$this->assertNull( $result );
	}

	public function test_pre_unschedule_returns_false_pre_unchanged_for_unrelated_hook(): void {
		$result = $this->upgrade->pre_unschedule_event( false, time(), 'some_other_hook' );
		$this->assertFalse( $result );
	}

	public function test_pre_unschedule_returns_pre_when_gu_event_not_scheduled(): void {
		$result = $this->upgrade->pre_unschedule_event( null, time(), 'gu_delete_access_tokens' );
		$this->assertNull( $result );
	}

	public function test_pre_unschedule_returns_pre_when_event_is_less_than_30_days_away(): void {
		$future = time() + DAY_IN_SECONDS;
		wp_schedule_single_event( $future, 'gu_delete_access_tokens' );

		$result = $this->upgrade->pre_unschedule_event( null, time(), 'gu_delete_access_tokens' );

		wp_unschedule_event( $future, 'gu_delete_access_tokens' );
		$this->assertNull( $result );
	}

	// -------------------------------------------------------------------------
	// run()
	// -------------------------------------------------------------------------

	public function test_run_returns_early_when_db_version_matches_current(): void {
		\Fragen\Git_Updater\Base::$options = [ 'db_version' => $this->get_db_version() ];
		update_site_option( 'git_updater', \Fragen\Git_Updater\Base::$options );

		$this->upgrade->run();

		$stored = get_site_option( 'git_updater' );
		$this->assertSame( $this->get_db_version(), $stored['db_version'] );
		$this->assertNotFalse( wp_next_scheduled( 'gu_delete_access_tokens' ) );
	}

	public function test_run_upgrades_db_version_when_older(): void {
		\Fragen\Git_Updater\Base::$options = [ 'some_token' => 'abc' ];
		update_site_option( 'git_updater', \Fragen\Git_Updater\Base::$options );

		$this->upgrade->run();

		$stored = get_site_option( 'git_updater' );
		$this->assertSame( $this->get_db_version(), $stored['db_version'] );
		$this->assertArrayHasKey( 'some_token', $stored );
	}

	public function test_run_hits_default_branch_when_db_version_is_newer(): void {
		\Fragen\Git_Updater\Base::$options = [ 'db_version' => '99.0.0' ];
		update_site_option( 'git_updater', \Fragen\Git_Updater\Base::$options );

		$this->upgrade->run();

		$stored = get_site_option( 'git_updater' );
		$this->assertSame( '99.0.0', $stored['db_version'] );
	}

	// -------------------------------------------------------------------------
	// flush_tokens()
	// -------------------------------------------------------------------------

	public function test_flush_tokens_returns_early_when_no_event_scheduled(): void {
		wp_cache_delete( 'cron', 'options' );
		wp_clear_scheduled_hook( 'gu_delete_access_tokens' );
		$this->assertFalse( wp_next_scheduled( 'gu_delete_access_tokens' ) );

		\Fragen\Git_Updater\Base::$options = [ 'github_access_token' => 'secret' ];
		update_site_option( 'git_updater', \Fragen\Git_Updater\Base::$options );

		$this->upgrade->flush_tokens();

		$stored = get_site_option( 'git_updater' );
		$this->assertArrayHasKey( 'github_access_token', $stored );
	}

	public function test_flush_tokens_filters_non_base_options_when_event_is_scheduled(): void {
		wp_cache_delete( 'cron', 'options' );
		wp_clear_scheduled_hook( 'gu_delete_access_tokens' );
		wp_schedule_event( time() + MONTH_IN_SECONDS, 'twicedaily', 'gu_delete_access_tokens' );

		\Fragen\Git_Updater\Base::$options = [
			'db_version'                   => '12.24.2',
			'branch_switch'                => '1',
			'bypass_background_processing' => '1',
			'current_branch_my-plugin'     => 'main',
			'github_access_token'          => 'secret',
			'bitbucket_token'              => 'other',
		];
		update_site_option( 'git_updater', \Fragen\Git_Updater\Base::$options );

		$this->upgrade->flush_tokens();

		$stored = get_site_option( 'git_updater' );
		$this->assertArrayHasKey( 'db_version', $stored );
		$this->assertArrayHasKey( 'branch_switch', $stored );
		$this->assertArrayHasKey( 'bypass_background_processing', $stored );
		$this->assertArrayHasKey( 'current_branch_my-plugin', $stored );
		$this->assertArrayNotHasKey( 'github_access_token', $stored );
		$this->assertArrayNotHasKey( 'bitbucket_token', $stored );
	}

	public function test_pre_unschedule_calls_flush_tokens_when_event_over_29_days_away(): void {
		wp_cache_delete( 'cron', 'options' );
		wp_clear_scheduled_hook( 'gu_delete_access_tokens' );
		$future = time() + ( 30 * DAY_IN_SECONDS ) + 3600;
		wp_schedule_single_event( $future, 'gu_delete_access_tokens' );

		\Fragen\Git_Updater\Base::$options = [
			'db_version'          => '12.24.2',
			'github_access_token' => 'token_to_flush',
		];
		update_site_option( 'git_updater', \Fragen\Git_Updater\Base::$options );

		$result = $this->upgrade->pre_unschedule_event( null, $future, 'gu_delete_access_tokens' );

		$this->assertNull( $result );
		$stored = get_site_option( 'git_updater' );
		$this->assertArrayNotHasKey( 'github_access_token', $stored );
	}
}
