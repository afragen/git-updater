<?php
/**
 * Tests for Ignore.
 *
 * Covers:
 * - gu_config_pre_process filter        — removes the ignored slug from the repo config
 * - gu_display_repos filter             — marks the ignored repo dismiss=true /
 *                                         remote_version=false
 * - gu_add_repo_setting_field filter    — clears the field array when the file matches
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\Ignore;

class Test_Ignore extends WP_UnitTestCase {

	public function tear_down(): void {
		Ignore::$repos = [];
		remove_all_filters( 'gu_config_pre_process' );
		remove_all_filters( 'gu_display_repos' );
		remove_all_filters( 'gu_add_repo_setting_field' );
		parent::tear_down();
	}

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
		new Ignore( 'my-plugin', null );
		$token       = new stdClass();
		$token->file = 'my-plugin/plugin.php';
		$arr         = [ 'label' => 'Token' ];

		$result = apply_filters( 'gu_add_repo_setting_field', $arr, $token );

		$this->assertSame( $arr, $result );
	}
}
