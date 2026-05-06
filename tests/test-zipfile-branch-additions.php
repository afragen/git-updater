<?php
/**
 * Tests for Zipfile_API, Branch, and Additions.
 *
 * Zipfile_API:
 * - set_git_servers()        — merges 'zipfile' entry into the git-servers array
 * - set_installed_apis()     — merges 'zipfile_api' entry into the installed-APIs array
 * - remote_install()         — populates download_link and git_updater_install_repo
 * - set_remote_install_data() — passthrough unless git_updater_api === 'zipfile'
 *
 * Branch:
 * - get_current_branch()     — cache hit returns cached branch; miss falls back to $repo->branch
 *
 * Additions:
 * - register()               — returns false for empty config, true for non-empty
 * - add_source()             — stamps missing source fields and writes the site option
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\API\Zipfile_API;
use Fragen\Git_Updater\Base;
use Fragen\Git_Updater\Branch;
use Fragen\Git_Updater\Additions\Additions;

// ---------------------------------------------------------------------------
// Zipfile_API
// ---------------------------------------------------------------------------

/**
 * Class Test_Zipfile_API
 */
class Test_Zipfile_API extends WP_UnitTestCase {

	private Zipfile_API $api;

	public function set_up(): void {
		parent::set_up();
		$this->api = new Zipfile_API();
	}

	public function tear_down(): void {
		remove_all_filters( 'gu_git_servers' );
		remove_all_filters( 'gu_installed_apis' );
		remove_all_filters( 'gu_install_remote_install' );
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// load_hooks()
	// -------------------------------------------------------------------------

	public function test_load_hooks_registers_git_servers_filter(): void {
		$this->api->load_hooks();
		$this->assertNotFalse( has_filter( 'gu_git_servers', [ $this->api, 'set_git_servers' ] ) );
	}

	public function test_load_hooks_registers_installed_apis_filter(): void {
		$this->api->load_hooks();
		$this->assertNotFalse( has_filter( 'gu_installed_apis', [ $this->api, 'set_installed_apis' ] ) );
	}

	public function test_load_hooks_registers_remote_install_filter(): void {
		$this->api->load_hooks();
		$this->assertNotFalse( has_filter( 'gu_install_remote_install', [ $this->api, 'set_remote_install_data' ] ) );
	}

	public function test_load_hooks_filters_are_applied_when_filters_run(): void {
		$this->api->load_hooks();

		$servers = apply_filters( 'gu_git_servers', [] );
		$this->assertArrayHasKey( 'zipfile', $servers );

		$apis = apply_filters( 'gu_installed_apis', [] );
		$this->assertArrayHasKey( 'zipfile_api', $apis );
	}

	// -------------------------------------------------------------------------
	// zipfile_slug()
	// -------------------------------------------------------------------------

	public function test_zipfile_slug_outputs_input_element_with_correct_id(): void {
		ob_start();
		$this->api->zipfile_slug();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'id="zipfile_slug"', $output );
	}

