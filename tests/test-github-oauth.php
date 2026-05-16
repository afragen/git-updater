<?php

use Fragen\Git_Updater\API\GitHub_API;
use Fragen\Git_Updater\OAuth\OAuth_Flow;

/**
 * Test GitHub OAuth helpers.
 */
class Test_GitHub_OAuth extends \WP_UnitTestCase {

	/**
	 * Build a reusable OAuth flow instance for helper method tests.
	 *
	 * @return OAuth_Flow
	 */
	private function get_oauth_flow() {
		return new OAuth_Flow(
			[
				'provider'     => 'github',
				'option_name'  => 'github_access_token',
				'settings_url' => admin_url( 'options-general.php?page=git-updater&tab=git_updater_settings&subtab=github' ),
				'start_arg'    => 'gu_github_oauth_start',
				'callback_arg' => 'gu_github_oauth_callback',
				'status_arg'   => 'gu_github_oauth',
			]
		);
	}

	/**
	 * Build an API instance for helper method tests.
	 *
	 * @return GitHub_API
	 */
	private function get_api() {
		return new GitHub_API();
	}

	/**
	 * Read private helper method values through reflection.
	 *
	 * @param GitHub_API $api API instance.
	 * @param string     $method Private method name.
	 * @param array      $args Method args.
	 *
	 * @return mixed
	 */
	private function invoke_private_method( $api, $method, $args = [] ) {
		$reflection = new ReflectionMethod( $api, $method );
		$reflection->setAccessible( true );

		return $reflection->invokeArgs( $api, $args );
	}

	/**
	 * Verify PKCE S256 challenge output.
	 */
	public function test_get_oauth_code_challenge() {
		$flow      = $this->get_oauth_flow();
		$challenge = $flow->get_code_challenge( 'abc123' );

		$this->assertSame( 'bKE9UspwyIPg8LsQHkJaiehiTeUdstI5JZOvaoQRgJA', $challenge );
	}

	/**
	 * Verify transient key derivation from OAuth state.
	 */
	public function test_get_oauth_transient_key() {
		$flow = $this->get_oauth_flow();
		$key  = $flow->get_transient_key( 'state-value' );

		$this->assertSame( 'gu_github_oauth_' . md5( 'state-value' ), $key );
	}

	/**
	 * Verify GitHub API wrappers continue to use reusable OAuth helpers.
	 */
	public function test_github_api_oauth_helper_wrappers() {
		$api       = $this->get_api();
		$challenge = $this->invoke_private_method( $api, 'get_oauth_code_challenge', [ 'abc123' ] );
		$key       = $this->invoke_private_method( $api, 'get_oauth_transient_key', [ 'state-value' ] );

		$this->assertSame( 'bKE9UspwyIPg8LsQHkJaiehiTeUdstI5JZOvaoQRgJA', $challenge );
		$this->assertSame( 'gu_github_oauth_' . md5( 'state-value' ), $key );
	}
}
