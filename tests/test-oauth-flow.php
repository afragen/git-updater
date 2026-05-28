<?php
/**
 * Tests for OAuth_Flow — reusable OAuth authorization-code + PKCE flow.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\OAuth\OAuth_Flow;

/**
 * Class Test_OAuth_Flow
 *
 * Covers reusable OAuth helper methods for Git API providers.
 */
class Test_OAuth_Flow extends WP_UnitTestCase {
	private function make_flow( array $overrides = [] ): OAuth_Flow {
		return new OAuth_Flow(
			array_merge(
				[
					'provider'               => 'example',
					'label'                  => 'Example',
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
				],
				$overrides
			)
		);
	}

	public function tear_down(): void {
		remove_all_filters( 'gu_test_oauth_credentials' );
		remove_all_filters( 'gu_test_bb_credentials' );
		remove_all_filters( 'wp_redirect' );
		remove_all_filters( 'pre_http_request' );
		unset(
			$GLOBALS['current_screen'],
			$_GET['gu_example_oauth_start'],
			$_GET['gu_example_oauth_callback'],
			$_GET['gu_example_oauth'],
			$_GET['_wpnonce'],
			$_GET['state'],
			$_GET['code'],
			$_GET['gu_bitbucket_oauth_start'],
			$_GET['gu_bitbucket_oauth_callback'],
			$_GET['gu_bitbucket_oauth']
		);
		delete_site_option( 'git_updater' );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Set admin screen context so is_admin() returns true in CLI tests.
	 */
	private function set_admin_screen(): void {
		set_current_screen( 'dashboard' );
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

	public function test_provider_config_includes_all_api_addon_hosts(): void {
		// GitHub is the only provider tested here — add-on plugins
		// are not loaded in this environment.
		$providers = [ 'github' ];

		foreach ( $providers as $provider ) {
			$config = OAuth_Flow::get_provider_config( $provider );

			$this->assertSame( $provider, $config['provider'] );
			$this->assertNotEmpty( $config['option_name'] );
			$this->assertNotEmpty( $config['authorize_url'] );
			$this->assertNotEmpty( $config['token_url'] );
			$this->assertNotEmpty( $config['credentials_filter'] );
			$this->assertNotEmpty( $config['callback_arg'] );
		}
	}

	public function test_for_provider_allows_self_hosted_endpoint_overrides(): void {
		add_filter(
			'gu_oauth_provider_config',
			static function ( $config, $provider ) {
				if ( 'gitea' === $provider ) {
					return [
						'provider'               => 'gitea',
						'label'                  => 'Gitea',
						'option_name'            => 'gitea_access_token',
						'authorize_url'          => '/login/oauth/authorize',
						'token_url'              => '/login/oauth/access_token',
						'default_scope'          => 'read:repository',
						'credentials_filter'     => 'gu_gitea_oauth_credentials',
						'client_id_constant'     => 'GU_GITEA_OAUTH_CLIENT_ID',
						'client_secret_constant' => 'GU_GITEA_OAUTH_CLIENT_SECRET',
						'scope_constant'         => 'GU_GITEA_OAUTH_SCOPE',
						'start_arg'              => 'gu_gitea_oauth_start',
						'callback_arg'           => 'gu_gitea_oauth_callback',
						'status_arg'             => 'gu_gitea_oauth',
						'nonce_action'           => 'gu-gitea-oauth-start',
					];
				}

				return $config;
			},
			10,
			3
		);

		$flow = OAuth_Flow::for_provider(
			'gitea',
			'https://example.test/wp-admin/options-general.php?page=git-updater',
			[
				'authorize_url' => 'https://git.example.test/login/oauth/authorize',
				'token_url'     => 'https://git.example.test/login/oauth/access_token',
			]
		);

		$this->assertSame( 'gu_gitea_oauth_' . md5( 'state-value' ), $flow->get_transient_key( 'state-value' ) );
		$this->assertStringContainsString( 'gu_gitea_oauth_callback=1', $flow->get_callback_url() );
	}

	public function test_get_start_url_includes_nonce_and_start_arg(): void {
		$flow = $this->make_flow();
		$url  = $flow->get_start_url();

		$this->assertStringContainsString( 'gu_example_oauth_start=1', $url );
		$this->assertStringContainsString( '_wpnonce=', $url );
	}

	public function test_get_status_returns_empty_when_no_query_arg(): void {
		$flow   = $this->make_flow();
		$status = $flow->get_status();

		$this->assertSame( '', $status );
	}

	public function test_get_status_returns_sanitized_value(): void {
		$flow                         = $this->make_flow();
		$_GET['gu_example_oauth']    = 'success';
		$this->assertSame( 'success', $flow->get_status() );

		$_GET['gu_example_oauth']    = 'error-nonce';
		$this->assertSame( 'error-nonce', $flow->get_status() );

		$_GET['gu_example_oauth']    = 'invalid!@#key';
		$this->assertSame( 'invalidkey', $flow->get_status() );
	}

	public function test_get_credentials_returns_defaults_when_no_constants_or_filter(): void {
		$flow        = $this->make_flow(
			[
				'credentials_filter' => '',
			]
		);
		$credentials = $flow->get_credentials();

		$this->assertSame( '', $credentials['client_id'] );
		$this->assertSame( '', $credentials['client_secret'] );
		$this->assertSame( 'repo', $credentials['scope'] );
	}

	public function test_get_credentials_reads_from_defined_constants(): void {
		if ( ! defined( 'GU_TEST_CREDS_CLIENT_ID' ) ) {
			define( 'GU_TEST_CREDS_CLIENT_ID', 'my-client-id' );
		}
		if ( ! defined( 'GU_TEST_CREDS_CLIENT_SECRET' ) ) {
			define( 'GU_TEST_CREDS_CLIENT_SECRET', 'my-client-secret' );
		}
		if ( ! defined( 'GU_TEST_CREDS_SCOPE' ) ) {
			define( 'GU_TEST_CREDS_SCOPE', 'read_repo' );
		}

		$flow = $this->make_flow(
			[
				'credentials_filter'     => '',
				'client_id_constant'     => 'GU_TEST_CREDS_CLIENT_ID',
				'client_secret_constant' => 'GU_TEST_CREDS_CLIENT_SECRET',
				'scope_constant'         => 'GU_TEST_CREDS_SCOPE',
			]
		);

		$credentials = $flow->get_credentials();

		$this->assertSame( 'my-client-id', $credentials['client_id'] );
		$this->assertSame( 'my-client-secret', $credentials['client_secret'] );
		$this->assertSame( 'read_repo', $credentials['scope'] );
	}

	public function test_get_credentials_filter_overrides_constants(): void {
		if ( ! defined( 'GU_TEST_FILTER_OVERRIDES_ID' ) ) {
			define( 'GU_TEST_FILTER_OVERRIDES_ID', 'from-constant' );
		}

		add_filter(
			'gu_test_oauth_credentials',
			static function ( $credentials ) {
				$credentials['client_id'] = 'from-filter';

				return $credentials;
			}
		);

		$flow = $this->make_flow(
			[
				'client_id_constant' => 'GU_TEST_FILTER_OVERRIDES_ID',
			]
		);

		$credentials = $flow->get_credentials();

		$this->assertSame( 'from-filter', $credentials['client_id'] );
	}

	public function test_render_authorize_controls_success_status(): void {
		$flow                      = $this->make_flow();
		$_GET['gu_example_oauth'] = 'success';

		ob_start();
		$flow->render_authorize_controls();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'OAuth token updated from Example.', $output );
	}

	public function test_render_authorize_controls_error_status(): void {
		$flow                      = $this->make_flow();
		$_GET['gu_example_oauth'] = 'error-token';

		ob_start();
		$flow->render_authorize_controls();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Example OAuth was not completed', $output );
	}

	public function test_render_authorize_controls_no_client_id_shows_instructions(): void {
		$flow = $this->make_flow(
			[
				'client_id_constant' => 'GU_EXAMPLE_ID',
				'credentials_filter' => 'gu_test_oauth_credentials',
			]
		);

		ob_start();
		$flow->render_authorize_controls();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'GU_EXAMPLE_ID', $output );
		$this->assertStringContainsString( 'gu_test_oauth_credentials', $output );
		$this->assertStringNotContainsString( 'Authorize via', $output );
	}

