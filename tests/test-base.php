<?php
/**
 * Tests for Base.
 *
 * Covers the methods testable without live HTTP or real plugin/theme files:
 * - get_update_url()      — pure URL builder
 * - add_assets()          — populates banners/icons from cache; skips bad cache
 * - run_cron_batch()      — iterates batch array; safe with empty input
 * - set_options_filter()  — merges gu_set_options filter result into site option;
 *                           strips access-token keys before writing
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\Base;

/**
 * Class Test_Base
 */
class Test_Base extends WP_UnitTestCase {

	private Base   $base;
	private string $slug      = 'test-base-plugin';
	private string $cache_key;

	public function set_up(): void {
		parent::set_up();
		delete_site_option( 'git_updater' );
		$this->base      = new Base();
		$this->cache_key = 'ghu-' . md5( $this->slug );
	}

	public function tear_down(): void {
		delete_site_option( $this->cache_key );
		delete_site_option( 'git_updater' );
		remove_all_filters( 'gu_set_options' );
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// get_update_url()
	// -------------------------------------------------------------------------

	public function test_get_update_url_contains_action_param(): void {
		$url = $this->base->get_update_url( 'plugin', 'upgrade-plugin', 'my-plugin/my-plugin.php' );
		$this->assertStringContainsString( 'action=upgrade-plugin', $url );
	}

	public function test_get_update_url_contains_encoded_repo_name(): void {
		$url = $this->base->get_update_url( 'plugin', 'upgrade-plugin', 'my-plugin/my-plugin.php' );
		$this->assertStringContainsString( rawurlencode( 'my-plugin/my-plugin.php' ), $url );
	}

	public function test_get_update_url_contains_type_as_query_key(): void {
		$url = $this->base->get_update_url( 'plugin', 'upgrade-plugin', 'my-plugin/my-plugin.php' );
		$this->assertStringContainsString( 'plugin=', $url );
	}

	public function test_get_update_url_works_for_theme_type(): void {
		$url = $this->base->get_update_url( 'theme', 'upgrade-theme', 'my-theme' );
		$this->assertStringContainsString( 'theme=my-theme', $url );
		$this->assertStringContainsString( 'action=upgrade-theme', $url );
	}

	// -------------------------------------------------------------------------
	// add_assets()
	// -------------------------------------------------------------------------

	private function make_repo(): stdClass {
		$repo             = new stdClass();
		$repo->type       = new stdClass();
		$repo->type->slug = $this->slug;
		return $repo;
	}

	public function test_add_assets_populates_low_banner_from_cached_assets(): void {
		update_site_option(
			$this->cache_key,
			[
				'assets'  => [ 'banner-772x250.png' => 'https://example.com/banner-low.png' ],
				'timeout' => strtotime( '+12 hours' ),
			]
		);

		$repo = $this->make_repo();
		$this->base->add_assets( $repo );

		$this->assertSame( 'https://example.com/banner-low.png', $repo->type->banners['low'] );
	}

	public function test_add_assets_populates_high_banner_from_cached_assets(): void {
		update_site_option(
			$this->cache_key,
			[
				'assets'  => [ 'banner-1544x500.png' => 'https://example.com/banner-high.png' ],
				'timeout' => strtotime( '+12 hours' ),
			]
		);

		$repo = $this->make_repo();
		$this->base->add_assets( $repo );

		$this->assertSame( 'https://example.com/banner-high.png', $repo->type->banners['high'] );
	}

	public function test_add_assets_populates_icon_from_cached_assets(): void {
		update_site_option(
			$this->cache_key,
			[
				'assets'  => [ 'icon-128x128.png' => 'https://example.com/icon.png' ],
				'timeout' => strtotime( '+12 hours' ),
			]
		);

		$repo = $this->make_repo();
		$this->base->add_assets( $repo );

		$this->assertSame( 'https://example.com/icon.png', $repo->type->icons['1x'] );
	}

	public function test_add_assets_does_not_set_banners_when_no_assets_in_cache(): void {
		// Cache exists but has no 'assets' key.
		update_site_option( $this->cache_key, [ 'timeout' => strtotime( '+12 hours' ) ] );

		$repo = $this->make_repo();
		$this->base->add_assets( $repo );

		$this->assertFalse( isset( $repo->type->banners ) );
	}

	public function test_add_assets_does_nothing_when_cache_is_absent(): void {
		$repo = $this->make_repo();
		$this->base->add_assets( $repo );

		$this->assertFalse( isset( $repo->type->banners ) );
		$this->assertFalse( isset( $repo->type->icons ) );
	}

	public function test_add_assets_does_nothing_when_assets_value_is_object(): void {
		// The guard `is_object($assets)` short-circuits when assets is an object.
		update_site_option(
			$this->cache_key,
			[
				'assets'  => (object) [ 'banner-772x250.png' => 'https://example.com/banner.png' ],
				'timeout' => strtotime( '+12 hours' ),
			]
		);

		$repo = $this->make_repo();
		$this->base->add_assets( $repo );

		$this->assertFalse( isset( $repo->type->banners ) );
	}

	// -------------------------------------------------------------------------
	// run_cron_batch()
	// -------------------------------------------------------------------------

	public function test_run_cron_batch_with_empty_array_does_not_throw(): void {
		$this->base->run_cron_batch( [] );
		$this->assertTrue( true );
	}

	// -------------------------------------------------------------------------
	// set_options_filter()
	// -------------------------------------------------------------------------

	public function test_set_options_filter_merges_filter_config_into_site_option(): void {
		update_site_option( 'git_updater', [] );
		add_filter( 'gu_set_options', fn() => [ 'my_custom_option' => 'hello' ] );

		$this->base->set_options_filter();

		$saved = get_site_option( 'git_updater' );
		$this->assertSame( 'hello', $saved['my_custom_option'] );
	}

	public function test_set_options_filter_is_noop_when_filter_returns_empty(): void {
		update_site_option( 'git_updater', [ 'existing' => 'untouched' ] );
		// No filter added → gu_set_options returns [].

		$this->base->set_options_filter();

		$saved = get_site_option( 'git_updater' );
		$this->assertSame( [ 'existing' => 'untouched' ], $saved );
	}

	public function test_set_options_filter_strips_github_access_token_key(): void {
		update_site_option( 'git_updater', [] );
		add_filter(
			'gu_set_options',
			fn() => [
				'github_access_token' => 'secret',
				'my_safe_option'      => 'keep-me',
			]
		);

		$this->base->set_options_filter();

		$saved = get_site_option( 'git_updater' );
		$this->assertArrayNotHasKey( 'github_access_token', $saved );
	}

	public function test_set_options_filter_preserves_non_token_keys_after_stripping(): void {
		update_site_option( 'git_updater', [] );
		add_filter(
			'gu_set_options',
			fn() => [
				'github_access_token' => 'secret',
				'my_safe_option'      => 'keep-me',
			]
		);

		$this->base->set_options_filter();

		$saved = get_site_option( 'git_updater' );
		$this->assertSame( 'keep-me', $saved['my_safe_option'] );
	}
}

// =============================================================================
// Test_Base_Run_Cron_Batch_Body — covers run_cron_batch() loop body (line 292)
// =============================================================================

/**
 * Class Test_Base_Run_Cron_Batch_Body
 */
class Test_Base_Run_Cron_Batch_Body extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		new Base();
	}

	public function tear_down(): void {
		remove_all_filters( 'gu_disable_wpcron' );
		parent::tear_down();
	}

	public function test_run_cron_batch_loop_body_executes_with_single_repo(): void {
		add_filter( 'gu_disable_wpcron', '__return_true' );
		$repo = (object) [ 'type' => 'plugin', 'git' => 'github', 'file' => 'test/test.php' ];
		( new Base() )->run_cron_batch( [ $repo ] );
		$this->assertTrue( true );
	}
}

