<?php
/**
 * Tests for GU_Upgrade and Ignore.
 *
 * GU_Upgrade:
 * - convert_ghu_options_to_gu_options() — migrate legacy github_updater site option
 * - pre_unschedule_event()              — passthrough filter; acts only on the
 *                                         gu_delete_access_tokens hook
 *
 * Ignore:
 * - gu_config_pre_process filter        — removes the ignored slug from the repo config
 * - gu_display_repos filter             — marks the ignored repo dismiss=true /
 *                                         remote_version=false
 * - gu_add_repo_setting_field filter    — clears the field array when the file matches
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\GU_Upgrade;
use Fragen\Git_Updater\Ignore;

// ---------------------------------------------------------------------------
// GU_Upgrade
// ---------------------------------------------------------------------------

/**
 * Class Test_GU_Upgrade
 */
class Test_GU_Upgrade extends WP_UnitTestCase {

	private GU_Upgrade $upgrade;
	private array $original_options;

	public function set_up(): void {
		parent::set_up();
		$this->upgrade        = new GU_Upgrade();
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
		// wp_next_scheduled returns false → days is negative → flush_tokens not called.
		$result = $this->upgrade->pre_unschedule_event( null, time(), 'gu_delete_access_tokens' );
		$this->assertNull( $result );
	}

	public function test_pre_unschedule_returns_pre_when_event_is_less_than_30_days_away(): void {
		$future = time() + DAY_IN_SECONDS; // 1 day from now — well under 29 days.
		wp_schedule_single_event( $future, 'gu_delete_access_tokens' );

		$result = $this->upgrade->pre_unschedule_event( null, time(), 'gu_delete_access_tokens' );

		wp_unschedule_event( $future, 'gu_delete_access_tokens' );
		$this->assertNull( $result );
	}

	// -------------------------------------------------------------------------
	// run()
	// -------------------------------------------------------------------------

	public function test_run_returns_early_when_db_version_matches_current(): void {
		\Fragen\Git_Updater\Base::$options = [ 'db_version' => '12.24.2' ];
		update_site_option( 'git_updater', \Fragen\Git_Updater\Base::$options );

		$this->upgrade->run();

		$stored = get_site_option( 'git_updater' );
		$this->assertSame( '12.24.2', $stored['db_version'] );
		$this->assertNotFalse( wp_next_scheduled( 'gu_delete_access_tokens' ) );
	}

	public function test_run_upgrades_db_version_when_older(): void {
		// No db_version key → ternary defaults to '6.0.0' (older than current).
		\Fragen\Git_Updater\Base::$options = [ 'some_token' => 'abc' ];
		update_site_option( 'git_updater', \Fragen\Git_Updater\Base::$options );

		$this->upgrade->run();

		$stored = get_site_option( 'git_updater' );
		$this->assertSame( '12.24.2', $stored['db_version'] );
		$this->assertArrayHasKey( 'some_token', $stored );
	}

