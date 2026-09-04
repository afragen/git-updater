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
		unset( $_GET['provider'], $_GET['gu_exchange_code'], $_GET['site_state'], $_GET['_wpnonce'], $_POST['provider'], $_POST['_wpnonce'] );
		foreach ( [ 'github', 'gitlab', 'bitbucket', 'gitea' ] as $provider ) {
			delete_site_transient( "gu_oauth_state_$provider" );
			delete_site_transient( "gu_oauth_refresh_lock_$provider" );
			delete_site_transient( "gu_oauth_refresh_result_$provider" );
		}
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'wp_redirect' );
	}

	/**
	 * Tear down test
	 */
	public function tear_down(): void {
		delete_site_option( 'git_updater' );
		unset( $_GET['provider'], $_GET['gu_exchange_code'], $_GET['site_state'], $_GET['_wpnonce'], $_POST['provider'], $_POST['_wpnonce'] );
		foreach ( [ 'github', 'gitlab', 'bitbucket', 'gitea' ] as $provider ) {
			delete_site_transient( "gu_oauth_state_$provider" );
			delete_site_transient( "gu_oauth_refresh_lock_$provider" );
			delete_site_transient( "gu_oauth_refresh_result_$provider" );
		}
		remove_all_actions( 'admin_post_gu_oauth_callback' );
		remove_all_actions( 'admin_post_gu_oauth_disconnect' );
		remove_all_actions( 'admin_post_gu_remove_token' );
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'wp_redirect' );
		remove_all_filters( 'gu_debug_token_refresh' );
		remove_all_filters( 'wp_mail' );
		remove_all_filters( 'cron_schedules' );
		remove_all_actions( 'gu_oauth_revoke_notify' );
		wp_clear_scheduled_hook( 'gu_oauth_revoke_notify' );
		unset( $GLOBALS['gu_fs'] );
		parent::tear_down();
	}

	/**
	 * Test fetch_token_from_connector returns null when connector not configured.
	 */
	public function test_fetch_token_from_connector_returns_null_without_config(): void {
		$this->oauth->connector_url = '';
		$method = new ReflectionMethod( OAuth_Connect::class, 'fetch_token_from_connector' );
		PHP_VERSION_ID < 80100 && $method->setAccessible( true );

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
		PHP_VERSION_ID < 80100 && $method->setAccessible( true );

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
		$this->assertNotFalse( has_action( 'admin_post_gu_remove_token', [ $this->oauth, 'handle_remove_token' ] ) );
		$this->assertNotFalse( has_action( 'gu_oauth_revoke_notify', [ $this->oauth, 'remind_admin_of_token_revocation' ] ) );
		$this->assertNotFalse( wp_next_scheduled( 'gu_oauth_revoke_notify' ) );
	}

	/**
	 * Test load_hooks does not schedule a duplicate cron event.
	 */
	public function test_load_hooks_does_not_schedule_duplicate_cron(): void {
		wp_clear_scheduled_hook( 'gu_oauth_revoke_notify' );
		$this->oauth->load_hooks();
		$first = wp_next_scheduled( 'gu_oauth_revoke_notify' );
		$this->assertNotFalse( $first );

		$this->oauth->load_hooks();
		$this->assertSame( $first, wp_next_scheduled( 'gu_oauth_revoke_notify' ) );
	}

	/**
	 * Test load_hooks schedules the revocation reminder on the daily schedule.
	 */
	public function test_load_hooks_schedules_revocation_notify_daily(): void {
		wp_clear_scheduled_hook( 'gu_oauth_revoke_notify' );
		$this->oauth->load_hooks();
		$event = wp_get_scheduled_event( 'gu_oauth_revoke_notify' );
		$this->assertNotFalse( $event );
		$this->assertSame( 'daily', $event->schedule );
		$this->assertLessThanOrEqual( DAY_IN_SECONDS, $event->timestamp - time() );
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

		set_site_transient( 'gu_oauth_state_github', 'test_state', 600 );
		$_GET['provider']         = 'github';
		$_GET['gu_exchange_code'] = 'test_exchange_code';
		$_GET['site_state']       = 'test_state';
		$_GET['_wpnonce']         = wp_create_nonce( 'gu_oauth_callback_github' );

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

		set_site_transient( 'gu_oauth_state_github', 'test_state', 600 );
		$_GET['provider']         = 'github';
		$_GET['gu_exchange_code'] = 'test_exchange_code';
		$_GET['site_state']       = 'test_state';
		$_GET['_wpnonce']         = wp_create_nonce( 'gu_oauth_callback_github' );

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

		set_site_transient( 'gu_oauth_state_github', 'github_state', 600 );
		$_GET['provider']         = 'github';
		$_GET['gu_exchange_code'] = 'github_code';
		$_GET['site_state']       = 'github_state';
		$_GET['_wpnonce']         = wp_create_nonce( 'gu_oauth_callback_github' );
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

		set_site_transient( 'gu_oauth_state_gitlab', 'gitlab_state', 600 );
		$_GET['provider']         = 'gitlab';
		$_GET['gu_exchange_code'] = 'gitlab_code';
		$_GET['site_state']       = 'gitlab_state';
		$_GET['_wpnonce']         = wp_create_nonce( 'gu_oauth_callback_gitlab' );

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
		$_GET['_wpnonce'] = wp_create_nonce( 'gu_oauth_callback_invalid_provider' );

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
		$_GET['_wpnonce'] = wp_create_nonce( 'gu_oauth_callback_github' );

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
	 * Test handle_callback rejects when site_state is missing or does not match stored transient (CSRF protection).
	 */
	public function test_handle_callback_rejects_invalid_state(): void {
		$user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->maybe_grant_super_admin( $user );
		wp_set_current_user( $user );

		set_site_transient( 'gu_oauth_state_github', 'expected_state', 600 );
		$_GET['provider']         = 'github';
		$_GET['gu_exchange_code'] = 'test_exchange_code';
		$_GET['site_state']       = 'wrong_state';
		$_GET['_wpnonce']         = wp_create_nonce( 'gu_oauth_callback_github' );

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
		// Transient must be consumed even on rejection (single-use semantics).
		$this->assertFalse( get_site_transient( 'gu_oauth_state_github' ) );
	}

	/**
	 * Test handle_callback rejects when _wpnonce is missing.
	 */
	public function test_handle_callback_rejects_missing_nonce(): void {
		$user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->maybe_grant_super_admin( $user );
		wp_set_current_user( $user );

		set_site_transient( 'gu_oauth_state_github', 'test_state', 600 );
		$_GET['provider']         = 'github';
		$_GET['gu_exchange_code'] = 'test_code';
		$_GET['site_state']       = 'test_state';
		// Intentionally omit $_GET['_wpnonce'].

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
	 * Test handle_callback rejects invalid nonce.
	 */
	public function test_handle_callback_rejects_invalid_nonce(): void {
		$user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->maybe_grant_super_admin( $user );
		wp_set_current_user( $user );

		set_site_transient( 'gu_oauth_state_github', 'test_state', 600 );
		$_GET['provider']         = 'github';
		$_GET['gu_exchange_code'] = 'test_code';
		$_GET['site_state']       = 'test_state';
		$_GET['_wpnonce']         = 'invalid_nonce';

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
		PHP_VERSION_ID < 80100 && $method->setAccessible( true );

		$url = $method->invoke( $this->oauth, 'github' );

		$this->assertStringContainsString( 'network/admin-post.php', $url );
		$this->assertStringContainsString( 'action=gu_oauth_callback', $url );
		$this->assertStringContainsString( '_wpnonce', $url );
	}

	/**
	 * Test get_callback_url uses admin_url on single-site (the : branch of
	 * the is_multisite() ternary). Skipped under multisite, where the
	 * ? branch (network_admin_url) runs instead.
	 *
	 * @group ms-excluded
	 */
	public function test_get_callback_url_uses_admin_on_single_site(): void {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single-site only test' );
		}

		$method = new ReflectionMethod( OAuth_Connect::class, 'get_callback_url' );
		PHP_VERSION_ID < 80100 && $method->setAccessible( true );

		$url = $method->invoke( $this->oauth, 'github' );

		$this->assertStringContainsString( 'admin-post.php', $url );
		$this->assertStringNotContainsString( 'network/', $url );
		$this->assertStringContainsString( 'action=gu_oauth_callback', $url );
		$this->assertStringContainsString( '_wpnonce', $url );
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

	public function test_is_token_expired_returns_false_when_github_token_is_fresh(): void {
		update_site_option( 'git_updater', [
			'github_access_token'       => 'ghu_tok',
			'github_token_expires_in'   => 28800,
			'github_token_acquired_at'  => time(),
		] );
		$this->assertFalse( $this->oauth->is_token_expired( 'github' ) );
	}

	public function test_is_token_expired_returns_true_when_github_token_is_expired(): void {
		update_site_option( 'git_updater', [
			'github_access_token'       => 'ghu_tok',
			'github_token_expires_in'   => 28800,
			'github_token_acquired_at'  => time() - 28801,
		] );
		$this->assertTrue( $this->oauth->is_token_expired( 'github' ) );
	}

	public function test_is_token_expired_returns_true_when_github_token_within_buffer(): void {
		update_site_option( 'git_updater', [
			'github_access_token'       => 'ghu_tok',
			'github_token_expires_in'   => 28800,
			'github_token_acquired_at'  => time() - 28600,
		] );
		// 200s remaining, buffer=300 → expired
		$this->assertTrue( $this->oauth->is_token_expired( 'github' ) );
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

	/**
	 * A provider flagged as revoked (prior real grant failure) must short-circuit
	 * without any HTTP call. Covers OAuth_Connect.php:502-507.
	 */
	public function test_refresh_token_returns_null_when_already_revoked(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [
			'github_access_token'         => 'tok',
			'github_refresh_token'        => 'ref',
			'gu_oauth_revoked_github'     => time(),
		] );

		$http_called = false;
		add_filter( 'pre_http_request', function () use ( &$http_called ) {
			$http_called = true;
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [ 'access_token' => 'new_tok' ] ),
				'headers'  => [],
			];
		}, 10, 3 );

		$this->assertNull( $this->oauth->refresh_token( 'github' ) );
		$this->assertFalse( $http_called, 'HTTP request should not be made when the token is already revoked.' );
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

	public function test_refresh_token_github_success_stores_rotated_refresh_token(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [
			'github_access_token'       => 'ghu_old',
			'github_refresh_token'      => 'ghr_old',
			'github_token_expires_in'   => 28800,
			'github_token_acquired_at'  => time() - 28801,
		] );

		add_filter( 'pre_http_request', static function ( $preempt, $args, $url ) {
			if ( strpos( $url, '/git-updater/github/oauth/refresh' ) !== false ) {
				return [
					'response' => [ 'code' => 200 ],
					'body'     => wp_json_encode( [
						'access_token'  => 'ghu_new',
						'refresh_token' => 'ghr_new',
						'expires_in'    => 28800,
					] ),
					'headers'  => [],
				];
			}
			return $preempt;
		}, 10, 3 );

		$result = $this->oauth->refresh_token( 'github' );
		$this->assertSame( 'ghu_new', $result );

		$options = get_site_option( 'git_updater' );
		$this->assertSame( 'ghu_new', $options['github_access_token'] );
		$this->assertSame( 'ghr_new', $options['github_refresh_token'] );
		$this->assertSame( 28800, $options['github_token_expires_in'] );
		$this->assertArrayHasKey( 'github_token_acquired_at', $options );
		$this->assertSame( 'oauth', $options['github_is_oauth_token'] );
		$this->assertSame( 'success', get_site_transient( 'gu_oauth_refresh_result_github' ) );
	}

	public function test_refresh_token_github_failure_deletes_token(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [
			'github_access_token'       => 'ghu_old',
			'github_refresh_token'      => 'ghr_old',
			'github_token_expires_in'   => 28800,
			'github_token_acquired_at'  => time() - 28801,
		] );

		add_filter( 'pre_http_request', static function ( $preempt, $args, $url ) {
			if ( strpos( $url, '/git-updater/github/oauth/refresh' ) !== false ) {
				return [
					'response' => [ 'code' => 401 ],
					'body'     => wp_json_encode( [
						'error'             => 'bad_refresh_token',
						'error_description' => 'The refresh token is invalid or expired.',
					] ),
					'headers'  => [],
				];
			}
			return $preempt;
		}, 10, 3 );

		$this->assertNull( $this->oauth->refresh_token( 'github' ) );
		// Token is deleted so the user is prompted to re-authorize.
		$persist_options = get_site_option( 'git_updater', [] );
		$this->assertNotEmpty( $persist_options['gu_oauth_revoked_github'] );

		$options = get_site_option( 'git_updater' );
		$this->assertArrayNotHasKey( 'github_access_token', $options );
		$this->assertArrayNotHasKey( 'github_refresh_token', $options );
		$this->assertArrayNotHasKey( 'github_is_oauth_token', $options );
	}

	// -------------------------------------------------------------------------
	// refresh_token() race condition / lock tests
	// -------------------------------------------------------------------------

	public function test_refresh_token_returns_null_when_lock_exists(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [ 'github_access_token' => 'tok', 'github_refresh_token' => 'ref' ] );
		set_site_transient( 'gu_oauth_refresh_lock_github', time(), 30 );

		$http_called = false;
		add_filter( 'pre_http_request', function () use ( &$http_called ) {
			$http_called = true;
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [ 'access_token' => 'new_tok' ] ),
				'headers'  => [],
			];
		}, 10, 3 );

		$result = $this->oauth->refresh_token( 'github' );

		$this->assertNull( $result );
		$this->assertFalse( $http_called, 'HTTP request should not be made when lock is held.' );
		// Lock should not be consumed.
		$this->assertNotFalse( get_site_transient( 'gu_oauth_refresh_lock_github' ) );
	}

	public function test_refresh_token_returns_cached_token_on_success_result(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [ 'gitlab_access_token' => 'refreshed_tok', 'gitlab_refresh_token' => 'ref' ] );
		set_site_transient( 'gu_oauth_refresh_result_gitlab', 'success', 60 );

		$http_called = false;
		add_filter( 'pre_http_request', function () use ( &$http_called ) {
			$http_called = true;
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [ 'access_token' => 'should_not_be_used' ] ),
				'headers'  => [],
			];
		}, 10, 3 );

		$result = $this->oauth->refresh_token( 'gitlab' );

		$this->assertSame( 'refreshed_tok', $result );
		$this->assertFalse( $http_called, 'HTTP request should not be made when success result exists.' );
	}

	public function test_refresh_token_syncs_static_options_on_success_result(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		Base::$options = [];
		API::$options  = [];
		update_site_option( 'git_updater', [ 'github_access_token' => 'cached_tok', 'github_refresh_token' => 'ref' ] );
		set_site_transient( 'gu_oauth_refresh_result_github', 'success', 60 );

		$this->oauth->refresh_token( 'github' );

		$this->assertSame( 'cached_tok', Base::$options['github_access_token'] );
		$this->assertSame( 'cached_tok', API::$options['github_access_token'] );
	}

	public function test_refresh_token_attempts_refresh_on_failure_result(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [ 'github_access_token' => 'old_tok', 'github_refresh_token' => 'ref' ] );
		set_site_transient( 'gu_oauth_refresh_result_github', 'failure', 60 );

		add_filter( 'pre_http_request', static function () {
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [ 'access_token' => 'new_tok' ] ),
				'headers'  => [],
			];
		}, 10, 3 );

		$result = $this->oauth->refresh_token( 'github' );

		$this->assertSame( 'new_tok', $result );
		// Failure transient was deleted before the attempt; success result is now set.
		$this->assertSame( 'success', get_site_transient( 'gu_oauth_refresh_result_github' ) );
	}

	public function test_refresh_token_sets_lock_and_clears_on_success(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [ 'gitlab_access_token' => 'old_tok', 'gitlab_refresh_token' => 'ref' ] );

		add_filter( 'pre_http_request', static function () {
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [ 'access_token' => 'new_tok', 'expires_in' => 7200 ] ),
				'headers'  => [],
			];
		}, 10, 3 );

		$result = $this->oauth->refresh_token( 'gitlab' );

		$this->assertSame( 'new_tok', $result );
		$this->assertFalse( get_site_transient( 'gu_oauth_refresh_lock_gitlab' ), 'Lock should be cleared after success.' );
		$this->assertSame( 'success', get_site_transient( 'gu_oauth_refresh_result_gitlab' ) );
	}

	public function test_refresh_token_sets_failure_result_on_http_error(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [ 'github_access_token' => 'tok', 'github_refresh_token' => 'ref' ] );

		add_filter( 'pre_http_request', static function () {
			return new WP_Error( 'http_error', 'Connection failed' );
		}, 10, 3 );

		$result = $this->oauth->refresh_token( 'github' );

		$this->assertNull( $result );
		$this->assertFalse( get_site_transient( 'gu_oauth_refresh_lock_github' ), 'Lock should be cleared after failure.' );
		$this->assertSame( 'failure', get_site_transient( 'gu_oauth_refresh_result_github' ) );
	}

	public function test_refresh_token_sets_failure_result_on_empty_response(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [ 'github_access_token' => 'tok', 'github_refresh_token' => 'ref' ] );

		add_filter( 'pre_http_request', static function () {
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [ 'error' => 'invalid_grant' ] ),
				'headers'  => [],
			];
		}, 10, 3 );

		$result = $this->oauth->refresh_token( 'github' );

		$this->assertNull( $result );
		$this->assertFalse( get_site_transient( 'gu_oauth_refresh_lock_github' ), 'Lock should be cleared after failure.' );
		$this->assertSame( 'failure', get_site_transient( 'gu_oauth_refresh_result_github' ) );

		// Empty access_token means the token is dead: delete it and flag re-auth.
		$options = get_site_option( 'git_updater' );
		$this->assertArrayNotHasKey( 'github_access_token', $options );
		$this->assertArrayNotHasKey( 'github_refresh_token', $options );
		$this->assertNotEmpty( $options['gu_oauth_revoked_github'] );
	}

	public function test_refresh_token_deletes_token_and_sets_error_cache_for_gitlab(): void {
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

		$options = get_site_option( 'git_updater' );
		$this->assertArrayNotHasKey( 'gitlab_access_token', $options );
		$this->assertArrayNotHasKey( 'gitlab_refresh_token', $options );
		$this->assertNotEmpty( $options['gu_oauth_revoked_gitlab'] );
	}

	public function test_delete_token_clears_refresh_transients(): void {
		set_site_transient( 'gu_oauth_refresh_lock_github', time(), 30 );
		set_site_transient( 'gu_oauth_refresh_result_github', 'success', 60 );

		update_site_option( 'git_updater', [ 'github_access_token' => 'tok' ] );

		$method = new ReflectionMethod( OAuth_Connect::class, 'delete_token' );
		PHP_VERSION_ID < 80100 && $method->setAccessible( true );
		$method->invoke( $this->oauth, 'github' );

		$this->assertFalse( get_site_transient( 'gu_oauth_refresh_lock_github' ) );
		$this->assertFalse( get_site_transient( 'gu_oauth_refresh_result_github' ) );
	}

	public function test_refresh_token_debug_log_on_lock_contention(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [ 'github_access_token' => 'tok', 'github_refresh_token' => 'ref' ] );
		set_site_transient( 'gu_oauth_refresh_lock_github', time(), 30 );

		add_filter( 'gu_debug_token_refresh', '__return_true' );

		$log = $this->with_error_log_capture( function () {
			$result = $this->oauth->refresh_token( 'github' );
			$this->assertNull( $result );
		} );

		$this->assertStringContainsString( 'already in progress', $log );
	}

	public function test_refresh_token_debug_log_on_result_reuse(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [ 'github_access_token' => 'tok', 'github_refresh_token' => 'ref' ] );
		set_site_transient( 'gu_oauth_refresh_result_github', 'success', 60 );

		add_filter( 'gu_debug_token_refresh', '__return_true' );

		$log = $this->with_error_log_capture( function () {
			$result = $this->oauth->refresh_token( 'github' );
			$this->assertSame( 'tok', $result );
		} );

		$this->assertStringContainsString( 'Reusing successful token refresh', $log );
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
		PHP_VERSION_ID < 80100 && $method->setAccessible( true );

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
		PHP_VERSION_ID < 80100 && $method->setAccessible( true );

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
		PHP_VERSION_ID < 80100 && $method->setAccessible( true );

		$method->invoke( $this->oauth, 'gitlab', 'tok', 'ref', null );

		$options = get_site_option( 'git_updater' );
		$this->assertSame( 'tok', $options['gitlab_access_token'] );
		$this->assertSame( 'ref', $options['gitlab_refresh_token'] );
		$this->assertSame( 'oauth', $options['gitlab_is_oauth_token'] );
	}

	public function test_save_token_stores_expires_in_and_acquired_at(): void {
		$method = new ReflectionMethod( OAuth_Connect::class, 'save_token' );
		PHP_VERSION_ID < 80100 && $method->setAccessible( true );

		$method->invoke( $this->oauth, 'gitlab', 'tok', 'ref', 7200 );

		$options = get_site_option( 'git_updater' );
		$this->assertSame( 7200, $options['gitlab_token_expires_in'] );
		$this->assertArrayHasKey( 'gitlab_token_acquired_at', $options );
		$this->assertSame( 'oauth', $options['gitlab_is_oauth_token'] );
	}

	public function test_save_token_clears_refresh_token_when_null(): void {
		$method = new ReflectionMethod( OAuth_Connect::class, 'save_token' );
		PHP_VERSION_ID < 80100 && $method->setAccessible( true );

		$method->invoke( $this->oauth, 'gitlab', 'tok', 'ref', null );
		$method->invoke( $this->oauth, 'gitlab', 'tok', null, null );

		$options = get_site_option( 'git_updater' );
		$this->assertArrayNotHasKey( 'gitlab_refresh_token', $options );
		$this->assertSame( 'oauth', $options['gitlab_is_oauth_token'] );
	}

	public function test_save_token_clears_expiry_when_null(): void {
		$method = new ReflectionMethod( OAuth_Connect::class, 'save_token' );
		PHP_VERSION_ID < 80100 && $method->setAccessible( true );

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
		PHP_VERSION_ID < 80100 && $method->setAccessible( true );
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

		set_site_transient( 'gu_oauth_state_gitlab', 'test_state', 600 );
		$_GET['provider']         = 'gitlab';
		$_GET['gu_exchange_code'] = 'test_exchange_code';
		$_GET['_wpnonce']         = wp_create_nonce( 'gu_oauth_callback_gitlab' );
		$_GET['site_state']       = 'test_state';

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

	/**
	 * A successful reconnect must clear the re-authorization error transient.
	 */
	public function test_handle_callback_clears_error_cache_on_success(): void {
		if ( ! defined( 'GIT_UPDATER_OAUTH_CONNECTOR_URL' ) ) {
			define( 'GIT_UPDATER_OAUTH_CONNECTOR_URL', 'https://connector.example.com' );
		}

		$user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->maybe_grant_super_admin( $user );
		wp_set_current_user( $user );

		$persist_options                        = get_site_option( 'git_updater', [] );
		$persist_options['gu_oauth_revoked_github'] = time();
		update_site_option( 'git_updater', $persist_options );
		set_site_transient( 'gu_oauth_state_github', 'test_state', 600 );
		$_GET['provider']         = 'github';
		$_GET['gu_exchange_code'] = 'test_exchange_code';
		$_GET['_wpnonce']         = wp_create_nonce( 'gu_oauth_callback_github' );
		$_GET['site_state']       = 'test_state';

		add_filter( 'pre_http_request', static function ( $preempt, $args, $url ) {
			if ( strpos( $url, '/token' ) !== false ) {
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => wp_json_encode( [ 'access_token' => 'test_access_token' ] ),
					'headers'  => [],
				];
			}
			return $preempt;
		}, 10, 3 );

		add_filter( 'wp_redirect', static function () {
			throw new RuntimeException( 'Redirect captured' );
		} );

		try {
			$this->oauth->handle_callback();
			$this->fail( 'Expected redirect to be captured' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'Redirect captured', $e->getMessage() );
		}

		$persist_options = get_site_option( 'git_updater', [] );
		$this->assertArrayNotHasKey( 'gu_oauth_revoked_github', $persist_options, 'Error flag should be cleared after successful reconnect.' );
	}

	/**
	 * The settings page must show the re-authorization notice while the persistent flag is set.
	 */
	public function test_settings_shows_oauth_revocation_notice(): void {
		$persist_options                        = get_site_option( 'git_updater', [] );
		$persist_options['gu_oauth_revoked_github'] = time();
		update_site_option( 'git_updater', $persist_options );

		$settings = new Settings();
		$method   = new ReflectionMethod( Settings::class, 'admin_page_notices' );
		PHP_VERSION_ID < 80100 && $method->setAccessible( true );

		ob_start();
		$method->invoke( $settings );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'access was revoked', $output );
		$this->assertStringContainsString( 'Connect button', $output );

		$persist_options = get_site_option( 'git_updater', [] );
		unset( $persist_options['gu_oauth_revoked_github'] );
		update_site_option( 'git_updater', $persist_options );
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
		PHP_VERSION_ID < 80100 && $save->setAccessible( true );
		$save->invoke( $this->oauth, 'bitbucket', 'tok', null, null );

		$this->assertTrue( $this->oauth->is_oauth_token( 'bitbucket' ) );

		$delete = new ReflectionMethod( OAuth_Connect::class, 'delete_token' );
		PHP_VERSION_ID < 80100 && $delete->setAccessible( true );
		$delete->invoke( $this->oauth, 'bitbucket' );

		$this->assertFalse( $this->oauth->is_oauth_token( 'bitbucket' ) );
	}

	public function test_save_token_syncs_api_static_options(): void {
		API::$options = [];

		$save = new ReflectionMethod( OAuth_Connect::class, 'save_token' );
		PHP_VERSION_ID < 80100 && $save->setAccessible( true );
		$save->invoke( $this->oauth, 'github', 'tok', null, null );

		$this->assertSame( 'oauth', API::$options['github_is_oauth_token'] );
		$this->assertSame( 'oauth', GitHub_API::$options['github_is_oauth_token'] );
	}

	public function test_delete_token_syncs_api_static_options(): void {
		API::$options = [ 'github_is_oauth_token' => 'oauth', 'github_access_token' => 'tok' ];
		update_site_option( 'git_updater', API::$options );

		$delete = new ReflectionMethod( OAuth_Connect::class, 'delete_token' );
		PHP_VERSION_ID < 80100 && $delete->setAccessible( true );
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
		PHP_VERSION_ID < 80100 && $save->setAccessible( true );
		$save->invoke( $this->oauth, 'github', 'tok', null, null );

		$filter = new ReflectionMethod( Settings::class, 'filter_options' );
		PHP_VERSION_ID < 80100 && $filter->setAccessible( true );

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

	// -------------------------------------------------------------------------
	// render_remove_token_field() tests
	// -------------------------------------------------------------------------

	public function test_render_remove_token_field_with_invalid_provider(): void {
		ob_start();
		$this->oauth->render_remove_token_field( [ 'provider' => 'invalid_provider' ] );
		$output = ob_get_clean();
		$this->assertEmpty( $output );
	}

	public function test_render_remove_token_field_returns_nothing_without_token(): void {
		delete_site_option( 'git_updater' );
		Base::$options = [];

		ob_start();
		$this->oauth->render_remove_token_field( [ 'provider' => 'github' ] );
		$output = ob_get_clean();
		$this->assertEmpty( $output );
	}

	public function test_render_remove_token_field_returns_nothing_for_oauth_token(): void {
		update_site_option( 'git_updater', [
			'github_access_token'  => 'oauth_tok',
			'github_is_oauth_token' => 'oauth',
		] );

		ob_start();
		$this->oauth->render_remove_token_field( [ 'provider' => 'github' ] );
		$output = ob_get_clean();
		$this->assertEmpty( $output );
	}

	public function test_render_remove_token_field_shows_remove_button_for_pat(): void {
		update_site_option( 'git_updater', [ 'github_access_token' => 'manual_pat' ] );

		ob_start();
		$this->oauth->render_remove_token_field( [ 'provider' => 'github' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'Remove Token', $output );
		$this->assertStringContainsString( 'gu_remove_token', $output );
	}

	public function test_render_remove_token_field_shows_button_for_non_oauth_provider(): void {
		update_site_option( 'git_updater', [ 'gitlab_access_token' => 'manual_pat' ] );

		ob_start();
		$this->oauth->render_remove_token_field( [ 'provider' => 'gitlab' ] );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'Remove Token', $output );
		$this->assertStringContainsString( 'gu_remove_token', $output );
	}

	// -------------------------------------------------------------------------
	// handle_remove_token() tests
	// -------------------------------------------------------------------------

	public function test_handle_remove_token_requires_admin_capability(): void {
		$user = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user );

		$_GET['provider'] = 'github';
		$_GET['_wpnonce'] = wp_create_nonce( 'gu_remove_token_github' );

		$this->expectException( WPDieException::class );
		$this->oauth->handle_remove_token();
	}

	public function test_handle_remove_token_removes_token_from_options(): void {
		$user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->maybe_grant_super_admin( $user );
		wp_set_current_user( $user );

		update_site_option( 'git_updater', [ 'github_access_token' => 'manual_pat' ] );

		$_GET['provider'] = 'github';
		$_GET['_wpnonce'] = wp_create_nonce( 'gu_remove_token_github' );

		add_filter( 'wp_redirect', function () {
			throw new RuntimeException( 'Redirect captured' );
		} );

		try {
			$this->oauth->handle_remove_token();
			$this->fail( 'Expected redirect to be captured' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'Redirect captured', $e->getMessage() );
		}

		$options = get_site_option( 'git_updater' );
		$this->assertArrayNotHasKey( 'github_access_token', $options );
	}

	public function test_handle_remove_token_ignores_oauth_token(): void {
		$user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->maybe_grant_super_admin( $user );
		wp_set_current_user( $user );

		update_site_option( 'git_updater', [
			'github_access_token'  => 'oauth_tok',
			'github_is_oauth_token' => 'oauth',
		] );

		$_GET['provider'] = 'github';
		$_GET['_wpnonce'] = wp_create_nonce( 'gu_remove_token_github' );

		add_filter( 'wp_redirect', function () {
			throw new RuntimeException( 'Redirect captured' );
		} );

		try {
			$this->oauth->handle_remove_token();
			$this->fail( 'Expected redirect to be captured' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'Redirect captured', $e->getMessage() );
		}

		// handle_remove_token only unsets the access_token key, not the OAuth metadata.
		$options = get_site_option( 'git_updater' );
		$this->assertArrayNotHasKey( 'github_access_token', $options );
		$this->assertSame( 'oauth', $options['github_is_oauth_token'] );
	}

	public function test_handle_remove_token_redirects_with_token_removed_status(): void {
		$user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->maybe_grant_super_admin( $user );
		wp_set_current_user( $user );

		update_site_option( 'git_updater', [ 'github_access_token' => 'manual_pat' ] );

		$_GET['provider'] = 'github';
		$_GET['_wpnonce'] = wp_create_nonce( 'gu_remove_token_github' );

		$captured_url = null;
		add_filter( 'wp_redirect', function ( $url ) use ( &$captured_url ) {
			$captured_url = $url;
			throw new RuntimeException( 'Redirect captured' );
		} );

		try {
			$this->oauth->handle_remove_token();
			$this->fail( 'Expected redirect to be captured' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'Redirect captured', $e->getMessage() );
		}

		$this->assertNotNull( $captured_url );
		$this->assertStringContainsString( 'token_removed', $captured_url );
	}

	public function test_handle_remove_token_syncs_static_options(): void {
		$user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->maybe_grant_super_admin( $user );
		wp_set_current_user( $user );

		API::$options = [ 'github_access_token' => 'manual_pat' ];
		update_site_option( 'git_updater', API::$options );

		$_GET['provider'] = 'github';
		$_GET['_wpnonce'] = wp_create_nonce( 'gu_remove_token_github' );

		add_filter( 'wp_redirect', function () {
			throw new RuntimeException( 'Redirect captured' );
		} );

		try {
			$this->oauth->handle_remove_token();
			$this->fail( 'Expected redirect to be captured' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'Redirect captured', $e->getMessage() );
		}

		$this->assertArrayNotHasKey( 'github_access_token', API::$options );
	}

	// -------------------------------------------------------------------------
	// gu_debug_token_refresh filter tests
	// -------------------------------------------------------------------------

	/**
	 * Redirect error_log to a temp file, run a callback, return captured output.
	 *
	 * @param callable $callback Code to execute while capturing error_log.
	 * @return string Captured error_log output.
	 */
	private function with_error_log_capture( callable $callback ): string {
		$tmp_file = tempnam( sys_get_temp_dir(), 'gu_err_' );
		$original = ini_get( 'error_log' );
		ini_set( 'error_log', $tmp_file );

		try {
			$callback();
		} finally {
			ini_set( 'error_log', $original );
			$contents = file_get_contents( $tmp_file );
			unlink( $tmp_file );
		}

		return $contents ?: '';
	}

	public function test_gu_debug_token_refresh_filter_is_applied(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [
			'github_access_token'  => 'tok',
			'github_refresh_token' => 'ref',
		] );

		$filter_applied = false;
		add_filter(
			'gu_debug_token_refresh',
			function () use ( &$filter_applied ) {
				$filter_applied = true;
				return false;
			}
		);

		add_filter( 'pre_http_request', static function () {
			return new WP_Error( 'http_error', 'Connection failed' );
		}, 10, 3 );

		$this->oauth->refresh_token( 'github' );

		$this->assertTrue( $filter_applied, 'gu_debug_token_refresh filter callback should be invoked.' );
	}

	public function test_refresh_token_logs_on_http_error_when_filter_true(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [
			'github_access_token'  => 'tok',
			'github_refresh_token' => 'ref',
		] );

		add_filter( 'gu_debug_token_refresh', '__return_true' );

		add_filter( 'pre_http_request', static function () {
			return new WP_Error( 'http_error', 'Connection failed' );
		}, 10, 3 );

		$log = $this->with_error_log_capture( function () {
			$result = $this->oauth->refresh_token( 'github' );
			$this->assertNull( $result );
		} );

		$this->assertStringContainsString( 'Token refresh failed for github', $log );
		$this->assertStringContainsString( 'Connection failed', $log );
	}

	public function test_refresh_token_logs_on_missing_token_when_filter_true(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [
			'github_access_token'  => 'tok',
			'github_refresh_token' => 'ref',
		] );

		add_filter( 'gu_debug_token_refresh', '__return_true' );

		add_filter( 'pre_http_request', static function () {
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [ 'error' => 'invalid_grant' ] ),
				'headers'  => [],
			];
		}, 10, 3 );

		$log = $this->with_error_log_capture( function () {
			$result = $this->oauth->refresh_token( 'github' );
			$this->assertNull( $result );
		} );

		$this->assertStringContainsString( 'No access token received', $log );
		$this->assertStringContainsString( 'Response body:', $log );
	}

	public function test_refresh_token_logs_on_success_when_filter_true(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [
			'github_access_token'  => 'old_tok',
			'github_refresh_token' => 'ref',
		] );

		add_filter( 'gu_debug_token_refresh', '__return_true' );

		add_filter( 'pre_http_request', static function () {
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [
					'access_token' => 'new_tok',
					'expires_in'   => 7200,
				] ),
				'headers' => [],
			];
		}, 10, 3 );

		$log = $this->with_error_log_capture( function () {
			$result = $this->oauth->refresh_token( 'github' );
			$this->assertSame( 'new_tok', $result );
		} );

		$this->assertStringContainsString( 'Token refreshed for github', $log );
		$this->assertStringContainsString( '2 hours', $log );
	}

	public function test_refresh_token_no_log_when_filter_false(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [
			'github_access_token'  => 'tok',
			'github_refresh_token' => 'ref',
		] );

		add_filter( 'pre_http_request', static function () {
			return new WP_Error( 'http_error', 'Connection failed' );
		}, 10, 3 );

		$log = $this->with_error_log_capture( function () {
			$this->oauth->refresh_token( 'github' );
		} );

		$this->assertStringNotContainsString( 'Token refresh failed', $log );
	}

	// -------------------------------------------------------------------------
	// OAuth revocation email notification tests
	// -------------------------------------------------------------------------

	/**
	 * Register a wp_mail filter that captures send attempts.
	 *
	 * @param array<int, array<string, mixed>> $mails Captured mail args.
	 * @return void
	 */
	private function capture_wp_mail( array &$mails ): void {
		add_filter( 'wp_mail', static function ( $args ) use ( &$mails ) {
			$mails[] = $args;
			return true;
		} );
	}

	/**
	 * Stub gu_fs() so can_use_premium_code() returns the given value.
	 *
	 * @param bool $premium Whether the user can use premium code.
	 * @return void
	 */
	private function stub_gu_fs( bool $premium ): void {
		$GLOBALS['gu_fs'] = new class( $premium ) {
			/** @var bool */
			private $premium;

			public function __construct( bool $premium ) {
				$this->premium = $premium;
			}

			public function can_use_premium_code(): bool {
				return $this->premium;
			}
		};
	}

	/**
	 * Set access tokens for all providers except the given one, so the cron
	 * reminder only acts on that provider.
	 *
	 * @param string $provider Provider slug to leave token-less.
	 * @return void
	 */
	private function set_tokens_for_other_providers( string $provider ): void {
		$options = get_site_option( 'git_updater', [] );
		foreach ( array_keys( OAuth_Connect::PROVIDERS ) as $p ) {
			if ( $p !== $provider ) {
				$options[ $p . '_access_token' ] = 'tok_' . $p;
			}
		}
		update_site_option( 'git_updater', $options );
	}

	/**
	 * Test get_running_providers only returns GitHub in the test environment
	 * (no API plugins active).
	 */
	public function test_get_running_providers_returns_github_only_in_tests(): void {
		$this->assertSame( [ 'github' ], $this->oauth->get_running_providers() );
	}

	/**
	 * Test notify_admin_of_token_revocation returns early for an unknown provider.
	 */
	public function test_notify_returns_early_for_unknown_provider(): void {
		$mails = [];
		$this->capture_wp_mail( $mails );

		$method = new ReflectionMethod( OAuth_Connect::class, 'notify_admin_of_token_revocation' );
		PHP_VERSION_ID < 80100 && $method->setAccessible( true );
		$method->invoke( $this->oauth, 'invalid_provider' );

		$this->assertCount( 0, $mails );
	}

	/**
	 * Test notify_admin_of_token_revocation returns early when a token is present.
	 */
	public function test_notify_returns_early_when_token_present(): void {
		update_site_option( 'git_updater', [
			'github_access_token'      => 'tok',
			'gu_oauth_notified_github' => time() - 2 * DAY_IN_SECONDS,
		] );
		$this->stub_gu_fs( true );

		$mails = [];
		$this->capture_wp_mail( $mails );

		$method = new ReflectionMethod( OAuth_Connect::class, 'notify_admin_of_token_revocation' );
		PHP_VERSION_ID < 80100 && $method->setAccessible( true );
		$method->invoke( $this->oauth, 'github' );

		$this->assertCount( 0, $mails );
	}

	/**
	 * Test notify_admin_of_token_revocation returns early when git_updater_skip_oauth_reminder
	 * filter returns true. Covers OAuth_Connect.php:684.
	 */
	public function test_notify_returns_early_when_skip_filter_true(): void {
		// No token stored so the method would normally proceed to send email.
		delete_site_option( 'git_updater' );

		add_filter( 'git_updater_skip_oauth_reminder', '__return_true' );

		$mails = [];
		$this->capture_wp_mail( $mails );

		$method = new ReflectionMethod( OAuth_Connect::class, 'notify_admin_of_token_revocation' );
		PHP_VERSION_ID < 80100 && $method->setAccessible( true );
		$method->invoke( $this->oauth, 'github' );

		remove_all_filters( 'git_updater_skip_oauth_reminder' );

		$this->assertCount( 0, $mails );
	}

	/**
	 * Test immediate email is sent when token refresh fails and deletes the token.
	 */
	public function test_refresh_failure_sends_immediate_email(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [
			'github_access_token'      => 'ghu_old',
			'github_refresh_token'     => 'ghr_old',
			'github_token_expires_in'  => 28800,
			'github_token_acquired_at' => time() - 28801,
		] );

		$mails = [];
		$this->capture_wp_mail( $mails );

		add_filter( 'pre_http_request', static function ( $preempt, $args, $url ) {
			if ( strpos( $url, '/git-updater/github/oauth/refresh' ) !== false ) {
				return [
					'response' => [ 'code' => 401 ],
					'body'     => wp_json_encode( [
						'error'             => 'bad_refresh_token',
						'error_description' => 'The refresh token is invalid or expired.',
					] ),
					'headers'  => [],
				];
			}
			return $preempt;
		}, 10, 3 );

		$this->assertNull( $this->oauth->refresh_token( 'github' ) );

		$this->assertCount( 1, $mails );
		$mail = $mails[0];
		$this->assertSame( get_option( 'admin_email' ), $mail['to'] );
		$this->assertStringContainsString( 'GitHub OAuth', $mail['subject'] );
		$this->assertStringContainsString( 'unable to refresh', $mail['message'] );
		$this->assertStringContainsString( 'revoked', $mail['message'] );
		$this->assertStringContainsString( 'subtab=github', $mail['message'] );

		$options = get_site_option( 'git_updater', [] );
		$this->assertNotEmpty( $options['gu_oauth_notified_github'] );
	}

	/**
	 * Test that a refresh failure using the connector's wp_send_json_error()
	 * wrapped shape ({success:false,data:{error:...}}) is correctly detected as
	 * a grant error → token deleted, revoked flag set, and admin email sent.
	 * Regression test for the top-level vs data.error parsing bug.
	 */
	public function test_refresh_failure_recognizes_wrapped_error_and_deletes_token(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [
			'bitbucket_access_token'       => 'bb_old',
			'bitbucket_refresh_token'      => 'bbr_old',
			'bitbucket_token_expires_in'   => 3600,
			'bitbucket_token_acquired_at'  => time() - 3601,
		] );

		$mails = [];
		$this->capture_wp_mail( $mails );

		add_filter( 'pre_http_request', static function ( $preempt, $args, $url ) {
			if ( strpos( $url, '/git-updater/bitbucket/oauth/refresh' ) !== false ) {
				return [
					'response' => [ 'code' => 401 ],
					'body'     => wp_json_encode( [
						'success' => false,
						'data'    => [
							'error'             => 'invalid_request',
							'error_description' => 'Invalid refresh_token',
						],
					] ),
					'headers'  => [],
				];
			}
			return $preempt;
		}, 10, 3 );

		$this->assertNull( $this->oauth->refresh_token( 'bitbucket' ) );

		// Token must be revoked + deleted so the Connect button reappears.
		$options = get_site_option( 'git_updater', [] );
		$this->assertArrayNotHasKey( 'bitbucket_access_token', $options );
		$this->assertArrayNotHasKey( 'bitbucket_refresh_token', $options );
		$this->assertNotEmpty( $options['gu_oauth_revoked_bitbucket'] );

		// Admin must be notified.
		$this->assertCount( 1, $mails );
		$mail = $mails[0];
		$this->assertStringContainsString( 'Bitbucket OAuth', $mail['subject'] );
		$this->assertStringContainsString( 'unable to refresh', $mail['message'] );
		$this->assertStringContainsString( 'revoked', $mail['message'] );
	}

	/**
	 * Test no duplicate email on repeated failure within the daily window.
	 */
	public function test_refresh_failure_does_not_send_duplicate_email(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [
			'github_access_token'      => 'ghu_old',
			'github_refresh_token'     => 'ghr_old',
			'github_token_expires_in'  => 28800,
			'github_token_acquired_at' => time() - 28801,
		] );

		$mails = [];
		$this->capture_wp_mail( $mails );

		add_filter( 'pre_http_request', static function ( $preempt, $args, $url ) {
			if ( strpos( $url, '/git-updater/github/oauth/refresh' ) !== false ) {
				return [
					'response' => [ 'code' => 401 ],
					'body'     => wp_json_encode( [ 'error' => 'bad_refresh_token' ] ),
					'headers'  => [],
				];
			}
			return $preempt;
		}, 10, 3 );

		$this->assertNull( $this->oauth->refresh_token( 'github' ) );
		$this->assertCount( 1, $mails );

		$this->set_tokens_for_other_providers( 'github' );

		// Second call: token already deleted; direct reminder path is gated by daily timestamp.
		$this->oauth->remind_admin_of_token_revocation();
		$this->assertCount( 1, $mails );
	}

	/**
	 * Test cron reminder sends while Connect button displays (revoked flag set).
	 */
	public function test_cron_reminder_sends_while_revoked(): void {
		$persist_options = [
			'gu_oauth_revoked_github'   => time() - DAY_IN_SECONDS,
			'gu_oauth_notified_github'  => time() - 2 * DAY_IN_SECONDS,
		];
		update_site_option( 'git_updater', $persist_options );
		$this->set_tokens_for_other_providers( 'github' );

		$mails = [];
		$this->capture_wp_mail( $mails );

		$this->oauth->remind_admin_of_token_revocation();

		$this->assertCount( 1, $mails );
		$this->assertStringContainsString( 'unable to refresh', $mails[0]['message'] );

		$options = get_site_option( 'git_updater', [] );
		$this->assertGreaterThan( $persist_options['gu_oauth_notified_github'], (int) $options['gu_oauth_notified_github'] );
	}

	/**
	 * Test cron reminder skips when a token is present.
	 */
	public function test_cron_reminder_skips_when_token_present(): void {
		update_site_option( 'git_updater', [
			'github_access_token'          => 'tok',
			'gu_oauth_notified_github'     => time() - 2 * DAY_IN_SECONDS,
		] );
		$this->set_tokens_for_other_providers( 'github' );

		$mails = [];
		$this->capture_wp_mail( $mails );

		$this->oauth->remind_admin_of_token_revocation();

		$this->assertCount( 0, $mails );
	}

	/**
	 * Test cron reminder sends "token is empty" message to premium users.
	 */
	public function test_cron_reminder_sends_empty_token_message_to_premium_user(): void {
		update_site_option( 'git_updater', [
			'gu_oauth_notified_github' => time() - 2 * DAY_IN_SECONDS,
		] );
		$this->set_tokens_for_other_providers( 'github' );
		$this->stub_gu_fs( true );

		$mails = [];
		$this->capture_wp_mail( $mails );

		$this->oauth->remind_admin_of_token_revocation();

		$this->assertCount( 1, $mails );
		$this->assertStringContainsString( 'token is empty', $mails[0]['message'] );
		$this->assertStringNotContainsString( 'unable to refresh', $mails[0]['message'] );

		$options = get_site_option( 'git_updater', [] );
		$this->assertArrayNotHasKey( 'gu_oauth_revoked_github', $options );
	}

	/**
	 * Test cron reminder does NOT send empty-token message to free users.
	 */
	public function test_cron_reminder_does_not_send_empty_token_message_to_free_user(): void {
		update_site_option( 'git_updater', [
			'gu_oauth_notified_github' => time() - 2 * DAY_IN_SECONDS,
		] );
		$this->set_tokens_for_other_providers( 'github' );
		$this->stub_gu_fs( false );

		$mails = [];
		$this->capture_wp_mail( $mails );

		$this->oauth->remind_admin_of_token_revocation();

		$this->assertCount( 0, $mails );
	}

	/**
	 * Test cron reminder skips when notified within the last day.
	 */
	public function test_cron_reminder_skips_when_notified_recently(): void {
		update_site_option( 'git_updater', [
			'gu_oauth_notified_github' => time() - HOUR_IN_SECONDS,
		] );
		$this->set_tokens_for_other_providers( 'github' );
		$this->stub_gu_fs( true );

		$mails = [];
		$this->capture_wp_mail( $mails );

		$this->oauth->remind_admin_of_token_revocation();

		$this->assertCount( 0, $mails );
	}

	/**
	 * Test no email when token present even with a backdated notified timestamp.
	 */
	public function test_cron_reminder_skips_when_token_present_but_backdated(): void {
		update_site_option( 'git_updater', [
			'github_access_token'      => 'tok',
			'gu_oauth_notified_github' => time() - 2 * DAY_IN_SECONDS,
		] );
		$this->set_tokens_for_other_providers( 'github' );
		$this->stub_gu_fs( true );

		$mails = [];
		$this->capture_wp_mail( $mails );

		$this->oauth->remind_admin_of_token_revocation();

		$this->assertCount( 0, $mails );
	}

	/**
	 * Test no email when token refresh succeeds.
	 */
	public function test_refresh_success_sends_no_email(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [ 'github_access_token' => 'old_tok', 'github_refresh_token' => 'ref' ] );

		$mails = [];
		$this->capture_wp_mail( $mails );

		add_filter( 'pre_http_request', static function () {
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [ 'access_token' => 'new_tok' ] ),
				'headers'  => [],
			];
		}, 10, 3 );

		$this->assertSame( 'new_tok', $this->oauth->refresh_token( 'github' ) );
		$this->assertCount( 0, $mails );
	}

	/**
	 * Test no email on plain HTTP error (WP_Error) — token is not deleted.
	 */
	public function test_http_error_sends_no_email(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [ 'github_access_token' => 'tok', 'github_refresh_token' => 'ref' ] );

		$mails = [];
		$this->capture_wp_mail( $mails );

		add_filter( 'pre_http_request', static function () {
			return new WP_Error( 'http_error', 'Connection failed' );
		}, 10, 3 );

		$this->assertNull( $this->oauth->refresh_token( 'github' ) );
		$this->assertCount( 0, $mails );
	}

	/**
	 * Test reconnect clears the notified timestamp.
	 */
	public function test_reconnect_clears_notified_timestamp(): void {
		if ( ! defined( 'GIT_UPDATER_OAUTH_CONNECTOR_URL' ) ) {
			define( 'GIT_UPDATER_OAUTH_CONNECTOR_URL', 'https://connector.example.com' );
		}

		$user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->maybe_grant_super_admin( $user );
		wp_set_current_user( $user );

		$persist_options                        = get_site_option( 'git_updater', [] );
		$persist_options['gu_oauth_revoked_github']  = time();
		$persist_options['gu_oauth_notified_github'] = time();
		update_site_option( 'git_updater', $persist_options );

		set_site_transient( 'gu_oauth_state_github', 'test_state', 600 );
		$_GET['provider']         = 'github';
		$_GET['gu_exchange_code'] = 'test_exchange_code';
		$_GET['_wpnonce']         = wp_create_nonce( 'gu_oauth_callback_github' );
		$_GET['site_state']       = 'test_state';

		add_filter( 'pre_http_request', static function ( $preempt, $args, $url ) {
			if ( strpos( $url, '/token' ) !== false ) {
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => wp_json_encode( [ 'access_token' => 'test_access_token' ] ),
					'headers'  => [],
				];
			}
			return $preempt;
		}, 10, 3 );

		add_filter( 'wp_redirect', static function () {
			throw new RuntimeException( 'Redirect captured' );
		} );

		try {
			$this->oauth->handle_callback();
			$this->fail( 'Expected redirect to be captured' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'Redirect captured', $e->getMessage() );
		}

		$options = get_site_option( 'git_updater', [] );
		$this->assertArrayNotHasKey( 'gu_oauth_revoked_github', $options );
		$this->assertArrayNotHasKey( 'gu_oauth_notified_github', $options );
	}

	// -------------------------------------------------------------------------
	// fetch_token_from_connector() — rate-limited branch (OAuth_Connect.php:398)
	// -------------------------------------------------------------------------

	/**
	 * A connector 429 short-circuits to null rather than being treated as a
	 * revocation. Covers OAuth_Connect.php:395-398.
	 */
	public function test_fetch_token_from_connector_returns_null_on_rate_limit(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';

		add_filter( 'pre_http_request', static function () {
			return [
				'response' => [ 'code' => 429 ],
				'body'     => wp_json_encode( [ 'error' => 'rate_limited' ] ),
				'headers'  => [],
			];
		}, 10, 3 );

		$method = new ReflectionMethod( OAuth_Connect::class, 'fetch_token_from_connector' );
		PHP_VERSION_ID < 80100 && $method->setAccessible( true );

		$this->assertNull( $method->invoke( $this->oauth, 'github', 'code' ) );
	}

	// -------------------------------------------------------------------------
	// save_token() — stores refresh-token lifetime metadata (OAuth_Connect.php:446-447)
	// -------------------------------------------------------------------------

	/**
	 * A non-null refresh_token_expires_in must persist both the lifetime and the
	 * acquisition timestamp. Covers OAuth_Connect.php:445-450.
	 */
	public function test_save_token_stores_refresh_token_lifetime(): void {
		$method = new ReflectionMethod( OAuth_Connect::class, 'save_token' );
		PHP_VERSION_ID < 80100 && $method->setAccessible( true );

		$method->invoke( $this->oauth, 'github', 'tok', 'ref', null, 7200 );

		$options = get_site_option( 'git_updater', [] );
		$this->assertSame( 7200, $options['github_refresh_token_expires_in'] );
		$this->assertArrayHasKey( 'github_refresh_token_acquired_at', $options );
	}

	// -------------------------------------------------------------------------
	// refresh_token() — Retry-After short-circuit (OAuth_Connect.php:512)
	// -------------------------------------------------------------------------

	/**
	 * A pending refresh-retry backoff must skip the HTTP round-trip entirely.
	 * Covers OAuth_Connect.php:510-513.
	 */
	public function test_refresh_token_short_circuits_on_retry_after(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [ 'github_access_token' => 'tok', 'github_refresh_token' => 'ref' ] );
		set_site_transient( 'gu_oauth_refresh_retry_github', time() + 300, 300 );

		$http_called = false;
		add_filter( 'pre_http_request', function () use ( &$http_called ) {
			$http_called = true;
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [ 'access_token' => 'new_tok' ] ),
				'headers'  => [],
			];
		}, 10, 3 );

		$this->assertNull( $this->oauth->refresh_token( 'github' ) );
		$this->assertFalse( $http_called, 'HTTP request should not be made while retry backoff is active.' );
	}

	// -------------------------------------------------------------------------
	// refresh_token() — rate-limited: preserve token, honor Retry-After
	// -------------------------------------------------------------------------

	/**
	 * 429 with Retry-After keeps the token, sets the backoff transient, does NOT
	 * revoke. Covers OAuth_Connect.php:574-583.
	 */
	public function test_refresh_token_rate_limited_preserves_token_and_sets_retry(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [ 'github_access_token' => 'tok', 'github_refresh_token' => 'ref' ] );

		add_filter( 'pre_http_request', static function () {
			return [
				'response' => [ 'code' => 429 ],
				'body'     => wp_json_encode( [ 'error' => 'rate_limited' ] ),
				'headers'  => [ 'Retry-After' => 60 ],
			];
		}, 10, 3 );

		$this->assertNull( $this->oauth->refresh_token( 'github' ) );

		$this->assertNotFalse( get_site_transient( 'gu_oauth_refresh_retry_github' ), 'Retry-After backoff should be set.' );
		$this->assertFalse( get_site_transient( 'gu_oauth_refresh_lock_github' ), 'Lock should be cleared.' );
		$this->assertSame( 'failure', get_site_transient( 'gu_oauth_refresh_result_github' ) );

		$options = get_site_option( 'git_updater', [] );
		$this->assertSame( 'tok', $options['github_access_token'], 'Token must NOT be deleted on rate limit.' );
		$this->assertArrayNotHasKey( 'gu_oauth_revoked_github', $options );
	}

	/**
	 * A rate-limit reason delivered in the body (code 200) is still a rate limit,
	 * not a revocation. Covers the error-name condition in 571-574.
	 */
	public function test_refresh_token_rate_limited_by_body_error_preserves_token(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [ 'github_access_token' => 'tok', 'github_refresh_token' => 'ref' ] );

		add_filter( 'pre_http_request', static function () {
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [ 'error' => 'rate_limit_exceeded' ] ),
				'headers'  => [],
			];
		}, 10, 3 );

		$this->assertNull( $this->oauth->refresh_token( 'github' ) );

		$options = get_site_option( 'git_updater', [] );
		$this->assertSame( 'tok', $options['github_access_token'] );
		$this->assertArrayNotHasKey( 'gu_oauth_revoked_github', $options );
	}

	/**
	 * With the debug filter on, a rate-limited refresh logs the backoff message.
	 * Covers OAuth_Connect.php:580-582.
	 */
	public function test_refresh_token_rate_limited_logs_when_debug_true(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [ 'github_access_token' => 'tok', 'github_refresh_token' => 'ref' ] );

		add_filter( 'gu_debug_token_refresh', '__return_true' );
		add_filter( 'pre_http_request', static function () {
			return [
				'response' => [ 'code' => 429 ],
				'body'     => wp_json_encode( [ 'error' => 'rate_limited' ] ),
				'headers'  => [ 'Retry-After' => 60 ],
			];
		}, 10, 3 );

		$log = $this->with_error_log_capture( function () {
			$this->assertNull( $this->oauth->refresh_token( 'github' ) );
		} );

		$this->assertStringContainsString( 'rate limited', $log );
	}

	// -------------------------------------------------------------------------
	// refresh_token() — empty access_token with no reason (preserve, no revoke)
	// -------------------------------------------------------------------------

	/**
	 * A 200 with no access_token and no recognized error must NOT be treated as
	 * a revocation. Covers OAuth_Connect.php:609-617.
	 */
	public function test_refresh_token_empty_access_token_no_error_preserves_token(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [ 'github_access_token' => 'tok', 'github_refresh_token' => 'ref' ] );

		add_filter( 'pre_http_request', static function () {
			return [
				'response' => [ 'code' => 200 ],
				'body'     => '{}',
				'headers'  => [],
			];
		}, 10, 3 );

		$this->assertNull( $this->oauth->refresh_token( 'github' ) );

		$this->assertFalse( get_site_transient( 'gu_oauth_refresh_lock_github' ), 'Lock should be cleared.' );
		$this->assertSame( 'failure', get_site_transient( 'gu_oauth_refresh_result_github' ) );

		$options = get_site_option( 'git_updater', [] );
		$this->assertSame( 'tok', $options['github_access_token'], 'Token must NOT be deleted without a reason.' );
		$this->assertArrayNotHasKey( 'gu_oauth_revoked_github', $options );
	}

	/**
	 * With the debug filter on, the empty-access-token path logs both messages.
	 * Covers OAuth_Connect.php:613-616.
	 */
	public function test_refresh_token_empty_access_token_logs_when_debug_true(): void {
		$this->oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [ 'github_access_token' => 'tok', 'github_refresh_token' => 'ref' ] );

		add_filter( 'gu_debug_token_refresh', '__return_true' );
		add_filter( 'pre_http_request', static function () {
			return [
				'response' => [ 'code' => 200 ],
				'body'     => '{}',
				'headers'  => [],
			];
		}, 10, 3 );

		$log = $this->with_error_log_capture( function () {
			$this->assertNull( $this->oauth->refresh_token( 'github' ) );
		} );

		$this->assertStringContainsString( 'No access token received', $log );
		$this->assertStringContainsString( 'Response body:', $log );
	}

	// -------------------------------------------------------------------------
	// is_token_expired() — refresh-token lifetime branch (OAuth_Connect.php:689-691)
	// -------------------------------------------------------------------------

	/**
	 * When the access token is fresh but the refresh token is about to expire,
	 * the provider is considered expired so a refresh is attempted first.
	 * Covers OAuth_Connect.php:686-692.
	 */
	public function test_is_token_expired_true_when_refresh_token_within_buffer(): void {
		update_site_option( 'git_updater', [
			'gitlab_access_token'              => 'tok',
			'gitlab_token_expires_in'          => 7200,
			'gitlab_token_acquired_at'         => time(),
			'gitlab_refresh_token_expires_in'  => 7200,
			'gitlab_refresh_token_acquired_at' => time() - 7000,
		] );

		// Access token has 7200s remaining (fresh); refresh token has 200s left
		// (≤ 300 buffer) → expired so a refresh is attempted first.
		$this->assertTrue( $this->oauth->is_token_expired( 'gitlab' ) );
	}
}