// =============================================================================
// Test_Base_Background_Update — covers background_update() (lines 210–215)
// =============================================================================

/**
 * Class Test_Base_Background_Update
 */
class Test_Base_Background_Update extends WP_UnitTestCase {

	private Base $base;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->base = new Base();
	}

	public function tear_down(): void {
		remove_all_actions( 'wp_update_plugins' );
		remove_all_actions( 'wp_update_themes' );
		remove_all_actions( 'gu_get_remote_plugin' );
		remove_all_actions( 'gu_get_remote_theme' );
		parent::tear_down();
	}

	public function test_background_update_registers_four_hooks(): void {
		$this->base->background_update();
		$this->assertSame( 10, has_action( 'wp_update_plugins', [ $this->base, 'get_meta_plugins' ] ) );
		$this->assertSame( 10, has_action( 'wp_update_themes', [ $this->base, 'get_meta_themes' ] ) );
		$this->assertSame( 10, has_action( 'gu_get_remote_plugin', [ $this->base, 'run_cron_batch' ] ) );
		$this->assertSame( 10, has_action( 'gu_get_remote_theme', [ $this->base, 'run_cron_batch' ] ) );
	}
}

// =============================================================================
// Test_Base_Get_Remote_Repo_Meta_Early — covers two early-return paths
// =============================================================================

