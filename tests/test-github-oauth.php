<?php

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

}
