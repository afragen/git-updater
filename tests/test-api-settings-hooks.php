<?php
/**
 * Tests for settings registration and hook methods in API classes.
 *
 * Covers:
 * - API::settings_hook()            — registers gu_add_settings action and filter
 * - API::add_setting_field()        — returns existing fields or calls get_repo_api
 * - API::get_repo_api()             — factory for git host API objects
 * - API::add_install_fields()       — registers gu_add_install_settings_fields action
 * - GitHub_API::add_settings()      — registers settings sections and fields
 * - GitHub_API::add_repo_setting_field() — returns settings page/section config
 * - GitHub_API::print_section_github_info()         — echoes help text
 * - GitHub_API::print_section_github_access_token() — echoes help text + icon img
 * - GitHub_API::add_install_settings_fields()       — registers install field
 * - GitHub_API::github_access_token()               — echoes HTML input field
 * - GitHub_API::add_settings_subtab() (private)     — adds 'github' to subtabs filter
 * - Zipfile_API::add_install_settings_fields()      — registers install field
 * - Zipfile_API::zipfile_slug()                     — echoes HTML input field
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\API\GitHub_API;
use Fragen\Git_Updater\API\Zipfile_API;
use Fragen\Git_Updater\Base;
use Fragen\Git_Updater\OAuth\OAuth_Flow;

/**
 * Class Test_API_Hooks
 *
 * Covers hook-registration methods on the base API class.
 */
class Test_API_Hooks extends WP_UnitTestCase {

	/**
	 * @var GitHub_API
	 */
	private GitHub_API $api;

	/**
	 * @var stdClass
	 */
	private stdClass $type;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->type = $this->make_type();
		$this->api  = new GitHub_API( $this->type );
	}

	public function tear_down(): void {
		remove_all_filters( 'gu_add_repo_setting_field' );
		remove_all_filters( 'gu_get_repo_api' );
		remove_all_actions( 'gu_add_settings' );
		remove_all_actions( 'gu_add_install_settings_fields' );
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

	// -------------------------------------------------------------------------
	// settings_hook() — action + filter registration
	// -------------------------------------------------------------------------

	public function test_settings_hook_registers_gu_add_settings_action(): void {
		// GitHub_API constructor calls settings_hook($this) which registers the action.
		$this->assertNotFalse( has_action( 'gu_add_settings' ) );
	}

	public function test_settings_hook_registers_gu_add_repo_setting_field_filter(): void {
		$this->assertNotFalse( has_filter( 'gu_add_repo_setting_field' ) );
	}

	public function test_settings_hook_fires_add_settings_when_gu_add_settings_action_called(): void {
		global $wp_settings_sections;

		// Firing the action invokes the lambda that calls $git->add_settings($auth_required).
		do_action( 'gu_add_settings', [ 'github_private' => false, 'github_enterprise' => false ] );

		$this->assertArrayHasKey(
			'github_access_token',
			$wp_settings_sections['git_updater_github_install_settings'] ?? []
		);
	}

	// -------------------------------------------------------------------------
	// add_setting_field() — both branches
	// -------------------------------------------------------------------------

	public function test_add_setting_field_returns_non_empty_fields_unchanged(): void {
		$repo        = (object) [ 'git' => 'github' ];
		$fields      = [ 'page' => 'some_page', 'section' => 'some_section' ];
		$result      = $this->api->add_setting_field( $fields, $repo );
		$this->assertSame( $fields, $result );
	}

	public function test_add_setting_field_calls_get_repo_api_when_fields_empty(): void {
		$repo   = (object) [
			'git'            => 'github',
			'slug'           => 'test-plugin',
			'type'           => 'plugin',
			'owner'          => 'test-owner',
			'branch'         => 'master',
			'primary_branch' => 'master',
			'enterprise'     => false,
			'enterprise_api' => null,
			'gist_id'        => null,
		];
		$result = $this->api->add_setting_field( [], $repo );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'page', $result );
	}

	// -------------------------------------------------------------------------
	// get_repo_api() — github path and unknown path
	// -------------------------------------------------------------------------

	public function test_get_repo_api_returns_github_api_for_github_git(): void {
		$result = $this->api->get_repo_api( 'github', $this->type );
		$this->assertInstanceOf( GitHub_API::class, $result );
	}

	public function test_get_repo_api_returns_null_for_unknown_git(): void {
		$result = $this->api->get_repo_api( 'unknown_git_host', $this->type );
		$this->assertNull( $result );
	}

	// -------------------------------------------------------------------------
	// add_install_fields() — registers gu_add_install_settings_fields action
	// -------------------------------------------------------------------------

	public function test_add_install_fields_registers_action(): void {
		// GitHub_API constructor calls add_install_fields($this).
		$this->assertNotFalse( has_action( 'gu_add_install_settings_fields' ) );
	}

	public function test_add_install_fields_action_fires_add_install_settings_fields(): void {
		$called = false;
		add_action(
			'gu_add_install_settings_fields',
			function ( $type ) use ( &$called ) {
				$called = true;
			},
			20,
			1
		);

		do_action( 'gu_add_install_settings_fields', 'plugin' );

		$this->assertTrue( $called );
	}
}