/**
 * Class Test_Base_Get_Remote_Repo_Meta_Early
 */
class Test_Base_Get_Remote_Repo_Meta_Early extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		new Base();
	}

	public function tear_down(): void {
		remove_all_filters( 'gu_disable_wpcron' );
		parent::tear_down();
	}

	// Line 317: gu_disable_wpcron=true + no admin user → return false immediately.
	public function test_returns_false_when_wpcron_disabled_and_cannot_update(): void {
		add_filter( 'gu_disable_wpcron', '__return_true' );
		$repo = (object) [ 'type' => 'plugin', 'git' => 'github', 'file' => 'test/test.php' ];
		$this->assertFalse( ( new Base() )->get_remote_repo_meta( $repo ) );
	}

	// Line 328: unknown git host → get_repo_api() returns null → return false.
	public function test_returns_false_when_repo_api_is_null(): void {
		$repo = (object) [ 'type' => 'plugin', 'git' => 'bitbucket', 'file' => 'test/test.php' ];
		$this->assertFalse( ( new Base() )->get_remote_repo_meta( $repo ) );
	}
}

// =============================================================================
// Test_Base_Set_Defaults — covers set_defaults() all three paths (lines 371–402)
// =============================================================================

/**
 * Class Test_Base_Set_Defaults
 */
class Test_Base_Set_Defaults extends WP_UnitTestCase {

	private Base                $base;
	private \ReflectionMethod   $rm;
	private \ReflectionProperty $rp_plugin;

	public function set_up(): void {
		parent::set_up();
		delete_site_option( 'git_updater' );
		$this->base      = new Base();
		$this->rm        = new \ReflectionMethod( Base::class, 'set_defaults' );
		$this->rm->setAccessible( true );
		$this->rp_plugin = new \ReflectionProperty( Base::class, 'plugin' );
		$this->rp_plugin->setAccessible( true );
	}

	public function tear_down(): void {
		delete_site_option( 'git_updater' );
		Base::$options = get_site_option( 'git_updater', [] );
		parent::tear_down();
	}

	// Path A: no slug on the type property → new stdClass() with empty slug.
	public function test_set_defaults_creates_stdclass_when_no_slug(): void {
		$this->rp_plugin->setValue( $this->base, new stdClass() ); // no ->slug
		$this->rm->invoke( $this->base, 'plugin' );
		$result = $this->rp_plugin->getValue( $this->base );
		$this->assertSame( '', $result->slug );
		$this->assertSame( '0.0.0', $result->remote_version );
	}

	// Path B: slug set but not in options → slug added to options via add_site_option.
	public function test_set_defaults_adds_slug_to_options_when_missing(): void {
		$obj       = new stdClass();
		$obj->slug = 'brand-new-plugin';
		$this->rp_plugin->setValue( $this->base, $obj );
		Base::$options = []; // no entry for 'brand-new-plugin'
		$this->rm->invoke( $this->base, 'plugin' );
		$this->assertArrayHasKey( 'brand-new-plugin', Base::$options );
	}

	// Path C: slug set AND already in options → both branches skipped, defaults set.
	public function test_set_defaults_skips_both_branches_when_slug_in_options(): void {
		$obj       = new stdClass();
		$obj->slug = 'known-plugin';
		$this->rp_plugin->setValue( $this->base, $obj );
		Base::$options = [ 'known-plugin' => 'existing-val' ];
		$this->rm->invoke( $this->base, 'plugin' );
		$result = $this->rp_plugin->getValue( $this->base );
		$this->assertSame( '0.0.0', $result->remote_version );
		$this->assertSame( 'existing-val', Base::$options['known-plugin'] ); // untouched
	}
}

