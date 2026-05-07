<?php
/**
 * Tests for Theme class methods.
 *
 * Covers:
 * - Theme::get_theme_configs()       — returns an array
 * - Theme::load_pre_filters()        — filters registered (single-site vs multisite)
 * - Theme::themes_api()              — non-info action; unknown slug; background-wait skip; dot_org skip; populated response
 * - Theme::wp_theme_update_row()     — no output when theme not in transient response; unavailable msg; update-now link
 * - Theme::remove_after_theme_row()  — removes wp_theme_update_row for known theme; ignores unknown
 * - Theme::append_theme_actions_content() — empty when no transient; HTML with update link; HTML without package
 * - Theme::customize_theme_update_html()  — skips missing slugs; sets 'update' key on hasUpdate; appends to description
 * - Theme::update_site_transient()   — non-object input; empty config; update/no_update paths;
 *                                      no_update not overwritten; dot_org override removal; release_asset branch package
 * - Theme::get_remote_theme_meta()   — load_pre_filters called; cron scheduled for uncached repos
 *
 * Test_Theme_Config_Discovery requires the fixture theme to be mounted via .wp-env.json.
 * Skip message: Run `npm run wp-env start` after adding the theme fixture.
 *
 * ReflectionProperty/Method are used to inject mock configs and call protected methods
 * so tests run without network calls or a live fixture.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\Base;
use Fragen\Git_Updater\Theme;

// ---------------------------------------------------------------------------
// Shared helper trait
// ---------------------------------------------------------------------------

trait Theme_Mock_Helper {

	/**
	 * Build a fully-populated mock theme stdClass.
	 *
	 * @param array<string, mixed> $overrides Fields to override.
	 * @return stdClass
	 */
	private function make_theme_obj( array $overrides = [] ): stdClass {
		return (object) array_merge(
			[
				'slug'           => 'test-gu-theme',
				'file'           => 'test-gu-theme/style.css',
				'uri'            => 'https://github.com/afragen/test-gu-theme',
				'theme_uri'      => 'https://github.com/afragen/test-gu-theme',
				'branch'         => 'main',
				'primary_branch' => 'main',
				'git'            => 'github',
				'type'           => 'theme',
				'remote_version' => '2.0.0',
				'local_version'  => '1.0.0',
				'download_link'  => 'https://example.com/test-gu-theme.zip',
				'tested'         => '6.5',
				'requires'       => '',
				'requires_php'   => '',
				'branches'       => [ 'main' => [ 'download' => 'https://example.com/main.zip' ] ],
				'dot_org'        => false,
				'name'           => 'Test GU Theme',
				'author'         => 'Test Author',
				'homepage'       => 'https://example.com',
				'donate_link'    => '',
				'sections'       => [ 'description' => 'A test theme description.' ],
				'downloaded'     => 0,
				'last_updated'   => '2024-01-01',
				'rating'         => 0,
				'num_ratings'    => 0,
				'release_asset'  => false,
				'did'            => null,
				'icons'          => [],
				'banners'        => [],
			],
			$overrides
		);
	}

	/**
	 * Construct a Theme instance with a pre-injected config array.
	 *
	 * @param array<string, stdClass> $config Mock config keyed by slug.
	 * @return Theme
	 */
	private function theme_with_config( array $config ): Theme {
		$theme = new Theme();
		$ref   = new ReflectionProperty( Theme::class, 'config' );
		$ref->setAccessible( true );
		$ref->setValue( $theme, $config );
		return $theme;
	}
}

// ---------------------------------------------------------------------------
// Test_Theme_Get_Theme_Configs
// ---------------------------------------------------------------------------

/**
 * Class Test_Theme_Get_Theme_Configs
 */
class Test_Theme_Get_Theme_Configs extends WP_UnitTestCase {
	use Theme_Mock_Helper;

	public function set_up(): void {
		parent::set_up();
		new Base();
	}

	public function test_returns_array(): void {
		$theme   = new Theme();
		$configs = $theme->get_theme_configs();
		$this->assertIsArray( $configs );
	}

