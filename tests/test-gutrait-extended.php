<?php
/**
 * Extended tests for GU_Trait methods not covered by test-gutrait.php.
 *
 * Covers:
 * - is_wp_cli()              — constant check (tests the false path in phpunit context)
 * - is_current_page()        — global $pagenow array match
 * - should_run_on_current_page() — composite page list logic
 * - get_cache_key()          — 'ghu-' prefixed MD5 key generation
 * - is_cache_timeout_valid() — timestamp future/past comparison
 * - can_update_repo()        — version comparison with WP/PHP compat guards
 * - parse_header_uri()       — parse_url / pathinfo-based URL decomposition
 * - get_did_hash()           — SHA-256 truncated to 6 hex chars
 * - get_file_without_did_hash() — strip DID hash suffix from plugin basename
 * - use_release_asset()      — release-asset eligibility matrix
 * - get_headers()            — default plugin/theme header array construction
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\Base;

/**
 * Class Test_GUTrait_Extended
 *
 * Uses GU_Trait directly so protected helpers are callable from the test body.
 */
class Test_GUTrait_Extended extends WP_UnitTestCase {

	use Fragen\Git_Updater\Traits\GU_Trait;

	/**
	 * Required by get_headers() — Base::$extra_headers accessed statically.
	 *
	 * @var array<string, string>
	 */
	public static $extra_headers = [];

	public function set_up(): void {
		parent::set_up();
		$this->type = $this->make_type();
	}

	private function make_type(): stdClass {
		$type                 = new stdClass();
		$type->slug           = 'test-plugin';
		$type->git            = 'github';
		$type->type           = 'plugin';
		$type->owner          = 'test-owner';
		$type->branch         = 'master';
		$type->primary_branch = 'master';
		$type->enterprise     = false;
		$type->enterprise_api = null;
		$type->gist_id        = null;
		return $type;
	}

	// -------------------------------------------------------------------------
	// is_wp_cli()
	// -------------------------------------------------------------------------

	/**
	 * PHPUnit runs phpunit directly (not via wp-cli), so WP_CLI is not defined
	 * and is_wp_cli() must return false.
	 */
	public function test_is_wp_cli_returns_bool(): void {
		$result = self::is_wp_cli();
		$this->assertIsBool( $result );
	}

