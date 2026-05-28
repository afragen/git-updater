<?php
/**
 * Tests for API\Zipfile_API.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\API\Zipfile_API;
use Fragen\Git_Updater\Base;

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

/**
 * Class Test_Zipfile_API_Settings
 *
 * Covers settings output methods in Zipfile_API.
 */
class Test_Zipfile_API_Settings extends WP_UnitTestCase {

	private Zipfile_API $zipfile;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->zipfile = new Zipfile_API();
	}

	public function test_add_install_settings_fields_registers_zipfile_slug_field(): void {
		global $wp_settings_fields;

		$this->zipfile->add_install_settings_fields( 'plugin' );

		$this->assertArrayHasKey( 'zipfile_slug', $wp_settings_fields['git_updater_install_plugin']['plugin'] ?? [] );
	}

	public function test_zipfile_slug_outputs_text_input(): void {
		ob_start();
		$this->zipfile->zipfile_slug();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'zipfile_slug', $output );
		$this->assertStringContainsString( 'type="text"', $output );
	}
}
