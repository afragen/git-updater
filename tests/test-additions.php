<?php
/**
 * Tests for Additions\Additions.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\Base;
use Fragen\Git_Updater\Additions\Additions;

class Test_Additions extends WP_UnitTestCase {

	private Additions $additions;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->additions = new Additions();
	}

	public function tear_down(): void {
		delete_site_option( 'git_updater_additions' );
		parent::tear_down();
	}

	public function test_register_returns_false_for_empty_config(): void {
		$result = $this->additions->register( [], [], 'plugin' );
		$this->assertFalse( $result );
	}

	public function test_register_returns_true_for_non_empty_config(): void {
		$config = [
			[
				'type' => 'github_plugin',
				'slug' => 'nonexistent-plugin-xyzzy',
				'uri'  => 'https://github.com/owner/nonexistent-plugin-xyzzy',
			],
		];

		$result = $this->additions->register( $config, [], 'plugin' );

		$this->assertTrue( $result );
	}

	public function test_add_source_stamps_missing_source_with_home_url_hash(): void {
		$config = [ [ 'slug' => 'my-plugin' ] ];

		$this->additions->add_source( $config );

		$saved = get_site_option( 'git_updater_additions' );
		$this->assertSame( md5( home_url() ), $saved[0]['source'] );
	}

	public function test_add_source_does_not_overwrite_existing_source(): void {
		$config = [ [ 'slug' => 'my-plugin', 'source' => 'already-set' ] ];

		$this->additions->add_source( $config );

		$this->assertFalse( get_site_option( 'git_updater_additions', false ) );
	}

	public function test_add_source_writes_option_only_when_config_changed(): void {
		$config = [
			[ 'slug' => 'plugin-a' ],
			[ 'slug' => 'plugin-b', 'source' => 'existing' ],
		];

		$this->additions->add_source( $config );

		$saved = get_site_option( 'git_updater_additions' );
		$this->assertSame( md5( home_url() ), $saved[0]['source'] );
		$this->assertSame( 'existing', $saved[1]['source'] );
	}

	public function test_add_source_does_not_write_option_for_empty_config(): void {
		$this->additions->add_source( [] );
		$this->assertFalse( get_site_option( 'git_updater_additions', false ) );
	}
}


class Test_Additions_Add_Headers extends WP_UnitTestCase {

	private Additions $additions;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->additions = new Additions();
	}

	private function make_config( string $type, string $slug, string $uri, array $extra = [] ): array {
		return [ array_merge( [ 'type' => $type, 'slug' => $slug, 'uri' => $uri ], $extra ) ];
	}

	// -------------------------------------------------------------------------
	// GitHub
	// -------------------------------------------------------------------------

	public function test_add_headers_github_plugin_sets_GitHubPluginURI(): void {
		$this->additions->add_headers(
			$this->make_config( 'github_plugin', 'no-file/no-file.php', 'https://github.com/owner/repo' ),
			[],
			'plugin'
		);
		$entry = $this->additions->add_to_git_updater['no-file/no-file.php'];
		$this->assertArrayHasKey( 'GitHubPluginURI', $entry );
		$this->assertSame( 'https://github.com/owner/repo', $entry['GitHubPluginURI'] );
	}

	public function test_add_headers_github_theme_sets_GitHubThemeURI(): void {
		$this->additions->add_headers(
			$this->make_config( 'github_theme', 'no-theme', 'https://github.com/owner/repo' ),
			[],
			'theme'
		);
		$this->assertArrayHasKey( 'GitHubThemeURI', $this->additions->add_to_git_updater['no-theme'] );
	}

	// -------------------------------------------------------------------------
	// Bitbucket
	// -------------------------------------------------------------------------

	public function test_add_headers_bitbucket_plugin_sets_BitbucketPluginURI(): void {
		$this->additions->add_headers(
			$this->make_config( 'bitbucket_plugin', 'bb-plugin/bb-plugin.php', 'https://bitbucket.org/owner/repo' ),
			[],
			'plugin'
		);
		$this->assertArrayHasKey( 'BitbucketPluginURI', $this->additions->add_to_git_updater['bb-plugin/bb-plugin.php'] );
	}

	public function test_add_headers_bitbucket_theme_sets_BitbucketThemeURI(): void {
		$this->additions->add_headers(
			$this->make_config( 'bitbucket_theme', 'bb-theme', 'https://bitbucket.org/owner/repo' ),
			[],
			'theme'
		);
		$this->assertArrayHasKey( 'BitbucketThemeURI', $this->additions->add_to_git_updater['bb-theme'] );
	}

	// -------------------------------------------------------------------------
	// GitLab
	// -------------------------------------------------------------------------

	public function test_add_headers_gitlab_plugin_sets_GitLabPluginURI(): void {
		$this->additions->add_headers(
			$this->make_config( 'gitlab_plugin', 'gl-plugin/gl-plugin.php', 'https://gitlab.com/owner/repo' ),
			[],
			'plugin'
		);
		$this->assertArrayHasKey( 'GitLabPluginURI', $this->additions->add_to_git_updater['gl-plugin/gl-plugin.php'] );
	}

	public function test_add_headers_gitlab_theme_sets_GitLabThemeURI(): void {
		$this->additions->add_headers(
			$this->make_config( 'gitlab_theme', 'gl-theme', 'https://gitlab.com/owner/repo' ),
			[],
			'theme'
		);
		$this->assertArrayHasKey( 'GitLabThemeURI', $this->additions->add_to_git_updater['gl-theme'] );
	}

	// -------------------------------------------------------------------------
	// Gitea
	// -------------------------------------------------------------------------

	public function test_add_headers_gitea_plugin_sets_GiteaPluginURI(): void {
		$this->additions->add_headers(
			$this->make_config( 'gitea_plugin', 'gta-plugin/gta-plugin.php', 'https://gitea.example.com/owner/repo' ),
			[],
			'plugin'
		);
		$this->assertArrayHasKey( 'GiteaPluginURI', $this->additions->add_to_git_updater['gta-plugin/gta-plugin.php'] );
	}

	public function test_add_headers_gitea_theme_sets_GiteaThemeURI(): void {
		$this->additions->add_headers(
			$this->make_config( 'gitea_theme', 'gta-theme', 'https://gitea.example.com/owner/repo' ),
			[],
			'theme'
		);
		$this->assertArrayHasKey( 'GiteaThemeURI', $this->additions->add_to_git_updater['gta-theme'] );
	}

	// -------------------------------------------------------------------------
	// Type mismatch
	// -------------------------------------------------------------------------

	public function test_add_headers_skips_repo_when_type_does_not_match_requested_type(): void {
		$this->additions->add_headers(
			$this->make_config( 'github_theme', 'some-theme', 'https://github.com/owner/repo' ),
			[],
			'plugin'  // config is a theme, but we ask for plugin
		);
		$this->assertEmpty( $this->additions->add_to_git_updater );
	}

	// -------------------------------------------------------------------------
	// PrimaryBranch
	// -------------------------------------------------------------------------

	public function test_add_headers_defaults_primary_branch_to_master_when_not_set(): void {
		$this->additions->add_headers(
			$this->make_config( 'github_plugin', 'no-file/no-file.php', 'https://github.com/owner/repo' ),
			[],
			'plugin'
		);
		$this->assertSame( 'master', $this->additions->add_to_git_updater['no-file/no-file.php']['PrimaryBranch'] );
	}

	public function test_add_headers_uses_custom_primary_branch_when_provided(): void {
		$this->additions->add_headers(
			$this->make_config( 'github_plugin', 'no-file/no-file.php', 'https://github.com/owner/repo', [ 'primary_branch' => 'main' ] ),
			[],
			'plugin'
		);
		$this->assertSame( 'main', $this->additions->add_to_git_updater['no-file/no-file.php']['PrimaryBranch'] );
	}

	// -------------------------------------------------------------------------
	// ReleaseAsset
	// -------------------------------------------------------------------------

	public function test_add_headers_release_asset_defaults_to_false(): void {
		$this->additions->add_headers(
			$this->make_config( 'github_plugin', 'no-file/no-file.php', 'https://github.com/owner/repo' ),
			[],
			'plugin'
		);
		$this->assertFalse( $this->additions->add_to_git_updater['no-file/no-file.php']['ReleaseAsset'] );
	}

	public function test_add_headers_release_asset_is_true_when_set(): void {
		$this->additions->add_headers(
			$this->make_config( 'github_plugin', 'no-file/no-file.php', 'https://github.com/owner/repo', [ 'release_asset' => true ] ),
			[],
			'plugin'
		);
		$this->assertTrue( $this->additions->add_to_git_updater['no-file/no-file.php']['ReleaseAsset'] );
	}

	// -------------------------------------------------------------------------
	// Multiple repos
	// -------------------------------------------------------------------------

	public function test_add_headers_processes_multiple_repos_in_one_call(): void {
		$config = [
			[ 'type' => 'github_plugin', 'slug' => 'plugin-a/plugin-a.php', 'uri' => 'https://github.com/owner/plugin-a' ],
			[ 'type' => 'github_plugin', 'slug' => 'plugin-b/plugin-b.php', 'uri' => 'https://github.com/owner/plugin-b' ],
		];
		$this->additions->add_headers( $config, [], 'plugin' );

		$this->assertArrayHasKey( 'plugin-a/plugin-a.php', $this->additions->add_to_git_updater );
		$this->assertArrayHasKey( 'plugin-b/plugin-b.php', $this->additions->add_to_git_updater );
	}

	public function test_add_headers_skips_mismatched_type_within_mixed_config(): void {
		$config = [
			[ 'type' => 'github_plugin', 'slug' => 'plugin-a/plugin-a.php', 'uri' => 'https://github.com/owner/plugin-a' ],
			[ 'type' => 'github_theme',  'slug' => 'some-theme',            'uri' => 'https://github.com/owner/some-theme' ],
		];
		$this->additions->add_headers( $config, [], 'plugin' );

		$this->assertArrayHasKey( 'plugin-a/plugin-a.php', $this->additions->add_to_git_updater );
		$this->assertArrayNotHasKey( 'some-theme', $this->additions->add_to_git_updater );
	}

	public function test_add_headers_reads_file_data_when_plugin_file_exists(): void {
		$config = [
			[
				'slug' => 'git-updater/git-updater.php',
				'type' => 'github_plugin',
				'uri'  => 'https://github.com/afragen/git-updater',
			],
		];
		$this->additions->add_headers( $config, [], 'plugin' );
		$this->assertArrayHasKey( 'git-updater/git-updater.php', $this->additions->add_to_git_updater );
	}
}

// ---------------------------------------------------------------------------
// Additions — deduplicate
// ---------------------------------------------------------------------------

/**
 * Class Test_Additions_Deduplicate
 */

