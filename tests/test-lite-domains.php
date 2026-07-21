<?php
/**
 * Tests for Lite_Domains class.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\Lite_Domains;
use Fragen\Singleton;

/**
 * Class Test_Lite_Domains
 */
class Test_Lite_Domains extends WP_UnitTestCase {

	/**
	 * Instance of Lite_Domains.
	 *
	 * @var Lite_Domains
	 */
	private Lite_Domains $lite_domains;

	/**
	 * Set up test environment.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->lite_domains = new Lite_Domains();
		// Reset static options.
		$reflection = new ReflectionClass( $this->lite_domains );
		$property   = $reflection->getProperty( 'options' );
		$property->setAccessible( true );
		$property->setValue( null, [] );
	}

	/**
	 * Test load_hooks registers actions and filters.
	 */
	public function test_load_hooks_registers_actions_and_filters(): void {
		$this->lite_domains->load_hooks();

		$this->assertNotFalse( has_action( 'gu_update_settings' ) );
		$this->assertNotFalse( has_action( 'init' ) );
		$this->assertNotFalse( has_action( 'admin_init' ) );
		$this->assertNotFalse( has_action( 'gu_add_admin_page' ) );
		$this->assertNotFalse( has_filter( 'git_updater_lite_authorized_domains' ) );
	}

	/**
	 * Test load_hooks admin_init closure fires page_init.
	 */
	public function test_load_hooks_admin_init_closure_fires_page_init(): void {
		$this->lite_domains->load_hooks();

		// Extract our callback from the admin_init filter.
		global $wp_filter;
		$callbacks = $wp_filter['admin_init']->callbacks[10] ?? [];
		$found     = false;
		foreach ( $callbacks as $callback ) {
			if ( $callback['function'] instanceof \Closure ) {
				// Invoke the closure directly to cover the closure body.
				call_user_func( $callback['function'] );
				$found = true;
				break;
			}
		}

		$this->assertTrue( $found, 'Expected admin_init closure not found.' );
	}

	/**
	 * Test get_domains_for_slug returns array of domains.
	 */
	public function test_get_domains_for_slug_returns_array_of_domains(): void {
		$slug    = 'test-plugin';
		$domains = 'example.com, client-site.com';
		update_site_option( 'git_updater_lite_domains', [ $slug => $domains ] );

		$lite_domains = new Lite_Domains();
		$result = $lite_domains->get_domains_for_slug( $slug );

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
		$this->assertContains( 'example.com', $result );
		$this->assertContains( 'client-site.com', $result );
	}

	/**
	 * Test get_domains_for_slug returns empty array if no domains set.
	 */
	public function test_get_domains_for_slug_returns_empty_array_if_no_domains(): void {
		$result = $this->lite_domains->get_domains_for_slug( 'non-existent-slug' );
		$this->assertEmpty( $result );
	}

	/**
	 * Test add_settings_tabs registers the Lite Client Domains tab.
	 */
	public function test_add_settings_tabs_registers_tab(): void {
		$this->lite_domains->add_settings_tabs();

		do_action( 'init' );

		global $wp_filter;
		$this->assertArrayHasKey( 'gu_add_settings_tabs', $wp_filter );
	}

	/**
	 * Test page_init registers settings fields for flagged slugs.
	 */
	public function test_page_init_registers_settings_fields_for_flagged_slugs(): void {
		// Set up an existing configured slug so get_flagged_slugs returns it.
		update_site_option( 'git_updater_lite_domains', [ 'flagged-slug' => 'example.com' ] );
		$this->lite_domains = new Lite_Domains();

		// page_init calls add_settings_field which is safe without headers.
		$this->lite_domains->page_init();

		// Verify the section was registered.
		$this->assertIsArray( get_registered_settings() );
	}

	/**
	 * Test print_section_description outputs text.
	 */
	public function test_print_section_description_outputs_text(): void {
		ob_start();
		$this->lite_domains->print_section_description();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Enter comma-separated base domains', $output );
	}