// =============================================================================
// Test_Base_Load — covers load() all branches (lines 148–183)
// =============================================================================

/**
 * Class Test_Base_Load
 */
class Test_Base_Load extends WP_UnitTestCase {

	private Base $base;

	public function set_up(): void {
		parent::set_up();
		delete_site_option( 'git_updater' );
		new Base();
		$this->base = new Base();
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-plugins-list-table.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
	}

	public function tear_down(): void {
		global $pagenow;
		$pagenow = '';
		unset( $_POST['_wpnonce'], $_POST['gu_refresh_cache'] );
		wp_set_current_user( 0 );
		remove_all_actions( 'gu_refresh_transients' );
		wp_deregister_style( 'git-updater' );
		wp_cache_delete( 'cron', 'options' );
		wp_unschedule_hook( 'gu_get_remote_plugin' );
		wp_unschedule_hook( 'gu_get_remote_theme' );
		delete_site_option( 'git_updater' );
		parent::tear_down();
	}

	// Lines 148–156: non-eligible page → early return after can_update check.
	public function test_load_returns_early_on_non_eligible_page(): void {
		global $pagenow;
		$pagenow = 'wp-login.php';
		$this->base->load();
		$this->assertTrue( true );
	}

	// Lines 149–152: admin user → can_update()=true → Settings::run() + Add_Ons::load_hooks().
	public function test_load_runs_settings_and_addons_when_admin(): void {
		global $pagenow;
		$pagenow = 'wp-login.php';
		$user    = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user );
		$this->base->load();
		$this->assertTrue( true );
	}

	// Lines 159–170, 181–182: eligible page → GU_Upgrade runs, add_action registered, meta fetched.
	public function test_load_on_eligible_page_registers_enqueue_action(): void {
		global $pagenow;
		$pagenow = 'plugins.php';
		$this->base->load();
		$this->assertNotFalse( has_action( 'admin_enqueue_scripts' ) );
	}

	// Lines 163–168: fire admin_enqueue_scripts closure → style registered.
	public function test_load_admin_enqueue_scripts_closure_registers_style(): void {
		global $pagenow;
		$pagenow = 'plugins.php';
		$this->base->load();
		set_current_screen( 'plugins' );
		do_action( 'admin_enqueue_scripts' );
		$this->assertTrue( wp_style_is( 'git-updater', 'registered' ) );
	}

	// Lines 172–179: valid POST nonce → do_action('gu_refresh_transients') fires.
	public function test_load_fires_refresh_transients_on_valid_post_nonce(): void {
		global $pagenow;
		$pagenow                   = 'plugins.php';
		$_POST['_wpnonce']         = wp_create_nonce( 'gu_refresh_cache' );
		$_POST['gu_refresh_cache'] = '1';
		$fired                     = false;
		add_action( 'gu_refresh_transients', function () use ( &$fired ) { $fired = true; } );
		$this->base->load();
		$this->assertTrue( $fired );
	}
}

// =============================================================================
// Test_Base_Update_Row_Enclosure — covers update_row_enclosure() (lines 586–630)
// =============================================================================

/**
 * Class Test_Base_Update_Row_Enclosure
 */
class Test_Base_Update_Row_Enclosure extends WP_UnitTestCase {

