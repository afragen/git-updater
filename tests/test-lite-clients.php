<?php
/**
 * Tests for Lite_Clients class.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\Lite_Clients;

/**
 * Class Test_Lite_Clients
 */
class Test_Lite_Clients extends WP_UnitTestCase {

	/**
	 * Instance of Lite_Clients.
	 *
	 * @var Lite_Clients
	 */
	private Lite_Clients $lite_clients;

	/**
	 * Set up test environment.
	 */
	public function set_up(): void {
		parent::set_up();
		$admin_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
		grant_super_admin( $admin_id );
		delete_site_option( 'git_updater_lite_client_keys' );
		delete_site_option( 'git_updater_additions' );
		$this->lite_clients = new Lite_Clients();

		// Keep tests order-independent: reset any repo config leaked by another test.
		foreach ( [ 'Plugin', 'Theme' ] as $type ) {
			$obj  = \Fragen\Singleton::get_instance( "Fragen\\Git_Updater\\{$type}", $this->lite_clients );
			$prop = ( new ReflectionClass( $obj ) )->getProperty( 'config' );
			PHP_VERSION_ID < 80100 && $prop->setAccessible( true );
			$prop->setValue( $obj, [] );
		}
	}

	public function tear_down(): void {
		delete_site_option( 'git_updater_lite_client_keys' );
		delete_site_option( 'git_updater_additions' );
		delete_site_transient( 'git_updater_lite_pending_' . md5( 'my-plugin' ) );
		remove_all_filters( 'gu_add_settings_tabs' );
		remove_all_filters( 'gu_save_redirect' );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Grab a registered closure for a hook and invoke it.
	 *
	 * @param string $hook Hook name.
	 * @return bool Whether a closure was invoked.
	 */
	private function invoke_first_closure( $hook ): bool {
		global $wp_filter;
		$callbacks = $wp_filter[ $hook ]->callbacks[10] ?? [];
		foreach ( $callbacks as $callback ) {
			if ( $callback['function'] instanceof \Closure ) {
				call_user_func( $callback['function'] );
				return true;
			}
		}
		return false;
	}

	/**
	 * Test load_hooks registers actions.
	 */
	public function test_load_hooks_registers_actions(): void {
		$this->lite_clients->load_hooks();

		$this->assertNotFalse( has_action( 'gu_update_settings' ) );
		$this->assertNotFalse( has_action( 'init' ) );
		$this->assertNotFalse( has_action( 'admin_init' ) );
		$this->assertNotFalse( has_action( 'gu_add_admin_page' ) );
	}

	/**
	 * Test init/admin_init closures fire.
	 */
	public function test_load_hooks_closures_fire(): void {
		$this->lite_clients->load_hooks();

		$this->assertTrue( $this->invoke_first_closure( 'init' ) );
		$this->assertTrue( $this->invoke_first_closure( 'admin_init' ) );
	}

	/**
	 * Test gu_add_admin_page closure dispatches to add_admin_page.
	 */
	public function test_gu_add_admin_page_closure_dispatches(): void {
		$this->lite_clients->load_hooks();

		global $wp_filter;
		$callbacks = $wp_filter['gu_add_admin_page']->callbacks[10] ?? [];
		$ran       = false;
		foreach ( $callbacks as $callback ) {
			if ( $callback['function'] instanceof \Closure ) {
				$callback['function']( 'wrong-tab', 'edit.php' );
				$callback['function']( 'git_updater_lite_clients', 'edit.php' );
				$ran = true;
				break;
			}
		}
		$this->assertTrue( $ran );
	}

	/**
	 * Test gu_update_settings closure dispatches to save_settings.
	 */
	public function test_gu_update_settings_closure_dispatches(): void {
		$this->lite_clients->load_hooks();

		global $wp_filter;
		$callbacks = $wp_filter['gu_update_settings']->callbacks[10] ?? [];
		$ran       = false;
		foreach ( $callbacks as $callback ) {
			if ( $callback['function'] instanceof \Closure ) {
				$callback['function']( [ 'option_page' => 'git_updater_lite_clients' ] );
				$ran = true;
				break;
			}
		}
		$this->assertTrue( $ran );
	}

	/**
	 * Test add_settings_tabs registers the Lite Clients tab.
	 */
	public function test_add_settings_tabs_registers_tab(): void {
		$this->lite_clients->add_settings_tabs();

		$this->assertNotFalse( has_filter( 'gu_add_settings_tabs' ) );
		$tabs = apply_filters( 'gu_add_settings_tabs', [] );
		$this->assertArrayHasKey( 'git_updater_lite_clients', $tabs );
	}

	/**
	 * Test page_init registers the settings group.
	 */
	public function test_page_init_registers_settings(): void {
		$this->lite_clients->page_init();

		$this->assertIsArray( get_registered_settings() );
	}

	/**
	 * Test is_approved_client_key with an approved grant.
	 */
	public function test_is_approved_client_key_true_for_approved_grant(): void {
		$hash = hash( 'sha256', 'client-key' );
		update_site_option(
			'git_updater_lite_client_keys',
			[ 'my-plugin' => [ $hash => [ 'status' => 'approved', 'label' => 'a.example.com' ] ] ]
		);

		$this->assertTrue( Lite_Clients::is_approved_client_key( 'my-plugin', $hash ) );
	}

	/**
	 * Test is_approved_client_key false for a pending/absent grant.
	 */
	public function test_is_approved_client_key_false_for_pending(): void {
		$hash = hash( 'sha256', 'client-key' );
		update_site_option(
			'git_updater_lite_client_keys',
			[ 'my-plugin' => [ $hash => [ 'status' => 'pending' ] ] ]
		);

		$this->assertFalse( Lite_Clients::is_approved_client_key( 'my-plugin', $hash ) );
	}

	/**
	 * Test is_approved_client_key honors the filter override.
	 */
	public function test_is_approved_client_key_filter_override(): void {
		$hash = hash( 'sha256', 'override-key' );
		add_filter(
			'git_updater_lite_client_key',
			static function () {
				return 'override-key';
			}
		);

		$this->assertTrue( Lite_Clients::is_approved_client_key( 'my-plugin', $hash ) );

		remove_all_filters( 'git_updater_lite_client_key' );
	}

	/**
	 * Test record_pending_client records, refreshes, and caps.
	 */
	public function test_record_pending_client_records_refreshes_and_caps(): void {
		$slug = 'my-plugin';

		Lite_Clients::record_pending_client( $slug, hash( 'sha256', 'k1' ), 'a.example.com' );
		$pending = get_site_transient( 'git_updater_lite_pending_' . md5( $slug ) );
		$this->assertArrayHasKey( hash( 'sha256', 'k1' ), $pending );
		$this->assertSame( 'a.example.com', $pending[ hash( 'sha256', 'k1' ) ]['label'] );

		// Refresh existing.
		Lite_Clients::record_pending_client( $slug, hash( 'sha256', 'k1' ), 'a.example.com' );
		$pending = get_site_transient( 'git_updater_lite_pending_' . md5( $slug ) );
		$this->assertCount( 1, $pending );

		// Fill past the cap to exercise eviction.
		for ( $i = 0; $i < 30; $i++ ) {
			Lite_Clients::record_pending_client( $slug, hash( 'sha256', 'bulk-' . $i ), 'b.example.com' );
		}
		$pending = get_site_transient( 'git_updater_lite_pending_' . md5( $slug ) );
		$this->assertLessThanOrEqual( 25, count( $pending ) );

		delete_site_transient( 'git_updater_lite_pending_' . md5( $slug ) );
	}

	/**
	 * Test record_pending_client ignores empty slug/hash.
	 */
	public function test_record_pending_client_ignores_empty(): void {
		Lite_Clients::record_pending_client( '', 'abc', 'x' );
		Lite_Clients::record_pending_client( 'slug', '', 'x' );
		$this->assertFalse( get_site_transient( 'git_updater_lite_pending_' . md5( 'slug' ) ) );
	}

	/**
	 * Seed a pending grant and return its hash.
	 *
	 * @param string $slug  Slug.
	 * @param string $key   Raw key.
	 * @param string $label Label.
	 * @return string Hash.
	 */
	private function seed_pending( $slug, $key, $label ): string {
		$hash = hash( 'sha256', $key );
		set_site_transient(
			'git_updater_lite_pending_' . md5( $slug ),
			[ $hash => [ 'label' => $label, 'seen' => time() ] ],
			604800
		);
		return $hash;
	}

	/**
	 * Test save_settings skips on bad nonce.
	 */
	public function test_save_settings_skips_on_bad_nonce(): void {
		$_POST['_wpnonce']    = 'invalid';
		$_POST['option_page'] = 'git_updater_lite_clients';
		$_POST['gu_lite_action'] = 'approve';
		$_POST['gu_lite_slug']   = 'my-plugin';
		$_POST['gu_lite_hash']   = hash( 'sha256', 'k' );

		$this->lite_clients->save_settings( [ 'option_page' => 'git_updater_lite_clients' ] );
		$this->assertEmpty( get_site_option( 'git_updater_lite_client_keys' ) );
		unset( $_POST['_wpnonce'], $_POST['option_page'], $_POST['gu_lite_action'], $_POST['gu_lite_slug'], $_POST['gu_lite_hash'] );
	}

	/**
	 * Test save_settings skips on wrong option_page.
	 */
	public function test_save_settings_skips_on_wrong_option_page(): void {
		$_POST['_wpnonce']    = wp_create_nonce( 'git_updater_lite_clients-options' );
		$_POST['option_page'] = 'wrong';
		$this->lite_clients->save_settings( [ 'option_page' => 'wrong' ] );
		$this->assertEmpty( get_site_option( 'git_updater_lite_client_keys' ) );
		unset( $_POST['_wpnonce'], $_POST['option_page'] );
	}

	/**
	 * Test save_settings approve moves pending to approved and registers redirect.
	 */
	public function test_save_settings_approve(): void {
		$hash                 = $this->seed_pending( 'my-plugin', 'k', 'a.example.com' );
		$_POST['_wpnonce']    = wp_create_nonce( 'git_updater_lite_clients-options' );
		$_POST['option_page'] = 'git_updater_lite_clients';
		$_POST['gu_lite_action'] = 'approve';
		$_POST['gu_lite_slug']   = 'my-plugin';
		$_POST['gu_lite_hash']   = $hash;

		$this->lite_clients->save_settings( [ 'option_page' => 'git_updater_lite_clients' ] );

		$grants = get_site_option( 'git_updater_lite_client_keys' );
		$this->assertSame( 'approved', $grants['my-plugin'][ $hash ]['status'] );
		$this->assertSame( 'a.example.com', $grants['my-plugin'][ $hash ]['label'] );
		$this->assertNotFalse( has_filter( 'gu_save_redirect' ) );
		$result = apply_filters( 'gu_save_redirect', [] );
		$this->assertContains( 'git_updater_lite_clients', $result );

		unset( $_POST['_wpnonce'], $_POST['option_page'], $_POST['gu_lite_action'], $_POST['gu_lite_slug'], $_POST['gu_lite_hash'] );
	}

	/**
	 * Test save_settings reject removes the pending grant.
	 */
	public function test_save_settings_reject(): void {
		$hash                 = $this->seed_pending( 'my-plugin', 'k', 'a.example.com' );
		$_POST['_wpnonce']    = wp_create_nonce( 'git_updater_lite_clients-options' );
		$_POST['option_page'] = 'git_updater_lite_clients';
		$_POST['gu_lite_action'] = 'reject';
		$_POST['gu_lite_slug']   = 'my-plugin';
		$_POST['gu_lite_hash']   = $hash;

		$this->lite_clients->save_settings( [ 'option_page' => 'git_updater_lite_clients' ] );

		$pending = get_site_transient( 'git_updater_lite_pending_' . md5( 'my-plugin' ) );
		$this->assertArrayNotHasKey( $hash, $pending );
		unset( $_POST['_wpnonce'], $_POST['option_page'], $_POST['gu_lite_action'], $_POST['gu_lite_slug'], $_POST['gu_lite_hash'] );
	}

	/**
	 * Test save_settings revoke removes the approved grant (and empty slug).
	 */
	public function test_save_settings_revoke(): void {
		$hash = hash( 'sha256', 'k' );
		update_site_option(
			'git_updater_lite_client_keys',
			[ 'my-plugin' => [ $hash => [ 'status' => 'approved', 'label' => 'a' ] ] ]
		);
		$_POST['_wpnonce']    = wp_create_nonce( 'git_updater_lite_clients-options' );
		$_POST['option_page'] = 'git_updater_lite_clients';
		$_POST['gu_lite_action'] = 'revoke';
		$_POST['gu_lite_slug']   = 'my-plugin';
		$_POST['gu_lite_hash']   = $hash;

		$this->lite_clients->save_settings( [ 'option_page' => 'git_updater_lite_clients' ] );

		$grants = get_site_option( 'git_updater_lite_client_keys' );
		$this->assertArrayNotHasKey( 'my-plugin', $grants );
		unset( $_POST['_wpnonce'], $_POST['option_page'], $_POST['gu_lite_action'], $_POST['gu_lite_slug'], $_POST['gu_lite_hash'] );
	}

	/**
	 * Test save_settings ignores an unknown action.
	 */
	public function test_save_settings_ignores_unknown_action(): void {
		$_POST['_wpnonce']    = wp_create_nonce( 'git_updater_lite_clients-options' );
		$_POST['option_page'] = 'git_updater_lite_clients';
		$_POST['gu_lite_action'] = 'nonsense';
		$_POST['gu_lite_slug']   = 'my-plugin';
		$_POST['gu_lite_hash']   = hash( 'sha256', 'k' );

		$this->lite_clients->save_settings( [ 'option_page' => 'git_updater_lite_clients' ] );
		$this->assertEmpty( get_site_option( 'git_updater_lite_client_keys' ) );
		unset( $_POST['_wpnonce'], $_POST['option_page'], $_POST['gu_lite_action'], $_POST['gu_lite_slug'], $_POST['gu_lite_hash'] );
	}

	/**
	 * Test get_flagged_slugs lists only private packages (retaining private
	 * Additions entries), and never lists known-public packages.
	 */
	public function test_get_flagged_slugs_sources(): void {
		update_site_option(
			'git_updater_additions',
			[
				[
					'ID'              => '123',
					'type'            => 'github_plugin',
					'slug'            => 'private-add/private-add.php',
					'uri'             => 'https://github.com/test/private-add',
					'uses_lite'       => true,
					'private_package' => false,
				],
				[
					'ID'              => '456',
					'type'            => 'github_plugin',
					'slug'            => 'public-add/public-add.php',
					'uri'             => 'https://github.com/test/public-add',
					'uses_lite'       => true,
					'private_package' => false,
				],
				[
					'ID'              => '789',
					'type'            => 'github_theme',
					'slug'            => 'unknown-add',
					'uri'             => 'https://github.com/test/unknown-add',
					'uses_lite'       => true,
					'private_package' => false,
				],
			]
		);

		$hash = hash( 'sha256', 'k' );
		update_site_option(
			'git_updater_lite_client_keys',
			[
				'existing-slug' => [ $hash => [ 'status' => 'approved' ] ],
				'public-grant'  => [ $hash => [ 'status' => 'approved' ] ],
			]
		);

		$repo = static function ( $slug, $is_private, $update_uri = '' ) {
			$obj                    = new \stdClass();
			$obj->slug              = $slug;
			$obj->is_private        = $is_private;
			$obj->update_uri        = $update_uri;
			$obj->private_package   = false;
			return $obj;
		};

		$mock_plugin = \Fragen\Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $this->lite_clients );
		$reflection  = new ReflectionClass( $mock_plugin );
		$config_prop = $reflection->getProperty( 'config' );
		PHP_VERSION_ID < 80100 && $config_prop->setAccessible( true );
		$config_prop->setValue(
			$mock_plugin,
			[
				'private-with-uri' => $repo( 'private-with-uri', true, 'https://github.com/test/private-with-uri' ),
				'private-add'      => $repo( 'private-add', true ),
				'public-add'       => $repo( 'public-add', false ),
				'public-grant'     => $repo( 'public-grant', false ),
			]
		);

		$method = new ReflectionMethod( $this->lite_clients, 'get_flagged_slugs' );
		PHP_VERSION_ID < 80100 && $method->setAccessible( true );
		$flagged = $method->invoke( $this->lite_clients );

		// Private packages are listed, whether auto-detected or added via Additions.
		$this->assertContains( 'private-with-uri', $flagged );
		$this->assertContains( 'private-add', $flagged );
		// Unknown privacy is retained (never dropped as if public).
		$this->assertContains( 'unknown-add', $flagged );
		// An existing grant stays revocable.
		$this->assertContains( 'existing-slug', $flagged );
		// Known-public packages never need a key.
		$this->assertNotContains( 'public-add', $flagged );
		$this->assertNotContains( 'public-grant', $flagged );

		$config_prop->setValue( $mock_plugin, [] );
	}

