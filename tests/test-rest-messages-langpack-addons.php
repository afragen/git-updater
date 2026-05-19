<?php
/**
 * Tests for REST_API, Messages, Language_Pack_API, and Add_Ons.
 *
 * REST_API:
 * - test()        — connection-check string
 * - get_namespace() — returns namespace array
 * - deprecated()  — returns deprecation-error array
 *
 * Messages:
 * - create_error_message() — page/nonce guard clause logic
 *
 * Language_Pack_API:
 * - get_language_pack() — cache-hit path sets $type->language_packs and returns true
 *
 * Add_Ons:
 * - plugins_api()       — passthrough for non-addon slugs; uses cached data for addon slugs
 * - add_settings_tabs() — registers gu_add_settings_tabs filter
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\REST\REST_API;
use Fragen\Git_Updater\Messages;
use Fragen\Git_Updater\API\Language_Pack_API;
use Fragen\Git_Updater\Add_Ons;
use Fragen\Git_Updater\Base;

// ---------------------------------------------------------------------------
// REST_API
// ---------------------------------------------------------------------------

/**
 * Class Test_REST_API
 */
class Test_REST_API extends WP_UnitTestCase {

	private REST_API $rest;

	public function set_up(): void {
		parent::set_up();
		$this->rest = new REST_API();
	}

	public function test_test_returns_connected_string(): void {
		$this->assertSame( 'Connected to Git Updater!', $this->rest->test() );
	}

	public function test_get_namespace_returns_array_with_namespace_key(): void {
		$result = $this->rest->get_namespace();
		$this->assertArrayHasKey( 'namespace', $result );
	}

	public function test_get_namespace_returns_correct_namespace_value(): void {
		$result = $this->rest->get_namespace();
		$this->assertSame( 'git-updater/v1', $result['namespace'] );
	}

	public function test_deprecated_returns_success_false(): void {
		$result = $this->rest->deprecated();
		$this->assertFalse( $result['success'] );
	}

	public function test_deprecated_error_message_mentions_old_namespace(): void {
		$result = $this->rest->deprecated();
		$this->assertStringContainsString( 'github-updater/v1', $result['error'] );
	}

	public function test_deprecated_error_message_mentions_current_namespace(): void {
		$result = $this->rest->deprecated();
		$this->assertStringContainsString( 'git-updater/v1', $result['error'] );
	}
}

// ---------------------------------------------------------------------------
// Messages
// ---------------------------------------------------------------------------

/**
 * Class Test_Messages
 */
class Test_Messages extends WP_UnitTestCase {

	private Messages $messages;

	public function set_up(): void {
		parent::set_up();
		$this->messages = new Messages();
	}