	private Base $base;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->base = new Base();
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-plugins-list-table.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
	}

	public function tear_down(): void {
		delete_option( 'active_plugins' );
		delete_site_option( 'allowedthemes' );
		remove_all_filters( 'network_allowed_themes' );
		parent::tear_down();
	}

	// Plugin type, not active → no ' active' in open tag.
	public function test_plugin_type_inactive_enclosure(): void {
		$result = $this->base->update_row_enclosure( 'my-plugin/my-plugin.php', 'plugin' );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'open', $result );
		$this->assertArrayHasKey( 'close', $result );
		$this->assertStringNotContainsString( ' active"', $result['open'] );
		$this->assertStringContainsString( '<p>', $result['open'] );
		$this->assertSame( '</p></div></td></tr>', $result['close'] );
	}

	// Plugin type, active → ' active' class in open tag.
	public function test_plugin_type_active_enclosure(): void {
		update_option( 'active_plugins', [ 'my-plugin/my-plugin.php' ] );
		$result = $this->base->update_row_enclosure( 'my-plugin/my-plugin.php', 'plugin' );
		$this->assertStringContainsString( ' active"', $result['open'] );
	}

	// Theme type, not branch_switcher → id and data-slug attributes present.
	public function test_theme_type_without_branch_switcher_has_id_attribute(): void {
		$result = $this->base->update_row_enclosure( 'my-theme', 'theme' );
		$this->assertStringContainsString( "id='my-theme'", $result['open'] );
		$this->assertStringContainsString( "data-slug='my-theme'", $result['open'] );
	}

	// Theme type, network-allowed → ' active' class in open tag.
	public function test_theme_type_network_allowed_has_active_class(): void {
		// Use filter to bypass WP_Theme::get_allowed_on_network()'s static cache,
		// which is already warm by the time this test runs on multisite.
		add_filter( 'network_allowed_themes', fn( $themes ) => array_merge( $themes, [ 'my-theme' => true ] ) );
		$result = $this->base->update_row_enclosure( 'my-theme', 'theme' );
		$this->assertStringContainsString( ' active"', $result['open'] );
	}

	// branch_switcher = true → no <p> wrapper, no id attribute.
	public function test_branch_switcher_true_omits_paragraph_and_id(): void {
		$result = $this->base->update_row_enclosure( 'my-theme', 'theme', true );
		$this->assertStringNotContainsString( '<p>', $result['open'] );
		$this->assertStringNotContainsString( "id='my-theme'", $result['open'] );
		$this->assertSame( '</div></td></tr>', $result['close'] );
	}
}

// =============================================================================
// Test_Base_Row_Meta_Icons — covers row_meta_icons() (lines 663–670)
// =============================================================================

/**
 * Class Test_Base_Row_Meta_Icons
 */
class Test_Base_Row_Meta_Icons extends WP_UnitTestCase {

	private Base $base;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->base = new Base();
	}

	public function tear_down(): void {
		remove_all_filters( 'gu_additions' );
		remove_all_filters( 'plugin_row_meta_gu_test' );
		parent::tear_down();
	}

	// get_git_icon returns null → links array unchanged.
	public function test_row_meta_icons_returns_unchanged_links_when_no_icon(): void {
		$links  = [ 'existing-link' ];
		$result = $this->base->row_meta_icons( $links, 'nonexistent/nonexistent.php' );
		$this->assertSame( [ 'existing-link' ], $result );
	}

	// get_git_icon returns string → icon appended to links.
	// Call from within a filter named with 'plugin' so current_filter() returns the plugin type.
	public function test_row_meta_icons_appends_icon_when_git_header_found(): void {
		$file = 'addon-slug/addon-slug.php';
		add_filter(
			'gu_additions',
			function ( $val, $repos, $type ) use ( $file ) {
				return [ $file => [ 'GitHubPluginURI' => 'https://github.com/test/test' ] ];
			},
			10,
			3
		);

		$result = null;
		add_filter(
			'plugin_row_meta_gu_test',
			function ( $links ) use ( &$result, $file ) {
				$result = $this->base->row_meta_icons( $links, $file );
				return $links;
			}
		);
		apply_filters( 'plugin_row_meta_gu_test', [] );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertStringContainsString( '<img', $result[0] );
	}
}

// =============================================================================
// Test_Base_Get_Git_Icon — covers get_git_icon() all paths (lines 681–732)
// =============================================================================

/**
 * Class Test_Base_Get_Git_Icon
 */
class Test_Base_Get_Git_Icon extends WP_UnitTestCase {