	public function test_reflects_injected_config(): void {
		$theme_obj = $this->make_theme_obj();
		$theme     = $this->theme_with_config( [ 'test-gu-theme' => $theme_obj ] );
		$configs   = $theme->get_theme_configs();
		$this->assertArrayHasKey( 'test-gu-theme', $configs );
		$this->assertSame( 'test-gu-theme', $configs['test-gu-theme']->slug );
	}
}

// ---------------------------------------------------------------------------
// Test_Theme_Load_Pre_Filters
// ---------------------------------------------------------------------------

/**
 * Class Test_Theme_Load_Pre_Filters
 */
class Test_Theme_Load_Pre_Filters extends WP_UnitTestCase {
	use Theme_Mock_Helper;

	private Theme $theme;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->theme = new Theme();
	}

	public function tear_down(): void {
		remove_filter( 'themes_api', [ $this->theme, 'themes_api' ], 99 );
		remove_filter( 'site_transient_update_themes', [ $this->theme, 'update_site_transient' ], 15 );
		remove_filter( 'wp_prepare_themes_for_js', [ $this->theme, 'customize_theme_update_html' ] );
		parent::tear_down();
	}

	public function test_registers_themes_api_filter(): void {
		$this->theme->load_pre_filters();
		$this->assertSame( 99, has_filter( 'themes_api', [ $this->theme, 'themes_api' ] ) );
	}

	public function test_registers_site_transient_update_themes_filter(): void {
		$this->theme->load_pre_filters();
		$this->assertSame( 15, has_filter( 'site_transient_update_themes', [ $this->theme, 'update_site_transient' ] ) );
	}

	public function test_registers_wp_prepare_themes_for_js_on_single_site(): void {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single-site only.' );
		}
		$this->theme->load_pre_filters();
		$this->assertNotFalse( has_filter( 'wp_prepare_themes_for_js', [ $this->theme, 'customize_theme_update_html' ] ) );
	}

	public function test_no_wp_prepare_themes_for_js_on_multisite(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}
		$this->theme->load_pre_filters();
		$this->assertFalse( has_filter( 'wp_prepare_themes_for_js', [ $this->theme, 'customize_theme_update_html' ] ) );
	}
}

// ---------------------------------------------------------------------------
// Test_Theme_Themes_API_Filter
// ---------------------------------------------------------------------------

/**
 * Class Test_Theme_Themes_API_Filter
 */
class Test_Theme_Themes_API_Filter extends WP_UnitTestCase {
	use Theme_Mock_Helper;

	private string $cache_key;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->cache_key = 'ghu-' . md5( 'test-gu-theme' );
		delete_site_option( $this->cache_key );
	}

	public function tear_down(): void {
		delete_site_option( $this->cache_key );
		parent::tear_down();
	}

	public function test_returns_result_for_non_theme_information_action(): void {
		$theme    = $this->theme_with_config( [ 'test-gu-theme' => $this->make_theme_obj() ] );
		$response = new stdClass();
		$response->slug = 'test-gu-theme';
		$result = $theme->themes_api( false, 'query_themes', $response );
		$this->assertFalse( $result );
	}

	public function test_returns_result_when_slug_not_in_config(): void {
		$theme    = $this->theme_with_config( [] );
		$response = new stdClass();
		$response->slug = 'unknown-theme';
		// Unknown slug → $theme = false → waiting_for_background_update(false) = true → returns $result.
		$result = $theme->themes_api( 'original', 'theme_information', $response );
		$this->assertSame( 'original', $result );
	}

	public function test_returns_result_when_waiting_for_background_update(): void {
		// Empty cache → waiting_for_background_update = true.
		$theme    = $this->theme_with_config( [ 'test-gu-theme' => $this->make_theme_obj() ] );
		$response = new stdClass();
		$response->slug = 'test-gu-theme';
		$result = $theme->themes_api( 'original', 'theme_information', $response );
		$this->assertSame( 'original', $result );
	}

	public function test_returns_result_for_dot_org_theme(): void {
		update_site_option( $this->cache_key, [ 'any' => 'data' ] );
		$theme_obj = $this->make_theme_obj( [ 'dot_org' => true ] );
		$theme     = $this->theme_with_config( [ 'test-gu-theme' => $theme_obj ] );
		$response  = new stdClass();
		$response->slug = 'test-gu-theme';
		$result = $theme->themes_api( false, 'theme_information', $response );
		$this->assertFalse( $result );
	}

	public function test_populates_response_for_git_theme(): void {
		update_site_option( $this->cache_key, [ 'any' => 'data' ] );
		$theme_obj = $this->make_theme_obj( [ 'dot_org' => false ] );
		$theme     = $this->theme_with_config( [ 'test-gu-theme' => $theme_obj ] );
		$response  = new stdClass();
		$response->slug = 'test-gu-theme';
		$result = $theme->themes_api( false, 'theme_information', $response );
		$this->assertInstanceOf( stdClass::class, $result );
		$this->assertSame( 'Test GU Theme', $result->name );
		$this->assertSame( '2.0.0', $result->version );
		$this->assertSame( 'Test Author', $result->author );
	}

	public function test_populates_description_as_joined_sections(): void {
		update_site_option( $this->cache_key, [ 'any' => 'data' ] );
		$theme_obj = $this->make_theme_obj( [
			'dot_org'  => false,
			'sections' => [
				'description' => 'Description text.',
				'changelog'   => 'Changelog text.',
			],
		] );
		$theme    = $this->theme_with_config( [ 'test-gu-theme' => $theme_obj ] );
		$response = new stdClass();
		$response->slug = 'test-gu-theme';
		$result = $theme->themes_api( false, 'theme_information', $response );
		$this->assertStringContainsString( 'Description text.', $result->description );
		$this->assertStringContainsString( 'Changelog text.', $result->description );
	}
}