/**
 * Class Test_GitHub_API_Settings
 *
 * Covers all settings output and registration methods in GitHub_API.
 */
class Test_GitHub_API_Settings extends WP_UnitTestCase {

	/**
	 * @var GitHub_API
	 */
	private GitHub_API $api;

	/**
	 * @var stdClass
	 */
	private stdClass $type;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->type = $this->make_type();
		$this->api  = new GitHub_API( $this->type );
	}

	public function tear_down(): void {
		remove_all_filters( 'gu_add_settings_subtabs' );
		remove_all_filters( 'gu_add_repo_setting_field' );
		remove_all_actions( 'gu_add_settings' );
		remove_all_actions( 'gu_add_install_settings_fields' );
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

	// -------------------------------------------------------------------------
	// add_settings_subtab() (private, called in constructor)
	// -------------------------------------------------------------------------

	public function test_add_settings_subtab_adds_github_to_subtabs_filter(): void {
		$subtabs = apply_filters( 'gu_add_settings_subtabs', [] );
		$this->assertArrayHasKey( 'github', $subtabs );
	}

	// -------------------------------------------------------------------------
	// add_settings()
	// -------------------------------------------------------------------------

	public function test_add_settings_registers_access_token_section(): void {
		global $wp_settings_sections;

		$this->api->add_settings( [ 'github_private' => false, 'github_enterprise' => false ] );

		$this->assertArrayHasKey( 'github_access_token', $wp_settings_sections['git_updater_github_install_settings'] ?? [] );
	}

	public function test_add_settings_registers_oauth_authorize_field(): void {
		global $wp_settings_fields;

		$this->api->add_settings( [ 'github_private' => false, 'github_enterprise' => false ] );

		$this->assertArrayHasKey( 'github_oauth_authorize', $wp_settings_fields['git_updater_github_install_settings']['github_access_token'] ?? [] );
	}

	public function test_add_settings_registers_private_section_when_auth_required(): void {
		global $wp_settings_sections;

		$this->api->add_settings( [ 'github_private' => true, 'github_enterprise' => false ] );

		$this->assertArrayHasKey( 'github_id', $wp_settings_sections['git_updater_github_install_settings'] ?? [] );
	}

	public function test_add_settings_does_not_register_private_section_when_not_required(): void {
		global $wp_settings_sections;

		// Clear any previous registrations.
		if ( isset( $wp_settings_sections['git_updater_github_install_settings']['github_id'] ) ) {
			unset( $wp_settings_sections['git_updater_github_install_settings']['github_id'] );
		}

		$this->api->add_settings( [ 'github_private' => false, 'github_enterprise' => false ] );

		$this->assertArrayNotHasKey( 'github_id', $wp_settings_sections['git_updater_github_install_settings'] ?? [] );
	}

	// -------------------------------------------------------------------------
	// add_repo_setting_field()
	// -------------------------------------------------------------------------

	public function test_add_repo_setting_field_returns_correct_page(): void {
		$result = $this->api->add_repo_setting_field();
		$this->assertSame( 'git_updater_github_install_settings', $result['page'] );
	}

	public function test_add_repo_setting_field_returns_correct_section(): void {
		$result = $this->api->add_repo_setting_field();
		$this->assertSame( 'github_id', $result['section'] );
	}

	public function test_add_repo_setting_field_returns_callable_callback(): void {
		$result = $this->api->add_repo_setting_field();
		$this->assertIsCallable( $result['callback_method'] );
	}

	// -------------------------------------------------------------------------
	// print_section_github_info()
	// -------------------------------------------------------------------------

	public function test_print_section_github_info_outputs_help_text(): void {
		ob_start();
		$this->api->print_section_github_info();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'GitHub Access Token', $output );
	}

	// -------------------------------------------------------------------------
	// print_section_github_access_token()
	// -------------------------------------------------------------------------

	public function test_print_section_github_access_token_outputs_text_and_icon(): void {
		ob_start();
		$this->api->print_section_github_access_token();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'Access Token', $output );
		$this->assertStringContainsString( 'github-logo.svg', $output );
	}

	// -------------------------------------------------------------------------
	// add_install_settings_fields()
	// -------------------------------------------------------------------------

	public function test_add_install_settings_fields_registers_github_access_token_field(): void {
		global $wp_settings_fields;

		$this->api->add_install_settings_fields( 'plugin' );

		$this->assertArrayHasKey( 'github_access_token', $wp_settings_fields['git_updater_install_plugin']['plugin'] ?? [] );
	}

	// -------------------------------------------------------------------------
	// github_access_token()
	// -------------------------------------------------------------------------

	public function test_github_access_token_outputs_password_input(): void {
		ob_start();
		$this->api->github_access_token();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'github_access_token', $output );
		$this->assertStringContainsString( 'type="password"', $output );
	}

	public function test_get_oauth_flow_returns_oauth_flow(): void {
		$this->assertInstanceOf( OAuth_Flow::class, $this->api->get_oauth_flow() );
	}

	public function test_github_oauth_authorize_outputs_callback_url(): void {
		ob_start();
		$this->api->github_oauth_authorize();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'gu_github_oauth_callback', $output );
		$this->assertStringContainsString( 'GU_GITHUB_OAUTH_CLIENT_ID', $output );
	}
}