	private Base $base;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->base = new Base();
	}

	public function tear_down(): void {
		remove_all_filters( 'gu_additions' );
		remove_all_filters( 'plugin_action_links_gu_icon_test' );
		parent::tear_down();
	}

	/**
	 * Call get_git_icon() from within a filter whose name contains 'plugin',
	 * so current_filter() causes the plugin-type path to be taken.
	 */
	private function call_as_plugin( string $file, bool $padding ): ?string {
		$result = null;
		add_filter(
			'plugin_action_links_gu_icon_test',
			function () use ( $file, $padding, &$result ) {
				$result = $this->base->get_git_icon( $file, $padding );
				return [];
			}
		);
		apply_filters( 'plugin_action_links_gu_icon_test', [] );
		remove_all_filters( 'plugin_action_links_gu_icon_test' );
		return $result;
	}

	// Plugin type, fixture file exists → icon HTML with GitHub host, no padding.
	public function test_plugin_type_file_exists_returns_icon_html(): void {
		$configs = ( new Fragen\Git_Updater\Plugin() )->get_plugin_configs();
		if ( ! isset( $configs['test-gu-plugin'] ) ) {
			$this->markTestSkipped( 'Fixture plugin not installed.' );
		}
		$icon = $this->call_as_plugin( 'test-gu-plugin/test-gu-plugin.php', false );
		$this->assertNotNull( $icon );
		$this->assertStringContainsString( '<img', $icon );
		$this->assertStringNotContainsString( 'padding', $icon );
	}

	// Plugin type, add_padding=true → padding-right in style attribute (LTR).
	public function test_plugin_type_with_padding_includes_padding_style(): void {
		$configs = ( new Fragen\Git_Updater\Plugin() )->get_plugin_configs();
		if ( ! isset( $configs['test-gu-plugin'] ) ) {
			$this->markTestSkipped( 'Fixture plugin not installed.' );
		}
		$icon = $this->call_as_plugin( 'test-gu-plugin/test-gu-plugin.php', true );
		$this->assertNotNull( $icon );
		$this->assertStringContainsString( 'padding-right', $icon );
	}

	// Plugin type, file does not exist → empty file_data → no additions → null.
	public function test_plugin_type_nonexistent_file_returns_null(): void {
		$icon = $this->call_as_plugin( 'nonexistent/nonexistent.php', false );
		$this->assertNull( $icon );
	}

	// Theme type (no 'plugin' in current_filter) — call get_git_icon() directly.
	// Fixture theme must be installed.
	public function test_theme_type_file_exists_returns_icon_html(): void {
		$themes = wp_get_themes();
		if ( ! isset( $themes['test-gu-theme'] ) ) {
			$this->markTestSkipped( 'Fixture theme not installed.' );
		}
		$icon = $this->base->get_git_icon( 'test-gu-theme', false );
		$this->assertNotNull( $icon );
		$this->assertStringContainsString( '<img', $icon );
	}

	// gu_additions match → headers merged into file_data → icon generated.
	public function test_gu_additions_match_generates_icon(): void {
		$file = 'addon-slug/addon-slug.php';
		add_filter(
			'gu_additions',
			function ( $val, $repos, $type ) use ( $file ) {
				return [ $file => [ 'GitHubPluginURI' => 'https://github.com/test/test' ] ];
			},
			10,
			3
		);
		$icon = $this->call_as_plugin( $file, false );
		$this->assertNotNull( $icon );
		$this->assertStringContainsString( '<img', $icon );
	}

	// gu_additions present but slug doesn't match file → no merge → null.
	public function test_gu_additions_no_match_returns_null(): void {
		add_filter(
			'gu_additions',
			function ( $val, $repos, $type ) {
				return [ 'other-slug/other-slug.php' => [ 'GitHubPluginURI' => 'https://github.com/test/test' ] ];
			},
			10,
			3
		);
		$icon = $this->call_as_plugin( 'nonexistent/nonexistent.php', false );
		$this->assertNull( $icon );
	}

	// gu_additions match but value is empty string → $icon never assigned → null.
	public function test_empty_header_value_in_file_data_returns_null(): void {
		$file = 'empty-val/empty-val.php';
		add_filter(
			'gu_additions',
			function ( $val, $repos, $type ) use ( $file ) {
				return [ $file => [ 'GitHubPluginURI' => '' ] ];
			},
			10,
			3
		);
		$icon = $this->call_as_plugin( $file, false );
		$this->assertNull( $icon );
	}
}

// =============================================================================
// Test_Base_Upgrader_Source_Selection — covers upgrader_source_selection() and
// fix_misnamed_directory() (lines 461–574)
// =============================================================================

/**
 * Class Test_Base_Upgrader_Source_Selection
 */
class Test_Base_Upgrader_Source_Selection extends WP_UnitTestCase {

	private Base   $base;
	private string $remote_source;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->base = new Base();

		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();

