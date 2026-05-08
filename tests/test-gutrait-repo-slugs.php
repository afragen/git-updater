<?php
/**
 * Complete coverage for GU_Trait::get_repo_slugs.
 *
 * Covers all branches of the method:
 * - C1: exact $repo->slug match (Plugin Singleton)
 * - C2: dirname($repo->file) match (synthetic config with slug ≠ dirname)
 * - C1 via Theme: Theme Singleton as upgrader_object
 * - A1: AJAX install path sets $arr['slug'] = $slug directly
 * - A2: AJAX fires but action has no 'install' — falls through to config loop
 * - C3: no match (covered in test-gutrait-complete.php)
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\API\GitHub_API;
use Fragen\Git_Updater\Base;
use Fragen\Singleton;

class Test_GUTrait_Repo_Slugs extends WP_UnitTestCase {

	/** @var GitHub_API */
	private GitHub_API $api;

	/** @var stdClass */
	private stdClass $type;

	/** @var \Fragen\Git_Updater\Plugin */
	private $plugin_obj;

	/** @var \Fragen\Git_Updater\Theme */
	private $theme_obj;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->type       = $this->make_type();
		$this->api        = new GitHub_API( $this->type );
		$this->plugin_obj = Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $this->api );
		$this->theme_obj  = Singleton::get_instance( 'Fragen\Git_Updater\Theme', $this->api );
	}

	public function tear_down(): void {
		remove_all_filters( 'wp_doing_ajax' );
		unset( $_POST['action'], $_POST['git_updater_repo'], $_REQUEST['_ajax_nonce'] );
		parent::tear_down();
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

	private function invoke_get_repo_slugs( string $slug, $upgrader_object ): array {
		$rm = $this->api->get_reflection_method( $this->api, 'get_repo_slugs' );
		return $rm->invoke( $this->api, $slug, $upgrader_object );
	}

	// -------------------------------------------------------------------------
	// C1 — exact slug match via Plugin Singleton
	// -------------------------------------------------------------------------

	public function test_get_repo_slugs_matches_by_repo_slug(): void {
		// Inject a synthetic config so the test is independent of whether the
		// fixture plugin is installed (CI runs without wp-env fixture mounts).
		$ref      = new ReflectionProperty( get_class( $this->plugin_obj ), 'config' );
		$ref->setAccessible( true );
		$original = $ref->getValue( $this->plugin_obj );
		$ref->setValue(
			$this->plugin_obj,
			[
				'test-gu-plugin' => (object) [
					'slug' => 'test-gu-plugin',
					'file' => 'test-gu-plugin/test-gu-plugin.php',
				],
			]
		);
		try {
			$result = $this->invoke_get_repo_slugs( 'test-gu-plugin', $this->plugin_obj );
		} finally {
			$ref->setValue( $this->plugin_obj, $original );
		}
		$this->assertSame( [ 'slug' => 'test-gu-plugin' ], $result );
	}

	// -------------------------------------------------------------------------
	// C2 — dirname($repo->file) match when slug ≠ directory name
	// -------------------------------------------------------------------------

	public function test_get_repo_slugs_matches_by_dirname_of_file(): void {
		// Simulate a plugin installed in a 'my-plugin-master/' directory
		// where the actual repo slug is 'my-plugin'.
		$ref      = new ReflectionProperty( get_class( $this->plugin_obj ), 'config' );
		$ref->setAccessible( true );
		$original = $ref->getValue( $this->plugin_obj );
		$ref->setValue(
			$this->plugin_obj,
			[
				'my-plugin' => (object) [
					'slug' => 'my-plugin',
					'file' => 'my-plugin-master/my-plugin.php',
				],
			]
		);
		try {
			$result = $this->invoke_get_repo_slugs( 'my-plugin-master', $this->plugin_obj );
		} finally {
			$ref->setValue( $this->plugin_obj, $original );
		}
		// dirname('my-plugin-master/my-plugin.php') === 'my-plugin-master' matches;
		// the returned slug is the repo slug, not the directory name.
		$this->assertSame( [ 'slug' => 'my-plugin' ], $result );
		$this->assertSame( 'my-plugin', $result['slug'] );
		$this->assertNotSame( 'my-plugin-master', $result['slug'] );
	}

	// -------------------------------------------------------------------------
	// C1 via Theme — declared private $config, different upgrader_object type
	// -------------------------------------------------------------------------

	public function test_get_repo_slugs_matches_by_slug_with_theme_upgrader_object(): void {
		// Inject a minimal theme config so the test is independent of whether
		// the fixture theme is discovered by get_theme_meta() in the test env.
		$ref      = new ReflectionProperty( get_class( $this->theme_obj ), 'config' );
		$ref->setAccessible( true );
		$original = $ref->getValue( $this->theme_obj );
		$ref->setValue(
			$this->theme_obj,
			[
				'test-gu-theme' => (object) [
					'slug' => 'test-gu-theme',
					'file' => 'test-gu-theme/style.css',
				],
			]
		);
		try {
			$result = $this->invoke_get_repo_slugs( 'test-gu-theme', $this->theme_obj );
		} finally {
			$ref->setValue( $this->theme_obj, $original );
		}
		$this->assertSame( [ 'slug' => 'test-gu-theme' ], $result );
	}

	// -------------------------------------------------------------------------
	// A1 — AJAX + action contains 'install' sets $arr['slug'] = $slug directly
	// -------------------------------------------------------------------------

	public function test_get_repo_slugs_ajax_install_action_sets_slug_directly(): void {
		add_filter( 'wp_doing_ajax', '__return_true' );
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'updates' );
		$_POST['action']         = 'install-plugin';
		unset( $_POST['git_updater_repo'] );

		// 'ajax-install-slug' is not in Plugin config, so the config loop
		// does not overwrite the value set by the AJAX block.
		$result = $this->invoke_get_repo_slugs( 'ajax-install-slug', $this->plugin_obj );

		$this->assertSame( 'ajax-install-slug', $result['slug'] );
	}

	// -------------------------------------------------------------------------
	// A2 — AJAX fires but action has no 'install'; falls through to config loop
	// -------------------------------------------------------------------------

	public function test_get_repo_slugs_ajax_non_install_action_falls_through_to_config_loop(): void {
		add_filter( 'wp_doing_ajax', '__return_true' );
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'updates' );
		$_POST['action']         = 'update-plugin'; // no 'install' substring
		unset( $_POST['git_updater_repo'] );

		// Inject synthetic config so the config-loop C1 match works on CI
		// (where the fixture plugin is not installed).
		$ref      = new ReflectionProperty( get_class( $this->plugin_obj ), 'config' );
		$ref->setAccessible( true );
		$original = $ref->getValue( $this->plugin_obj );
		$ref->setValue(
			$this->plugin_obj,
			[
				'test-gu-plugin' => (object) [
					'slug' => 'test-gu-plugin',
					'file' => 'test-gu-plugin/test-gu-plugin.php',
				],
			]
		);
		try {
			// AJAX block fires but doesn't set $arr['slug']. Config loop then runs
			// and finds the plugin slug via C1.
			$result = $this->invoke_get_repo_slugs( 'test-gu-plugin', $this->plugin_obj );
		} finally {
			$ref->setValue( $this->plugin_obj, $original );
		}

		$this->assertSame( [ 'slug' => 'test-gu-plugin' ], $result );
	}

	// -------------------------------------------------------------------------
	// null $upgrader_object — defaults to $this (Plugin), line 671
	// -------------------------------------------------------------------------

	public function test_get_repo_slugs_null_upgrader_object_defaults_to_self_plugin(): void {
		// Invoke on $this->plugin_obj so $this inside get_repo_slugs is Plugin.
		// With $upgrader_object = null, line 671 executes: $upgrader_object = $this.
		// get_class_vars('Plugin', 'config') resolves to Plugin's $config.
		// Searching for a nonexistent slug returns an empty array.
		$rm     = $this->api->get_reflection_method( $this->plugin_obj, 'get_repo_slugs' );
		$result = $rm->invoke( $this->plugin_obj, 'nonexistent-slug-xyz-abc', null );
		$this->assertIsArray( $result );
	}
}
