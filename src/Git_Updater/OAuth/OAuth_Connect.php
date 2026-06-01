<?php
/**
 * Git Updater OAuth Connect
 *
 * Handles OAuth token acquisition via connector service.
 *
 * @package Git_Updater
 */

namespace Fragen\Git_Updater\OAuth;

use Fragen\Git_Updater\API\API;
use Fragen\Git_Updater\Base;

/**
 * Class OAuth_Connect
 *
 * Handles OAuth connect/disconnect/callback for all git providers.
 */
class OAuth_Connect {

	/**
	 * Provider configurations.
	 *
	 * @var array<string, array<string, string>>
	 */
	const PROVIDERS = [
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

	/**
	 * Override for connector URL. When set, bypasses the constant check.
	 * Used for testing the "no connector" path.
	 *
	 * @var string|null
	 */
	public ?string $connector_url = null;

	/**
	 * Load hooks for OAuth handling.
	 *
	 * @return void
	 */
	public function load_hooks(): void {
		add_action( 'admin_post_gu_oauth_callback', [ $this, 'handle_callback' ] );
		add_action( 'admin_post_gu_oauth_disconnect', [ $this, 'handle_disconnect' ] );
	}

	/**
	 * Render the connect button field.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @return void
	 */
	public function render_connect_field( array $args ): void {
		$provider = $args['provider'] ?? '';
		$config   = self::PROVIDERS[ $provider ] ?? null;

		if ( ! $config ) {
			return;
		}

		$options   = get_site_option( 'git_updater', [] );
		$token     = $options[ $config['option_key'] ] ?? '';
		$connector = $this->get_connector_url();

		if ( $token ) {
			$this->render_connected_state( $provider, $config );
			return;
		}

		if ( ! $connector ) {
			$this->render_no_connector_message();
			return;
		}

		$this->render_connect_button( $provider, $config, $connector );
	}

	/**
	 * Render the connected state with disconnect button.
	 *
	 * @param string                $provider Provider slug.
	 * @param array<string, string> $config   Provider configuration.
	 * @return void
	 */
	private function render_connected_state( string $provider, array $config ): void {
		$disconnect_url = add_query_arg(
			[
				'action'   => 'gu_oauth_disconnect',
				'provider' => $provider,
				'_wpnonce' => wp_create_nonce( 'gu_oauth_disconnect_' . $provider ),
			],
			admin_url( 'admin-post.php' )
		);
		echo '<span class="gu-oauth-connected">&#10003; ' . esc_html__( 'Connected', 'git-updater' ) . '</span> ';
		echo '<a href="' . esc_url( $disconnect_url ) . '" class="button button-small">' . esc_html__( 'Disconnect', 'git-updater' ) . '</a>';
	}

	/**
	 * Render message when connector URL is not configured.
	 *
	 * @return void
	 */
	private function render_no_connector_message(): void {
		echo '<p class="description">';
		esc_html_e( 'Define GIT_UPDATER_OAUTH_CONNECTOR_URL in wp-config.php to enable OAuth.', 'git-updater' );
		echo '</p>';
	}

	/**
	 * Render the connect button.
	 *
	 * @param string                $provider  Provider slug.
	 * @param array<string, string> $config    Provider configuration.
	 * @param string                $connector Connector URL.
	 * @return void
	 */
	private function render_connect_button( string $provider, array $config, string $connector ): void {
		$callback_url = $this->get_callback_url( $provider );

		// Build the authorize URL on the connector.
		$authorize_url = $connector . 'git-updater/' . $provider . '/oauth/authorize';
		$authorize_url = add_query_arg( 'redirect', rawurlencode( $callback_url ), $authorize_url );

		// Add Gitea-specific parameters if needed.
		if ( 'gitea' === $provider ) {
			$options = get_site_option( 'git_updater', [] );
			if ( ! empty( $options['gitea_server'] ) && ! empty( $options['gitea_client_id'] ) ) {
				$authorize_url = add_query_arg( 'base_url', rawurlencode( $options['gitea_server'] ), $authorize_url );
				$authorize_url = add_query_arg( 'client_id', rawurlencode( $options['gitea_client_id'] ), $authorize_url );
			} else {
				echo '<p class="description">';
				esc_html_e( 'Please enter Gitea Server URL and OAuth App Client ID first.', 'git-updater' );
				echo '</p>';
				return;
			}
		}

		echo '<a href="' . esc_url( $authorize_url ) . '" class="button button-primary">';
		/* translators: %s is the provider label, e.g. "GitHub". */
		echo esc_html( sprintf( __( 'Connect %s', 'git-updater' ), $config['label'] ) );
		echo '</a>';
	}

	/**
	 * Handle OAuth callback from connector.
	 *
	 * @return void
	 */
	public function handle_callback(): void {
		if ( ! current_user_can( is_multisite() ? 'manage_network_options' : 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'git-updater' ) ); // @codeCoverageIgnore
		}

		$provider      = sanitize_key( $_GET['provider'] ?? '' );
		$exchange_code = sanitize_text_field( wp_unslash( $_GET['gu_exchange_code'] ?? '' ) );

		if ( ! isset( self::PROVIDERS[ $provider ] ) || empty( $exchange_code ) ) {
			$this->redirect_with_status( $provider, 'oauth_error' );
			return; // @codeCoverageIgnore
		}

		$result = $this->fetch_token_from_connector( $provider, $exchange_code );

		if ( $result && ! empty( $result['access_token'] ) ) {
			$this->save_token(
				$provider,
				$result['access_token'],
				$result['refresh_token'] ?? null,
				$result['expires_in'] ?? null
			);
			$this->redirect_with_status( $provider, 'oauth_connected' );
		} else {
			$this->redirect_with_status( $provider, 'oauth_error' );
		}
	}