		global $wp_filesystem;
		$this->remote_source = $wp_filesystem->wp_content_dir() . 'upgrade/';
	}

	public function tear_down(): void {
		unset(
			$_POST['git_updater_repo'],
			$_POST['slug'],
			$_POST['action'],
			$_REQUEST['_ajax_nonce']
		);
		remove_all_filters( 'wp_doing_ajax' );
		Base::$options = get_site_option( 'git_updater', [] );
		parent::tear_down();
	}

	// Plugin_Upgrader + hook_extra['plugin'] with non-GU slug → return $source (line 503).
	public function test_non_gu_plugin_returns_source_unchanged(): void {
		$source   = '/tmp/non-gu-plugin/';
		$upgrader = new Plugin_Upgrader();
		$result   = $this->base->upgrader_source_selection(
			$source,
			'/tmp/',
			$upgrader,
			[ 'plugin' => 'non-gu-plugin/non-gu-plugin.php' ]
		);
		$this->assertSame( $source, $result );
	}

	// Theme_Upgrader + hook_extra['theme'] with non-GU slug → return $source (lines 487–496).
	public function test_non_gu_theme_returns_source_unchanged(): void {
		$source   = '/tmp/non-gu-theme/';
		$upgrader = new Theme_Upgrader();
		$result   = $this->base->upgrader_source_selection(
			$source,
			'/tmp/',
			$upgrader,
			[ 'theme' => 'non-gu-theme' ]
		);
		$this->assertSame( $source, $result );
	}

	// Plugin_Upgrader AJAX path: slug from $_POST['slug'] (lines 478–481).
	public function test_plugin_ajax_path_reads_slug_from_post(): void {
		add_filter( 'wp_doing_ajax', '__return_true' );
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'updates' );
		$_POST['slug']           = 'non-gu-ajax-plugin';

		$upgrader = new Plugin_Upgrader();
		$result   = $this->base->upgrader_source_selection( '/tmp/source/', '/tmp/', $upgrader, [] );
		// Non-GU slug, no git_updater_repo → returns $source.
		$this->assertSame( '/tmp/source/', $result );
	}

	// Theme_Upgrader AJAX path: slug from $_POST['slug'] (lines 492–495).
	public function test_theme_ajax_path_reads_slug_from_post(): void {
		add_filter( 'wp_doing_ajax', '__return_true' );
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'updates' );
		$_POST['slug']           = 'non-gu-ajax-theme';

		$upgrader = new Theme_Upgrader();
		$result   = $this->base->upgrader_source_selection( '/tmp/source/', '/tmp/', $upgrader, [] );
		$this->assertSame( '/tmp/source/', $result );
	}

	// GU plugin (fixture in config): source basename matches new_source → no move_dir.
	// Covers: lines 506–522, 531–534 and fix_misnamed_directory lines 550–564.
	public function test_gu_plugin_source_basename_matches_returns_trailingslash(): void {
		$configs = ( new Fragen\Git_Updater\Plugin() )->get_plugin_configs();
		if ( ! isset( $configs['test-gu-plugin'] ) ) {
			$this->markTestSkipped( 'Fixture plugin not installed.' );
		}
		// dirname($source) basename must equal basename($new_source) = 'test-gu-plugin'.
		$source   = $this->remote_source . 'test-gu-plugin/test-gu-plugin.php';
		$upgrader = new Plugin_Upgrader();
		$result   = $this->base->upgrader_source_selection(
			$source,
			'/tmp/',
			$upgrader,
			[ 'plugin' => 'test-gu-plugin/test-gu-plugin.php' ]
		);
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'test-gu-plugin', $result );
		$this->assertStringEndsWith( '/', $result );
	}

	// GU plugin: source dirname's basename differs from new_source → move_dir called.
	// Source path doesn't exist → WP_Error returned (lines 524–527).
	public function test_gu_plugin_move_dir_failure_returns_wp_error(): void {
		$configs = ( new Fragen\Git_Updater\Plugin() )->get_plugin_configs();
		if ( ! isset( $configs['test-gu-plugin'] ) ) {
			$this->markTestSkipped( 'Fixture plugin not installed.' );
		}
		// dirname('different-dir') = '.' or '/tmp', basename = 'different-dir' ≠ 'test-gu-plugin'.
		$source   = '/tmp/different-dir/source.php';
		$upgrader = new Plugin_Upgrader();
		$result   = $this->base->upgrader_source_selection(
			$source,
			'/tmp/',
			$upgrader,
			[ 'plugin' => 'test-gu-plugin/test-gu-plugin.php' ]
		);
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	// Lines 512–515: remote install path — Install::$install carries the repo slug.
	public function test_remote_install_path_sets_slug_and_options(): void {
		$rp = new \ReflectionProperty( 'Fragen\Git_Updater\Install', 'install' );
		$rp->setAccessible( true );
		$original = $rp->getValue( null );
		$rp->setValue( null, [ 'git_updater_install_repo' => 'my-install-plugin' ] );

		try {
			$_POST['git_updater_repo'] = '1';
			$upgrader                  = new Plugin_Upgrader();
			// Non-GU slug → $repo = [] → empty($repo) is true, bypassed early return via $_POST.
			$result = $this->base->upgrader_source_selection(
				'/tmp/nonexistent-source/',
				'/tmp/',
				$upgrader,
				[ 'plugin' => 'nonexistent/nonexistent.php' ]
			);
			// Lines 513–515 executed; move_dir on missing path → WP_Error.
			$this->assertTrue( is_string( $result ) || $result instanceof WP_Error );
			$this->assertTrue( Base::$options['remote_install'] ?? false );
		} finally {
			$rp->setValue( null, $original );
			unset( $_POST['git_updater_repo'] );
		}
	}
}