	public function test_run_hits_default_branch_when_db_version_is_newer(): void {
		\Fragen\Git_Updater\Base::$options = [ 'db_version' => '99.0.0' ];
		update_site_option( 'git_updater', \Fragen\Git_Updater\Base::$options );

		$this->upgrade->run();

		// save_db_version() was not called — stored version is still 99.0.0.
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

		// Use truthy values for base keys — '0' would be dropped by array_filter.
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

	// -------------------------------------------------------------------------
	// pre_unschedule_event() — $days > 29 branch
	// -------------------------------------------------------------------------

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

// ---------------------------------------------------------------------------
// Ignore
// ---------------------------------------------------------------------------

/**
 * Class Test_Ignore
 */
class Test_Ignore extends WP_UnitTestCase {

	public function tear_down(): void {
		// Reset the shared static registry so tests don't bleed into each other.
		Ignore::$repos = [];

		remove_all_filters( 'gu_config_pre_process' );
		remove_all_filters( 'gu_display_repos' );
		remove_all_filters( 'gu_add_repo_setting_field' );

		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// gu_config_pre_process filter
	// -------------------------------------------------------------------------

	public function test_config_pre_process_removes_ignored_slug_from_config(): void {
		new Ignore( 'my-plugin', 'my-plugin/plugin.php' );
		$config = [
			'my-plugin'    => (object) [ 'slug' => 'my-plugin' ],
			'other-plugin' => (object) [ 'slug' => 'other-plugin' ],
		];

		$result = apply_filters( 'gu_config_pre_process', $config );

		$this->assertArrayNotHasKey( 'my-plugin', $result );
	}

	public function test_config_pre_process_preserves_non_ignored_repos(): void {
		new Ignore( 'my-plugin', 'my-plugin/plugin.php' );
		$config = [
			'my-plugin'    => (object) [],
			'other-plugin' => (object) [],
		];

		$result = apply_filters( 'gu_config_pre_process', $config );

		$this->assertArrayHasKey( 'other-plugin', $result );
	}

	public function test_config_pre_process_handles_empty_config_gracefully(): void {
		new Ignore( 'my-plugin', 'my-plugin/plugin.php' );

		$result = apply_filters( 'gu_config_pre_process', [] );

		$this->assertSame( [], $result );
	}

	public function test_config_pre_process_removes_all_ignored_slugs_when_multiple_ignored(): void {
		new Ignore( 'plugin-a', 'plugin-a/plugin-a.php' );
		new Ignore( 'plugin-b', 'plugin-b/plugin-b.php' );
		$config = [
			'plugin-a' => (object) [],
			'plugin-b' => (object) [],
			'plugin-c' => (object) [],
		];

		$result = apply_filters( 'gu_config_pre_process', $config );

		$this->assertArrayNotHasKey( 'plugin-a', $result );
		$this->assertArrayNotHasKey( 'plugin-b', $result );
		$this->assertArrayHasKey( 'plugin-c', $result );
	}

	// -------------------------------------------------------------------------
	// gu_display_repos filter
	// -------------------------------------------------------------------------

	public function test_display_repos_sets_dismiss_true_for_ignored_repo(): void {
		new Ignore( 'my-plugin', 'my-plugin/plugin.php' );
		$repo       = new stdClass();
		$type_repos = [ 'my-plugin' => $repo ];

		$result = apply_filters( 'gu_display_repos', $type_repos );

		$this->assertTrue( $result['my-plugin']->dismiss );
	}

	public function test_display_repos_sets_remote_version_false_for_ignored_repo(): void {
		new Ignore( 'my-plugin', 'my-plugin/plugin.php' );
		$repo                 = new stdClass();
		$repo->remote_version = '1.0.0';
		$type_repos           = [ 'my-plugin' => $repo ];

		$result = apply_filters( 'gu_display_repos', $type_repos );

		$this->assertFalse( $result['my-plugin']->remote_version );
	}

	public function test_display_repos_leaves_non_ignored_repos_unchanged(): void {
		new Ignore( 'my-plugin', 'my-plugin/plugin.php' );
		$repo                 = new stdClass();
		$repo->remote_version = '2.0.0';
		$type_repos           = [ 'other-plugin' => $repo ];

		$result = apply_filters( 'gu_display_repos', $type_repos );

		$this->assertSame( '2.0.0', $result['other-plugin']->remote_version );
	}

	// -------------------------------------------------------------------------
	// gu_add_repo_setting_field filter
	// -------------------------------------------------------------------------

	public function test_setting_field_returns_empty_array_when_file_matches_ignored_repo(): void {
		new Ignore( 'my-plugin', 'my-plugin/plugin.php' );
		$token       = new stdClass();
		$token->file = 'my-plugin/plugin.php';

		$result = apply_filters( 'gu_add_repo_setting_field', [ 'label' => 'Token', 'type' => 'text' ], $token );

		$this->assertSame( [], $result );
	}

	public function test_setting_field_returns_array_unchanged_when_file_does_not_match(): void {
		new Ignore( 'my-plugin', 'my-plugin/plugin.php' );
		$token       = new stdClass();
		$token->file = 'other-plugin/plugin.php';
		$arr         = [ 'label' => 'Token', 'type' => 'text' ];

		$result = apply_filters( 'gu_add_repo_setting_field', $arr, $token );

		$this->assertSame( $arr, $result );
	}

	public function test_setting_field_returns_array_unchanged_when_no_repos_ignored(): void {
		// Ignore with null file means no file-match is possible.
		new Ignore( 'my-plugin', null );
		$token       = new stdClass();
		$token->file = 'my-plugin/plugin.php';
		$arr         = [ 'label' => 'Token' ];

		$result = apply_filters( 'gu_add_repo_setting_field', $arr, $token );

		$this->assertSame( $arr, $result );
	}
}
