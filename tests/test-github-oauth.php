<?php

use Fragen\Git_Updater\API\GitHub_API;

/**
 * Test GitHub OAuth helpers.
 */
class Test_GitHub_OAuth extends \WP_UnitTestCase {

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
		$api       = $this->get_api();
		$challenge = $this->invoke_private_method( $api, 'get_oauth_code_challenge', [ 'abc123' ] );

		$this->assertSame( 'bKE9UspwyIPg8LsQHkJaiehiTeUdstI5JZOvaoQRgJA', $challenge );
	}

	/**
	 * Verify transient key derivation from OAuth state.
	 */
	public function test_get_oauth_transient_key() {
		$api = $this->get_api();
		$key = $this->invoke_private_method( $api, 'get_oauth_transient_key', [ 'state-value' ] );

		$this->assertSame( 'gu_github_oauth_' . md5( 'state-value' ), $key );
	}
}