	/**
	 * Handle OAuth disconnect.
	 *
	 * @return void
	 */
	public function handle_disconnect(): void {
		$provider = sanitize_key( $_GET['provider'] ?? '' );

		check_admin_referer( 'gu_oauth_disconnect_' . $provider );

		if ( ! current_user_can( is_multisite() ? 'manage_network_options' : 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'git-updater' ) ); // @codeCoverageIgnore
		}

		$this->delete_token( $provider );
		$this->redirect_with_status( $provider, 'oauth_disconnected' );
	}

	/**
	 * Get the connector URL from configuration.
	 *
	 * @return string
	 */
	private function get_connector_url(): string {
		if ( null !== $this->connector_url ) {
			return $this->connector_url;
		}
		$url = defined( 'GIT_UPDATER_OAUTH_CONNECTOR_URL' ) ? constant( 'GIT_UPDATER_OAUTH_CONNECTOR_URL' ) : '';
		return $url ? trailingslashit( $url ) : '';
	}

	/**
	 * Get the callback URL for OAuth.
	 *
	 * @param string $provider Provider slug.
	 * @return string
	 */
	private function get_callback_url( string $provider ): string {
		$base = is_multisite()
			? network_admin_url( 'admin-post.php' ) // @codeCoverageIgnore
			: admin_url( 'admin-post.php' );
		return add_query_arg(
			[
				'action'   => 'gu_oauth_callback',
				'provider' => $provider,
			],
			$base
		);
	}

	/**
	 * Fetch token data from connector using exchange code.
	 *
	 * @param string $provider      Provider slug.
	 * @param string $exchange_code Exchange code from connector.
	 * @return array<string, mixed>|null Token data array or null on failure.
	 */
	private function fetch_token_from_connector( string $provider, string $exchange_code ): ?array {
		$connector = $this->get_connector_url();
		if ( ! $connector ) {
			return null;
		}

		$url = $connector . 'git-updater/' . $provider . '/oauth/token';
		$url = add_query_arg( 'code', $exchange_code, $url );

		$response = wp_remote_get( $url, [ 'timeout' => 15 ] );
		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			return null;
		}

		return [
			'access_token'  => sanitize_text_field( $body['access_token'] ),
			'refresh_token' => ! empty( $body['refresh_token'] ) ? sanitize_text_field( $body['refresh_token'] ) : null,
			'expires_in'    => ! empty( $body['expires_in'] ) ? (int) $body['expires_in'] : null,
		];
	}

	/**
	 * Save token and optional refresh token / expiry metadata to options.
	 *
	 * @param string      $provider      Provider slug.
	 * @param string      $token         Access token.
	 * @param string|null $refresh_token Refresh token, if available.
	 * @param int|null    $expires_in    Seconds until token expiry, if known.
	 * @return void
	 */
	private function save_token( string $provider, string $token, ?string $refresh_token = null, ?int $expires_in = null ): void {
		$config  = self::PROVIDERS[ $provider ];
		$options = get_site_option( 'git_updater', [] );

		$options[ $config['option_key'] ]         = $token;
		$options[ $provider . '_is_oauth_token' ] = 'oauth';

		if ( $refresh_token ) {
			$options[ $config['refresh_option_key'] ] = $refresh_token;
		} else {
			unset( $options[ $config['refresh_option_key'] ] );
		}

		if ( $expires_in ) {
			$options[ $provider . '_token_expires_in' ]  = $expires_in;
			$options[ $provider . '_token_acquired_at' ] = time();
		} else {
			unset( $options[ $provider . '_token_expires_in' ], $options[ $provider . '_token_acquired_at' ] );
		}

		update_site_option( 'git_updater', $options );
		Base::$options = $options;
		API::$options  = $options;
	}

	/**
	 * Delete token and associated metadata from options.
	 *
	 * @param string $provider Provider slug.
	 * @return void
	 */
	private function delete_token( string $provider ): void {
		$config  = self::PROVIDERS[ $provider ];
		$options = get_site_option( 'git_updater', [] );

		unset( $options[ $config['option_key'] ] );
		unset( $options[ $config['refresh_option_key'] ] );
		unset( $options[ $provider . '_token_expires_in' ] );
		unset( $options[ $provider . '_token_acquired_at' ] );
		unset( $options[ $provider . '_is_oauth_token' ] );
		update_site_option( 'git_updater', $options );
		Base::$options = $options;
		API::$options  = $options;
	}

	/**
	 * Attempt to refresh an expired token via the connector.
	 *
	 * @param string $provider Provider slug.
	 * @return string|null New access token or null on failure.
	 */
	public function refresh_token( string $provider ): ?string {
		$connector = $this->get_connector_url();
		if ( ! $connector || ! isset( self::PROVIDERS[ $provider ] ) ) {
			return null;
		}

		$config        = self::PROVIDERS[ $provider ];
		$options       = get_site_option( 'git_updater', [] );
		$refresh_token = $options[ $config['refresh_option_key'] ] ?? null;

		if ( ! $refresh_token ) {
			return null;
		}

		$url = $connector . 'git-updater/' . $provider . '/oauth/refresh';

		$response = wp_remote_post(
			$url,
			[
				'timeout' => 15,
				'body'    => [ 'refresh_token' => $refresh_token ],
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			return null;
		}

		$new_token         = sanitize_text_field( $body['access_token'] );
		$new_refresh_token = ! empty( $body['refresh_token'] ) ? sanitize_text_field( $body['refresh_token'] ) : null;
		$expires_in        = ! empty( $body['expires_in'] ) ? (int) $body['expires_in'] : null;

		$this->save_token( $provider, $new_token, $new_refresh_token ?? $refresh_token, $expires_in );

		return $new_token;
	}

	/**
	 * Check whether the stored token for a provider was acquired via OAuth.
	 *
	 * @param string $provider Provider slug.
	 * @return bool True when the OAuth flag is set; false when missing or unknown provider.
	 */
	public function is_oauth_token( string $provider ): bool {
		if ( ! isset( self::PROVIDERS[ $provider ] ) ) {
			return false;
		}
		$options = ! empty( Base::$options ) ? Base::$options : get_site_option( 'git_updater', [] );
		return ! empty( $options[ $provider . '_is_oauth_token' ] );
	}

	/**
	 * Check if a provider's token is expired or about to expire.
	 *
	 * @param string $provider Provider slug.
	 * @param int    $buffer   Seconds before expiry to consider "expired" (default 300 = 5 minutes).
	 * @return bool True if token is expired, missing, or unknown provider.
	 */
	public function is_token_expired( string $provider, int $buffer = 300 ): bool {
		if ( ! isset( self::PROVIDERS[ $provider ] ) ) {
			return true;
		}

		$config  = self::PROVIDERS[ $provider ];
		$options = get_site_option( 'git_updater', [] );

		// No token stored — treat as expired for refresh purposes.
		if ( empty( $options[ $config['option_key'] ] ) ) {
			return true;
		}

		// No expiry metadata (e.g., GitHub tokens never expire) — assume valid.
		$expires_in  = $options[ $provider . '_token_expires_in' ] ?? null;
		$acquired_at = $options[ $provider . '_token_acquired_at' ] ?? null;
		if ( null === $expires_in || null === $acquired_at ) {
			return false;
		}

		$elapsed   = time() - (int) $acquired_at;
		$remaining = (int) $expires_in - $elapsed;

		return $remaining <= $buffer;
	}

	/**
	 * Redirect with status message.
	 *
	 * @param string $provider Provider slug.
	 * @param string $status   Status key.
	 * @return void
	 */
	private function redirect_with_status( string $provider, string $status ): void {
		$subtab   = $provider ?: 'git_updater';
		$base_url = is_multisite() ? network_admin_url( 'settings.php' ) : admin_url( 'options-general.php' );

		$location = add_query_arg(
			[
				'page'   => 'git-updater',
				'tab'    => 'git_updater_settings',
				'subtab' => $subtab,
				$status  => '1',
			],
			$base_url
		);

		$location = add_query_arg( '_wpnonce', wp_create_nonce( 'gu_settings' ), $location );

		wp_safe_redirect( $location );
		exit; // @codeCoverageIgnore
	}
}