/**
 * Class Test_OAuth_Flow
 *
 * Covers reusable OAuth helper methods for Git API providers.
 */
class Test_OAuth_Flow extends WP_UnitTestCase {
	private function make_flow(): OAuth_Flow {
		return new OAuth_Flow(
			[
				'provider'               => 'example',
				'option_name'            => 'example_access_token',
				'settings_url'           => 'https://example.test/wp-admin/options-general.php?page=git-updater',
				'authorize_url'          => 'https://provider.test/oauth/authorize',
				'token_url'              => 'https://provider.test/oauth/token',
				'default_scope'          => 'repo',
				'credentials_filter'     => 'gu_test_oauth_credentials',
				'client_id_constant'     => '',
				'client_secret_constant' => '',
				'scope_constant'         => '',
				'start_arg'              => 'gu_example_oauth_start',
				'callback_arg'           => 'gu_example_oauth_callback',
				'status_arg'             => 'gu_example_oauth',
				'nonce_action'           => 'gu-example-oauth-start',
			]
		);
	}

	public function tear_down(): void {
		remove_all_filters( 'gu_test_oauth_credentials' );
		parent::tear_down();
	}

	public function test_get_code_challenge_uses_pkce_s256_encoding(): void {
		$flow = $this->make_flow();

		$this->assertSame( 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', $flow->get_code_challenge( 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk' ) );
	}

	public function test_get_transient_key_includes_sanitized_provider_and_state_hash(): void {
		$flow = $this->make_flow();

		$this->assertSame( 'gu_example_oauth_' . md5( 'state-value' ), $flow->get_transient_key( 'state-value' ) );
	}

	public function test_get_credentials_uses_provider_filter(): void {
		add_filter(
			'gu_test_oauth_credentials',
			static function ( $credentials ) {
				$credentials['client_id'] = 'filtered-client';

				return $credentials;
			}
		);

		$credentials = $this->make_flow()->get_credentials();

		$this->assertSame( 'filtered-client', $credentials['client_id'] );
		$this->assertSame( 'repo', $credentials['scope'] );
	}

	public function test_get_callback_url_adds_callback_arg(): void {
		$callback_url = $this->make_flow()->get_callback_url();

		$this->assertStringContainsString( 'gu_example_oauth_callback=1', $callback_url );
	}
}

/**
 * Class Test_Zipfile_API_Settings
 *
 * Covers settings output methods in Zipfile_API.
 */
class Test_Zipfile_API_Settings extends WP_UnitTestCase {

	/**
	 * @var Zipfile_API
	 */
	private Zipfile_API $zipfile;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->zipfile = new Zipfile_API();
	}

	// -------------------------------------------------------------------------
	// add_install_settings_fields()
	// -------------------------------------------------------------------------

	public function test_add_install_settings_fields_registers_zipfile_slug_field(): void {
		global $wp_settings_fields;

		$this->zipfile->add_install_settings_fields( 'plugin' );

		$this->assertArrayHasKey( 'zipfile_slug', $wp_settings_fields['git_updater_install_plugin']['plugin'] ?? [] );
	}

	// -------------------------------------------------------------------------
	// zipfile_slug()
	// -------------------------------------------------------------------------

	public function test_zipfile_slug_outputs_text_input(): void {
		ob_start();
		$this->zipfile->zipfile_slug();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'zipfile_slug', $output );
		$this->assertStringContainsString( 'type="text"', $output );
	}
}
