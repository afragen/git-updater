<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  GPL-3.0-or-later
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

namespace Fragen\Git_Updater\OAuth;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Reusable OAuth authorization-code + PKCE flow for Git Updater API providers.
 *
 * API add-ons can instantiate this class with provider-specific endpoints,
 * option names, query args, constants, and filters instead of duplicating the
 * callback, state, PKCE, token exchange, and settings redirect handling.
 */
class OAuth_Flow {
	/**
	 * Provider OAuth defaults for bundled and add-on Git hosts.
	 *
	 * @var array<string, array<string, string>>
	 */
	private const PROVIDER_CONFIGS = [
		'github'    => [
			'provider'               => 'github',
			'label'                  => 'GitHub',
			'option_name'            => 'github_access_token',
			'authorize_url'          => 'https://github.com/login/oauth/authorize',
			'token_url'              => 'https://github.com/login/oauth/access_token',
			'default_scope'          => 'repo',
			'credentials_filter'     => 'gu_github_oauth_credentials',
			'client_id_constant'     => 'GU_GITHUB_OAUTH_CLIENT_ID',
			'client_secret_constant' => 'GU_GITHUB_OAUTH_CLIENT_SECRET',
			'scope_constant'         => 'GU_GITHUB_OAUTH_SCOPE',
			'start_arg'              => 'gu_github_oauth_start',
			'callback_arg'           => 'gu_github_oauth_callback',
			'status_arg'             => 'gu_github_oauth',
			'nonce_action'           => 'gu-github-oauth-start',
		],
		'gist'      => [
			'provider'               => 'gist',
			'label'                  => 'GitHub Gist',
			'option_name'            => 'github_access_token',
			'authorize_url'          => 'https://github.com/login/oauth/authorize',
			'token_url'              => 'https://github.com/login/oauth/access_token',
			'default_scope'          => 'gist',
			'credentials_filter'     => 'gu_gist_oauth_credentials',
			'client_id_constant'     => 'GU_GIST_OAUTH_CLIENT_ID',
			'client_secret_constant' => 'GU_GIST_OAUTH_CLIENT_SECRET',
			'scope_constant'         => 'GU_GIST_OAUTH_SCOPE',
			'start_arg'              => 'gu_gist_oauth_start',
			'callback_arg'           => 'gu_gist_oauth_callback',
			'status_arg'             => 'gu_gist_oauth',
			'nonce_action'           => 'gu-gist-oauth-start',
		],
		'gitlab'    => [
			'provider'               => 'gitlab',
			'label'                  => 'GitLab',
			'option_name'            => 'gitlab_access_token',
			'authorize_url'          => 'https://gitlab.com/oauth/authorize',
			'token_url'              => 'https://gitlab.com/oauth/token',
			'default_scope'          => 'read_api',
			'credentials_filter'     => 'gu_gitlab_oauth_credentials',
			'client_id_constant'     => 'GU_GITLAB_OAUTH_CLIENT_ID',
			'client_secret_constant' => 'GU_GITLAB_OAUTH_CLIENT_SECRET',
			'scope_constant'         => 'GU_GITLAB_OAUTH_SCOPE',
			'start_arg'              => 'gu_gitlab_oauth_start',
			'callback_arg'           => 'gu_gitlab_oauth_callback',
			'status_arg'             => 'gu_gitlab_oauth',
			'nonce_action'           => 'gu-gitlab-oauth-start',
		],
		'gitea'     => [
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
		],
		'bitbucket' => [
			'provider'               => 'bitbucket',
			'label'                  => 'Bitbucket',
			'option_name'            => 'bitbucket_access_token',
			'authorize_url'          => 'https://bitbucket.org/site/oauth2/authorize',
			'token_url'              => 'https://bitbucket.org/site/oauth2/access_token',
			'default_scope'          => 'repository',
			'credentials_filter'     => 'gu_bitbucket_oauth_credentials',
			'client_id_constant'     => 'GU_BITBUCKET_OAUTH_CLIENT_ID',
			'client_secret_constant' => 'GU_BITBUCKET_OAUTH_CLIENT_SECRET',
			'scope_constant'         => 'GU_BITBUCKET_OAUTH_SCOPE',
			'start_arg'              => 'gu_bitbucket_oauth_start',
			'callback_arg'           => 'gu_bitbucket_oauth_callback',
			'status_arg'             => 'gu_bitbucket_oauth',
			'nonce_action'           => 'gu-bitbucket-oauth-start',
		],
	];

