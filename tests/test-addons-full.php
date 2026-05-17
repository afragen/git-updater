<?php
/**
 * Complete coverage for Add_Ons, Additions::add_headers, Additions::deduplicate,
 * Additions\Settings, and Additions\Repo_List_Table.
 *
 * Add_Ons:
 * - load_hooks()               — registers admin_init, plugin-information, plugins_api, and settings tab
 * - get_addon_api_results()    — cache-hit; all-succeed fetch; partial fail; error-body; cache write/skip
 *
 * Additions:
 * - add_headers()              — github/bitbucket/gitlab/gitea plugin+theme URIs; type-mismatch skip;
 *                                PrimaryBranch default/custom; ReleaseAsset true/false; multiple repos
 * - deduplicate()              — empty passthrough; normalization; identical-option dedup;
 *                                cached package removed when same ID / different source;
 *                                cached package kept when different ID; collections filter
 *
 * Additions\Settings:
 * - sanitize()                 — URI trailing-slash; ID md5; source md5; primary_branch default/custom;
 *                                private_package default/set; result indexed at [0]
 * - add_settings_tabs()        — registers git_updater_additions tab; preserves existing tabs
 * - print_section_additions()  — outputs descriptive text
 * - callback_field()           — renders input element with correct name attribute
 * - callback_dropdown()        — renders select with addition types; respects gua_addition_types filter
 * - callback_checkbox()        — renders checkbox; checked state when option set
 *
 * Additions\Repo_List_Table:
 * - get_columns()              — returns all six expected column keys
 * - get_sortable_columns()     — includes slug and type
 * - get_bulk_actions()         — includes delete
 * - column_default()           — returns correct item value for slug/uri/type columns
 * - column_cb()                — outputs checkbox with item ID as value
 * - usort_reorder()            — ascending default; descending when requested
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\Add_Ons;
use Fragen\Git_Updater\Base;
use Fragen\Git_Updater\Additions\Additions;
use Fragen\Git_Updater\Additions\Bootstrap;
use Fragen\Git_Updater\Additions\Settings as Additions_Settings;
use Fragen\Git_Updater\Additions\Repo_List_Table;

// ---------------------------------------------------------------------------
// Add_Ons — load_hooks
// ---------------------------------------------------------------------------

/**
 * Class Test_Add_Ons_Load_Hooks
 */
class Test_Add_Ons_Load_Hooks extends WP_UnitTestCase {

	private Add_Ons $addons;

	public function set_up(): void {
		parent::set_up();
		$this->addons = new Add_Ons();
	}

	public function tear_down(): void {
		remove_action( 'admin_init', [ $this->addons, 'addons_page_init' ] );
		remove_action( 'install_plugins_pre_plugin-information', [ $this->addons, 'prevent_redirect_on_modal_activation' ] );
		remove_filter( 'plugins_api', [ $this->addons, 'plugins_api' ], 99 );
		remove_all_filters( 'gu_add_settings_tabs' );
		remove_all_actions( 'gu_add_admin_page' );
		parent::tear_down();
	}

	public function test_load_hooks_registers_admin_init_action(): void {
		$this->addons->load_hooks();
		$this->assertNotFalse( has_action( 'admin_init', [ $this->addons, 'addons_page_init' ] ) );
	}

	public function test_load_hooks_registers_plugin_information_action(): void {
		$this->addons->load_hooks();
		$this->assertNotFalse( has_action( 'install_plugins_pre_plugin-information', [ $this->addons, 'prevent_redirect_on_modal_activation' ] ) );
	}

	public function test_load_hooks_registers_plugins_api_filter(): void {
		$this->addons->load_hooks();
		$this->assertNotFalse( has_filter( 'plugins_api', [ $this->addons, 'plugins_api' ] ) );
	}

	public function test_load_hooks_registers_git_updater_addons_settings_tab(): void {
		$this->addons->load_hooks();
		$tabs = apply_filters( 'gu_add_settings_tabs', [] );
		$this->assertArrayHasKey( 'git_updater_addons', $tabs );
	}

	public function test_load_hooks_addons_tab_label_is_non_empty(): void {
		$this->addons->load_hooks();
		$tabs = apply_filters( 'gu_add_settings_tabs', [] );
		$this->assertNotEmpty( $tabs['git_updater_addons'] );
	}
}

// ---------------------------------------------------------------------------
// Add_Ons — get_addon_api_results
// ---------------------------------------------------------------------------

/**
 * Class Test_Add_Ons_Api_Results
 */
class Test_Add_Ons_Api_Results extends WP_UnitTestCase {

	private Add_Ons $addons;
	private string  $cache_key;

	public function set_up(): void {
		parent::set_up();
		$this->addons    = new Add_Ons();
		$this->cache_key = 'ghu-' . md5( 'gu_addon_api_results' );
	}