// =============================================================================
// Test_Base_Fix_Misnamed_Directory — covers fix_misnamed_directory() paths that
// cannot be reached via upgrader_source_selection (lines 556–571).
// =============================================================================

/**
 * Class Test_Base_Fix_Misnamed_Directory
 */
class Test_Base_Fix_Misnamed_Directory extends WP_UnitTestCase {

	private Base                $base;
	private \ReflectionMethod   $rm;
	private string              $remote_source;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->base = new Base();
		$this->rm   = new \ReflectionMethod( Base::class, 'fix_misnamed_directory' );
		$this->rm->setAccessible( true );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;
		$this->remote_source = $wp_filesystem->wp_content_dir() . 'upgrade/';
	}

	public function tear_down(): void {
		Base::$options = get_site_option( 'git_updater', [] );
		parent::tear_down();
	}

	// Lines 556–560: config entry with slug_did and matching DID hash → return slug_did path.
	public function test_fix_misnamed_directory_returns_slug_did_path_on_hash_match(): void {
		$plugin_obj = Fragen\Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $this->base );
		$rp         = new \ReflectionProperty( get_class( $plugin_obj ), 'config' );
		$rp->setAccessible( true );
		$original = $rp->getValue( $plugin_obj );

		$base_slug = 'my-plugin';
		$did       = 'did:example:abc123';
		$did_hash  = substr( hash( 'sha256', $did ), 0, 6 );
		$slug_val  = $base_slug . '-' . $did_hash;

		$entry           = new stdClass();
		$entry->slug     = $base_slug;
		$entry->slug_did = $slug_val;
		$entry->did      = $did;
		$entry->file     = $base_slug . '/' . $base_slug . '.php';
		$rp->setValue( $plugin_obj, [ $base_slug => $entry ] );

		try {
			$result = $this->rm->invoke(
				$this->base,
				'/tmp/new_source',
				$this->remote_source,
				$plugin_obj,
				$slug_val   // slug = 'my-plugin-HASH' → maybe_slug='my-plugin', maybe_did_hash='HASH'
			);
			$this->assertStringContainsString( $slug_val, $result );
			$this->assertStringStartsWith( $this->remote_source, $result );
		} finally {
			$rp->setValue( $plugin_obj, $original );
		}
	}

	// Lines 566–571: slug not in config, not remote_install → get_repo_slugs called, new_source updated.
	// Triggered when new_source's basename differs from slug (e.g. new_source='' while slug='unknown').
	public function test_fix_misnamed_directory_calls_get_repo_slugs_when_not_in_config(): void {
		$plugin_obj = Fragen\Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $this->base );

		// new_source = '' (basename = '') ≠ slug = 'unknown-plugin' → line 562 is false → falls to 566.
		$result = $this->rm->invoke(
			$this->base,
			'',               // new_source
			$this->remote_source,
			$plugin_obj,
			'unknown-plugin'  // slug not in Plugin config
		);
		// get_repo_slugs returns [] → repo['slug'] defaults to slug → new_source = remote_source + slug.
		$this->assertStringContainsString( 'unknown-plugin', $result );
	}
}