	/**
	 * OAuth provider configuration.
	 *
	 * @var array<string, string>
	 */
	private $config;

	/**
	 * Constructor.
	 *
	 * @param array<string, string> $config OAuth provider configuration.
	 */
	public function __construct( $config ) {
		$this->config = wp_parse_args(
			$config,
			[
				'provider'               => '',
				'label'                  => '',
				'option_name'            => '',
				'settings_url'           => '',
				'authorize_url'          => '',
				'token_url'              => '',
				'default_scope'          => '',
				'credentials_filter'     => '',
				'client_id_constant'     => '',
				'client_secret_constant' => '',
				'scope_constant'         => '',
				'start_arg'              => '',
				'callback_arg'           => '',
				'status_arg'             => '',
				'nonce_action'           => '',
			]
		);
	}

	/**
	 * Create a provider flow using core defaults plus caller overrides.
	 *
	 * API add-ons can call this to use the same provider presets while still
	 * supplying add-on-specific settings URLs or self-hosted endpoints.
	 *
	 * @param string                $provider Provider key.
	 * @param string                $settings_url Settings redirect URL.
	 * @param array<string, string> $overrides Provider configuration overrides.
	 *
	 * @return self
	 */
	public static function for_provider( $provider, $settings_url, $overrides = [] ) {
		$config                 = self::get_provider_config( $provider, $overrides );
		$config['settings_url'] = $settings_url;

		return new self( $config );
	}

	/**
	 * Return provider defaults merged with caller overrides.
	 *
	 * @param string                $provider Provider key.
	 * @param array<string, string> $overrides Provider configuration overrides.
	 *
	 * @return array<string, string>
	 */
	public static function get_provider_config( $provider, $overrides = [] ) {
		$config = self::PROVIDER_CONFIGS[ $provider ] ?? [];

		return array_merge( $config, $overrides );
	}