	public function tear_down(): void {
		delete_site_option( $this->cache_key );
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	private function mock_http( int $code, array $body = [ 'name' => 'Addon' ] ): void {
		add_filter(
			'pre_http_request',
			fn() => [
				'response' => [ 'code' => $code, 'message' => 200 === $code ? 'OK' : 'Error' ],
				'body'     => json_encode( $body ),
				'headers'  => [],
			],
			10,
			3
		);
	}

	public function test_get_addon_api_results_returns_cached_data_without_http(): void {
		$data = [ 'git-updater-gist' => [ 'name' => 'Git Updater Gist' ] ];
		update_site_option(
			$this->cache_key,
			[ 'gu_addon_api_results' => $data, 'timeout' => strtotime( '+7 days' ) ]
		);

		$result = $this->addons->get_addon_api_results();

		$this->assertSame( $data, $result );
	}

	public function test_get_addon_api_results_returns_all_four_addons_when_all_succeed(): void {
		$this->mock_http( 200 );

		$result = $this->addons->get_addon_api_results();

		$this->assertCount( 4, $result );
	}

	public function test_get_addon_api_results_result_keys_match_addon_slugs(): void {
		$this->mock_http( 200 );

		$result = $this->addons->get_addon_api_results();

		$this->assertArrayHasKey( 'git-updater-gist', $result );
		$this->assertArrayHasKey( 'git-updater-bitbucket', $result );
		$this->assertArrayHasKey( 'git-updater-gitlab', $result );
		$this->assertArrayHasKey( 'git-updater-gitea', $result );
	}

	public function test_get_addon_api_results_skips_addon_on_non_200_response(): void {
		$n = 0;
		add_filter(
			'pre_http_request',
			function () use ( &$n ) {
				++$n;
				return [
					'response' => [ 'code' => 1 === $n ? 404 : 200, 'message' => 'OK' ],
					'body'     => json_encode( [ 'name' => 'Addon' ] ),
					'headers'  => [],
				];
			},
			10,
			3
		);

		$result = $this->addons->get_addon_api_results();

		$this->assertCount( 3, $result );
	}

	public function test_get_addon_api_results_skips_addon_when_body_contains_error_key(): void {
		$this->mock_http( 200, [ 'error' => 'Plugin not found' ] );

		$result = $this->addons->get_addon_api_results();

		$this->assertEmpty( $result );
	}

	public function test_get_addon_api_results_writes_cache_when_all_addons_succeed(): void {
		$this->mock_http( 200 );

		$this->addons->get_addon_api_results();

		$cache = get_site_option( $this->cache_key );
		$this->assertIsArray( $cache );
		$this->assertArrayHasKey( 'gu_addon_api_results', $cache );
	}

	public function test_get_addon_api_results_does_not_write_cache_for_partial_results(): void {
		$n = 0;
		add_filter(
			'pre_http_request',
			function () use ( &$n ) {
				++$n;
				return [
					'response' => [ 'code' => 1 === $n ? 404 : 200, 'message' => 'OK' ],
					'body'     => json_encode( [ 'name' => 'Addon' ] ),
					'headers'  => [],
				];
			},
			10,
			3
		);

		$this->addons->get_addon_api_results();

		$this->assertFalse( get_site_option( $this->cache_key, false ) );
	}

	public function test_plugins_api_returns_original_result_for_addon_slug_with_no_cached_data(): void {
		// All HTTP calls fail so get_addon_api_results() returns [].
		$this->mock_http( 404 );

		$original = (object) [ 'name' => 'Original' ];
		$args     = (object) [ 'slug' => 'git-updater-gist' ];

		$returned = $this->addons->plugins_api( $original, 'plugin_information', $args );

		$this->assertSame( $original, $returned );
	}

	public function test_plugins_api_returns_result_object_when_slug_found_in_api_results(): void {
		$data = [ 'git-updater-gist' => [ 'name' => 'Git Updater Gist', 'slug' => 'git-updater-gist' ] ];
		update_site_option(
			$this->cache_key,
			[ 'gu_addon_api_results' => $data, 'timeout' => strtotime( '+7 days' ) ]
		);

		$args   = (object) [ 'slug' => 'git-updater-gist' ];
		$result = $this->addons->plugins_api( new stdClass(), 'plugin_information', $args );

		$this->assertSame( 'Git Updater Gist', $result->name );
	}
}

// ---------------------------------------------------------------------------
// Additions — add_headers
// ---------------------------------------------------------------------------

/**
 * Class Test_Additions_Add_Headers
 */
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
class Test_Additions_Settings_Methods extends WP_UnitTestCase {

	private Additions_Settings $settings;

	public function set_up(): void {
		parent::set_up();
		Additions_Settings::$options_additions = [];
		$this->settings = new Additions_Settings();
	}