	/**
	 * Test is_flagged_for_warning true and false.
	 */
	public function test_is_flagged_for_warning(): void {
		$private = new \stdClass();
		$private->slug = 'private-plugin';
		$private->is_private = true;

		$public = new \stdClass();
		$public->slug = 'public-plugin';
		$public->is_private = false;

		$mock_plugin = \Fragen\Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $this->lite_clients );
		$reflection  = new ReflectionClass( $mock_plugin );
		$config_prop = $reflection->getProperty( 'config' );
		PHP_VERSION_ID < 80100 && $config_prop->setAccessible( true );
		$config_prop->setValue( $mock_plugin, [ 'private-plugin' => $private, 'public-plugin' => $public ] );

		$method = new ReflectionMethod( $this->lite_clients, 'is_flagged_for_warning' );
		PHP_VERSION_ID < 80100 && $method->setAccessible( true );

		$this->assertTrue( $method->invoke( $this->lite_clients, 'private-plugin' ) );
		$this->assertFalse( $method->invoke( $this->lite_clients, 'public-plugin' ) );

		$config_prop->setValue( $mock_plugin, [] );
	}

	/**
	 * Test add_admin_page renders for the correct tab and nothing otherwise.
	 */
	public function test_add_admin_page(): void {
		$hash = hash( 'sha256', 'k' );
		update_site_option(
			'git_updater_lite_client_keys',
			[
				'my-plugin' => [
					$hash => [ 'status' => 'approved', 'label' => 'a.example.com', 'created' => time(), 'seen' => time() ],
				],
			]
		);
		$this->seed_pending( 'my-plugin', 'pendingkey', 'p.example.com' );

		ob_start();
		$this->lite_clients->add_admin_page( 'git_updater_lite_clients', 'edit.php' );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'Lite Clients', $output );
		$this->assertStringContainsString( '<form', $output );
		$this->assertStringContainsString( 'a.example.com', $output );

		ob_start();
		$this->lite_clients->add_admin_page( 'wrong-tab', 'edit.php' );
		$this->assertEmpty( ob_get_clean() );
	}

	/**
	 * Test add_admin_page renders the empty state.
	 */
	public function test_add_admin_page_empty_state(): void {
		ob_start();
		$this->lite_clients->add_admin_page( 'git_updater_lite_clients', 'edit.php' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'No Lite-distributed private packages', $output );
	}

	/**
	 * Test add_admin_page renders the private-repo warning.
	 */
	public function test_add_admin_page_renders_private_warning(): void {
		$hash = hash( 'sha256', 'k' );
		update_site_option(
			'git_updater_lite_client_keys',
			[ 'warn-slug' => [ $hash => [ 'status' => 'approved', 'label' => 'a', 'created' => time(), 'seen' => time() ] ] ]
		);

		$mock_repo = new \stdClass();
		$mock_repo->slug = 'warn-slug';
		$mock_repo->is_private = true;
		$mock_plugin = \Fragen\Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $this->lite_clients );
		$reflection  = new ReflectionClass( $mock_plugin );
		$config_prop = $reflection->getProperty( 'config' );
		PHP_VERSION_ID < 80100 && $config_prop->setAccessible( true );
		$config_prop->setValue( $mock_plugin, [ 'warn-slug' => $mock_repo ] );

		ob_start();
		$this->lite_clients->add_admin_page( 'git_updater_lite_clients', 'edit.php' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'private repository', $output );

		$config_prop->setValue( $mock_plugin, [] );
	}

	/**
	 * Test add_admin_page renders empty approved/pending rows.
	 */
	public function test_add_admin_page_empty_rows(): void {
		$hash = hash( 'sha256', 'k' );
		update_site_option(
			'git_updater_lite_client_keys',
			[ 'my-plugin' => [] ]
		);

		ob_start();
		$this->lite_clients->add_admin_page( 'git_updater_lite_clients', 'edit.php' );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'None.', $output );
	}
}