	public function test_is_wp_cli_false_when_not_running_under_wpcli(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->markTestSkipped( 'Running under WP-CLI — true branch already covered.' );
		}
		$this->assertFalse( self::is_wp_cli() );
	}

	// -------------------------------------------------------------------------
	// is_current_page()
	// -------------------------------------------------------------------------

	public function test_is_current_page_returns_true_when_pagenow_in_array(): void {
		global $pagenow;
		$pagenow = 'plugins.php';
		$this->assertTrue( $this->is_current_page( [ 'plugins.php', 'themes.php' ] ) );
	}

	public function test_is_current_page_returns_false_when_pagenow_not_in_array(): void {
		global $pagenow;
		$pagenow = 'index.php';
		$this->assertFalse( $this->is_current_page( [ 'plugins.php', 'themes.php' ] ) );
	}

	public function test_is_current_page_returns_false_for_empty_array(): void {
		global $pagenow;
		$pagenow = 'plugins.php';
		$this->assertFalse( $this->is_current_page( [] ) );
	}

	public function test_is_current_page_uses_strict_comparison(): void {
		global $pagenow;
		$pagenow = 'plugins';
		$this->assertFalse( $this->is_current_page( [ 'plugins.php' ] ) );
	}

	// -------------------------------------------------------------------------
	// should_run_on_current_page()
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider data_should_run_on_current_page
	 */
	public function test_should_run_on_current_page( string $page, bool $expected ): void {
		global $pagenow;
		$pagenow = $page;
		$this->assertSame( $expected, self::should_run_on_current_page() );
	}

	public function data_should_run_on_current_page(): array {
		return [
			'update-core page'   => [ 'update-core.php', true ],
			'update page'        => [ 'update.php', true ],
			'plugins page'       => [ 'plugins.php', true ],
			'themes page'        => [ 'themes.php', true ],
			'plugin-install page' => [ 'plugin-install.php', true ],
			'theme-install page' => [ 'theme-install.php', true ],
			'admin-ajax page'    => [ 'admin-ajax.php', true ],
			'index page'         => [ 'index.php', true ],
			'wp-cron page'       => [ 'wp-cron.php', true ],
			'settings page (single-site only)' => [ 'options.php', ! is_multisite() ],
			'random page'        => [ 'edit-comments.php', false ],
			'dashboard'          => [ 'dashboard.php', false ],
		];
	}

	// -------------------------------------------------------------------------
	// get_cache_key()
	// -------------------------------------------------------------------------

	public function test_get_cache_key_uses_type_slug_by_default(): void {
		$key = $this->get_cache_key();
		$this->assertSame( 'ghu-' . md5( 'test-plugin' ), $key );
	}

	public function test_get_cache_key_uses_provided_repo_name(): void {
		$key = $this->get_cache_key( 'my-other-plugin' );
		$this->assertSame( 'ghu-' . md5( 'my-other-plugin' ), $key );
	}

	public function test_get_cache_key_starts_with_ghu_prefix(): void {
		$key = $this->get_cache_key( 'anything' );
		$this->assertStringStartsWith( 'ghu-', $key );
	}

	public function test_get_cache_key_is_deterministic(): void {
		$this->assertSame( $this->get_cache_key( 'slug' ), $this->get_cache_key( 'slug' ) );
	}

	public function test_get_cache_key_differs_for_different_slugs(): void {
		$this->assertNotSame( $this->get_cache_key( 'slug-a' ), $this->get_cache_key( 'slug-b' ) );
	}

	// -------------------------------------------------------------------------
	// is_cache_timeout_valid()
	// -------------------------------------------------------------------------

	public function test_is_cache_timeout_valid_future_timestamp_is_valid(): void {
		$this->assertTrue( $this->is_cache_timeout_valid( strtotime( '+1 hour' ) ) );
	}

	public function test_is_cache_timeout_valid_past_timestamp_is_invalid(): void {
		$this->assertFalse( $this->is_cache_timeout_valid( strtotime( '-1 hour' ) ) );
	}

	public function test_is_cache_timeout_valid_zero_is_invalid(): void {
		$this->assertFalse( $this->is_cache_timeout_valid( 0 ) );
	}

	public function test_is_cache_timeout_valid_far_future_is_valid(): void {
		$this->assertTrue( $this->is_cache_timeout_valid( strtotime( '+7 days' ) ) );
	}

	// -------------------------------------------------------------------------
	// can_update_repo()
	// -------------------------------------------------------------------------

	public function test_can_update_repo_newer_remote_returns_true(): void {
		$type                 = clone $this->type;
		$type->remote_version = '2.0.0';
		$type->local_version  = '1.0.0';
		$this->assertTrue( $this->can_update_repo( $type ) );
	}

	public function test_can_update_repo_same_version_returns_false(): void {
		$type                 = clone $this->type;
		$type->remote_version = '1.0.0';
		$type->local_version  = '1.0.0';
		$this->assertFalse( $this->can_update_repo( $type ) );
	}

	public function test_can_update_repo_older_remote_returns_false(): void {
		$type                 = clone $this->type;
		$type->remote_version = '0.9.0';
		$type->local_version  = '1.0.0';
		$this->assertFalse( $this->can_update_repo( $type ) );
	}

	public function test_can_update_repo_missing_version_fields_returns_false(): void {
		$type = clone $this->type;
		$this->assertFalse( $this->can_update_repo( $type ) );
	}

	public function test_can_update_repo_incompatible_wp_version_returns_false(): void {
		$type                 = clone $this->type;
		$type->remote_version = '2.0.0';
		$type->local_version  = '1.0.0';
		$type->requires       = '99.0';
		$this->assertFalse( $this->can_update_repo( $type ) );
	}

	public function test_can_update_repo_incompatible_php_version_returns_false(): void {
		$type                 = clone $this->type;
		$type->remote_version = '2.0.0';
		$type->local_version  = '1.0.0';
		$type->requires_php   = '99.0';
		$this->assertFalse( $this->can_update_repo( $type ) );
	}

	public function test_can_update_repo_empty_requires_fields_does_not_block(): void {
		$type                 = clone $this->type;
		$type->remote_version = '2.0.0';
		$type->local_version  = '1.0.0';
		$type->requires       = '';
		$type->requires_php   = '';
		$this->assertTrue( $this->can_update_repo( $type ) );
	}

	// -------------------------------------------------------------------------
	// parse_header_uri()   (protected — accessible because the trait is used here)
	// -------------------------------------------------------------------------

	public function test_parse_header_uri_github_https_url(): void {
		$result = $this->parse_header_uri( 'https://github.com/afragen/git-updater' );

		$this->assertSame( 'https', $result['scheme'] );
		$this->assertSame( 'github.com', $result['host'] );
		$this->assertSame( 'afragen', $result['owner'] );
		$this->assertSame( 'git-updater', $result['repo'] );
		$this->assertSame( 'afragen/git-updater', $result['owner_repo'] );
		$this->assertSame( 'https://github.com', $result['base_uri'] );
	}

	public function test_parse_header_uri_strips_git_extension(): void {
		$result = $this->parse_header_uri( 'https://github.com/owner/my-repo.git' );
		$this->assertSame( 'my-repo', $result['repo'] );
	}

	public function test_parse_header_uri_preserves_original_url(): void {
		$url    = 'https://github.com/owner/repo';
		$result = $this->parse_header_uri( $url );
		$this->assertSame( $url, $result['original'] );
	}

	public function test_parse_header_uri_owner_repo_combines_owner_and_repo(): void {
		$result = $this->parse_header_uri( 'https://github.com/myorg/my-plugin' );
		$this->assertSame( 'myorg/my-plugin', $result['owner_repo'] );
	}

	// -------------------------------------------------------------------------
	// get_did_hash()
	// -------------------------------------------------------------------------

	public function test_get_did_hash_returns_six_hex_characters(): void {
		$hash = $this->get_did_hash( 'did:example:123456' );
		$this->assertSame( 6, strlen( $hash ) );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{6}$/', $hash );
	}

	public function test_get_did_hash_is_deterministic(): void {
		$this->assertSame(
			$this->get_did_hash( 'did:example:abc' ),
			$this->get_did_hash( 'did:example:abc' )
		);
	}

	public function test_get_did_hash_different_dids_produce_different_hashes(): void {
		$this->assertNotSame(
			$this->get_did_hash( 'did:example:aaa' ),
			$this->get_did_hash( 'did:example:bbb' )
		);
	}

	// -------------------------------------------------------------------------
	// get_file_without_did_hash()
	// -------------------------------------------------------------------------

	public function test_get_file_without_did_hash_removes_hash_suffix_from_slug(): void {
		$did    = 'did:example:123';
		$hash   = $this->get_did_hash( $did );
		$plugin = "my-plugin-{$hash}/my-plugin.php";

		$result = $this->get_file_without_did_hash( $did, $plugin );
		$this->assertSame( 'my-plugin/my-plugin.php', $result );
	}

	public function test_get_file_without_did_hash_preserves_filename(): void {
		$did    = 'did:example:xyz';
		$hash   = $this->get_did_hash( $did );
		$plugin = "some-plugin-{$hash}/some-plugin.php";

		$result = $this->get_file_without_did_hash( $did, $plugin );
		$this->assertStringEndsWith( 'some-plugin.php', $result );
	}

	// -------------------------------------------------------------------------
	// use_release_asset()
	// -------------------------------------------------------------------------

	public function test_use_release_asset_returns_false_without_release_asset_property(): void {
		$this->type->newest_tag = '1.0.0';
		$this->assertFalse( $this->use_release_asset() );
	}

	public function test_use_release_asset_returns_false_when_release_asset_is_false(): void {
		$this->type->release_asset = false;
		$this->type->newest_tag    = '1.0.0';
		$this->assertFalse( $this->use_release_asset() );
	}

	public function test_use_release_asset_returns_false_when_newest_tag_is_zero(): void {
		$this->type->release_asset = true;
		$this->type->newest_tag    = '0.0.0';
		$this->assertFalse( $this->use_release_asset() );
	}

	public function test_use_release_asset_returns_true_on_primary_branch_without_switch(): void {
		$this->type->release_asset = true;
		$this->type->newest_tag    = '1.0.0';
		// branch == primary_branch and branch_switch === false
		$this->assertTrue( $this->use_release_asset( false ) );
	}

	public function test_use_release_asset_returns_true_when_switching_to_primary_branch(): void {
		$this->type->release_asset = true;
		$this->type->newest_tag    = '1.0.0';
		$this->type->branches      = [ 'master' => [], 'develop' => [] ];
		// branch_switch == primary_branch
		$this->assertTrue( $this->use_release_asset( 'master' ) );
	}

	public function test_use_release_asset_returns_true_when_switching_to_tag(): void {
		$this->type->release_asset = true;
		$this->type->newest_tag    = '1.0.0';
		$this->type->branches      = [ 'master' => [], 'develop' => [] ];
		// '1.0.0' tag is not in branches array → is_tag = true
		$this->assertTrue( $this->use_release_asset( '1.0.0' ) );
	}

	public function test_use_release_asset_returns_false_when_switching_to_non_primary_branch(): void {
		$this->type->release_asset = true;
		$this->type->newest_tag    = '1.0.0';
		$this->type->branches      = [ 'master' => [], 'develop' => [] ];
		// 'develop' is in branches and is not primary_branch
		$this->assertFalse( $this->use_release_asset( 'develop' ) );
	}

	// -------------------------------------------------------------------------
	// get_headers()
	// -------------------------------------------------------------------------

	public function test_get_headers_plugin_contains_required_keys(): void {
		$headers = $this->get_headers( 'plugin' );
		foreach ( [ 'Name', 'Version', 'Author', 'Description' ] as $key ) {
			$this->assertArrayHasKey( $key, $headers, "Plugin headers must contain '{$key}'." );
		}
	}

	public function test_get_headers_theme_contains_required_keys(): void {
		$headers = $this->get_headers( 'theme' );
		foreach ( [ 'Name', 'Version', 'Author', 'Description' ] as $key ) {
			$this->assertArrayHasKey( $key, $headers, "Theme headers must contain '{$key}'." );
		}
	}

	public function test_get_headers_plugin_name_value_is_plugin_name(): void {
		$headers = $this->get_headers( 'plugin' );
		$this->assertSame( 'Plugin Name', $headers['Name'] );
	}

	public function test_get_headers_theme_name_value_is_theme_name(): void {
		$headers = $this->get_headers( 'theme' );
		$this->assertSame( 'Theme Name', $headers['Name'] );
	}

	public function test_get_headers_merges_extra_headers(): void {
		Base::$extra_headers['CustomHeader'] = 'Custom Header';
		$headers = $this->get_headers( 'plugin' );
		$this->assertArrayHasKey( 'CustomHeader', $headers );
		// Clean up to avoid cross-test pollution.
		unset( Base::$extra_headers['CustomHeader'] );
	}
}