	public function test_zipfile_slug_outputs_input_element_with_correct_name(): void {
		ob_start();
		$this->api->zipfile_slug();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'name="zipfile_slug"', $output );
	}

	public function test_zipfile_slug_outputs_placeholder(): void {
		ob_start();
		$this->api->zipfile_slug();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'my-repo-slug', $output );
	}

	// -------------------------------------------------------------------------
	// set_git_servers()
	// -------------------------------------------------------------------------

	public function test_set_git_servers_adds_zipfile_entry(): void {
		$result = $this->api->set_git_servers( [] );
		$this->assertArrayHasKey( 'zipfile', $result );
		$this->assertSame( 'Zipfile', $result['zipfile'] );
	}

	public function test_set_git_servers_preserves_existing_servers(): void {
		$result = $this->api->set_git_servers( [ 'github' => 'GitHub' ] );
		$this->assertArrayHasKey( 'github', $result );
		$this->assertArrayHasKey( 'zipfile', $result );
	}

	public function test_set_git_servers_works_on_empty_input(): void {
		$result = $this->api->set_git_servers( [] );
		$this->assertSame( [ 'zipfile' => 'Zipfile' ], $result );
	}

	// -------------------------------------------------------------------------
	// set_installed_apis()
	// -------------------------------------------------------------------------

	public function test_set_installed_apis_adds_zipfile_api_entry(): void {
		$result = $this->api->set_installed_apis( [] );
		$this->assertArrayHasKey( 'zipfile_api', $result );
		$this->assertTrue( $result['zipfile_api'] );
	}

	public function test_set_installed_apis_preserves_existing_entries(): void {
		$result = $this->api->set_installed_apis( [ 'github_api' => true ] );
		$this->assertArrayHasKey( 'github_api', $result );
		$this->assertArrayHasKey( 'zipfile_api', $result );
	}

	// -------------------------------------------------------------------------
	// remote_install()
	// -------------------------------------------------------------------------

	public function test_remote_install_sets_download_link_from_uri(): void {
		$headers = [ 'uri' => 'https://example.com/my-plugin.zip', 'original' => 'https://fallback.com/file.zip' ];
		$install = [ 'zipfile_slug' => 'my-plugin' ];

		$result = $this->api->remote_install( $headers, $install );

		$this->assertSame( 'https://example.com/my-plugin.zip', $result['download_link'] );
	}

	public function test_remote_install_falls_back_to_original_when_uri_empty(): void {
		$headers = [ 'uri' => '', 'original' => 'https://fallback.com/file.zip' ];
		$install = [ 'zipfile_slug' => 'my-plugin' ];

		$result = $this->api->remote_install( $headers, $install );

		$this->assertSame( 'https://fallback.com/file.zip', $result['download_link'] );
	}

	public function test_remote_install_sets_git_updater_install_repo_from_zipfile_slug(): void {
		$headers = [ 'uri' => 'https://example.com/my-plugin.zip', 'original' => '' ];
		$install = [ 'zipfile_slug' => 'my-plugin' ];

		$result = $this->api->remote_install( $headers, $install );

		$this->assertSame( 'my-plugin', $result['git_updater_install_repo'] );
	}

	// -------------------------------------------------------------------------
	// set_remote_install_data()
	// -------------------------------------------------------------------------

	public function test_set_remote_install_data_returns_install_unchanged_for_non_zipfile_api(): void {
		$install = [ 'git_updater_api' => 'github', 'download_link' => 'https://github.com/file.zip' ];
		$headers = [];

		$result = $this->api->set_remote_install_data( $install, $headers );

		$this->assertSame( $install, $result );
	}

	public function test_set_remote_install_data_delegates_to_remote_install_for_zipfile(): void {
		$install = [
			'git_updater_api' => 'zipfile',
			'zipfile_slug'    => 'my-plugin',
		];
		$headers = [ 'uri' => 'https://example.com/my-plugin.zip', 'original' => '' ];

		$result = $this->api->set_remote_install_data( $install, $headers );

		$this->assertSame( 'https://example.com/my-plugin.zip', $result['download_link'] );
		$this->assertSame( 'my-plugin', $result['git_updater_install_repo'] );
	}
}

// ---------------------------------------------------------------------------
// Branch
// ---------------------------------------------------------------------------

/**
 * Class Test_Branch
 */
class Test_Branch extends WP_UnitTestCase {

	private Branch $branch;
	private string $slug   = 'test-plugin';
	private string $cache_key;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->branch    = new Branch();
		$this->cache_key = 'ghu-' . md5( $this->slug );
	}

	public function tear_down(): void {
		delete_site_option( $this->cache_key );
		parent::tear_down();
	}

	private function make_repo( string $branch = 'master' ): stdClass {
		$repo         = new stdClass();
		$repo->slug   = $this->slug;
		$repo->branch = $branch;
		return $repo;
	}

	// -------------------------------------------------------------------------
	// get_current_branch()
	// -------------------------------------------------------------------------

	public function test_get_current_branch_returns_repo_branch_when_cache_empty(): void {
		$result = $this->branch->get_current_branch( $this->make_repo( 'master' ) );
		$this->assertSame( 'master', $result );
	}

	public function test_get_current_branch_returns_cached_branch_when_set(): void {
		update_site_option( $this->cache_key, [ 'current_branch' => 'develop' ] );

		$result = $this->branch->get_current_branch( $this->make_repo( 'master' ) );

		$this->assertSame( 'develop', $result );
	}

	public function test_get_current_branch_falls_back_to_repo_branch_when_cache_has_no_current_branch(): void {
		update_site_option( $this->cache_key, [ 'some_other_key' => 'value' ] );

		$result = $this->branch->get_current_branch( $this->make_repo( 'feature' ) );

		$this->assertSame( 'feature', $result );
	}

	public function test_get_current_branch_falls_back_when_cached_current_branch_is_empty_string(): void {
		update_site_option( $this->cache_key, [ 'current_branch' => '' ] );

		$result = $this->branch->get_current_branch( $this->make_repo( 'main' ) );

		$this->assertSame( 'main', $result );
	}
}

// ---------------------------------------------------------------------------
// Additions
// ---------------------------------------------------------------------------

/**
 * Class Test_Additions
 */
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

	// -------------------------------------------------------------------------
	// register()
	// -------------------------------------------------------------------------

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

	// -------------------------------------------------------------------------
	// add_source()
	// -------------------------------------------------------------------------

	public function test_add_source_stamps_missing_source_with_home_url_hash(): void {
		$config = [ [ 'slug' => 'my-plugin' ] ];

		$this->additions->add_source( $config );

		$saved = get_site_option( 'git_updater_additions' );
		$this->assertSame( md5( home_url() ), $saved[0]['source'] );
	}

	public function test_add_source_does_not_overwrite_existing_source(): void {
		$config = [ [ 'slug' => 'my-plugin', 'source' => 'already-set' ] ];

		$this->additions->add_source( $config );

		// No change → option not written.
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
