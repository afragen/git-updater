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