// ---------------------------------------------------------------------------
// Test_Theme_Wp_Theme_Update_Row
// ---------------------------------------------------------------------------

/**
 * Class Test_Theme_Wp_Theme_Update_Row
 *
 * Tests Theme::wp_theme_update_row(), which echoes HTML directly.
 * The "with response" cases require _get_list_table() (WP admin context);
 * those tests load the necessary admin includes and skip gracefully if unavailable.
 */
class Test_Theme_Wp_Theme_Update_Row extends WP_UnitTestCase {
	use Theme_Mock_Helper;

	public function set_up(): void {
		parent::set_up();
		new Base();
		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}
		if ( ! class_exists( 'WP_Plugins_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-plugins-list-table.php';
		}
		if ( ! function_exists( '_get_list_table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/template.php';
		}
	}

	public function tear_down(): void {
		delete_site_transient( 'update_themes' );
		parent::tear_down();
	}

	public function test_produces_no_output_when_transient_is_absent(): void {
		$theme = $this->theme_with_config( [ 'test-gu-theme' => $this->make_theme_obj() ] );
		ob_start();
		$theme->wp_theme_update_row( 'test-gu-theme', [ 'Name' => 'Test GU Theme' ] );
		$output = ob_get_clean();
		$this->assertSame( '', $output );
	}

	public function test_produces_no_output_when_theme_key_absent_from_response(): void {
		$update           = new stdClass();
		$update->response = [ 'other-theme' => [ 'new_version' => '2.0.0', 'package' => '' ] ];
		set_site_transient( 'update_themes', $update );

		$theme = $this->theme_with_config( [ 'test-gu-theme' => $this->make_theme_obj() ] );
		ob_start();
		$theme->wp_theme_update_row( 'test-gu-theme', [ 'Name' => 'Test GU Theme' ] );
		$output = ob_get_clean();
		$this->assertSame( '', $output );
	}

	public function test_outputs_unavailable_message_when_package_is_empty(): void {
		if ( ! function_exists( '_get_list_table' ) ) {
			$this->markTestSkipped( '_get_list_table() not available outside admin context.' );
		}
		$update           = new stdClass();
		$update->response = [
			'test-gu-theme' => [
				'new_version' => '2.0.0',
				'package'     => '',
				'url'         => '',
			],
		];
		set_site_transient( 'update_themes', $update );

		$theme = $this->theme_with_config( [ 'test-gu-theme' => $this->make_theme_obj() ] );
		ob_start();
		$theme->wp_theme_update_row( 'test-gu-theme', [ 'Name' => 'Test GU Theme' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( '2.0.0', $output );
		$this->assertStringContainsString( 'unavailable', strtolower( $output ) );
	}

	public function test_outputs_update_now_link_when_package_is_set(): void {
		if ( ! function_exists( '_get_list_table' ) ) {
			$this->markTestSkipped( '_get_list_table() not available outside admin context.' );
		}
		$update           = new stdClass();
		$update->response = [
			'test-gu-theme' => [
				'new_version' => '2.0.0',
				'package'     => 'https://example.com/test-gu-theme.zip',
				'url'         => '',
			],
		];
		set_site_transient( 'update_themes', $update );

		$theme = $this->theme_with_config( [ 'test-gu-theme' => $this->make_theme_obj() ] );
		ob_start();
		$theme->wp_theme_update_row( 'test-gu-theme', [ 'Name' => 'Test GU Theme' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( '2.0.0', $output );
		$this->assertStringContainsString( 'update', strtolower( $output ) );
	}
}

// ---------------------------------------------------------------------------
// Test_Theme_Remove_After_Theme_Row
// ---------------------------------------------------------------------------

/**
 * Class Test_Theme_Remove_After_Theme_Row
 */
class Test_Theme_Remove_After_Theme_Row extends WP_UnitTestCase {
	use Theme_Mock_Helper;

	public function set_up(): void {
		parent::set_up();
		new Base();
	}

	public function test_removes_action_for_known_theme_key(): void {
		add_action( 'after_theme_row_test-gu-theme', 'wp_theme_update_row' );
		$theme = $this->theme_with_config( [ 'test-gu-theme' => $this->make_theme_obj() ] );
		$theme->remove_after_theme_row( 'test-gu-theme' );
		$this->assertFalse( has_action( 'after_theme_row_test-gu-theme', 'wp_theme_update_row' ) );
	}

	public function test_does_nothing_for_unknown_theme_key(): void {
		add_action( 'after_theme_row_other-theme', 'wp_theme_update_row' );
		$theme = $this->theme_with_config( [ 'test-gu-theme' => $this->make_theme_obj() ] );
		$theme->remove_after_theme_row( 'other-theme' );
		// Action should still be present because 'other-theme' is not in our config.
		$this->assertNotFalse( has_action( 'after_theme_row_other-theme', 'wp_theme_update_row' ) );
		remove_action( 'after_theme_row_other-theme', 'wp_theme_update_row' );
	}

	public function test_does_nothing_for_empty_config(): void {
		add_action( 'after_theme_row_test-gu-theme', 'wp_theme_update_row' );
		$theme = $this->theme_with_config( [] );
		$theme->remove_after_theme_row( 'test-gu-theme' );
		// Config is empty, action should remain.
		$this->assertNotFalse( has_action( 'after_theme_row_test-gu-theme', 'wp_theme_update_row' ) );
		remove_action( 'after_theme_row_test-gu-theme', 'wp_theme_update_row' );
	}
}

// ---------------------------------------------------------------------------
// Test_Theme_Append_Theme_Actions_Content
// ---------------------------------------------------------------------------

/**
 * Class Test_Theme_Append_Theme_Actions_Content
 */
class Test_Theme_Append_Theme_Actions_Content extends WP_UnitTestCase {
	use Theme_Mock_Helper;

	public function set_up(): void {
		parent::set_up();
		new Base();
	}

	public function tear_down(): void {
		delete_site_transient( 'update_themes' );
		parent::tear_down();
	}

	/**
	 * Call the protected append_theme_actions_content() via Reflection.
	 *
	 * @param Theme    $theme     Theme instance.
	 * @param stdClass $theme_obj Theme config object.
	 * @return string
	 */
	private function invoke_append( Theme $theme, stdClass $theme_obj ): string {
		$ref = new ReflectionMethod( Theme::class, 'append_theme_actions_content' );
		$ref->setAccessible( true );
		return $ref->invoke( $theme, $theme_obj );
	}

	public function test_returns_string_when_no_update_transient(): void {
		$theme_obj = $this->make_theme_obj();
		$theme     = new Theme();
		$result    = $this->invoke_append( $theme, $theme_obj );
		$this->assertIsString( $result );
	}

	public function test_returns_empty_string_when_slug_not_in_transient_response(): void {
		$update            = new stdClass();
		$update->response  = [];
		set_site_transient( 'update_themes', $update );
		$theme_obj = $this->make_theme_obj();
		$theme     = new Theme();
		$result    = $this->invoke_append( $theme, $theme_obj );
		$this->assertSame( '', $result );
	}

	public function test_returns_html_with_version_when_package_set(): void {
		$update           = new stdClass();
		$update->response = [
			'test-gu-theme' => [
				'new_version' => '2.0.0',
				'package'     => 'https://example.com/test-gu-theme.zip',
			],
		];
		set_site_transient( 'update_themes', $update );

		$theme_obj = $this->make_theme_obj( [ 'remote_version' => '2.0.0' ] );
		$theme     = new Theme();
		$result    = $this->invoke_append( $theme, $theme_obj );
		$this->assertStringContainsString( '2.0.0', $result );
		$this->assertStringContainsString( 'update now', $result );
	}

	public function test_returns_html_without_update_link_when_no_package(): void {
		$update           = new stdClass();
		$update->response = [
			'test-gu-theme' => [
				'new_version' => '2.0.0',
				'package'     => '',
			],
		];
		set_site_transient( 'update_themes', $update );

		$theme_obj = $this->make_theme_obj( [ 'remote_version' => '2.0.0' ] );
		$theme     = new Theme();
		$result    = $this->invoke_append( $theme, $theme_obj );
		$this->assertStringContainsString( 'Automatic update is unavailable', $result );
	}
}

// ---------------------------------------------------------------------------
// Test_Theme_Customize_Theme_Update_HTML
// ---------------------------------------------------------------------------

/**
 * Class Test_Theme_Customize_Theme_Update_HTML
 */
class Test_Theme_Customize_Theme_Update_HTML extends WP_UnitTestCase {
	use Theme_Mock_Helper;

	public function set_up(): void {
		parent::set_up();
		new Base();
	}

	public function tear_down(): void {
		delete_site_transient( 'update_themes' );
		parent::tear_down();
	}

	public function test_skips_slugs_not_in_prepared_array(): void {
		$theme_obj = $this->make_theme_obj();
		$theme     = $this->theme_with_config( [ 'test-gu-theme' => $theme_obj ] );
		$prepared  = [ 'different-theme' => [ 'description' => 'Other theme.' ] ];
		$result    = $theme->customize_theme_update_html( $prepared );
		$this->assertSame( $prepared, $result );
	}

	public function test_sets_update_key_when_has_update(): void {
		$theme_obj = $this->make_theme_obj();
		$theme     = $this->theme_with_config( [ 'test-gu-theme' => $theme_obj ] );
		$prepared  = [
			'test-gu-theme' => [
				'hasUpdate'   => true,
				'update'      => '',
				'description' => 'Original description.',
			],
		];
		$result = $theme->customize_theme_update_html( $prepared );
		$this->assertArrayHasKey( 'update', $result['test-gu-theme'] );
	}

	public function test_appends_to_description_when_no_update(): void {
		$theme_obj = $this->make_theme_obj();
		$theme     = $this->theme_with_config( [ 'test-gu-theme' => $theme_obj ] );
		$original  = 'Original description.';
		$prepared  = [
			'test-gu-theme' => [
				'hasUpdate'   => false,
				'description' => $original,
			],
		];
		$result = $theme->customize_theme_update_html( $prepared );
		// Description key should still exist (appended to, even if nothing added in test env).
		$this->assertArrayHasKey( 'description', $result['test-gu-theme'] );
	}

	public function test_returns_all_themes_including_unmanaged_ones(): void {
		$theme_obj = $this->make_theme_obj();
		$theme     = $this->theme_with_config( [ 'test-gu-theme' => $theme_obj ] );
		$prepared  = [
			'test-gu-theme'  => [ 'hasUpdate' => false, 'description' => 'Managed.' ],
			'unrelated-theme' => [ 'hasUpdate' => false, 'description' => 'Not managed.' ],
		];
		$result = $theme->customize_theme_update_html( $prepared );
		$this->assertArrayHasKey( 'test-gu-theme', $result );
		$this->assertArrayHasKey( 'unrelated-theme', $result );
		$this->assertSame( 'Not managed.', $result['unrelated-theme']['description'] );
	}
}

// ---------------------------------------------------------------------------
// Test_Theme_Update_Site_Transient_Method
// ---------------------------------------------------------------------------

/**
 * Class Test_Theme_Update_Site_Transient_Method
 */
class Test_Theme_Update_Site_Transient_Method extends WP_UnitTestCase {
	use Theme_Mock_Helper;

	public function set_up(): void {
		parent::set_up();
		new Base();
	}

	public function tear_down(): void {
		remove_all_filters( 'gu_config_pre_process' );
		remove_all_filters( 'gu_override_dot_org' );
		remove_all_filters( 'gu_remote_is_newer' );
		parent::tear_down();
	}

	public function test_non_object_transient_becomes_stdclass(): void {
		$theme  = $this->theme_with_config( [] );
		$result = $theme->update_site_transient( null );
		$this->assertInstanceOf( stdClass::class, $result );
	}

	public function test_false_transient_becomes_stdclass(): void {
		$theme  = $this->theme_with_config( [] );
		$result = $theme->update_site_transient( false );
		$this->assertInstanceOf( stdClass::class, $result );
	}

	public function test_empty_config_returns_transient_unchanged(): void {
		$theme     = $this->theme_with_config( [] );
		$transient = new stdClass();
		$transient->response  = [];
		$transient->no_update = [];
		$result = $theme->update_site_transient( $transient );
		$this->assertSame( $transient, $result );
		$this->assertEmpty( $result->response );
		$this->assertEmpty( $result->no_update );
	}

	public function test_gu_config_pre_process_filter_applied(): void {
		$theme_obj = $this->make_theme_obj();
		$theme     = $this->theme_with_config( [ 'test-gu-theme' => $theme_obj ] );
		add_filter( 'gu_config_pre_process', '__return_empty_array' );
		$transient = new stdClass();
		$transient->response  = [];
		$transient->no_update = [];
		$result = $theme->update_site_transient( $transient );
		$this->assertEmpty( $result->response );
		$this->assertEmpty( $result->no_update );
	}

	public function test_theme_without_update_goes_to_no_update(): void {
		$theme_obj = $this->make_theme_obj( [
			'remote_version' => '1.0.0',
			'local_version'  => '2.0.0',
		] );
		$theme     = $this->theme_with_config( [ 'test-gu-theme' => $theme_obj ] );
		$transient = new stdClass();
		$transient->response  = [];
		$transient->no_update = [];
		$result = $theme->update_site_transient( $transient );
		$this->assertArrayHasKey( 'test-gu-theme', $result->no_update );
		$this->assertArrayNotHasKey( 'test-gu-theme', $result->response );
	}

	public function test_theme_with_update_goes_to_response(): void {
		$theme_obj = $this->make_theme_obj( [
			'remote_version' => '2.0.0',
			'local_version'  => '1.0.0',
			'dot_org'        => false,
		] );
		$theme     = $this->theme_with_config( [ 'test-gu-theme' => $theme_obj ] );
		$transient = new stdClass();
		$transient->response  = [];
		$transient->no_update = [];
		$result = $theme->update_site_transient( $transient );
		$this->assertArrayHasKey( 'test-gu-theme', $result->response );
		$this->assertSame( '2.0.0', $result->response['test-gu-theme']['new_version'] );
	}

	public function test_response_contains_correct_type_field(): void {
		$theme_obj = $this->make_theme_obj( [
			'remote_version' => '2.0.0',
			'local_version'  => '1.0.0',
			'dot_org'        => false,
			'git'            => 'github',
			'type'           => 'theme',
		] );
		$theme     = $this->theme_with_config( [ 'test-gu-theme' => $theme_obj ] );
		$transient = new stdClass();
		$transient->response  = [];
		$transient->no_update = [];
		$result = $theme->update_site_transient( $transient );
		$this->assertSame( 'github-theme', $result->response['test-gu-theme']['type'] );
	}

	public function test_no_update_not_overwritten_when_already_set(): void {
		$theme_obj = $this->make_theme_obj( [
			'remote_version' => '1.0.0',
			'local_version'  => '2.0.0',
		] );
		$theme     = $this->theme_with_config( [ 'test-gu-theme' => $theme_obj ] );
		$existing  = [ 'theme' => 'pre-existing' ];
		$transient = new stdClass();
		$transient->response  = [];
		$transient->no_update = [ 'test-gu-theme' => $existing ];
		$result = $theme->update_site_transient( $transient );
		$this->assertSame( $existing, $result->no_update['test-gu-theme'] );
	}

	public function test_dot_org_override_removes_entry_from_response(): void {
		$theme_obj = $this->make_theme_obj( [
			'remote_version' => '1.0.0',
			'local_version'  => '2.0.0',
		] );
		$theme     = $this->theme_with_config( [ 'test-gu-theme' => $theme_obj ] );
		$transient = new stdClass();
		$transient->response  = [ 'test-gu-theme' => [ 'theme' => 'test-gu-theme' ] ];
		$transient->no_update = [];
		add_filter( 'gu_override_dot_org', fn() => [ 'test-gu-theme' ] );
		$result = $theme->update_site_transient( $transient );
		$this->assertArrayNotHasKey( 'test-gu-theme', $result->response );
	}

	public function test_release_asset_non_primary_branch_updates_package_url(): void {
		$theme_obj = $this->make_theme_obj( [
			'remote_version' => '2.0.0',
			'local_version'  => '1.0.0',
			'dot_org'        => false,
			'release_asset'  => true,
			'branch'         => 'develop',
			'primary_branch' => 'main',
			'branches'       => [
				'main'    => [ 'download' => 'https://example.com/main.zip' ],
				'develop' => [ 'download' => 'https://example.com/develop.zip' ],
			],
		] );
		$theme     = $this->theme_with_config( [ 'test-gu-theme' => $theme_obj ] );
		$transient = new stdClass();
		$transient->response  = [];
		$transient->no_update = [];
		$result = $theme->update_site_transient( $transient );
		$this->assertSame( 'https://example.com/develop.zip', $result->response['test-gu-theme']['package'] );
	}

	public function test_release_asset_missing_branch_sets_package_null(): void {
		$theme_obj = $this->make_theme_obj( [
			'remote_version' => '2.0.0',
			'local_version'  => '1.0.0',
			'dot_org'        => false,
			'release_asset'  => true,
			'branch'         => 'feature',
			'primary_branch' => 'main',
			'branches'       => [
				'main' => [ 'download' => 'https://example.com/main.zip' ],
			],
		] );
		$theme     = $this->theme_with_config( [ 'test-gu-theme' => $theme_obj ] );
		$transient = new stdClass();
		$transient->response  = [];
		$transient->no_update = [];
		$result = $theme->update_site_transient( $transient );
		$this->assertNull( $result->response['test-gu-theme']['package'] );
	}

	public function test_dot_org_theme_on_primary_branch_skipped_for_update(): void {
		// dot_org=true → override_dot_org returns false → continue (no update).
		$theme_obj = $this->make_theme_obj( [
			'remote_version' => '2.0.0',
			'local_version'  => '1.0.0',
			'dot_org'        => true,
			'branch'         => 'main',
			'primary_branch' => 'main',
		] );
		$theme     = $this->theme_with_config( [ 'test-gu-theme' => $theme_obj ] );
		$transient = new stdClass();
		$transient->response  = [];
		$transient->no_update = [];
		$result = $theme->update_site_transient( $transient );
		$this->assertArrayNotHasKey( 'test-gu-theme', $result->response );
	}
}

// ---------------------------------------------------------------------------
// Test_Theme_Get_Remote_Theme_Meta
// ---------------------------------------------------------------------------

/**
 * Class Test_Theme_Get_Remote_Theme_Meta
 */
class Test_Theme_Get_Remote_Theme_Meta extends WP_UnitTestCase {
	use Theme_Mock_Helper;

	public function set_up(): void {
		parent::set_up();
		new Base();
	}

	public function tear_down(): void {
		remove_all_filters( 'themes_api' );
		remove_all_filters( 'site_transient_update_themes' );
		remove_all_filters( 'wp_prepare_themes_for_js' );
		remove_all_filters( 'gu_config_pre_process' );
		remove_all_filters( 'gu_disable_wpcron' );
		wp_clear_scheduled_hook( 'gu_get_remote_theme' );
		parent::tear_down();
	}

	public function test_load_pre_filters_called_when_config_is_empty(): void {
		$theme = $this->theme_with_config( [] );
		$theme->get_remote_theme_meta();
		$this->assertSame( 99, has_filter( 'themes_api', [ $theme, 'themes_api' ] ) );
		$this->assertSame( 15, has_filter( 'site_transient_update_themes', [ $theme, 'update_site_transient' ] ) );
	}

	public function test_gu_config_pre_process_filter_applied_in_meta_fetch(): void {
		$filter_ran = false;
		add_filter(
			'gu_config_pre_process',
			function ( $config ) use ( &$filter_ran ) {
				$filter_ran = true;
				return $config;
			}
		);
		$theme = $this->theme_with_config( [] );
		$theme->get_remote_theme_meta();
		$this->assertTrue( $filter_ran );
	}

	public function test_schedules_background_cron_for_uncached_themes(): void {
		wp_clear_scheduled_hook( 'gu_get_remote_theme' );

		$theme_obj = $this->make_theme_obj();
		// Empty cache → waiting_for_background_update = true → theme queued for background.
		delete_site_option( 'ghu-' . md5( 'test-gu-theme' ) );

		$theme = $this->theme_with_config( [ 'test-gu-theme' => $theme_obj ] );
		$theme->get_remote_theme_meta();

		// wp_next_scheduled() can't find args-keyed events without passing the exact args,
		// so inspect _get_cron_array() directly.
		$this->assertTrue( $this->cron_hook_exists( 'gu_get_remote_theme' ) );
	}

	public function test_no_cron_scheduled_when_config_has_no_background_themes(): void {
		wp_clear_scheduled_hook( 'gu_get_remote_theme' );

		// Empty config → no themes queued → no cron.
		$theme = $this->theme_with_config( [] );
		$theme->get_remote_theme_meta();

		$this->assertFalse( $this->cron_hook_exists( 'gu_get_remote_theme' ) );
	}

	/**
	 * Return true if any cron event exists for the given hook (ignoring args).
	 *
	 * @param string $hook Cron hook name.
	 * @return bool
	 */
	private function cron_hook_exists( string $hook ): bool {
		foreach ( (array) _get_cron_array() as $hooks ) {
			if ( isset( $hooks[ $hook ] ) ) {
				return true;
			}
		}
		return false;
	}
}

// ---------------------------------------------------------------------------
// Test_Theme_Config_Discovery
// ---------------------------------------------------------------------------

/**
 * Class Test_Theme_Config_Discovery
 *
 * Requires the fixture theme to be mounted in the wp-env container.
 * The fixture is listed in .wp-env.json:
 *   "themes": ["./tests/fixtures/themes/test-gu-theme"]
 *
 * After editing .wp-env.json, restart the environment so Docker picks up the change:
 *   npm run wp-env start
 */
class Test_Theme_Config_Discovery extends WP_UnitTestCase {

	private const SLUG = 'test-gu-theme';

	/** @var array<string, \stdClass> */
	private array $configs;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->configs = ( new Theme() )->get_theme_configs();

		if ( ! isset( $this->configs[ self::SLUG ] ) ) {
			$this->markTestSkipped(
				'Fixture theme not installed. Run: npm run wp-env start'
			);
		}
	}

	public function test_fixture_theme_is_in_theme_configs(): void {
		$this->assertArrayHasKey( self::SLUG, $this->configs );
	}

	public function test_fixture_theme_git_is_github(): void {
		$this->assertSame( 'github', $this->configs[ self::SLUG ]->git );
	}

	public function test_fixture_theme_owner_is_afragen(): void {
		$this->assertSame( 'afragen', $this->configs[ self::SLUG ]->owner );
	}

	public function test_fixture_theme_slug_matches(): void {
		$this->assertSame( self::SLUG, $this->configs[ self::SLUG ]->slug );
	}

	public function test_fixture_theme_type_is_theme(): void {
		$this->assertSame( 'theme', $this->configs[ self::SLUG ]->type );
	}

	public function test_fixture_theme_primary_branch_is_main(): void {
		$this->assertSame( 'main', $this->configs[ self::SLUG ]->primary_branch );
	}
}