	/**
	 * Start OAuth flow and process callback.
	 *
	 * @return void
	 */
	public function maybe_handle_flow() {
		$capability = is_multisite() ? 'manage_network_options' : 'manage_options';
		if ( ! is_admin() || ! current_user_can( $capability ) ) {
			return;
		}

		$start_arg    = $this->config['start_arg'];
		$callback_arg = $this->config['callback_arg'];

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is validated in start_flow().
		if ( ! empty( $start_arg ) && isset( $_GET[ $start_arg ] ) ) {
			$this->start_flow();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback is validated through state and PKCE verifier.
		if ( ! empty( $callback_arg ) && isset( $_GET[ $callback_arg ] ) ) {
			$this->complete_flow();
		}
	}

	/**
	 * Return current OAuth status query arg for settings UI messaging.
	 *
	 * @return string
	 */
	public function get_status() {
		$status_arg = $this->config['status_arg'];

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status query arg for UI message only.
		return ! empty( $status_arg ) && isset( $_GET[ $status_arg ] ) ? sanitize_key( wp_unslash( $_GET[ $status_arg ] ) ) : '';
	}

	/**
	 * Build start URL for OAuth flow.
	 *
	 * @return string
	 */
	public function get_start_url() {
		return add_query_arg(
			[
				$this->config['start_arg'] => 1,
				'_wpnonce'                 => wp_create_nonce( $this->config['nonce_action'] ),
			],
			$this->config['settings_url']
		);
	}

	/**
	 * Return OAuth credentials from constants and provider filter.
	 *
	 * @return array<string, string>
	 */
	public function get_credentials() {
		$client_id_constant     = $this->config['client_id_constant'];
		$client_secret_constant = $this->config['client_secret_constant'];
		$scope_constant         = $this->config['scope_constant'];

		$credentials = [
			'client_id'     => $client_id_constant && defined( $client_id_constant ) ? (string) constant( $client_id_constant ) : '',
			'client_secret' => $client_secret_constant && defined( $client_secret_constant ) ? (string) constant( $client_secret_constant ) : '',
			'scope'         => $scope_constant && defined( $scope_constant ) ? (string) constant( $scope_constant ) : $this->config['default_scope'],
		];

		if ( ! empty( $this->config['credentials_filter'] ) ) {
			$credentials = apply_filters( $this->config['credentials_filter'], $credentials, $this->config );
		}

		return array_map( 'strval', $credentials );
	}

	/**
	 * Build transient key for OAuth flow state.
	 *
	 * @param string $state OAuth state.
	 *
	 * @return string
	 */
	public function get_transient_key( $state ) {
		return 'gu_' . sanitize_key( $this->config['provider'] ) . '_oauth_' . md5( $state );
	}

	/**
	 * Build S256 PKCE challenge.
	 *
	 * @param string $verifier PKCE verifier.
	 *
	 * @return string
	 */
	public function get_code_challenge( $verifier ) {
		$hash = hash( 'sha256', $verifier, true );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for RFC7636 base64url PKCE encoding.
		return rtrim( strtr( base64_encode( $hash ), '+/', '-_' ), '=' );
	}

	/**
	 * Build callback URL for provider OAuth.
	 *
	 * @return string
	 */
	public function get_callback_url() {
		return add_query_arg( $this->config['callback_arg'], 1, $this->config['settings_url'] );
	}

	/**
	 * Output reusable OAuth controls and status messages for provider settings.
	 *
	 * @return void
	 */
	public function render_authorize_controls() {
		$credentials = $this->get_credentials();
		$status      = $this->get_status();
		$label       = $this->config['label'];

		if ( 'success' === $status ) {
			printf( '<p><strong>%s</strong></p>', esc_html( sprintf( __( 'OAuth token updated from %s.', 'git-updater' ), $label ) ) );
		}

		if ( str_starts_with( $status, 'error-' ) ) {
			printf( '<p><strong>%s</strong></p>', esc_html( sprintf( __( '%s OAuth was not completed. You can retry below.', 'git-updater' ), $label ) ) );
		}

		printf(
			'<p class="description">%s <code>%s</code></p>',
			esc_html__( 'OAuth callback URL:', 'git-updater' ),
			esc_html( $this->get_callback_url() )
		);

		if ( empty( $credentials['client_id'] ) ) {
			printf(
				'<p class="description">%s</p>',
				esc_html( sprintf( __( 'To enable OAuth authorization, set %1$s in wp-config.php or filter %2$s.', 'git-updater' ), $this->config['client_id_constant'], $this->config['credentials_filter'] ) )
			);

			return;
		}

		printf(
			'<p><a class="button button-secondary" href="%s">%s</a></p>',
			esc_url( $this->get_start_url() ),
			esc_html( sprintf( __( 'Authorize via %s OAuth', 'git-updater' ), $label ) )
		);
	}

	/**
	 * Start provider OAuth redirect.
	 *
	 * @return void
	 */
	private function start_flow() {
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), $this->config['nonce_action'] ) ) {
			$this->redirect_with_status( 'error-nonce' );

			return;
		}

		$credentials = $this->get_credentials();
		if ( empty( $credentials['client_id'] ) ) {
			$this->redirect_with_status( 'error-client-id' );

			return;
		}

		$state    = wp_generate_password( 48, false, false );
		$verifier = wp_generate_password( 96, false, false );

		set_transient(
			$this->get_transient_key( $state ),
			[
				'code_verifier' => $verifier,
			],
			15 * MINUTE_IN_SECONDS
		);

		$authorize_args = [
			'client_id'             => $credentials['client_id'],
			'redirect_uri'          => $this->get_callback_url(),
			'scope'                 => $credentials['scope'],
			'state'                 => $state,
			'code_challenge'        => $this->get_code_challenge( $verifier ),
			'code_challenge_method' => 'S256',
		];

		/**
		 * Filter provider authorize URL args before redirect.
		 *
		 * @since 13.4.0
		 *
		 * @param array<string, string> $authorize_args OAuth authorize query args.
		 * @param array<string, string> $credentials    OAuth credentials.
		 * @param array<string, string> $config         OAuth provider configuration.
		 */
		$authorize_args = apply_filters( 'gu_oauth_authorize_args', $authorize_args, $credentials, $this->config );

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Provider authorize URLs are intentionally external OAuth endpoints.
		wp_redirect( esc_url_raw( add_query_arg( $authorize_args, $this->config['authorize_url'] ) ) );
		exit;
	}

	/**
	 * Process provider callback and save token.
	 *
	 * @return void
	 */
	private function complete_flow() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback is validated through state and PKCE verifier.
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback is validated through state and PKCE verifier.
		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';

		if ( empty( $state ) || empty( $code ) ) {
			$this->redirect_with_status( 'error-callback' );

			return;
		}

		$key      = $this->get_transient_key( $state );
		$flow     = get_transient( $key );
		$verifier = is_array( $flow ) && ! empty( $flow['code_verifier'] ) ? (string) $flow['code_verifier'] : '';
		delete_transient( $key );

		if ( empty( $verifier ) ) {
			$this->redirect_with_status( 'error-state' );

			return;
		}

		$token = $this->exchange_code_for_token( $this->get_credentials(), $code, $verifier );

		if ( empty( $token ) ) {
			$this->redirect_with_status( 'error-token' );

			return;
		}

		$options                                 = get_site_option( 'git_updater', [] );
		$options                                 = is_array( $options ) ? $options : [];
		$options[ $this->config['option_name'] ] = $token;
		update_site_option( 'git_updater', $options );

		$this->redirect_with_status( 'success' );
	}

	/**
	 * Exchange callback code for access token.
	 *
	 * @param array<string, string> $credentials OAuth credentials.
	 * @param string                $code        Callback code.
	 * @param string                $verifier    PKCE verifier.
	 *
	 * @return string
	 */
	private function exchange_code_for_token( $credentials, $code, $verifier ) {
		$body = [
			'client_id'     => $credentials['client_id'],
			'code'          => $code,
			'redirect_uri'  => $this->get_callback_url(),
			'code_verifier' => $verifier,
		];

		if ( 'bitbucket' === $this->config['provider'] ) {
			$body['grant_type'] = 'authorization_code';
		}

		if ( ! empty( $credentials['client_secret'] ) ) {
			$body['client_secret'] = $credentials['client_secret'];
		}

		/**
		 * Filter provider token request body before exchange.
		 *
		 * @since 13.4.0
		 *
		 * @param array<string, string> $body        OAuth token request body.
		 * @param array<string, string> $credentials OAuth credentials.
		 * @param array<string, string> $config      OAuth provider configuration.
		 */
		$body = apply_filters( 'gu_oauth_token_request_body', $body, $credentials, $this->config );

		$response = wp_remote_post(
			$this->config['token_url'],
			[
				'timeout' => 15,
				'headers' => [
					'Accept' => 'application/json',
				],
				'body'    => $body,
			]
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$payload = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $payload ) || empty( $payload['access_token'] ) ) {
			return '';
		}

		return sanitize_text_field( (string) $payload['access_token'] );
	}

	/**
	 * Redirect to provider settings with flow status.
	 *
	 * @param string $status OAuth status value.
	 * @return void
	 */
	private function redirect_with_status( $status ) {
		wp_safe_redirect(
			add_query_arg(
				$this->config['status_arg'],
				$status,
				$this->config['settings_url']
			)
		);
		exit;
	}
}