	public function test_render_authorize_controls_with_client_id_shows_button(): void {
		add_filter(
			'gu_test_oauth_credentials',
			static function ( $credentials ) {
				$credentials['client_id'] = 'test-client';

				return $credentials;
			}
		);

		$flow = $this->make_flow(
			[
				'label' => 'Example',
			]
		);

		ob_start();
		$flow->render_authorize_controls();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'gu_example_oauth_start', $output );
		$this->assertStringContainsString( 'Authorize via Example OAuth', $output );
	}

	public function test_maybe_handle_flow_starts_flow_with_valid_nonce(): void {
		$this->set_admin_screen();
		wp_set_current_user( 1 );

		add_filter(
			'gu_test_oauth_credentials',
			static function ( $credentials ) {
				$credentials['client_id'] = 'test-client';

				return $credentials;
			}
		);

		$_GET['gu_example_oauth_start'] = '1';
		$_GET['_wpnonce']               = wp_create_nonce( 'gu-example-oauth-start' );

		$flow         = $this->make_flow();
		$redirect_url = null;
		$redirect_hit = false;

		add_filter(
			'wp_redirect',
			static function ( $url ) use ( &$redirect_url, &$redirect_hit ) {
				$redirect_url = $url;
				$redirect_hit = true;

				return false;
			}
		);

		$flow->maybe_handle_flow();

		$this->assertTrue( $redirect_hit );
		$this->assertStringContainsString( 'provider.test/oauth/authorize', $redirect_url );
		$this->assertStringContainsString( 'client_id=test-client', $redirect_url );
		$this->assertStringContainsString( 'code_challenge_method=S256', $redirect_url );
		$this->assertStringContainsString( 'state=', $redirect_url );
	}

	public function test_maybe_handle_flow_redirects_error_nonce_on_bad_nonce(): void {
		$this->set_admin_screen();
		wp_set_current_user( 1 );
		add_filter( 'allowed_redirect_hosts', [ $this, 'allow_test_host' ] );

		$_GET['gu_example_oauth_start'] = '1';
		$_GET['_wpnonce']               = 'invalid-nonce';

		$flow           = $this->make_flow();
		$redirected_url = null;

		add_filter(
			'wp_redirect',
			static function ( $url ) use ( &$redirected_url ) {
				$redirected_url = $url;

				return false;
			}
		);

		$flow->maybe_handle_flow();

		$this->assertIsString( $redirected_url );
		$this->assertStringContainsString( 'gu_example_oauth=error-nonce', $redirected_url );
	}

	public function allow_test_host( array $hosts ): array {
		$hosts[] = 'example.test';

		return $hosts;
	}

	public function test_maybe_handle_flow_redirects_error_client_id_when_empty(): void {
		$this->set_admin_screen();
		wp_set_current_user( 1 );
		add_filter( 'allowed_redirect_hosts', [ $this, 'allow_test_host' ] );

		$_GET['gu_example_oauth_start'] = '1';
		$_GET['_wpnonce']               = wp_create_nonce( 'gu-example-oauth-start' );

		$flow           = $this->make_flow();
		$redirected_url = null;

		add_filter(
			'wp_redirect',
			static function ( $url ) use ( &$redirected_url ) {
				$redirected_url = $url;

				return false;
			}
		);

		$flow->maybe_handle_flow();

		$this->assertIsString( $redirected_url );
		$this->assertStringContainsString( 'gu_example_oauth=error-client-id', $redirected_url );
	}

	public function test_maybe_handle_flow_redirects_error_callback_when_state_missing(): void {
		$this->set_admin_screen();
		wp_set_current_user( 1 );
		add_filter( 'allowed_redirect_hosts', [ $this, 'allow_test_host' ] );

		$_GET['gu_example_oauth_callback'] = '1';

		$flow           = $this->make_flow();
		$redirected_url = null;

		add_filter(
			'wp_redirect',
			static function ( $url ) use ( &$redirected_url ) {
				$redirected_url = $url;

				return false;
			}
		);

		$flow->maybe_handle_flow();

		$this->assertIsString( $redirected_url );
		$this->assertStringContainsString( 'gu_example_oauth=error-callback', $redirected_url );
	}

	public function test_maybe_handle_flow_redirects_error_state_when_transient_missing(): void {
		$this->set_admin_screen();
		wp_set_current_user( 1 );
		add_filter( 'allowed_redirect_hosts', [ $this, 'allow_test_host' ] );

		$_GET['gu_example_oauth_callback'] = '1';
		$_GET['state']                     = 'missing-transient-state';
		$_GET['code']                      = 'test-code';

		$flow           = $this->make_flow();
		$redirected_url = null;

		add_filter(
			'wp_redirect',
			static function ( $url ) use ( &$redirected_url ) {
				$redirected_url = $url;

				return false;
			}
		);

		$flow->maybe_handle_flow();

		$this->assertIsString( $redirected_url );
		$this->assertStringContainsString( 'gu_example_oauth=error-state', $redirected_url );
	}

	public function test_maybe_handle_flow_completes_and_redirects_success(): void {
		$this->set_admin_screen();
		wp_set_current_user( 1 );
		add_filter( 'allowed_redirect_hosts', [ $this, 'allow_test_host' ] );

		$state    = 'test-state-value';
		$verifier = 'test-verifier-value';

		set_transient(
			'gu_example_oauth_' . md5( $state ),
			[ 'code_verifier' => $verifier ],
			15 * MINUTE_IN_SECONDS
		);

		$_GET['gu_example_oauth_callback'] = '1';
		$_GET['state']                     = $state;
		$_GET['code']                      = 'test-auth-code';

		$flow = $this->make_flow();

		add_filter(
			'gu_test_oauth_credentials',
			static function ( $credentials ) {
				$credentials['client_id'] = 'test-client';

				return $credentials;
			}
		);

		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) {
				if ( str_contains( $url, 'provider.test/oauth/token' ) ) {
					return [
						'response' => [ 'code' => 200 ],
						'body'     => json_encode( [ 'access_token' => 'gho_test-token-value' ] ),
					];
				}

				return $preempt;
			},
			10,
			3
		);

		$redirected_url = null;
		add_filter(
			'wp_redirect',
			static function ( $url ) use ( &$redirected_url ) {
				$redirected_url = $url;

				return false;
			}
		);

		$flow->maybe_handle_flow();

		$this->assertIsString( $redirected_url );
		$this->assertStringContainsString( 'gu_example_oauth=success', $redirected_url );

		$options = get_site_option( 'git_updater', [] );
		$this->assertIsArray( $options );
		$this->assertSame( 'gho_test-token-value', $options['example_access_token'] );
	}

	public function test_maybe_handle_flow_redirects_error_token_on_exchange_failure(): void {
		$this->set_admin_screen();
		wp_set_current_user( 1 );
		add_filter( 'allowed_redirect_hosts', [ $this, 'allow_test_host' ] );

		$state    = 'test-state-fail';
		$verifier = 'test-verifier-fail';

		set_transient(
			'gu_example_oauth_' . md5( $state ),
			[ 'code_verifier' => $verifier ],
			15 * MINUTE_IN_SECONDS
		);

		$_GET['gu_example_oauth_callback'] = '1';
		$_GET['state']                     = $state;
		$_GET['code']                      = 'bad-code';

		$flow = $this->make_flow();

		add_filter(
			'gu_test_oauth_credentials',
			static function ( $credentials ) {
				$credentials['client_id'] = 'test-client';

				return $credentials;
			}
		);

		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) {
				if ( str_contains( $url, 'provider.test/oauth/token' ) ) {
					return [
						'response' => [ 'code' => 401 ],
						'body'     => '{"error":"invalid_grant"}',
					];
				}

				return $preempt;
			},
			10,
			3
		);

		$redirected_url = null;
		add_filter(
			'wp_redirect',
			static function ( $url ) use ( &$redirected_url ) {
				$redirected_url = $url;

				return false;
			}
		);

		$flow->maybe_handle_flow();

		$this->assertIsString( $redirected_url );
		$this->assertStringContainsString( 'gu_example_oauth=error-token', $redirected_url );
	}

	public function test_exchange_code_for_token_includes_client_secret_when_set(): void {
		$this->set_admin_screen();
		wp_set_current_user( 1 );
		add_filter( 'allowed_redirect_hosts', [ $this, 'allow_test_host' ] );

		$state    = 'test-state-with-secret';
		$verifier = 'test-verifier';

		set_transient(
			'gu_example_oauth_' . md5( $state ),
			[ 'code_verifier' => $verifier ],
			15 * MINUTE_IN_SECONDS
		);

		$_GET['gu_example_oauth_callback'] = '1';
		$_GET['state']                     = $state;
		$_GET['code']                      = 'auth-code';

		if ( ! defined( 'GU_TEST_SECRET_CLIENT_ID' ) ) {
			define( 'GU_TEST_SECRET_CLIENT_ID', 'my-client' );
		}
		if ( ! defined( 'GU_TEST_SECRET_CLIENT_SECRET' ) ) {
			define( 'GU_TEST_SECRET_CLIENT_SECRET', 'my-secret' );
		}

		$flow = $this->make_flow(
			[
				'credentials_filter'     => '',
				'client_id_constant'     => 'GU_TEST_SECRET_CLIENT_ID',
				'client_secret_constant' => 'GU_TEST_SECRET_CLIENT_SECRET',
			]
		);

		$token_request_body = null;
		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) use ( &$token_request_body ) {
				if ( str_contains( $url, 'provider.test/oauth/token' ) ) {
					if ( is_array( $args['body'] ) ) {
						$token_request_body = $args['body'];
					} else {
						parse_str( (string) $args['body'], $token_request_body );
					}

					return [
						'response' => [ 'code' => 200 ],
						'body'     => json_encode( [ 'access_token' => 'gho_test' ] ),
					];
				}

				return $preempt;
			},
			10,
			3
		);

		$redirected_url = null;
		add_filter(
			'wp_redirect',
			static function ( $url ) use ( &$redirected_url ) {
				$redirected_url = $url;

				return false;
			}
		);

		$flow->maybe_handle_flow();

		$this->assertIsArray( $token_request_body );
		$this->assertSame( 'my-secret', $token_request_body['client_secret'] ?? '' );
	}

	public function test_exchange_code_for_token_includes_grant_type_for_bitbucket(): void {
		$this->set_admin_screen();
		wp_set_current_user( 1 );
		add_filter( 'allowed_redirect_hosts', [ $this, 'allow_test_host' ] );

		$state    = 'test-bb-state';
		$verifier = 'test-bb-verifier';

		set_transient(
			'gu_bitbucket_oauth_' . md5( $state ),
			[ 'code_verifier' => $verifier ],
			15 * MINUTE_IN_SECONDS
		);

		$_GET['gu_bitbucket_oauth_callback'] = '1';
		$_GET['state']                       = $state;
		$_GET['code']                        = 'bb-auth-code';

		$bitbucket_flow = new OAuth_Flow(
			[
				'provider'               => 'bitbucket',
				'label'                  => 'Bitbucket',
				'option_name'            => 'bitbucket_access_token',
				'settings_url'           => 'https://example.test/wp-admin/options-general.php?page=git-updater',
				'authorize_url'          => 'https://bitbucket.org/site/oauth2/authorize',
				'token_url'              => 'https://bitbucket.org/site/oauth2/access_token',
				'default_scope'          => 'repository',
				'credentials_filter'     => 'gu_test_bb_credentials',
				'client_id_constant'     => '',
				'client_secret_constant' => '',
				'scope_constant'         => '',
				'start_arg'              => 'gu_bitbucket_oauth_start',
				'callback_arg'           => 'gu_bitbucket_oauth_callback',
				'status_arg'             => 'gu_bitbucket_oauth',
				'nonce_action'           => 'gu-bitbucket-oauth-start',
			]
		);

		add_filter(
			'gu_test_bb_credentials',
			static function ( $credentials ) {
				$credentials['client_id'] = 'bb-client';

				return $credentials;
			}
		);

		$token_request_body = null;
		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) use ( &$token_request_body ) {
				if ( str_contains( $url, 'bitbucket.org/site/oauth2/access_token' ) ) {
					if ( is_array( $args['body'] ) ) {
						$token_request_body = $args['body'];
					} else {
						parse_str( (string) $args['body'], $token_request_body );
					}

					return [
						'response' => [ 'code' => 200 ],
						'body'     => json_encode( [ 'access_token' => 'bb_test' ] ),
					];
				}

				return $preempt;
			},
			10,
			3
		);

		$redirected_url = null;
		add_filter(
			'wp_redirect',
			static function ( $url ) use ( &$redirected_url ) {
				$redirected_url = $url;

				return false;
			}
		);

		$bitbucket_flow->maybe_handle_flow();

		$this->assertIsArray( $token_request_body );
		$this->assertSame( 'authorization_code', $token_request_body['grant_type'] ?? '' );
	}

	public function test_maybe_handle_flow_does_nothing_for_non_admin_user(): void {
		$this->set_admin_screen();
		$uid = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $uid );

		$_GET['gu_example_oauth_start'] = '1';

		$flow         = $this->make_flow();
		$redirect_hit = false;

		add_filter(
			'wp_redirect',
			static function () use ( &$redirect_hit ) {
				$redirect_hit = true;

				return false;
			}
		);

		$flow->maybe_handle_flow();

		$this->assertFalse( $redirect_hit );
	}

	public function test_maybe_handle_flow_redirects_error_token_on_wp_error(): void {
		$this->set_admin_screen();
		wp_set_current_user( 1 );
		add_filter( 'allowed_redirect_hosts', [ $this, 'allow_test_host' ] );

		$state    = 'test-state-wp-error';
		$verifier = 'test-verifier-wp-error';

		set_transient(
			'gu_example_oauth_' . md5( $state ),
			[ 'code_verifier' => $verifier ],
			15 * MINUTE_IN_SECONDS
		);

		$_GET['gu_example_oauth_callback'] = '1';
		$_GET['state']                     = $state;
		$_GET['code']                      = 'auth-code';

		$flow = $this->make_flow();

		add_filter(
			'gu_test_oauth_credentials',
			static function ( $credentials ) {
				$credentials['client_id'] = 'test-client';

				return $credentials;
			}
		);

		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) {
				if ( str_contains( $url, 'provider.test/oauth/token' ) ) {
					return new \WP_Error( 'http_request_failed', 'Connection refused' );
				}

				return $preempt;
			},
			10,
			3
		);

		$redirected_url = null;
		add_filter(
			'wp_redirect',
			static function ( $url ) use ( &$redirected_url ) {
				$redirected_url = $url;

				return false;
			}
		);

		$flow->maybe_handle_flow();

		$this->assertIsString( $redirected_url );
		$this->assertStringContainsString( 'gu_example_oauth=error-token', $redirected_url );
	}
}
