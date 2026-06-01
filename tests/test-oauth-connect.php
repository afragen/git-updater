<?php
/**
 * Test OAuth_Connect class
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\API\API;
use Fragen\Git_Updater\API\GitHub_API;
use Fragen\Git_Updater\Base;
use Fragen\Git_Updater\OAuth\OAuth_Connect;
use Fragen\Git_Updater\Settings;

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
		Base::$options = [];
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
	 */
	public function test_fetch_token_from_connector_returns_null_without_config(): void {
		$this->oauth->connector_url = '';
		$method = new ReflectionMethod( OAuth_Connect::class, 'fetch_token_from_connector' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->oauth, 'github', 'test_code' );

		$this->assertNull( $result );
	}

	/**
	 * Test render_connect_field shows no connector message when connector URL is empty.
	 */
	public function test_render_connect_field_shows_no_connector_message(): void {
		$this->oauth->connector_url = '';
		ob_start();
		$this->oauth->render_connect_field( [ 'provider' => 'github' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'GIT_UPDATER_OAUTH_CONNECTOR_URL', $output );
	}

	/**
	 * Test fetch_token_from_connector returns null when response has no access_token.
	 */
	public function test_fetch_token_from_connector_returns_null_on_empty_token_response(): void {
		$method = new ReflectionMethod( OAuth_Connect::class, 'fetch_token_from_connector' );
		$method->setAccessible( true );

		add_filter( 'pre_http_request', static function () {
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [ 'error' => 'invalid_grant' ] ),
				'headers'  => [],
			];
		}, 10, 3 );

		$result = $method->invoke( $this->oauth, 'github', 'bad_code' );
		$this->assertNull( $result );
	}

	/**
	 * Test PROVIDERS constant
	 */
	public function test_providers_constant(): void {
		$expected = [
			'github'    => [
				'option_key'         => 'github_access_token',
				'refresh_option_key' => 'github_refresh_token',
				'label'              => 'GitHub',
			],
			'gitlab'    => [
				'option_key'         => 'gitlab_access_token',
				'refresh_option_key' => 'gitlab_refresh_token',
				'label'              => 'GitLab',
			],
			'bitbucket' => [
				'option_key'         => 'bitbucket_access_token',
				'refresh_option_key' => 'bitbucket_refresh_token',
				'label'              => 'Bitbucket',
			],
			'gitea'     => [
				'option_key'         => 'gitea_access_token',
				'refresh_option_key' => 'gitea_refresh_token',
				'label'              => 'Gitea',
			],
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
			'github_access_token'   => 'test_token',
			'github_is_oauth_token' => 'oauth',
			'gitlab_access_token'   => 'other_token',
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
		$this->assertArrayNotHasKey( 'github_is_oauth_token', $options );
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

	// -------------------------------------------------------------------------
	// is_token_expired() tests
	// -------------------------------------------------------------------------

	public function test_is_token_expired_returns_true_for_unknown_provider(): void {
		$this->assertTrue( $this->oauth->is_token_expired( 'invalid_provider' ) );
	}

	public function test_is_token_expired_returns_true_when_no_token_stored(): void {
		$this->assertTrue( $this->oauth->is_token_expired( 'github' ) );
	}

	public function test_is_token_expired_returns_false_when_no_expiry_metadata(): void {
		update_site_option( 'git_updater', [ 'github_access_token' => 'tok' ] );
		$this->assertFalse( $this->oauth->is_token_expired( 'github' ) );
	}

	public function test_is_token_expired_returns_false_when_token_is_fresh(): void {
		update_site_option( 'git_updater', [
			'gitlab_access_token'       => 'tok',
			'gitlab_token_expires_in'   => 7200,
			'gitlab_token_acquired_at'  => time(),
		] );
		$this->assertFalse( $this->oauth->is_token_expired( 'gitlab' ) );
	}

	public function test_is_token_expired_returns_true_when_token_is_expired(): void {
		update_site_option( 'git_updater', [
			'gitlab_access_token'       => 'tok',
			'gitlab_token_expires_in'   => 7200,
			'gitlab_token_acquired_at'  => time() - 7201,
		] );
		$this->assertTrue( $this->oauth->is_token_expired( 'gitlab' ) );
	}

	public function test_is_token_expired_returns_true_when_within_buffer(): void {
		update_site_option( 'git_updater', [
			'gitlab_access_token'       => 'tok',
			'gitlab_token_expires_in'   => 7200,
			'gitlab_token_acquired_at'  => time() - 7000,
		] );
		// 200s remaining, buffer=300 → expired
		$this->assertTrue( $this->oauth->is_token_expired( 'gitlab' ) );
	}

	public function test_is_token_expired_returns_false_when_outside_buffer(): void {
		update_site_option( 'git_updater', [
			'gitlab_access_token'       => 'tok',
			'gitlab_token_expires_in'   => 7200,
			'gitlab_token_acquired_at'  => time() - 6000,
		] );
		// 1200s remaining, buffer=300 → not expired
		$this->assertFalse( $this->oauth->is_token_expired( 'gitlab' ) );
	}

	public function test_is_token_expired_custom_buffer(): void {
		update_site_option( 'git_updater', [
			'bitbucket_access_token'       => 'tok',
			'bitbucket_token_expires_in'   => 7200,
			'bitbucket_token_acquired_at'  => time() - 7100,
		] );
		// 100s remaining, buffer=60 → not expired
		$this->assertFalse( $this->oauth->is_token_expired( 'bitbucket', 60 ) );
	}

	// -------------------------------------------------------------------------
	// refresh_token() tests
	// -------------------------------------------------------------------------

	public function test_refresh_token_returns_null_without_connector_url(): void {
		$this->oauth->connector_url = '';
		$this->assertNull( $this->oauth->refresh_token( 'github' ) );
	}

	public function test_refresh_token_returns_null_for_invalid_provider(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		$this->assertNull( $this->oauth->refresh_token( 'invalid_provider' ) );
	}

	public function test_refresh_token_returns_null_without_refresh_token(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [ 'gitlab_access_token' => 'tok' ] );
		$this->assertNull( $this->oauth->refresh_token( 'gitlab' ) );
	}

	public function test_refresh_token_returns_null_on_http_error(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [ 'gitlab_access_token' => 'tok', 'gitlab_refresh_token' => 'ref' ] );

		add_filter( 'pre_http_request', static function () {
			return new WP_Error( 'http_error', 'Connection failed' );
		}, 10, 3 );

		$this->assertNull( $this->oauth->refresh_token( 'gitlab' ) );
	}

	public function test_refresh_token_returns_null_on_missing_access_token(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [ 'gitlab_access_token' => 'tok', 'gitlab_refresh_token' => 'ref' ] );

		add_filter( 'pre_http_request', static function () {
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [ 'error' => 'invalid_grant' ] ),
				'headers'  => [],
			];
		}, 10, 3 );

		$this->assertNull( $this->oauth->refresh_token( 'gitlab' ) );
	}

	public function test_refresh_token_returns_new_token_on_success(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [ 'gitlab_access_token' => 'old_tok', 'gitlab_refresh_token' => 'ref' ] );

		add_filter( 'pre_http_request', static function () {
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [
					'access_token'  => 'new_tok',
					'refresh_token' => 'new_ref',
					'expires_in'    => 7200,
				] ),
				'headers'  => [],
			];
		}, 10, 3 );

		$result = $this->oauth->refresh_token( 'gitlab' );
		$this->assertSame( 'new_tok', $result );

		$options = get_site_option( 'git_updater' );
		$this->assertSame( 'new_tok', $options['gitlab_access_token'] );
		$this->assertSame( 'new_ref', $options['gitlab_refresh_token'] );
		$this->assertSame( 7200, $options['gitlab_token_expires_in'] );
		$this->assertArrayHasKey( 'gitlab_token_acquired_at', $options );
		$this->assertSame( 'oauth', $options['gitlab_is_oauth_token'] );
	}

	public function test_refresh_token_preserves_old_refresh_when_not_rotated(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [ 'gitlab_access_token' => 'old_tok', 'gitlab_refresh_token' => 'old_ref' ] );

		add_filter( 'pre_http_request', static function () {
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [ 'access_token' => 'new_tok' ] ),
				'headers'  => [],
			];
		}, 10, 3 );

		$result = $this->oauth->refresh_token( 'gitlab' );
		$this->assertSame( 'new_tok', $result );

		$options = get_site_option( 'git_updater' );
		$this->assertSame( 'old_ref', $options['gitlab_refresh_token'] );
	}

	// -------------------------------------------------------------------------
	// fetch_token_from_connector() — updated return type
	// -------------------------------------------------------------------------

	public function test_fetch_token_returns_array_with_access_token_only(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';

		add_filter( 'pre_http_request', static function () {
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [ 'access_token' => 'tok' ] ),
				'headers'  => [],
			];
		}, 10, 3 );

		$method = new ReflectionMethod( OAuth_Connect::class, 'fetch_token_from_connector' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->oauth, 'github', 'code' );

		$this->assertIsArray( $result );
		$this->assertSame( 'tok', $result['access_token'] );
		$this->assertNull( $result['refresh_token'] );
		$this->assertNull( $result['expires_in'] );
	}

	public function test_fetch_token_returns_array_with_all_fields(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';

		add_filter( 'pre_http_request', static function () {
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [
					'access_token'  => 'tok',
					'refresh_token' => 'ref',
					'expires_in'    => 7200,
				] ),
				'headers'  => [],
			];
		}, 10, 3 );

		$method = new ReflectionMethod( OAuth_Connect::class, 'fetch_token_from_connector' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->oauth, 'gitlab', 'code' );

		$this->assertIsArray( $result );
		$this->assertSame( 'tok', $result['access_token'] );
		$this->assertSame( 'ref', $result['refresh_token'] );
		$this->assertSame( 7200, $result['expires_in'] );
	}

	// -------------------------------------------------------------------------
	// save_token() — updated behavior
	// -------------------------------------------------------------------------

	public function test_save_token_stores_refresh_token(): void {
		$method = new ReflectionMethod( OAuth_Connect::class, 'save_token' );
		$method->setAccessible( true );

		$method->invoke( $this->oauth, 'gitlab', 'tok', 'ref', null );

		$options = get_site_option( 'git_updater' );
		$this->assertSame( 'tok', $options['gitlab_access_token'] );
		$this->assertSame( 'ref', $options['gitlab_refresh_token'] );
		$this->assertSame( 'oauth', $options['gitlab_is_oauth_token'] );
	}

	public function test_save_token_stores_expires_in_and_acquired_at(): void {
		$method = new ReflectionMethod( OAuth_Connect::class, 'save_token' );
		$method->setAccessible( true );

		$method->invoke( $this->oauth, 'gitlab', 'tok', 'ref', 7200 );

		$options = get_site_option( 'git_updater' );
		$this->assertSame( 7200, $options['gitlab_token_expires_in'] );
		$this->assertArrayHasKey( 'gitlab_token_acquired_at', $options );
		$this->assertSame( 'oauth', $options['gitlab_is_oauth_token'] );
	}

	public function test_save_token_clears_refresh_token_when_null(): void {
		$method = new ReflectionMethod( OAuth_Connect::class, 'save_token' );
		$method->setAccessible( true );

		$method->invoke( $this->oauth, 'gitlab', 'tok', 'ref', null );
		$method->invoke( $this->oauth, 'gitlab', 'tok', null, null );

		$options = get_site_option( 'git_updater' );
		$this->assertArrayNotHasKey( 'gitlab_refresh_token', $options );
		$this->assertSame( 'oauth', $options['gitlab_is_oauth_token'] );
	}

	public function test_save_token_clears_expiry_when_null(): void {
		$method = new ReflectionMethod( OAuth_Connect::class, 'save_token' );
		$method->setAccessible( true );

		$method->invoke( $this->oauth, 'gitlab', 'tok', 'ref', 7200 );
		$method->invoke( $this->oauth, 'gitlab', 'tok', null, null );

		$options = get_site_option( 'git_updater' );
		$this->assertArrayNotHasKey( 'gitlab_token_expires_in', $options );
		$this->assertArrayNotHasKey( 'gitlab_token_acquired_at', $options );
		$this->assertSame( 'oauth', $options['gitlab_is_oauth_token'] );
	}

	// -------------------------------------------------------------------------
	// delete_token() — updated behavior
	// -------------------------------------------------------------------------

	public function test_delete_token_removes_all_provider_keys(): void {
		update_site_option( 'git_updater', [
			'github_access_token'       => 'tok',
			'github_refresh_token'      => 'ref',
			'github_token_expires_in'   => 7200,
			'github_token_acquired_at'  => time(),
			'github_is_oauth_token'     => 'oauth',
			'gitlab_access_token'       => 'other_tok',
		] );

		$method = new ReflectionMethod( OAuth_Connect::class, 'delete_token' );
		$method->setAccessible( true );
		$method->invoke( $this->oauth, 'github' );

		$options = get_site_option( 'git_updater' );
		$this->assertArrayNotHasKey( 'github_access_token', $options );
		$this->assertArrayNotHasKey( 'github_refresh_token', $options );
		$this->assertArrayNotHasKey( 'github_token_expires_in', $options );
		$this->assertArrayNotHasKey( 'github_token_acquired_at', $options );
		$this->assertArrayNotHasKey( 'github_is_oauth_token', $options );
		$this->assertSame( 'other_tok', $options['gitlab_access_token'] );
	}

	// -------------------------------------------------------------------------
	// handle_callback() — saves refresh token and expires_in
	// -------------------------------------------------------------------------

	public function test_handle_callback_saves_refresh_token_on_success(): void {
		if ( ! defined( 'GIT_UPDATER_OAUTH_CONNECTOR_URL' ) ) {
			define( 'GIT_UPDATER_OAUTH_CONNECTOR_URL', 'https://connector.example.com' );
		}

		$user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->maybe_grant_super_admin( $user );
		wp_set_current_user( $user );

		$_GET['provider']         = 'gitlab';
		$_GET['gu_exchange_code'] = 'test_exchange_code';

		add_filter( 'pre_http_request', static function ( $preempt, $args, $url ) {
			if ( strpos( $url, '/token' ) !== false ) {
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => wp_json_encode( [
						'access_token'  => 'test_access_token',
						'refresh_token' => 'test_refresh_token',
						'expires_in'    => 7200,
					] ),
					'headers'  => [],
				];
			}
			return $preempt;
		}, 10, 3 );

		add_filter( 'wp_redirect', static function() {
			throw new RuntimeException( 'Redirect captured' );
		} );

		try {
			$this->oauth->handle_callback();
			$this->fail( 'Expected redirect to be captured' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'Redirect captured', $e->getMessage() );
		}

		$options = get_site_option( 'git_updater' );
		$this->assertEquals( 'test_access_token', $options['gitlab_access_token'] );
		$this->assertEquals( 'test_refresh_token', $options['gitlab_refresh_token'] );
		$this->assertEquals( 7200, $options['gitlab_token_expires_in'] );
		$this->assertArrayHasKey( 'gitlab_token_acquired_at', $options );
		$this->assertSame( 'oauth', $options['gitlab_is_oauth_token'] );
	}

	// -------------------------------------------------------------------------
	// is_oauth_token() tests
	// -------------------------------------------------------------------------

	public function test_is_oauth_token_returns_false_for_unknown_provider(): void {
		$this->assertFalse( $this->oauth->is_oauth_token( 'fakehub' ) );
	}

	public function test_is_oauth_token_returns_false_when_option_missing(): void {
		update_site_option( 'git_updater', [] );
		$this->assertFalse( $this->oauth->is_oauth_token( 'github' ) );
	}

	public function test_is_oauth_token_returns_true_when_flag_set(): void {
		update_site_option( 'git_updater', [ 'github_is_oauth_token' => 'oauth' ] );
		$this->assertTrue( $this->oauth->is_oauth_token( 'github' ) );
	}

	public function test_is_oauth_token_returns_false_when_flag_explicitly_false(): void {
		update_site_option( 'git_updater', [ 'github_is_oauth_token' => false ] );
		$this->assertFalse( $this->oauth->is_oauth_token( 'github' ) );
	}

	public function test_is_oauth_token_true_after_save_token_then_false_after_delete_token(): void {
		$save = new ReflectionMethod( OAuth_Connect::class, 'save_token' );
		$save->setAccessible( true );
		$save->invoke( $this->oauth, 'bitbucket', 'tok', null, null );

		$this->assertTrue( $this->oauth->is_oauth_token( 'bitbucket' ) );

		$delete = new ReflectionMethod( OAuth_Connect::class, 'delete_token' );
		$delete->setAccessible( true );
		$delete->invoke( $this->oauth, 'bitbucket' );

		$this->assertFalse( $this->oauth->is_oauth_token( 'bitbucket' ) );
	}

	public function test_save_token_syncs_api_static_options(): void {
		API::$options = [];

		$save = new ReflectionMethod( OAuth_Connect::class, 'save_token' );
		$save->setAccessible( true );
		$save->invoke( $this->oauth, 'github', 'tok', null, null );

		$this->assertSame( 'oauth', API::$options['github_is_oauth_token'] );
		$this->assertSame( 'oauth', GitHub_API::$options['github_is_oauth_token'] );
	}

	public function test_delete_token_syncs_api_static_options(): void {
		API::$options = [ 'github_is_oauth_token' => 'oauth', 'github_access_token' => 'tok' ];
		update_site_option( 'git_updater', API::$options );

		$delete = new ReflectionMethod( OAuth_Connect::class, 'delete_token' );
		$delete->setAccessible( true );
		$delete->invoke( $this->oauth, 'github' );

		$this->assertArrayNotHasKey( 'github_is_oauth_token', API::$options );
		$this->assertArrayNotHasKey( 'github_is_oauth_token', GitHub_API::$options );
	}

	/**
	 * Regression: the OAuth flag must survive two consecutive settings-form saves.
	 *
	 * Pre-fix, boolean `true` was coerced to string '1' by sanitize_text_field on the
	 * first save, then stripped by filter_options' array_filter on the second save.
	 * With the string sentinel 'oauth', neither step removes it.
	 */
	public function test_is_oauth_token_survives_two_settings_form_saves(): void {
		$save = new ReflectionMethod( OAuth_Connect::class, 'save_token' );
		$save->setAccessible( true );
		$save->invoke( $this->oauth, 'github', 'tok', null, null );

		$filter = new ReflectionMethod( Settings::class, 'filter_options' );
		$filter->setAccessible( true );

		$run_save = function () use ( $filter ) {
			$_POST['_wpnonce']    = wp_create_nonce( 'git_updater-options' );
			$_POST['option_page'] = 'git_updater';
			$_POST['git_updater'] = [];
			Base::$options        = get_site_option( 'git_updater', [] );
			$settings             = new Settings();
			$options              = $filter->invoke( $settings );
			update_site_option( 'git_updater', $settings->sanitize( $options ) );
			unset( $_POST['_wpnonce'], $_POST['option_page'], $_POST['git_updater'] );
		};

		$run_save();
		$run_save();

		Base::$options = get_site_option( 'git_updater', [] );
		$this->assertTrue( $this->oauth->is_oauth_token( 'github' ) );
		$this->assertSame( 'oauth', Base::$options['github_is_oauth_token'] );
	}
}
