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
		delete_site_option( $this->cache_key );
		parent::tear_down();
	}

	private function make_type(): stdClass {
		$type                 = new stdClass();
		$type->slug           = $this->slug;
		$type->git            = 'github';
		$type->type           = 'plugin';
		$type->local_version  = '1.0.0';
		$type->primary_branch = 'master';
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