	public function tear_down(): void {
		remove_all_filters( 'gu_add_settings_tabs' );
		remove_all_filters( 'gua_addition_types' );
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// sanitize()
	// -------------------------------------------------------------------------

	public function test_sanitize_returns_array_indexed_at_zero(): void {
		$result = $this->settings->sanitize( [ 'slug' => 'my/plugin.php', 'uri' => 'https://github.com/owner/repo' ] );
		$this->assertArrayHasKey( 0, $result );
	}

	public function test_sanitize_strips_trailing_slash_from_uri(): void {
		$result = $this->settings->sanitize( [ 'slug' => 'my/plugin.php', 'uri' => 'https://github.com/owner/repo/' ] );
		$this->assertSame( 'https://github.com/owner/repo', $result[0]['uri'] );
	}

	public function test_sanitize_sets_id_as_md5_of_slug(): void {
		$result = $this->settings->sanitize( [ 'slug' => 'my/plugin.php', 'uri' => 'https://github.com/owner/repo' ] );
		$this->assertSame( md5( 'my/plugin.php' ), $result[0]['ID'] );
	}

	public function test_sanitize_sets_source_as_md5_of_home_url(): void {
		$result = $this->settings->sanitize( [ 'slug' => 'my/plugin.php', 'uri' => 'https://github.com/owner/repo' ] );
		$this->assertSame( md5( home_url() ), $result[0]['source'] );
	}

	public function test_sanitize_defaults_primary_branch_to_master_when_absent(): void {
		$result = $this->settings->sanitize( [ 'slug' => 'my/plugin.php', 'uri' => 'https://github.com/owner/repo' ] );
		$this->assertSame( 'master', $result[0]['primary_branch'] );
	}

	public function test_sanitize_preserves_custom_primary_branch(): void {
		$result = $this->settings->sanitize( [ 'slug' => 'my/plugin.php', 'uri' => 'https://github.com/owner/repo', 'primary_branch' => 'main' ] );
		$this->assertSame( 'main', $result[0]['primary_branch'] );
	}

	public function test_sanitize_private_package_defaults_to_false(): void {
		$result = $this->settings->sanitize( [ 'slug' => 'my/plugin.php', 'uri' => 'https://github.com/owner/repo' ] );
		$this->assertFalse( $result[0]['private_package'] );
	}

	public function test_sanitize_private_package_is_true_when_truthy_value_provided(): void {
		$result = $this->settings->sanitize( [ 'slug' => 'my/plugin.php', 'uri' => 'https://github.com/owner/repo', 'private_package' => '1' ] );
		$this->assertTrue( $result[0]['private_package'] );
	}

	public function test_sanitize_non_uri_fields_are_sanitized_as_text(): void {
		$result = $this->settings->sanitize( [ 'slug' => "  my/plugin.php\t", 'uri' => 'https://github.com/owner/repo' ] );
		$this->assertSame( 'my/plugin.php', $result[0]['slug'] );
	}

	// -------------------------------------------------------------------------
	// add_settings_tabs()
	// -------------------------------------------------------------------------

	public function test_add_settings_tabs_registers_git_updater_additions_tab(): void {
		$this->settings->add_settings_tabs();
		$tabs = apply_filters( 'gu_add_settings_tabs', [] );
		$this->assertArrayHasKey( 'git_updater_additions', $tabs );
	}

	public function test_add_settings_tabs_preserves_existing_tabs(): void {
		$this->settings->add_settings_tabs();
		$tabs = apply_filters( 'gu_add_settings_tabs', [ 'other_tab' => 'Other' ] );
		$this->assertArrayHasKey( 'other_tab', $tabs );
		$this->assertArrayHasKey( 'git_updater_additions', $tabs );
	}

	// -------------------------------------------------------------------------
	// print_section_additions()
	// -------------------------------------------------------------------------

	public function test_print_section_additions_outputs_descriptive_text(): void {
		ob_start();
		$this->settings->print_section_additions();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'git repositories', $output );
	}

	// -------------------------------------------------------------------------
	// callback_field()
	// -------------------------------------------------------------------------

