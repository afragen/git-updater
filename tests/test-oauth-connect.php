<?php
/**
 * Test OAuth_Connect class
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\OAuth\OAuth_Connect;

/**
 * Test OAuth_Connect functionality
 */
class Test_OAuth_Connect extends GU_Test_Case {

	/**
	 * OAuth_Connect instance
	 *
	 * @var OAuth_Connect
	 */
	private $oauth;

	/**
	 * Set up test
	 */
	public function set_up(): void {
		parent::set_up();
		$this->oauth = new OAuth_Connect();
		delete_site_option( 'git_updater' );
		unset( $_GET['provider'], $_GET['gu_exchange_code'], $_GET['_wpnonce'], $_POST['provider'], $_POST['_wpnonce'] );
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'wp_redirect' );
	}

	/**
	 * Tear down test
	 */
	public function tear_down(): void {
		delete_site_option( 'git_updater' );
		unset( $_GET['provider'], $_GET['gu_exchange_code'], $_GET['_wpnonce'], $_POST['provider'], $_POST['_wpnonce'] );
		remove_all_actions( 'admin_post_gu_oauth_callback' );
		remove_all_actions( 'admin_post_gu_oauth_disconnect' );
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'wp_redirect' );
		parent::tear_down();
	}

	/**
	 * Test fetch_token_from_connector returns null when connector not configured.
	 * Must run before any test that defines GIT_UPDATER_OAUTH_CONNECTOR_URL.
	 */
	public function test_fetch_token_from_connector_returns_null_without_config(): void {
		$method = new ReflectionMethod( OAuth_Connect::class, 'fetch_token_from_connector' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->oauth, 'github', 'test_code' );

		$this->assertNull( $result );
	}

	/**
	 * Test render_connect_field shows no connector message.
	 * Must run before any test that defines GIT_UPDATER_OAUTH_CONNECTOR_URL.
	 */
	public function test_render_connect_field_shows_no_connector_message(): void {
		ob_start();
		$this->oauth->render_connect_field( [ 'provider' => 'github' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'GIT_UPDATER_OAUTH_CONNECTOR_URL', $output );
	}

	/**
	 * Test PROVIDERS constant
	 */
	public function test_providers_constant(): void {
		$expected = [
			'github'    => [ 'option_key' => 'github_access_token', 'label' => 'GitHub' ],
			'gitlab'    => [ 'option_key' => 'gitlab_access_token', 'label' => 'GitLab' ],
			'bitbucket' => [ 'option_key' => 'bitbucket_access_token', 'label' => 'Bitbucket' ],
			'gitea'     => [ 'option_key' => 'gitea_access_token', 'label' => 'Gitea' ],
		];
		$this->assertEquals( $expected, OAuth_Connect::PROVIDERS );
	}

	/**
	 * Test load_hooks registers actions
	 */
	public function test_load_hooks_registers_actions(): void {
		$this->oauth->load_hooks();
		$this->assertNotFalse( has_action( 'admin_post_gu_oauth_callback', [ $this->oauth, 'handle_callback' ] ) );
		$this->assertNotFalse( has_action( 'admin_post_gu_oauth_disconnect', [ $this->oauth, 'handle_disconnect' ] ) );
	}

	/**
	 * Test render_connect_field with invalid provider
	 */
	public function test_render_connect_field_with_invalid_provider(): void {
		ob_start();
		$this->oauth->render_connect_field( [ 'provider' => 'invalid_provider' ] );
		$output = ob_get_clean();
		$this->assertEmpty( $output );
	}

	/**
	 * Test render_connect_field shows connected state
	 */
	public function test_render_connect_field_shows_connected_state(): void {
		update_site_option( 'git_updater', [ 'github_access_token' => 'test_token' ] );
		ob_start();
		$this->oauth->render_connect_field( [ 'provider' => 'github' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'Connected', $output );
		$this->assertStringContainsString( 'Disconnect', $output );
		$this->assertStringContainsString( 'gu_oauth_disconnect', $output );
	}

	/**
	 * Test render_connect_field shows connect button
	 */
	public function test_render_connect_field_shows_connect_button(): void {
		if ( ! defined( 'GIT_UPDATER_OAUTH_CONNECTOR_URL' ) ) {
			define( 'GIT_UPDATER_OAUTH_CONNECTOR_URL', 'https://connector.example.com' );
		}
		ob_start();
		$this->oauth->render_connect_field( [ 'provider' => 'github' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'Connect GitHub', $output );
		$this->assertStringContainsString( 'button-primary', $output );
		$this->assertStringContainsString( 'gu_oauth_callback', $output );
	}

	/**
	 * Test render_connect_field for GitLab
	 */
	public function test_render_connect_field_for_gitlab(): void {
		if ( ! defined( 'GIT_UPDATER_OAUTH_CONNECTOR_URL' ) ) {
			define( 'GIT_UPDATER_OAUTH_CONNECTOR_URL', 'https://connector.example.com' );
		}
		ob_start();
		$this->oauth->render_connect_field( [ 'provider' => 'gitlab' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'Connect GitLab', $output );
	}

	/**
	 * Test render_connect_field for Gitea without server settings
	 */
	public function test_render_connect_field_for_gitea_without_server_settings(): void {
		if ( ! defined( 'GIT_UPDATER_OAUTH_CONNECTOR_URL' ) ) {
			define( 'GIT_UPDATER_OAUTH_CONNECTOR_URL', 'https://connector.example.com' );
		}
		ob_start();
		$this->oauth->render_connect_field( [ 'provider' => 'gitea' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'Gitea Server URL', $output );
	}

	/**
	 * Test render_connect_field for Gitea with server settings
	 */
	public function test_render_connect_field_for_gitea_with_server_settings(): void {
		if ( ! defined( 'GIT_UPDATER_OAUTH_CONNECTOR_URL' ) ) {
			define( 'GIT_UPDATER_OAUTH_CONNECTOR_URL', 'https://connector.example.com' );
		}
		update_site_option( 'git_updater', [
			'gitea_server'    => 'https://gitea.example.com',
			'gitea_client_id' => 'test_client_id',
		] );
		ob_start();
		$this->oauth->render_connect_field( [ 'provider' => 'gitea' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'Connect Gitea', $output );
		$this->assertStringContainsString( 'base_url', $output );
		$this->assertStringContainsString( 'client_id', $output );
	}

	/**
	 * Test handle_callback with insufficient permissions
	 */
	public function test_handle_callback_with_insufficient_permissions(): void {
		$user = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user );

		$this->expectException( WPDieException::class );
		$this->oauth->handle_callback();
	}

	/**
	 * Grant super admin on multisite for admin users.
	 *
	 * @param int $user_id User ID.
	 */
	private function maybe_grant_super_admin( int $user_id ): void {
		if ( is_multisite() ) {
			grant_super_admin( $user_id );
		}
	}

	/**
	 * Test handle_callback saves token on success
	 */
	public function test_handle_callback_saves_token_on_success(): void {
		if ( ! defined( 'GIT_UPDATER_OAUTH_CONNECTOR_URL' ) ) {
			define( 'GIT_UPDATER_OAUTH_CONNECTOR_URL', 'https://connector.example.com' );
		}

		$user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->maybe_grant_super_admin( $user );
		wp_set_current_user( $user );

		$_GET['provider']         = 'github';
		$_GET['gu_exchange_code'] = 'test_exchange_code';

		add_filter( 'pre_http_request', static function( $preempt, $args, $url ) {
			if ( strpos( $url, '/token' ) !== false ) {
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => wp_json_encode( [ 'access_token' => 'test_access_token' ] ),
					'headers'  => [],
				];
			}
			return $preempt;
		}, 10, 3 );

		$redirected = false;
		add_filter( 'wp_redirect', function( $url ) use ( &$redirected ) {
			$redirected = true;
			$this->assertStringContainsString( 'oauth_connected', $url );
			throw new RuntimeException( 'Redirect captured' );
		} );

		try {
			$this->oauth->handle_callback();
			$this->fail( 'Expected redirect to be captured' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'Redirect captured', $e->getMessage() );
		}

		$options = get_site_option( 'git_updater' );
		$this->assertEquals( 'test_access_token', $options['github_access_token'] );
	}

	/**
	 * Test handle_callback with failed token fetch
	 */
	public function test_handle_callback_with_failed_token_fetch(): void {
		if ( ! defined( 'GIT_UPDATER_OAUTH_CONNECTOR_URL' ) ) {
			define( 'GIT_UPDATER_OAUTH_CONNECTOR_URL', 'https://connector.example.com' );
		}

		$user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->maybe_grant_super_admin( $user );
		wp_set_current_user( $user );

		$_GET['provider']         = 'github';
		$_GET['gu_exchange_code'] = 'test_exchange_code';

		add_filter( 'pre_http_request', static function( $preempt, $args, $url ) {
			if ( strpos( $url, '/token' ) !== false ) {
				return new WP_Error( 'http_error', 'Connection failed' );
			}
			return $preempt;
		}, 10, 3 );

		$captured_url = null;
		add_filter( 'wp_redirect', function( $url ) use ( &$captured_url ) {
			$captured_url = $url;
			throw new RuntimeException( 'Redirect captured' );
		} );

		try {
			$this->oauth->handle_callback();
			$this->fail( 'Expected redirect to be captured' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'Redirect captured', $e->getMessage() );
		}

		$this->assertNotNull( $captured_url );
		$this->assertStringContainsString( 'oauth_error', $captured_url );
	}

	/**
	 * Test handle_disconnect with insufficient permissions
	 */
	public function test_handle_disconnect_with_insufficient_permissions(): void {
		$user = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user );

		$_GET['provider'] = 'github';
		$_REQUEST['_wpnonce'] = $_GET['_wpnonce'] = wp_create_nonce( 'gu_oauth_disconnect_github' );

		$this->expectException( WPDieException::class );
		$this->oauth->handle_disconnect();
	}

	/**
	 * Test handle_disconnect with invalid nonce
	 */
	public function test_handle_disconnect_with_invalid_nonce(): void {
		$user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->maybe_grant_super_admin( $user );
		wp_set_current_user( $user );

		$_GET['provider'] = 'github';
		$_GET['_wpnonce'] = 'invalid_nonce';

		$this->expectException( WPDieException::class );
		$this->oauth->handle_disconnect();
	}

	/**
	 * Test handle_disconnect successfully removes token
	 */
	public function test_handle_disconnect_successfully_removes_token(): void {
		$user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->maybe_grant_super_admin( $user );
		wp_set_current_user( $user );

		update_site_option( 'git_updater', [
			'github_access_token' => 'test_token',
			'gitlab_access_token' => 'other_token',
		] );

		$_GET['provider'] = 'github';
		$_REQUEST['_wpnonce'] = $_GET['_wpnonce'] = wp_create_nonce( 'gu_oauth_disconnect_github' );

		$redirect_url = null;
		add_filter( 'wp_redirect', function( $url ) use ( &$redirect_url ) {
			$redirect_url = $url;
			throw new RuntimeException( 'Redirect captured' );
		} );

		try {
			$this->oauth->handle_disconnect();
			$this->fail( 'Expected redirect to be captured' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'Redirect captured', $e->getMessage() );
		}

		$this->assertNotNull( $redirect_url );
		$this->assertStringContainsString( 'oauth_disconnected', $redirect_url );

		$options = get_site_option( 'git_updater' );
		$this->assertArrayNotHasKey( 'github_access_token', $options );
		$this->assertEquals( 'other_token', $options['gitlab_access_token'] );
	}

	/**
	 * Test token persistence across providers
	 */
	public function test_token_persistence_across_providers(): void {
		$user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->maybe_grant_super_admin( $user );
		wp_set_current_user( $user );

		if ( ! defined( 'GIT_UPDATER_OAUTH_CONNECTOR_URL' ) ) {
			define( 'GIT_UPDATER_OAUTH_CONNECTOR_URL', 'https://connector.example.com' );
		}

		$_GET['provider']         = 'github';
		$_GET['gu_exchange_code'] = 'github_code';
		add_filter( 'pre_http_request', static function( $preempt, $args, $url ) {
			if ( strpos( $url, 'github' ) !== false && strpos( $url, '/token' ) !== false ) {
				return [
					'response' => [ 'code' => 200 ],
					'body'     => wp_json_encode( [ 'access_token' => 'github_token' ] ),
				];
			}
			return $preempt;
		}, 10, 3 );

		add_filter( 'wp_redirect', static function() {
			throw new RuntimeException( 'Redirect captured' );
		} );
		try {
			$this->oauth->handle_callback();
		} catch ( RuntimeException $e ) {
			// Expected
		}

		add_filter( 'pre_http_request', static function( $preempt, $args, $url ) {
			if ( strpos( $url, 'gitlab' ) !== false && strpos( $url, '/token' ) !== false ) {
				return [
					'response' => [ 'code' => 200 ],
					'body'     => wp_json_encode( [ 'access_token' => 'gitlab_token' ] ),
				];
			}
			return $preempt;
		}, 10, 4 );

		$_GET['provider']         = 'gitlab';
		$_GET['gu_exchange_code'] = 'gitlab_code';

		try {
			$this->oauth->handle_callback();
		} catch ( RuntimeException $e ) {
			// Expected
		}

		$options = get_site_option( 'git_updater' );
		$this->assertEquals( 'github_token', $options['github_access_token'] );
		$this->assertEquals( 'gitlab_token', $options['gitlab_access_token'] );
	}

	/**
	 * Test handle_callback with invalid provider redirects with error.
	 */
	public function test_handle_callback_with_invalid_provider(): void {
		$user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->maybe_grant_super_admin( $user );
		wp_set_current_user( $user );

		$_GET['provider'] = 'invalid_provider';
		$_GET['gu_exchange_code'] = 'test_code';

		$captured_url = null;
		add_filter( 'wp_redirect', function( $url ) use ( &$captured_url ) {
			$captured_url = $url;
			throw new RuntimeException( 'Redirect captured' );
		} );

		try {
			$this->oauth->handle_callback();
			$this->fail( 'Expected redirect to be captured' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'Redirect captured', $e->getMessage() );
		}

		$this->assertNotNull( $captured_url );
		$this->assertStringContainsString( 'oauth_error', $captured_url );
	}

	/**
	 * Test handle_callback with empty exchange code redirects with error.
	 */
	public function test_handle_callback_with_empty_exchange_code(): void {
		$user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->maybe_grant_super_admin( $user );
		wp_set_current_user( $user );

		$_GET['provider'] = 'github';
		$_GET['gu_exchange_code'] = '';

		$captured_url = null;
		add_filter( 'wp_redirect', function( $url ) use ( &$captured_url ) {
			$captured_url = $url;
			throw new RuntimeException( 'Redirect captured' );
		} );

		try {
			$this->oauth->handle_callback();
			$this->fail( 'Expected redirect to be captured' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'Redirect captured', $e->getMessage() );
		}

		$this->assertNotNull( $captured_url );
		$this->assertStringContainsString( 'oauth_error', $captured_url );
	}

	/**
	 * Test get_callback_url uses network_admin_url on multisite.
	 * @group ms-required
	 */
	public function test_get_callback_url_uses_network_admin_on_multisite(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only test' );
		}

		$method = new ReflectionMethod( OAuth_Connect::class, 'get_callback_url' );
		$method->setAccessible( true );

		$url = $method->invoke( $this->oauth, 'github' );

		$this->assertStringContainsString( 'network/admin-post.php', $url );
		$this->assertStringContainsString( 'action=gu_oauth_callback', $url );
	}
}