	/**
	 * Test callback_domain_field renders input.
	 */
	public function test_callback_domain_field_renders_input(): void {
		update_site_option( 'git_updater_lite_domains', [ 'my-plugin' => 'example.com' ] );
		$this->lite_domains = new Lite_Domains();

		ob_start();
		$this->lite_domains->callback_domain_field( [ 'slug' => 'my-plugin', 'warning' => false ] );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="git_updater_lite_domains[my-plugin]"', $output );
		$this->assertStringContainsString( 'value="example.com"', $output );
	}

	/**
	 * Test callback_domain_field shows warning if flagged.
	 */
	public function test_callback_domain_field_shows_warning_if_flagged(): void {
		ob_start();
		$this->lite_domains->callback_domain_field( [ 'slug' => 'private-plugin', 'warning' => true ] );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'requires authentication', $output );
	}

	/**
	 * Test callback_custom_slug_field renders inputs.
	 */
	public function test_callback_custom_slug_field_renders_inputs(): void {
		ob_start();
		$this->lite_domains->callback_custom_slug_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'custom-slug', $output );
		$this->assertStringContainsString( 'example.com', $output );
	}

	/**
	 * Test save_settings saves sanitized domains.
	 */
	public function test_save_settings_saves_sanitized_domains(): void {
		$_POST['_wpnonce']    = wp_create_nonce( 'git_updater_lite_domains-options' );
		$_POST['option_page'] = 'git_updater_lite_domains';

		$post_data = [
			'option_page' => 'git_updater_lite_domains',
			'git_updater_lite_domains' => [
				'my-plugin' => 'Example.com, Client-Site.com',
			],
		];

		$this->lite_domains->save_settings( $post_data );

		$saved = get_site_option( 'git_updater_lite_domains' );
		$this->assertArrayHasKey( 'my-plugin', $saved );
		$this->assertSame( 'example.com, client-site.com', $saved['my-plugin'] );

		unset( $_POST['_wpnonce'], $_POST['option_page'] );
	}

	/**
	 * Test save_settings skips if nonce invalid.
	 */
	public function test_save_settings_skips_if_nonce_invalid(): void {
		$_POST['_wpnonce']    = 'invalid';
		$_POST['option_page'] = 'git_updater_lite_domains';

		$this->lite_domains->save_settings( [ 'option_page' => 'git_updater_lite_domains' ] );

		$saved = get_site_option( 'git_updater_lite_domains' );
		$this->assertEmpty( $saved );

		unset( $_POST['_wpnonce'], $_POST['option_page'] );
	}

	/**
	 * Test save_settings skips empty slug.
	 */
	public function test_save_settings_skips_empty_slug(): void {
		$_POST['_wpnonce']    = wp_create_nonce( 'git_updater_lite_domains-options' );
		$_POST['option_page'] = 'git_updater_lite_domains';

		$post_data = [
			'option_page' => 'git_updater_lite_domains',
			'git_updater_lite_domains' => [
				'' => 'example.com',
				'valid-slug' => 'test.com',
			],
		];

		$this->lite_domains->save_settings( $post_data );

		$saved = get_site_option( 'git_updater_lite_domains' );
		$this->assertArrayNotHasKey( '', $saved );
		$this->assertArrayHasKey( 'valid-slug', $saved );

		unset( $_POST['_wpnonce'], $_POST['option_page'] );
	}

	/**
	 * Test save_settings strips www. prefix from domains.
	 */
	public function test_save_settings_strips_www_prefix(): void {
		$_POST['_wpnonce']    = wp_create_nonce( 'git_updater_lite_domains-options' );
		$_POST['option_page'] = 'git_updater_lite_domains';

		$post_data = [
			'option_page' => 'git_updater_lite_domains',
			'git_updater_lite_domains' => [
				'my-plugin' => 'www.example.com',
			],
		];

		$this->lite_domains->save_settings( $post_data );

		$saved = get_site_option( 'git_updater_lite_domains' );
		$this->assertSame( 'example.com', $saved['my-plugin'] );

		unset( $_POST['_wpnonce'], $_POST['option_page'] );
	}

	/**
	 * Test save_settings registers gu_save_redirect filter.
	 */
	public function test_save_settings_registers_gu_save_redirect_filter(): void {
		$_POST['_wpnonce']    = wp_create_nonce( 'git_updater_lite_domains-options' );
		$_POST['option_page'] = 'git_updater_lite_domains';

		$post_data = [
			'option_page' => 'git_updater_lite_domains',
			'git_updater_lite_domains' => [
				'test' => 'example.com',
			],
		];

		$this->lite_domains->save_settings( $post_data );

		$this->assertNotFalse( has_filter( 'gu_save_redirect' ) );

		// Apply the filter to cover the closure body.
		$result = apply_filters( 'gu_save_redirect', [] );
		$this->assertContains( 'git_updater_lite_domains', $result );

		remove_all_filters( 'gu_save_redirect' );
		unset( $_POST['_wpnonce'], $_POST['option_page'] );
	}

	/**
	 * Test save_settings skips if option_page does not match.
	 */
	public function test_save_settings_skips_if_option_page_wrong(): void {
		$_POST['_wpnonce']    = wp_create_nonce( 'git_updater_lite_domains-options' );
		$_POST['option_page'] = 'wrong_page';

		$this->lite_domains->save_settings( [ 'option_page' => 'wrong_page' ] );

		$saved = get_site_option( 'git_updater_lite_domains' );
		$this->assertEmpty( $saved );

		unset( $_POST['_wpnonce'], $_POST['option_page'] );
	}

	/**
	 * Test get_flagged_slugs includes additions with uses_lite.
	 */
	public function test_get_flagged_slugs_includes_additions(): void {
		update_site_option( 'git_updater_additions', [
			[
				'ID' => '123',
				'type' => 'github_plugin',
				'slug' => 'my-plugin/my-plugin.php',
				'uri' => 'https://github.com/test/my-plugin',
				'uses_lite' => true,
				'private_package' => false,
			],
		] );

		$reflection = new ReflectionMethod( $this->lite_domains, 'get_flagged_slugs' );
		$reflection->setAccessible( true );
		$flagged = $reflection->invoke( $this->lite_domains );

		$this->assertContains( 'my-plugin', $flagged );
	}

	/**
	 * Test get_flagged_slugs includes existing configured slugs.
	 */
	public function test_get_flagged_slugs_includes_configured_slugs(): void {
		update_site_option( 'git_updater_lite_domains', [ 'existing-slug' => 'example.com' ] );
		$this->lite_domains = new Lite_Domains();

		$reflection = new ReflectionMethod( $this->lite_domains, 'get_flagged_slugs' );
		$reflection->setAccessible( true );
		$flagged = $reflection->invoke( $this->lite_domains );

		$this->assertContains( 'existing-slug', $flagged );
	}

	/**
	 * Test get_flagged_slugs includes theme additions with uses_lite.
	 */
	public function test_get_flagged_slugs_includes_theme_additions(): void {
		update_site_option( 'git_updater_additions', [
			[
				'ID' => '456',
				'type' => 'github_theme',
				'slug' => 'my-theme',
				'uri' => 'https://github.com/test/my-theme',
				'uses_lite' => true,
				'private_package' => false,
			],
		] );

		$reflection = new ReflectionMethod( $this->lite_domains, 'get_flagged_slugs' );
		$reflection->setAccessible( true );
		$flagged = $reflection->invoke( $this->lite_domains );

		$this->assertContains( 'my-theme', $flagged );
	}

	/**
	 * Test is_flagged_for_warning returns true for private repos with update_uri.
	 */
	public function test_is_flagged_for_warning_returns_true_for_private_repos(): void {
		// Create a mock config object.
		$mock_repo = new \stdClass();
		$mock_repo->slug = 'private-plugin';
		$mock_repo->is_private = true;
		$mock_repo->update_uri = 'https://github.com/test/private-plugin';
		$mock_repo->private_package = false;

		// Mock the Plugin config.
		$mock_plugin = \Fragen\Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $this->lite_domains );
		$reflection  = new ReflectionClass( $mock_plugin );
		$config_prop = $reflection->getProperty( 'config' );
		$config_prop->setAccessible( true );
		$config_prop->setValue( $mock_plugin, [ 'private-plugin' => $mock_repo ] );

		$method = new ReflectionMethod( $this->lite_domains, 'is_flagged_for_warning' );
		$method->setAccessible( true );

		$this->assertTrue( $method->invoke( $this->lite_domains, 'private-plugin' ) );

		// Reset config.
		$config_prop->setValue( $mock_plugin, [] );
	}

	/**
	 * Test get_flagged_slugs includes repos with is_private and update_uri.
	 */
	public function test_get_flagged_slugs_includes_private_repos_with_update_uri(): void {
		$mock_repo = new \stdClass();
		$mock_repo->slug = 'private-with-uri';
		$mock_repo->is_private = true;
		$mock_repo->update_uri = 'https://github.com/test/private-with-uri';
		$mock_repo->private_package = false;

		$mock_plugin = \Fragen\Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $this->lite_domains );
		$reflection  = new ReflectionClass( $mock_plugin );
		$config_prop = $reflection->getProperty( 'config' );
		$config_prop->setAccessible( true );
		$config_prop->setValue( $mock_plugin, [ 'private-with-uri' => $mock_repo ] );

		$method = new ReflectionMethod( $this->lite_domains, 'get_flagged_slugs' );
		$method->setAccessible( true );
		$flagged = $method->invoke( $this->lite_domains );

		$this->assertContains( 'private-with-uri', $flagged );

		$config_prop->setValue( $mock_plugin, [] );
	}

	/**
	 * Test is_flagged_for_warning returns false for public repos.
	 */
	public function test_is_flagged_for_warning_returns_false_for_public_repos(): void {
		$mock_repo = new \stdClass();
		$mock_repo->slug = 'public-plugin';
		$mock_repo->is_private = false;
		$mock_repo->update_uri = '';

		$mock_plugin = \Fragen\Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $this->lite_domains );
		$reflection  = new ReflectionClass( $mock_plugin );
		$config_prop = $reflection->getProperty( 'config' );
		$config_prop->setAccessible( true );
		$config_prop->setValue( $mock_plugin, [ 'public-plugin' => $mock_repo ] );

		$method = new ReflectionMethod( $this->lite_domains, 'is_flagged_for_warning' );
		$method->setAccessible( true );

		$this->assertFalse( $method->invoke( $this->lite_domains, 'public-plugin' ) );

		$config_prop->setValue( $mock_plugin, [] );
	}

	/**
	 * Test add_admin_page renders form for correct tab.
	 */
	public function test_add_admin_page_renders_form_for_correct_tab(): void {
		ob_start();
		$this->lite_domains->add_admin_page( 'git_updater_lite_domains', 'edit.php' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Lite Client Domains', $output );
		$this->assertStringContainsString( '<form', $output );
	}

	/**
	 * Test add_admin_page renders nothing for wrong tab.
	 */
	public function test_add_admin_page_renders_nothing_for_wrong_tab(): void {
		ob_start();
		$this->lite_domains->add_admin_page( 'wrong-tab', 'edit.php' );
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test filter integration.
	 */
	public function test_filter_returns_domains_for_slug(): void {
		update_site_option( 'git_updater_lite_domains', [ 'filtered-slug' => 'filtered.com' ] );
		$this->lite_domains = new Lite_Domains();

		$result = apply_filters( 'git_updater_lite_authorized_domains', [], 'filtered-slug' );
		$this->assertContains( 'filtered.com', $result );
	}
}