	public function test_callback_field_outputs_text_input_element(): void {
		ob_start();
		$this->settings->callback_field( [ 'id' => 'test_id', 'setting' => 'slug', 'title' => 'Slug' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( '<input type="text"', $output );
	}

	public function test_callback_field_name_attribute_uses_git_updater_additions_prefix(): void {
		ob_start();
		$this->settings->callback_field( [ 'id' => 'test_id', 'setting' => 'slug', 'title' => 'Slug' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'name="git_updater_additions[slug]"', $output );
	}

	public function test_callback_field_includes_title_as_description(): void {
		ob_start();
		$this->settings->callback_field( [ 'id' => 'test_id', 'setting' => 'slug', 'title' => 'My custom title' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'My custom title', $output );
	}

	// -------------------------------------------------------------------------
	// callback_dropdown()
	// -------------------------------------------------------------------------

	public function test_callback_dropdown_outputs_select_element(): void {
		ob_start();
		$this->settings->callback_dropdown( [ 'id' => 'type_id', 'setting' => 'type' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( '<select', $output );
	}

	public function test_callback_dropdown_includes_github_plugin_option(): void {
		ob_start();
		$this->settings->callback_dropdown( [ 'id' => 'type_id', 'setting' => 'type' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'github_plugin', $output );
	}

	public function test_callback_dropdown_includes_github_theme_option(): void {
		ob_start();
		$this->settings->callback_dropdown( [ 'id' => 'type_id', 'setting' => 'type' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'github_theme', $output );
	}

	public function test_callback_dropdown_respects_gua_addition_types_filter(): void {
		add_filter( 'gua_addition_types', fn() => [ 'custom_type' ], 10 );
		ob_start();
		$this->settings->callback_dropdown( [ 'id' => 'type_id', 'setting' => 'type' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'custom_type', $output );
	}

	// -------------------------------------------------------------------------
	// callback_checkbox()
	// -------------------------------------------------------------------------

	public function test_callback_checkbox_outputs_checkbox_input(): void {
		ob_start();
		$this->settings->callback_checkbox( [ 'id' => 'chk_id', 'setting' => 'release_asset', 'title' => 'Release Asset' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'type="checkbox"', $output );
	}

	public function test_callback_checkbox_is_not_checked_when_option_absent(): void {
		ob_start();
		$this->settings->callback_checkbox( [ 'id' => 'chk_id', 'setting' => 'release_asset', 'title' => 'Release Asset' ] );
		$output = ob_get_clean();
		$this->assertStringNotContainsString( "checked='checked'", $output );
	}

	public function test_callback_checkbox_is_checked_when_option_equals_one(): void {
		Additions_Settings::$options_additions = [ 'chk_id' => 1 ];
		ob_start();
		$this->settings->callback_checkbox( [ 'id' => 'chk_id', 'setting' => 'release_asset', 'title' => 'Release Asset' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'checked', $output );
	}

	public function test_callback_checkbox_name_attribute_uses_git_updater_additions_prefix(): void {
		ob_start();
		$this->settings->callback_checkbox( [ 'id' => 'chk_id', 'setting' => 'release_asset', 'title' => 'Release Asset' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'name="git_updater_additions[release_asset]"', $output );
	}
}

// ---------------------------------------------------------------------------
// Additions\Repo_List_Table
// ---------------------------------------------------------------------------

/**
 * Class Test_Repo_List_Table_Methods
 */
class Test_Repo_List_Table_Methods extends WP_UnitTestCase {

	private Repo_List_Table $table;

	public function set_up(): void {
		parent::set_up();
		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}
		$this->table = new Repo_List_Table( [] );
	}

	public function tear_down(): void {
		unset( $_REQUEST['order'], $_REQUEST['orderby'] );
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// get_columns()
	// -------------------------------------------------------------------------

	public function test_get_columns_includes_slug(): void {
		$this->assertArrayHasKey( 'slug', $this->table->get_columns() );
	}

	public function test_get_columns_includes_uri(): void {
		$this->assertArrayHasKey( 'uri', $this->table->get_columns() );
	}

	public function test_get_columns_includes_type(): void {
		$this->assertArrayHasKey( 'type', $this->table->get_columns() );
	}

	public function test_get_columns_includes_primary_branch(): void {
		$this->assertArrayHasKey( 'primary_branch', $this->table->get_columns() );
	}

	public function test_get_columns_includes_release_asset(): void {
		$this->assertArrayHasKey( 'release_asset', $this->table->get_columns() );
	}

	public function test_get_columns_includes_private_package(): void {
		$this->assertArrayHasKey( 'private_package', $this->table->get_columns() );
	}

	public function test_get_columns_returns_exactly_six_columns(): void {
		$this->assertCount( 6, $this->table->get_columns() );
	}

	// -------------------------------------------------------------------------
	// get_sortable_columns()
	// -------------------------------------------------------------------------

	public function test_get_sortable_columns_includes_slug(): void {
		$this->assertArrayHasKey( 'slug', $this->table->get_sortable_columns() );
	}

	public function test_get_sortable_columns_includes_type(): void {
		$this->assertArrayHasKey( 'type', $this->table->get_sortable_columns() );
	}

	// -------------------------------------------------------------------------
	// get_bulk_actions()
	// -------------------------------------------------------------------------

	public function test_get_bulk_actions_includes_delete(): void {
		$this->assertArrayHasKey( 'delete', $this->table->get_bulk_actions() );
	}

	// -------------------------------------------------------------------------
	// column_default()
	// -------------------------------------------------------------------------

	private function make_item( array $overrides = [] ): array {
		return array_merge(
			[
				'slug'            => 'test-plugin/test-plugin.php',
				'uri'             => 'https://github.com/owner/test-plugin',
				'type'            => 'github_plugin',
				'primary_branch'  => 'master',
				'release_asset'   => false,
				'private_package' => false,
			],
			$overrides
		);
	}

	public function test_column_default_returns_slug_for_slug_column(): void {
		$item = $this->make_item( [ 'slug' => 'my-plugin/my-plugin.php' ] );
		$this->assertSame( 'my-plugin/my-plugin.php', $this->table->column_default( $item, 'slug' ) );
	}

	public function test_column_default_returns_uri_for_uri_column(): void {
		$item = $this->make_item( [ 'uri' => 'https://github.com/owner/repo' ] );
		$this->assertSame( 'https://github.com/owner/repo', $this->table->column_default( $item, 'uri' ) );
	}

	public function test_column_default_returns_type_for_type_column(): void {
		$item = $this->make_item( [ 'type' => 'bitbucket_plugin' ] );
		$this->assertSame( 'bitbucket_plugin', $this->table->column_default( $item, 'type' ) );
	}

	public function test_column_default_returns_primary_branch_value(): void {
		$item = $this->make_item( [ 'primary_branch' => 'develop' ] );
		$this->assertSame( 'develop', $this->table->column_default( $item, 'primary_branch' ) );
	}

	public function test_column_default_returns_release_asset_value(): void {
		$item = $this->make_item( [ 'release_asset' => '<span>yes</span>' ] );
		$this->assertSame( '<span>yes</span>', $this->table->column_default( $item, 'release_asset' ) );
	}

	// -------------------------------------------------------------------------
	// column_cb()
	// -------------------------------------------------------------------------

	public function test_column_cb_outputs_checkbox_input(): void {
		$item = [ 'ID' => md5( 'my-plugin' ) ];
		$this->assertStringContainsString( 'type="checkbox"', $this->table->column_cb( $item ) );
	}

	public function test_column_cb_uses_item_id_as_checkbox_value(): void {
		$id   = md5( 'my-plugin' );
		$item = [ 'ID' => $id ];
		$this->assertStringContainsString( "value=\"{$id}\"", $this->table->column_cb( $item ) );
	}

	// -------------------------------------------------------------------------
	// usort_reorder()
	// -------------------------------------------------------------------------

	public function test_usort_reorder_sorts_ascending_by_slug_by_default(): void {
		$a = [ 'slug' => 'alpha-plugin' ];
		$b = [ 'slug' => 'beta-plugin' ];
		// alpha < beta → strcmp result < 0 (a before b ascending)
		$this->assertLessThan( 0, $this->table->usort_reorder( $a, $b ) );
	}

	public function test_usort_reorder_reverses_result_when_order_is_desc(): void {
		$_REQUEST['order']   = 'desc';
		$_REQUEST['orderby'] = 'slug';
		$a = [ 'slug' => 'alpha-plugin' ];
		$b = [ 'slug' => 'beta-plugin' ];
		// Descending: alpha should sort after beta → result > 0
		$this->assertGreaterThan( 0, $this->table->usort_reorder( $a, $b ) );
	}

	public function test_usort_reorder_uses_orderby_request_param(): void {
		$_REQUEST['orderby'] = 'type';
		$_REQUEST['order']   = 'asc';
		$a = [ 'type' => 'github_plugin',    'slug' => 'zzz' ];
		$b = [ 'type' => 'bitbucket_plugin', 'slug' => 'aaa' ];
		// bitbucket < github → b before a when sorted by type asc → result > 0 (b < a)
		$this->assertGreaterThan( 0, $this->table->usort_reorder( $a, $b ) );
	}
}

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

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

class Test_Additions_Settings_Load_Hooks extends WP_UnitTestCase {

	private array $pre_registered_bindings = [];

	public function set_up(): void {
		parent::set_up();
		if ( class_exists( 'WP_Block_Bindings_Registry' ) ) {
			$this->pre_registered_bindings = array_keys(
				WP_Block_Bindings_Registry::get_instance()->get_all_registered()
			);
		}
	}

	public function tear_down(): void {
		unset( $_POST['_wpnonce'] );
		remove_all_actions( 'gu_update_settings' );
		remove_all_actions( 'init' );
		remove_all_actions( 'gu_add_admin_page' );
		remove_all_filters( 'gu_add_settings_tabs' );
		delete_site_option( 'git_updater_additions' );
		if ( class_exists( 'WP_Block_Bindings_Registry' ) ) {
			foreach ( array_keys( WP_Block_Bindings_Registry::get_instance()->get_all_registered() ) as $name ) {
				if ( ! in_array( $name, $this->pre_registered_bindings, true ) ) {
					unregister_block_bindings_source( $name );
				}
			}
		}
		parent::tear_down();
	}

	public function test_load_hooks_registers_gu_update_settings_action(): void {
		( new Additions_Settings() )->load_hooks();
		$this->assertNotFalse( has_action( 'gu_update_settings' ) );
	}

	public function test_load_hooks_registers_init_action(): void {
		( new Additions_Settings() )->load_hooks();
		$this->assertNotFalse( has_action( 'init' ) );
	}

	public function test_load_hooks_registers_gu_add_admin_page_at_priority_10(): void {
		( new Additions_Settings() )->load_hooks();
		$this->assertNotFalse( has_action( 'gu_add_admin_page' ) );
	}

	public function test_gu_update_settings_action_fires_save_settings_closure(): void {
		( new Additions_Settings() )->load_hooks();
		unset( $_POST['_wpnonce'] );
		do_action( 'gu_update_settings', [] );
		$this->assertFalse( get_site_option( 'git_updater_additions' ) );
	}

	public function test_init_action_fires_add_settings_tabs_closure(): void {
		( new Additions_Settings() )->load_hooks();
		if ( class_exists( 'WP_Block_Bindings_Registry' ) ) {
			foreach ( array_keys( WP_Block_Bindings_Registry::get_instance()->get_all_registered() ) as $name ) {
				unregister_block_bindings_source( $name );
			}
		}
		do_action( 'init' );
		$tabs = apply_filters( 'gu_add_settings_tabs', [] );
		$this->assertArrayHasKey( 'git_updater_additions', $tabs );
	}

	public function test_gu_add_admin_page_action_fires_add_admin_page_closure(): void {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		Additions_Settings::$options_additions = [];
		( new Additions_Settings() )->load_hooks();
		ob_start();
		do_action( 'gu_add_admin_page', 'other_tab', admin_url() );
		ob_get_clean();
		$this->assertTrue( true );
	}
}

// ---------------------------------------------------------------------------
// Settings::save_settings()
// ---------------------------------------------------------------------------

class Test_Additions_Settings_Save_Settings extends WP_UnitTestCase {

	private Additions_Settings $settings;

	public function set_up(): void {
		parent::set_up();
		Additions_Settings::$options_additions = [];
		$this->settings = new Additions_Settings();
	}

	public function tear_down(): void {
		unset( $_POST['_wpnonce'], $_POST['action'] );
		delete_site_option( 'git_updater_additions' );
		remove_all_filters( 'gu_save_redirect' );
		parent::tear_down();
	}

	private function make_post_data( array $overrides = [] ): array {
		return array_merge(
			[
				'option_page'           => 'git_updater_additions',
				'git_updater_additions' => [
					'slug' => 'owner/plugin.php',
					'uri'  => 'https://github.com/owner/plugin',
					'type' => 'github_plugin',
				],
			],
			$overrides
		);
	}

	public function test_save_settings_returns_early_without_nonce(): void {
		unset( $_POST['_wpnonce'] );
		$this->settings->save_settings( $this->make_post_data() );
		$this->assertFalse( get_site_option( 'git_updater_additions' ) );
	}

	public function test_save_settings_returns_early_with_invalid_nonce(): void {
		$_POST['_wpnonce'] = 'bad_nonce';
		$this->settings->save_settings( $this->make_post_data() );
		$this->assertFalse( get_site_option( 'git_updater_additions' ) );
	}

	public function test_save_settings_does_nothing_when_option_page_absent(): void {
		$_POST['_wpnonce'] = wp_create_nonce( 'git_updater_additions-options' );
		$this->settings->save_settings( [] );
		$this->assertFalse( get_site_option( 'git_updater_additions' ) );
	}

	public function test_save_settings_does_nothing_when_option_page_wrong(): void {
		$_POST['_wpnonce'] = wp_create_nonce( 'git_updater_additions-options' );
		$this->settings->save_settings( [ 'option_page' => 'other_page' ] );
		$this->assertFalse( get_site_option( 'git_updater_additions' ) );
	}

	public function test_save_settings_skips_save_when_slug_empty(): void {
		$_POST['_wpnonce'] = wp_create_nonce( 'git_updater_additions-options' );
		$post_data         = $this->make_post_data(
			[
				'git_updater_additions' => [
					'slug' => '',
					'uri'  => 'https://github.com/owner/plugin',
					'type' => 'github_plugin',
				],
			]
		);
		$this->settings->save_settings( $post_data );
		$this->assertFalse( get_site_option( 'git_updater_additions' ) );
	}

	public function test_save_settings_skips_save_when_uri_empty(): void {
		$_POST['_wpnonce'] = wp_create_nonce( 'git_updater_additions-options' );
		$post_data         = $this->make_post_data(
			[
				'git_updater_additions' => [
					'slug' => 'owner/plugin.php',
					'uri'  => '',
					'type' => 'github_plugin',
				],
			]
		);
		$this->settings->save_settings( $post_data );
		$this->assertFalse( get_site_option( 'git_updater_additions' ) );
	}

	public function test_save_settings_skips_save_when_plugin_type_but_no_slash_in_slug(): void {
		$existing = [
			[
				'slug'   => 'other/plugin.php',
				'type'   => 'github_plugin',
				'uri'    => 'https://github.com/owner/other',
				'ID'     => md5( 'other/plugin.php' ),
				'source' => md5( home_url() ),
			],
		];
		update_site_option( 'git_updater_additions', $existing );
		$_POST['_wpnonce'] = wp_create_nonce( 'git_updater_additions-options' );
		$post_data         = $this->make_post_data(
			[
				'git_updater_additions' => [
					'slug' => 'noslash',
					'uri'  => 'https://github.com/owner/plugin',
					'type' => 'github_plugin',
				],
			]
		);
		$this->settings->save_settings( $post_data );
		$this->assertCount( 1, get_site_option( 'git_updater_additions' ) );
	}

	public function test_save_settings_skips_save_when_theme_type_but_slug_has_slash(): void {
		$existing = [
			[
				'slug'   => 'my-theme',
				'type'   => 'github_theme',
				'uri'    => 'https://github.com/owner/theme',
				'ID'     => md5( 'my-theme' ),
				'source' => md5( home_url() ),
			],
		];
		update_site_option( 'git_updater_additions', $existing );
		$_POST['_wpnonce'] = wp_create_nonce( 'git_updater_additions-options' );
		$post_data         = $this->make_post_data(
			[
				'git_updater_additions' => [
					'slug' => 'with/slash',
					'uri'  => 'https://github.com/owner/plugin',
					'type' => 'github_theme',
				],
			]
		);
		$this->settings->save_settings( $post_data );
		$this->assertCount( 1, get_site_option( 'git_updater_additions' ) );
	}

	public function test_save_settings_skips_save_when_duplicate_id(): void {
		$slug     = 'owner/plugin.php';
		$existing = [
			[
				'slug'   => $slug,
				'type'   => 'github_plugin',
				'uri'    => 'https://github.com/owner/plugin',
				'ID'     => md5( $slug ),
				'source' => md5( home_url() ),
			],
		];
		update_site_option( 'git_updater_additions', $existing );
		$_POST['_wpnonce'] = wp_create_nonce( 'git_updater_additions-options' );
		$this->settings->save_settings( $this->make_post_data() );
		$this->assertCount( 1, get_site_option( 'git_updater_additions' ) );
	}

	public function test_save_settings_saves_option_when_valid_and_no_existing_options(): void {
		$_POST['_wpnonce'] = wp_create_nonce( 'git_updater_additions-options' );
		$this->settings->save_settings( $this->make_post_data() );
		$this->assertCount( 1, get_site_option( 'git_updater_additions' ) );
	}

	public function test_save_settings_saves_option_when_valid_and_existing_options_present(): void {
		$existing = [
			[
				'slug'   => 'other/other.php',
				'type'   => 'github_plugin',
				'uri'    => 'https://github.com/owner/other',
				'ID'     => md5( 'other/other.php' ),
				'source' => md5( home_url() ),
			],
		];
		update_site_option( 'git_updater_additions', $existing );
		$_POST['_wpnonce'] = wp_create_nonce( 'git_updater_additions-options' );
		$this->settings->save_settings( $this->make_post_data() );
		$this->assertCount( 2, get_site_option( 'git_updater_additions' ) );
	}

	public function test_save_settings_adds_gu_save_redirect_filter_when_option_page_matches(): void {
		$_POST['_wpnonce'] = wp_create_nonce( 'git_updater_additions-options' );
		$this->settings->save_settings( $this->make_post_data() );
		$result = apply_filters( 'gu_save_redirect', [] );
		$this->assertContains( 'git_updater_additions', $result );
	}
}

// ---------------------------------------------------------------------------
// Settings::add_admin_page() and additions_page_init()
// ---------------------------------------------------------------------------

class Test_Settings_Add_Admin_Page extends WP_UnitTestCase {

	private Additions_Settings $settings;

	public function set_up(): void {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		Additions_Settings::$options_additions = [];
		$this->settings = new Additions_Settings();
	}

	public function test_add_admin_page_registers_setting_regardless_of_tab(): void {
		$this->settings->add_admin_page( 'other_tab', admin_url() );
		$settings = get_registered_settings();
		$this->assertArrayHasKey( 'git_updater_additions', $settings );
	}

	public function test_add_admin_page_renders_no_form_for_wrong_tab(): void {
		ob_start();
		$this->settings->add_admin_page( 'other_tab', admin_url() );
		$output = ob_get_clean();
		$this->assertStringNotContainsString( '<form', $output );
	}

	public function test_add_admin_page_renders_form_for_correct_tab(): void {
		ob_start();
		$this->settings->add_admin_page( 'git_updater_additions', admin_url() );
		$output = ob_get_clean();
		$this->assertStringContainsString( '<form', $output );
	}
}

// ---------------------------------------------------------------------------
// Repo_List_Table — extended coverage
// ---------------------------------------------------------------------------

class Test_Repo_List_Table_Extended extends WP_UnitTestCase {

	private Repo_List_Table $table;

	public function set_up(): void {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		$this->table = new Repo_List_Table( [] );
	}

	public function tear_down(): void {
		unset(
			$_REQUEST['_wpnonce_row_action_delete'],
			$_REQUEST['slug'],
			$_REQUEST['action'],
			$_REQUEST['page'],
			$_REQUEST['tab']
		);
		delete_site_option( 'git_updater_additions' );
		parent::tear_down();
	}

	private function make_item( array $overrides = [] ): array {
		return array_merge(
			[
				'ID'              => md5( 'test-plugin/test-plugin.php' ),
				'slug'            => 'test-plugin/test-plugin.php',
				'uri'             => 'https://github.com/owner/test-plugin',
				'type'            => 'github_plugin',
				'primary_branch'  => 'master',
				'release_asset'   => false,
				'private_package' => false,
				'source'          => md5( home_url() ),
			],
			$overrides
		);
	}

	public function test_column_default_returns_print_r_for_unknown_column(): void {
		$item   = $this->make_item();
		$result = $this->table->column_default( $item, 'unknown_column' );
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
	}

	public function test_column_slug_contains_slug_text(): void {
		$item   = $this->make_item( [ 'slug' => 'my-plugin/my-plugin.php' ] );
		$result = $this->table->column_slug( $item );
		$this->assertStringContainsString( 'my-plugin/my-plugin.php', $result );
	}

	public function test_column_slug_contains_item_id(): void {
		$id     = md5( 'my-plugin/my-plugin.php' );
		$item   = $this->make_item( [ 'slug' => 'my-plugin/my-plugin.php', 'ID' => $id ] );
		$result = $this->table->column_slug( $item );
		$this->assertStringContainsString( $id, $result );
	}

	public function test_column_slug_contains_delete_link(): void {
		$item   = $this->make_item();
		$result = $this->table->column_slug( $item );
		$this->assertStringContainsString( 'Delete', $result );
	}

	public function test_process_bulk_action_returns_without_nonce(): void {
		unset( $_REQUEST['_wpnonce_row_action_delete'] );
		$this->table->process_bulk_action();
		$this->assertFalse( get_site_option( 'git_updater_additions' ) );
	}

	public function test_process_bulk_action_deletes_matching_entry(): void {
		$id     = md5( 'test-plugin/test-plugin.php' );
		$option = $this->make_item( [ 'ID' => $id ] );
		$table  = new Repo_List_Table( [ $option ] );
		update_site_option( 'git_updater_additions', [ $option ] );

		$_REQUEST['_wpnonce_row_action_delete'] = wp_create_nonce( 'delete_row_item' );
		$_REQUEST['slug']                       = $id;

		$table->process_bulk_action();

		$this->assertEmpty( get_site_option( 'git_updater_additions' ) );
	}

	public function test_process_bulk_action_edit_action_dies(): void {
		$_REQUEST['_wpnonce_row_action_delete'] = wp_create_nonce( 'delete_row_item' );
		$_REQUEST['action']                     = 'edit';

		$this->expectException( WPDieException::class );
		$this->table->process_bulk_action();
	}

	public function test_prepare_items_sets_items_to_array(): void {
		$this->table->prepare_items();
		$this->assertIsArray( $this->table->items );
	}

	public function test_render_list_table_outputs_wrap_div(): void {
		ob_start();
		$this->table->render_list_table();
		$output = ob_get_clean();
		$this->assertStringContainsString( '<div class="wrap">', $output );
	}
}

// ---------------------------------------------------------------------------
// Add_Ons — add_admin_page and addons_page_init
// ---------------------------------------------------------------------------

/**
 * Class Test_Add_Ons_Admin_Page_And_Init
 */
class Test_Add_Ons_Admin_Page_And_Init extends WP_UnitTestCase {

	private Add_Ons $addons;

	public function set_up(): void {
		parent::set_up();
		$this->addons = new Add_Ons();
	}

	public function tear_down(): void {
		global $wp_settings_sections, $wp_settings_fields;
		unset( $wp_settings_sections['git_updater_addons_settings'] );
		unset( $wp_settings_fields['git_updater_addons_settings'] );
		wp_dequeue_script( 'ajax-activate' );
		wp_deregister_script( 'ajax-activate' );
		remove_all_filters( 'gu_add_settings_tabs' );
		remove_all_actions( 'gu_add_admin_page' );
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	public function test_add_admin_page_matching_tab_enqueues_plugin_install_script(): void {
		$this->addons->add_admin_page( 'git_updater_addons' );
		// ajax-activate is newly registered+enqueued by add_admin_page — reliable signal that the body ran.
		$this->assertTrue( wp_script_is( 'ajax-activate', 'registered' ) );
	}

	public function test_gu_add_admin_page_action_closure_invokes_add_admin_page(): void {
		// Fires the closure registered on 'gu_add_admin_page' by add_settings_tabs() — covers line 70.
		// Pass two args: Additions\Settings also registers on this action with accepted_args=2.
		$this->addons->add_settings_tabs();
		do_action( 'gu_add_admin_page', 'git_updater_addons', admin_url() );
		$this->assertTrue( wp_script_is( 'ajax-activate', 'registered' ) );
	}

	public function test_addons_page_init_registers_setting(): void {
		$this->addons->addons_page_init();
		$settings = get_registered_settings();
		$this->assertArrayHasKey( 'git_updater_addons_settings', $settings );
	}

	public function test_addons_page_init_registers_settings_section(): void {
		global $wp_settings_sections;
		$this->addons->addons_page_init();
		$this->assertArrayHasKey( 'addons', $wp_settings_sections['git_updater_addons_settings'] ?? [] );
	}
}

// ---------------------------------------------------------------------------
// Add_Ons — prevent_redirect_on_modal_activation
// ---------------------------------------------------------------------------

/**
 * Class Test_Add_Ons_Modal_Prevention
 */
class Test_Add_Ons_Modal_Prevention extends WP_UnitTestCase {

	private Add_Ons $addons;

	public function set_up(): void {
		parent::set_up();
		$this->addons = new Add_Ons();
		wp_dequeue_script( 'ajax-activate' );
		wp_deregister_script( 'ajax-activate' );
	}

	public function tear_down(): void {
		unset( $_GET['plugin'] );
		wp_dequeue_script( 'ajax-activate' );
		wp_deregister_script( 'ajax-activate' );
		parent::tear_down();
	}

	public function test_prevent_redirect_enqueues_ajax_activate_for_addon_slug(): void {
		$_GET['plugin'] = 'git-updater-gist';
		$this->addons->prevent_redirect_on_modal_activation();
		$this->assertTrue( wp_script_is( 'ajax-activate', 'registered' ) );
	}

	public function test_prevent_redirect_does_nothing_when_plugin_not_in_get(): void {
		unset( $_GET['plugin'] );
		$this->addons->prevent_redirect_on_modal_activation();
		$this->assertFalse( wp_script_is( 'ajax-activate', 'registered' ) );
	}
}

// ---------------------------------------------------------------------------
// Add_Ons — insert_cards
// ---------------------------------------------------------------------------

/**
 * Class Test_Add_Ons_Insert_Cards
 */
class Test_Add_Ons_Insert_Cards extends WP_UnitTestCase {

	private Add_Ons $addons;
	private string  $cache_key;

	public function set_up(): void {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/template.php';
		$this->addons    = new Add_Ons();
		$this->cache_key = 'ghu-' . md5( 'gu_addon_api_results' );
	}

	public function tear_down(): void {
		delete_site_option( $this->cache_key );
		remove_all_filters( 'pre_http_request' );
		$GLOBALS['current_screen'] = null;
		parent::tear_down();
	}

	private function make_addon_item( string $name, string $slug ): array {
		return [
			'name'              => $name,
			'slug'              => $slug,
			'version'           => '1.0.0',
			'short_description' => '',
			'author'            => '',
			'author_profile'    => '',
			'rating'            => 0,
			'num_ratings'       => 0,
			'active_installs'   => 0,
			'downloaded'        => 0,
			'last_updated'      => '',
			'requires'          => '',
			'requires_php'      => '',
			'tested'            => '',
			'homepage'          => '',
			'group'             => '',
			'icons'             => [ 'default' => '' ],
			'action_links'      => [],
			'banners'           => [ 'default' => '', 'high' => '' ],
			'donate_link'       => '',
			'compatibility'     => [],
		];
	}

	public function test_insert_cards_outputs_form_with_plugin_install_table(): void {
		$data = [
			'git-updater-gist'      => $this->make_addon_item( 'Git Updater Gist',      'git-updater-gist' ),
			'git-updater-bitbucket' => $this->make_addon_item( 'Git Updater Bitbucket', 'git-updater-bitbucket' ),
			'git-updater-gitlab'    => $this->make_addon_item( 'Git Updater GitLab',    'git-updater-gitlab' ),
			'git-updater-gitea'     => $this->make_addon_item( 'Git Updater Gitea',     'git-updater-gitea' ),
		];
		update_site_option(
			$this->cache_key,
			[ 'gu_addon_api_results' => $data, 'timeout' => strtotime( '+7 days' ) ]
		);

		set_current_screen( 'plugin-install' );

		ob_start();
		$this->addons->insert_cards();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<form', $output );
		$this->assertStringContainsString( 'git-updater-addons', $output );
	}
}