	public function tear_down(): void {
		global $pagenow;
		$pagenow = '';
		unset( $_GET['_wpnonce'], $_GET['page'] );
		set_current_screen( 'front' );
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'network_admin_notices' );
		Messages::$error_message = '';
		parent::tear_down();
	}

	public function test_create_error_message_returns_false_when_pagenow_is_empty(): void {
		global $pagenow;
		$pagenow = '';

		$this->assertFalse( $this->messages->create_error_message() );
	}

	public function test_create_error_message_returns_false_on_settings_page_without_nonce(): void {
		global $pagenow;
		$pagenow = 'options-general.php';

		$this->assertFalse( $this->messages->create_error_message() );
	}

	public function test_create_error_message_returns_true_on_update_core_page(): void {
		global $pagenow;
		$pagenow = 'update-core.php';

		// update-core.php is in $update_pages — nonce is not required to pass the guard.
		$this->assertTrue( $this->messages->create_error_message() );
	}

	public function test_create_error_message_returns_true_on_plugins_page(): void {
		global $pagenow;
		$pagenow = 'plugins.php';

		$this->assertTrue( $this->messages->create_error_message() );
	}

	public function test_create_error_message_returns_true_on_settings_page_with_valid_nonce_and_page(): void {
		global $pagenow;
		$pagenow             = 'options-general.php';
		$_GET['_wpnonce']    = wp_create_nonce( 'gu_settings' );
		$_GET['page']        = 'git-updater';

		$this->assertTrue( $this->messages->create_error_message() );
	}

	public function test_create_error_message_wp_error_registers_show_wp_error_action(): void {
		global $pagenow;
		$pagenow = 'update-core.php';
		set_current_screen( 'update-core' );

		$error  = new WP_Error( 'test_code', 'Something went wrong' );
		$result = $this->messages->create_error_message( $error );

		$this->assertTrue( $result );
		$this->assertSame( 'Something went wrong', Messages::$error_message );
		$this->assertNotFalse( has_action( 'admin_notices', [ $this->messages, 'show_wp_error' ] ) );
	}

	public function test_create_error_message_get_license_registers_action(): void {
		global $pagenow;
		$pagenow = 'update-core.php';
		set_current_screen( 'update-core' );

		$result = $this->messages->create_error_message( 'get_license' );

		$this->assertTrue( $result );
		$this->assertNotFalse( has_action( 'admin_notices', [ $this->messages, 'get_license' ] ) );
	}

	public function test_create_error_message_waiting_registers_action(): void {
		global $pagenow;
		$pagenow = 'update-core.php';
		set_current_screen( 'update-core' );

		$result = $this->messages->create_error_message( 'waiting' );

		$this->assertTrue( $result );
		$this->assertNotFalse( has_action( 'admin_notices', [ $this->messages, 'waiting' ] ) );
	}

	public function test_show_wp_error_outputs_error_div(): void {
		Messages::$error_message = 'Test error output';

		ob_start();
		$this->messages->show_wp_error();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-error', $output );
		$this->assertStringContainsString( 'Test error output', $output );
	}

	public function test_waiting_outputs_info_div(): void {
		ob_start();
		$this->messages->waiting();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-info', $output );
		$this->assertStringContainsString( 'WP-Cron', $output );
	}

	public function test_get_license_returns_early_when_user_is_paying(): void {
		$orig_fs          = $GLOBALS['gu_fs'] ?? null;
		$GLOBALS['gu_fs'] = new class {
			public function is_not_paying(): bool {
				return false;
			}
		};

		ob_start();
		$this->messages->get_license();
		$output = ob_get_clean();

		$GLOBALS['gu_fs'] = $orig_fs;

		$this->assertSame( '', $output );
	}

	public function test_get_license_outputs_html_when_not_paying_and_notice_active(): void {
		ob_start();
		$this->messages->get_license();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-info', $output );
		$this->assertStringContainsString( 'Purchase from Store', $output );
	}

	public function test_create_error_message_git_type_does_not_register_action(): void {
		global $pagenow;
		$pagenow = 'update-core.php';
		set_current_screen( 'update-core' );

		$result = $this->messages->create_error_message( 'git' );

		$this->assertTrue( $result );
		$this->assertFalse( has_action( 'admin_notices', [ $this->messages, 'waiting' ] ) );
	}
}

// ---------------------------------------------------------------------------
// Language_Pack_API
// ---------------------------------------------------------------------------

/**
 * Class Test_Language_Pack_API
 */
class Test_Language_Pack_API extends WP_UnitTestCase {