class Test_Additions_Deduplicate extends WP_UnitTestCase {

	private Additions $additions;
	private string    $plugin_cache_key;
	private string    $theme_cache_key;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->additions        = new Additions();
		$this->plugin_cache_key = 'ghu-' . md5( 'git_updater_repository_add_plugin' );
		$this->theme_cache_key  = 'ghu-' . md5( 'git_updater_repository_add_theme' );
	}

	public function tear_down(): void {
		delete_site_option( $this->plugin_cache_key );
		delete_site_option( $this->theme_cache_key );
		delete_site_option( 'git_updater_collections' );
		parent::tear_down();
	}

	public function test_deduplicate_returns_empty_array_immediately_for_empty_options(): void {
		$this->assertSame( [], $this->additions->deduplicate( [] ) );
	}

	public function test_deduplicate_normalizes_release_asset_to_false_when_absent(): void {
		$options = [ [ 'ID' => md5( 'my-plugin' ), 'source' => 'src1' ] ];
		$result  = $this->additions->deduplicate( $options );
		$this->assertFalse( $result[0]['release_asset'] );
	}

	public function test_deduplicate_normalizes_private_package_to_false_when_absent(): void {
		$options = [ [ 'ID' => md5( 'my-plugin' ), 'source' => 'src1' ] ];
		$result  = $this->additions->deduplicate( $options );
		$this->assertFalse( $result[0]['private_package'] );
	}

	public function test_deduplicate_normalizes_release_asset_to_true_when_truthy(): void {
		$options = [ [ 'ID' => md5( 'my-plugin' ), 'source' => 'src1', 'release_asset' => '1' ] ];
		$result  = $this->additions->deduplicate( $options );
		$this->assertTrue( $result[0]['release_asset'] );
	}

	public function test_deduplicate_removes_identical_duplicate_entries(): void {
		$item    = [ 'ID' => md5( 'my-plugin' ), 'source' => 'src1' ];
		$options = [ $item, $item ];
		$result  = $this->additions->deduplicate( $options );
		$this->assertCount( 1, $result );
	}

	public function test_deduplicate_removes_cached_package_when_same_id_different_source(): void {
		$shared_id = md5( 'shared-plugin' );
		$options   = [ [ 'ID' => $shared_id, 'source' => 'local-source' ] ];

		update_site_option( $this->plugin_cache_key, [
			'git_updater_repository_add_plugin' => [ [ 'ID' => $shared_id, 'source' => 'remote-source' ] ],
			'timeout'                            => strtotime( '+12 hours' ),
		] );

		$result  = $this->additions->deduplicate( $options );
		$sources = array_column( $result, 'source' );
		$this->assertNotContains( 'remote-source', $sources );
	}

	public function test_deduplicate_keeps_cached_package_when_ids_differ(): void {
		$options  = [ [ 'ID' => md5( 'plugin-a' ), 'source' => 'src1' ] ];
		$packages = [ [ 'ID' => md5( 'plugin-b' ), 'source' => 'src2' ] ];

		update_site_option( $this->plugin_cache_key, [
			'git_updater_repository_add_plugin' => $packages,
			'timeout'                            => strtotime( '+12 hours' ),
		] );

		$result = $this->additions->deduplicate( $options );
		$ids    = array_column( $result, 'ID' );
		$this->assertContains( md5( 'plugin-b' ), $ids );
	}

	public function test_deduplicate_merges_theme_cache_packages(): void {
		$options  = [ [ 'ID' => md5( 'my-plugin' ), 'source' => 'src1' ] ];
		$packages = [ [ 'ID' => md5( 'my-theme' ), 'source' => 'src2' ] ];

		update_site_option( $this->theme_cache_key, [
			'git_updater_repository_add_theme' => $packages,
			'timeout'                          => strtotime( '+12 hours' ),
		] );

		$result = $this->additions->deduplicate( $options );
		$ids    = array_column( $result, 'ID' );
		$this->assertContains( md5( 'my-theme' ), $ids );
	}

	public function test_deduplicate_removes_option_whose_source_matches_a_collection(): void {
		$collection_id = md5( 'collection-home' );
		$options       = [ [ 'ID' => md5( 'remote-plugin' ), 'source' => $collection_id ] ];
		update_site_option( 'git_updater_collections', [ [ 'ID' => $collection_id ] ] );

		$result = $this->additions->deduplicate( $options );

		$this->assertEmpty( $result );
	}

	public function test_deduplicate_keeps_option_whose_source_does_not_match_any_collection(): void {
		$options = [ [ 'ID' => md5( 'local-plugin' ), 'source' => 'local-source' ] ];
		update_site_option( 'git_updater_collections', [ [ 'ID' => 'other-collection' ] ] );

		$result = $this->additions->deduplicate( $options );

		$this->assertCount( 1, $result );
	}
}

// ---------------------------------------------------------------------------
// Additions\Settings
// ---------------------------------------------------------------------------

/**
 * Class Test_Additions_Settings_Methods
 */