	private string $slug      = 'test-langpack-plugin';
	private string $cache_key;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->cache_key = 'ghu-' . md5( $this->slug );
	}

	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'gu_get_language_pack_json' );
		remove_all_filters( 'gu_post_process_language_pack_package' );
		delete_site_option( $this->cache_key );
		delete_site_option( 'ghu-' . md5( $this->slug . '_error' ) );
		parent::tear_down();
	}

	private function make_type(): stdClass {
		$type                 = new stdClass();
		$type->slug           = $this->slug;
		$type->git            = 'github';
		$type->type           = 'plugin';
		$type->owner          = 'test-owner';
		$type->branch         = 'master';
		$type->primary_branch = 'master';
		$type->enterprise     = false;
		$type->enterprise_api = null;
		$type->gist_id        = null;
		$type->local_version  = '1.0.0';
		return $type;
	}

	public function test_get_language_pack_returns_true_on_cache_hit(): void {
		$languages = (object) [ 'en_US' => (object) [ 'language' => 'en_US', 'package' => 'en_US.zip' ] ];
		update_site_option( $this->cache_key, [ 'languages' => $languages ] );

		$api    = new Language_Pack_API( $this->make_type() );
		$result = $api->get_language_pack( [ 'owner_repo' => 'owner/test-langpack-plugin', 'uri' => 'https://github.com/owner/test-langpack-plugin' ] );

		$this->assertTrue( $result );
	}

	public function test_get_language_pack_sets_language_packs_on_type_from_cache(): void {
		$languages = (object) [ 'en_US' => (object) [ 'language' => 'en_US', 'package' => 'en_US.zip' ] ];
		update_site_option( $this->cache_key, [ 'languages' => $languages ] );

		$type   = $this->make_type();
		$api    = new Language_Pack_API( $type );
		$api->get_language_pack( [ 'owner_repo' => 'owner/test-langpack-plugin', 'uri' => 'https://github.com/owner/test-langpack-plugin' ] );

		$this->assertEquals( $languages, $type->language_packs );
	}

	public function test_get_language_pack_does_not_set_language_packs_when_cache_has_empty_languages(): void {
		// Cache exists but 'languages' key is missing — should fall through to the API call path.
		// Seed with a cache that has no 'languages' key.
		update_site_option( $this->cache_key, [ 'timeout' => strtotime( '+12 hours' ) ] );

		$type = $this->make_type();
		// language_packs should NOT be set yet.
		$this->assertFalse( isset( $type->language_packs ) );
	}

	// -------------------------------------------------------------------------
	// HTTP-fetch path (cache miss → api() call)
	// -------------------------------------------------------------------------

	/**
	 * Build the mock HTTP response that api() would return for a GitHub
	 * contents call returning a base64-encoded language-pack.json.
	 *
	 * @param array<string, mixed> $locales Locale data keyed by locale string.
	 * @return array<string, mixed> WordPress HTTP response array.
	 */
	private function make_lang_pack_http_response( array $locales ): array {
		$lang_pack_json  = json_encode( (object) $locales );
		$github_api_body = json_encode(
			(object) [
				'content'  => base64_encode( $lang_pack_json ),
				'encoding' => 'base64',
			]
		);
		return [
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'body'     => $github_api_body,
			'headers'  => [],
			'cookies'  => [],
		];
	}

	private function intercept_http_with( array $response ): void {
		add_filter( 'pre_http_request', fn() => $response, 10, 3 );
	}

	public function test_get_language_pack_returns_false_when_api_fails(): void {
		$this->intercept_http_with( [
			'response' => [ 'code' => 404, 'message' => 'Not Found' ],
			'body'     => json_encode( [ 'message' => 'Not Found' ] ),
			'headers'  => [],
		] );

		$api    = new Language_Pack_API( $this->make_type() );
		$result = $api->get_language_pack( [
			'owner_repo' => 'owner/' . $this->slug,
			'uri'        => 'https://github.com/owner/' . $this->slug,
		] );

		$this->assertFalse( $result );
	}

	public function test_get_language_pack_returns_true_when_api_succeeds(): void {
		$this->intercept_http_with(
			$this->make_lang_pack_http_response( [
				'fr_FR' => [ 'language' => 'fr_FR', 'package' => '/locales/fr_FR.zip' ],
			] )
		);

		$api    = new Language_Pack_API( $this->make_type() );
		$result = $api->get_language_pack( [
			'owner_repo' => 'owner/' . $this->slug,
			'uri'        => 'https://github.com/owner/' . $this->slug,
		] );

		$this->assertTrue( $result );
	}

	public function test_get_language_pack_sets_language_packs_on_type_after_fetch(): void {
		$this->intercept_http_with(
			$this->make_lang_pack_http_response( [
				'fr_FR' => [ 'language' => 'fr_FR', 'package' => '/locales/fr_FR.zip' ],
			] )
		);

		$type = $this->make_type();
		$api  = new Language_Pack_API( $type );
		$api->get_language_pack( [
			'owner_repo' => 'owner/' . $this->slug,
			'uri'        => 'https://github.com/owner/' . $this->slug,
		] );

		$this->assertTrue( isset( $type->language_packs ) );
		$this->assertTrue( isset( $type->language_packs->fr_FR ) );
	}

	public function test_get_language_pack_writes_language_packs_to_cache(): void {
		$this->intercept_http_with(
			$this->make_lang_pack_http_response( [
				'fr_FR' => [ 'language' => 'fr_FR', 'package' => '/locales/fr_FR.zip' ],
			] )
		);

		$api = new Language_Pack_API( $this->make_type() );
		$api->get_language_pack( [
			'owner_repo' => 'owner/' . $this->slug,
			'uri'        => 'https://github.com/owner/' . $this->slug,
		] );

		$cache = get_site_option( $this->cache_key );
		$this->assertArrayHasKey( 'languages', $cache );
	}

	public function test_get_language_pack_constructs_package_url_from_uri_and_primary_branch(): void {
		$this->intercept_http_with(
			$this->make_lang_pack_http_response( [
				'fr_FR' => [ 'language' => 'fr_FR', 'package' => '/locales/fr_FR.zip' ],
			] )
		);

		$type = $this->make_type(); // primary_branch = 'master'
		$api  = new Language_Pack_API( $type );
		$api->get_language_pack( [
			'owner_repo' => 'owner/' . $this->slug,
			'uri'        => 'https://github.com/owner/' . $this->slug,
		] );

		$package_url = $type->language_packs->fr_FR->package;
		$this->assertStringContainsString( 'raw/refs/heads/master', $package_url );
		$this->assertStringContainsString( '/locales/fr_FR.zip', $package_url );
		$this->assertStringContainsString( 'https://github.com/owner/' . $this->slug, $package_url );
	}

	public function test_get_language_pack_sets_type_and_version_on_locale(): void {
		$this->intercept_http_with(
			$this->make_lang_pack_http_response( [
				'fr_FR' => [ 'language' => 'fr_FR', 'package' => '/locales/fr_FR.zip' ],
			] )
		);

		$type = $this->make_type(); // type='plugin', local_version='1.0.0'
		$api  = new Language_Pack_API( $type );
		$api->get_language_pack( [
			'owner_repo' => 'owner/' . $this->slug,
			'uri'        => 'https://github.com/owner/' . $this->slug,
		] );

		$this->assertSame( 'plugin', $type->language_packs->fr_FR->type );
		$this->assertSame( '1.0.0', $type->language_packs->fr_FR->version );
	}

	public function test_get_language_pack_gu_post_process_language_pack_package_filter_overrides_url(): void {
		$this->intercept_http_with(
			$this->make_lang_pack_http_response( [
				'fr_FR' => [ 'language' => 'fr_FR', 'package' => '/locales/fr_FR.zip' ],
			] )
		);
		add_filter(
			'gu_post_process_language_pack_package',
			fn() => 'https://custom.cdn.example.com/fr_FR.zip',
			10,
			4
		);

		$type = $this->make_type();
		$api  = new Language_Pack_API( $type );
		$api->get_language_pack( [
			'owner_repo' => 'owner/' . $this->slug,
			'uri'        => 'https://github.com/owner/' . $this->slug,
		] );

		$this->assertSame( 'https://custom.cdn.example.com/fr_FR.zip', $type->language_packs->fr_FR->package );
	}

	public function test_get_language_pack_uses_gu_get_language_pack_json_filter_for_non_github(): void {
		$fake_response = json_decode(
			json_encode( [ 'de_DE' => [ 'language' => 'de_DE', 'package' => '/locales/de_DE.zip' ] ] )
		);
		add_filter(
			'gu_get_language_pack_json',
			fn( $response ) => $fake_response,
			10,
			4
		);

		$type      = $this->make_type();
		$type->git = 'bitbucket';
		$api       = new Language_Pack_API( $type );
		$result    = $api->get_language_pack( [
			'owner_repo' => 'owner/' . $this->slug,
			'uri'        => 'https://bitbucket.org/owner/' . $this->slug,
		] );

		$this->assertTrue( $result );
		$this->assertTrue( isset( $type->language_packs->de_DE ) );
	}
}

// ---------------------------------------------------------------------------
// Add_Ons
// ---------------------------------------------------------------------------

/**
 * Class Test_Add_Ons
 */
class Test_Add_Ons extends WP_UnitTestCase {

	private Add_Ons $addons;
	private string  $addons_cache_key;

	public function set_up(): void {
		parent::set_up();
		$this->addons           = new Add_Ons();
		$this->addons_cache_key = 'ghu-' . md5( 'gu_addon_api_results' );
	}

	public function tear_down(): void {
		delete_site_option( $this->addons_cache_key );
		remove_all_filters( 'gu_add_settings_tabs' );
		remove_all_actions( 'gu_add_admin_page' );
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// plugins_api()
	// -------------------------------------------------------------------------

	public function test_plugins_api_returns_result_unchanged_for_non_addon_slug(): void {
		$result   = (object) [ 'name' => 'Some Plugin' ];
		$args     = (object) [ 'slug' => 'not-an-addon' ];

		$returned = $this->addons->plugins_api( $result, 'plugin_information', $args );

		$this->assertSame( $result, $returned );
	}

	public function test_plugins_api_returns_cached_addon_data_as_object(): void {
		$addon_data = [ 'git-updater-gist' => [ 'name' => 'Git Updater Gist', 'slug' => 'git-updater-gist', 'version' => '1.0.0' ] ];
		update_site_option(
			$this->addons_cache_key,
			[
				'gu_addon_api_results' => $addon_data,
				'timeout'              => strtotime( '+7 days' ),
			]
		);

		$args   = (object) [ 'slug' => 'git-updater-gist' ];
		$result = $this->addons->plugins_api( false, 'plugin_information', $args );

		$this->assertIsObject( $result );
		$this->assertSame( 'Git Updater Gist', $result->name );
	}

	// -------------------------------------------------------------------------
	// add_settings_tabs()
	// -------------------------------------------------------------------------

	public function test_add_settings_tabs_registers_git_updater_addons_tab(): void {
		$this->addons->add_settings_tabs();

		$tabs = apply_filters( 'gu_add_settings_tabs', [] );

		$this->assertArrayHasKey( 'git_updater_addons', $tabs );
	}

	public function test_add_settings_tabs_preserves_existing_tabs(): void {
		$this->addons->add_settings_tabs();

		$tabs = apply_filters( 'gu_add_settings_tabs', [ 'existing_tab' => 'Existing' ] );

		$this->assertArrayHasKey( 'existing_tab', $tabs );
		$this->assertArrayHasKey( 'git_updater_addons', $tabs );
	}
}
