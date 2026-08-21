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
use Fragen\Git_Updater\Traits\GU_Trait;

/**
 * Class OAuth_Connect
 *
 * Handles OAuth connect/disconnect/callback for all git providers.
 */
class OAuth_Connect {
	use GU_Trait;

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
	 * TTL in seconds for the refresh lock transient.
	 * Set to 2x the HTTP timeout (15s) to cover worst-case latency.
	 */
	private const REFRESH_LOCK_TTL = 30;

	/**
	 * TTL in seconds for the refresh result transient.
	 * Long enough for concurrent request bursts to benefit.
	 */
	private const REFRESH_RESULT_TTL = 60;

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
		add_action( 'admin_post_gu_remove_token', [ $this, 'handle_remove_token' ] );
		add_action( 'gu_oauth_revoke_notify', [ $this, 'remind_admin_of_token_revocation' ] );

		// Custom 36-hour schedule for the revocation reminder.
		add_filter(
			'cron_schedules',
			static function ( $schedules ) {
				$schedules['gu_oauth_revoke_36h'] = [
					'interval' => 36 * HOUR_IN_SECONDS,
					'display'  => esc_html__( 'Every 36 hours', 'git-updater' ),
				];
				return $schedules;
			}
		);
		if ( false === wp_next_scheduled( 'gu_oauth_revoke_notify' ) ) {
			wp_schedule_event( time() + 36 * HOUR_IN_SECONDS, 'gu_oauth_revoke_36h', 'gu_oauth_revoke_notify' );
		}
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
			$this->render_connected_state( $provider );
			return;
		}

		if ( ! $connector ) {
			$this->render_no_connector_message();
			return;
		}

		$this->render_connect_button( $provider, $config, $connector );
	}

	/**
	 * Render the remove token button for non-OAuth tokens.
	 *
	 * Only shown when a manual API token (PAT) is stored and the token
	 * was not acquired via OAuth. Hidden when no token is set or when
	 * the token is an OAuth token.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @return void
	 */
	public function render_remove_token_field( array $args ): void {
		$provider = $args['provider'] ?? '';
		$config   = self::PROVIDERS[ $provider ] ?? null;

		if ( ! $config ) {
			return;
		}

		// Only show when a non-OAuth token is present.
		$options = get_site_option( 'git_updater', [] );
		if ( empty( $options[ $config['option_key'] ] ) || $this->is_oauth_token( $provider ) ) {
			return;
		}

		$remove_url = add_query_arg(
			[
				'action'   => 'gu_remove_token',
				'provider' => $provider,
				'_wpnonce' => wp_create_nonce( 'gu_remove_token_' . $provider ),
			],
			admin_url( 'admin-post.php' )
		);
		echo '<a href="' . esc_url( $remove_url ) . '" class="button button-small">' . esc_html__( 'Remove Token', 'git-updater' ) . '</a>';
	}

	/**
	 * Render the connected state with disconnect button.
	 *
	 * @param string $provider Provider slug.
	 * @return void
	 */
	private function render_connected_state( string $provider ): void {
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

		// Generate single-use CSRF token, stashed for callback verification.
		$site_state = wp_generate_password( 32, false );
		set_site_transient( "gu_oauth_state_$provider", $site_state, 10 * MINUTE_IN_SECONDS );

		// Build the authorize URL on the connector.
		$authorize_url = $connector . 'git-updater/' . $provider . '/oauth/authorize';
		$authorize_url = add_query_arg( 'redirect', rawurlencode( $callback_url ), $authorize_url );
		$authorize_url = add_query_arg( 'site_state', $site_state, $authorize_url );

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

		$provider = sanitize_key( $_GET['provider'] ?? '' );

		// Verify WordPress nonce — proves the callback corresponds to this user's session.
		if ( ! isset( $_GET['_wpnonce'] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'gu_oauth_callback_' . $provider )
		) {
			$this->redirect_with_status( $provider, 'oauth_error' );
			return; // @codeCoverageIgnore
		}

		$exchange_code = sanitize_text_field( wp_unslash( $_GET['gu_exchange_code'] ?? '' ) );
		$site_state    = sanitize_text_field( wp_unslash( $_GET['site_state'] ?? '' ) );

		if ( ! isset( self::PROVIDERS[ $provider ] ) || empty( $exchange_code ) ) {
			$this->redirect_with_status( $provider, 'oauth_error' );
			return; // @codeCoverageIgnore
		}

		// Verify CSRF state — paired with the transient set in render_connect_button().
		$expected_state = get_site_transient( "gu_oauth_state_$provider" );
		delete_site_transient( "gu_oauth_state_$provider" );
		if ( empty( $expected_state ) || empty( $site_state ) || ! hash_equals( (string) $expected_state, $site_state ) ) {
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
			// Clear any prior re-authorization notice now that we're reconnected.
			$persist_options = get_site_option( 'git_updater', [] );
			unset( $persist_options[ 'gu_oauth_revoked_' . $provider ] );
			unset( $persist_options[ 'gu_oauth_notified_' . $provider ] );
			update_site_option( 'git_updater', $persist_options );
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
	 * Handle removal of a non-OAuth token.
	 *
	 * @return void
	 */
	public function handle_remove_token(): void {
		$provider = sanitize_key( $_GET['provider'] ?? '' );

		if ( ! isset( $_GET['_wpnonce'] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'gu_remove_token_' . $provider )
		) {
			wp_die( esc_html__( 'Forbidden', 'git-updater' ) ); // @codeCoverageIgnore
		}

		if ( ! current_user_can( is_multisite() ? 'manage_network_options' : 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'git-updater' ) ); // @codeCoverageIgnore
		}

		$options = get_site_option( 'git_updater', [] );
		$config  = self::PROVIDERS[ $provider ] ?? null;

		if ( $config ) {
			unset( $options[ $config['option_key'] ] );
			update_site_option( 'git_updater', $options );
			Base::$options = $options;
			API::$options  = $options;
		}

		$this->redirect_with_status( $provider, 'token_removed' );
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
				'_wpnonce' => wp_create_nonce( 'gu_oauth_callback_' . $provider ),
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
		unset( $options[ 'gu_oauth_revoked_' . $provider ] );
		unset( $options[ 'gu_oauth_notified_' . $provider ] );
		update_site_option( 'git_updater', $options );
		Base::$options = $options;
		API::$options  = $options;

		delete_site_transient( $this->get_lock_transient_name( $provider ) );
		delete_site_transient( $this->get_result_transient_name( $provider ) );
	}

	/**
	 * Attempt to refresh an expired token via the connector.
	 *
	 * @param string $provider Provider slug.
	 * @return string|null New access token or null on failure.
	 *
	 * @phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
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

		$debug = apply_filters( 'gu_debug_token_refresh', false );

		// Check if a concurrent request already completed a refresh.
		$result_transient = get_site_transient( $this->get_result_transient_name( $provider ) );
		if ( 'success' === $result_transient ) {
			// Another request refreshed successfully — reuse the new token.
			$options       = get_site_option( 'git_updater', [] );
			Base::$options = $options;
			API::$options  = $options;
			if ( $debug ) {
				error_log( 'Git Updater: Reusing successful token refresh for ' . $provider . '.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return $options[ $config['option_key'] ] ?? null;
		}
		if ( 'failure' === $result_transient ) {
			// Previous concurrent refresh failed — delete the result and try again.
			delete_site_transient( $this->get_result_transient_name( $provider ) );
		}

		// Check if a concurrent request is already refreshing.
		$lock_transient = get_site_transient( $this->get_lock_transient_name( $provider ) );
		if ( $lock_transient ) {
			if ( $debug ) {
				error_log( 'Git Updater: Token refresh already in progress for ' . $provider . ', skipping.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return null;
		}

		// Acquire the lock.
		set_site_transient( $this->get_lock_transient_name( $provider ), time(), self::REFRESH_LOCK_TTL );

		$url      = $connector . 'git-updater/' . $provider . '/oauth/refresh';
		$response = wp_remote_post(
			$url,
			[
				'timeout' => 15,
				'body'    => [ 'refresh_token' => $refresh_token ],
			]
		);

		if ( is_wp_error( $response ) ) {
			delete_site_transient( $this->get_lock_transient_name( $provider ) );
			set_site_transient( $this->get_result_transient_name( $provider ), 'failure', self::REFRESH_RESULT_TTL );
			if ( $debug ) {
				error_log( 'Git Updater: Token refresh failed for ' . $provider . ': ' . $response->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			// Provider rejected the refresh (e.g. revoked/rotated refresh token):
			// drop the now-invalid token so the Connect button reappears.
			$this->delete_token( $provider );
			$persist_options                                    = get_site_option( 'git_updater', [] );
			$persist_options[ 'gu_oauth_revoked_' . $provider ] = time();
			update_site_option( 'git_updater', $persist_options );

			// Notify the site admin that the token could not be refreshed and was revoked.
			$this->notify_admin_of_token_revocation( $provider );

			delete_site_transient( $this->get_lock_transient_name( $provider ) );
			set_site_transient( $this->get_result_transient_name( $provider ), 'failure', self::REFRESH_RESULT_TTL );
			if ( $debug ) {
				error_log( 'Git Updater: Token refresh failed for ' . $provider . ': No access token received.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Response body: ' . wp_remote_retrieve_body( $response ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return null;
		}

		$new_token         = sanitize_text_field( $body['access_token'] );
		$new_refresh_token = ! empty( $body['refresh_token'] ) ? sanitize_text_field( $body['refresh_token'] ) : null;
		$expires_in        = ! empty( $body['expires_in'] ) ? (int) $body['expires_in'] : null;

		$this->save_token( $provider, $new_token, $new_refresh_token ?? $refresh_token, $expires_in );

		delete_site_transient( $this->get_lock_transient_name( $provider ) );
		set_site_transient( $this->get_result_transient_name( $provider ), 'success', self::REFRESH_RESULT_TTL );

		if ( $debug ) {
			error_log( 'Git Updater: Token refreshed for ' . $provider . '. New token expires in ' . ( ( (int) $expires_in / 3600 ) ?: 'unknown' ) . ' hours.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
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
	 * Get the site transient name for the refresh lock.
	 *
	 * @param string $provider Provider slug.
	 * @return string Transient name.
	 */
	private function get_lock_transient_name( string $provider ): string {
		return 'gu_oauth_refresh_lock_' . $provider;
	}

	/**
	 * Get the site transient name for the refresh result.
	 *
	 * @param string $provider Provider slug.
	 * @return string Transient name.
	 */
	private function get_result_transient_name( string $provider ): string {
		return 'gu_oauth_refresh_result_' . $provider;
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

	/**
	 * Notify the site administrator via email that a provider's OAuth token
	 * is missing (Connect button displayed). Message differs based on whether
	 * the token was revoked by a failed refresh or is simply empty. Sends at
	 * most once per 36-hour period.
	 *
	 * @param string $provider Provider slug.
	 * @return void
	 *
	 * @filter git_updater_skip_oauth_reminder bool $skip, string $provider
	 *           Return true to skip the email reminder for a specific provider.
	 *           Example:
	 *           add_filter( 'git_updater_skip_oauth_reminder', function( $skip, $provider ) {
	 *               return 'github' === $provider ? true : $skip;
	 *           }, 10, 2 );
	 */
	private function notify_admin_of_token_revocation( string $provider ): void {
		if ( ! isset( self::PROVIDERS[ $provider ] ) ) {
			return;
		}

		if ( apply_filters( 'git_updater_skip_oauth_reminder', false, $provider ) ) {
			return;
		}

		$config  = self::PROVIDERS[ $provider ];
		$options = get_site_option( 'git_updater', [] );

		// Only notify when the OAuth token is actually missing
		// (Connect button displayed, same condition as render_connect_field()).
		if ( ! empty( $options[ $config['option_key'] ] ) ) {
			return;
		}

		// Skip if we already emailed within the last 36 hours.
		$notified_at = (int) ( $options[ 'gu_oauth_notified_' . $provider ] ?? 0 );
		if ( $notified_at > time() - 36 * HOUR_IN_SECONDS ) {
			return;
		}

		// Tokens are network-scoped (site options), so use the network admin email on multisite.
		$admin_email  = is_multisite() ? get_site_option( 'admin_email' ) : get_option( 'admin_email' );
		$base_url     = is_multisite() ? network_admin_url( 'settings.php' ) : admin_url( 'options-general.php' );
		$settings_url = add_query_arg(
			[
				'page'   => 'git-updater',
				'tab'    => 'git_updater_settings',
				'subtab' => $provider,
			],
			$base_url
		);

		$subject = sprintf(
			/* translators: %s is the provider label, e.g. "GitHub". */
			__( 'Git Updater: %s OAuth access has been revoked', 'git-updater' ),
			$config['label']
		);

		// The revoked flag is only ever set by a failed token refresh, so it
		// distinguishes "revoked by refresh failure" from "token simply empty"
		// (removed via settings, Remove Token button, or never connected).
		if ( ! empty( $options[ 'gu_oauth_revoked_' . $provider ] ) ) {
			$message = sprintf(
				/* translators: 1: provider label, 2: settings page URL. */
				__( 'Git Updater was unable to refresh your %1$s OAuth access token, so the token has been revoked and removed. Please reconnect using the Connect button on the Git Updater settings page: %2$s', 'git-updater' ),
				$config['label'],
				$settings_url
			);
		} elseif ( function_exists( '\Fragen\Git_Updater\gu_fs' ) && \Fragen\Git_Updater\gu_fs()->can_use_premium_code() ) {
			// Only premium license holders get the empty-token reminder.
			$message = sprintf(
				/* translators: 1: provider label, 2: settings page URL. */
				__( 'Your %1$s OAuth access token is empty, so Git Updater cannot access your private repositories. Please reconnect using the Connect button on the Git Updater settings page: %2$s', 'git-updater' ),
				$config['label'],
				$settings_url
			);
		} else {
			// Free users: no empty-token reminder email.
			return;
		}

		if ( wp_mail( $admin_email, $subject, $message ) ) {
			$options[ 'gu_oauth_notified_' . $provider ] = time();
			update_site_option( 'git_updater', $options );
		}
	}

	/**
	 * Get the providers whose API is running. Uses the canonical
	 * get_running_git_servers() list, which always includes GitHub (bundled
	 * with git-updater) plus any provider registered via the
	 * gu_running_git_servers filter by its API plugin.
	 *
	 * @return array<int, string> Active provider slugs.
	 */
	public function get_running_providers(): array {
		$running   = $this->get_running_git_servers();
		$providers = [];
		foreach ( array_keys( self::PROVIDERS ) as $provider ) {
			if ( in_array( $provider, $running, true ) ) {
				$providers[] = $provider;
			}
		}
		return $providers;
	}

	/**
	 * Cron callback: re-notify the admin every 36 hours while any running
	 * provider's Connect button is still displayed (no token stored).
	 * Mirrors the condition in render_connect_field().
	 *
	 * @return void
	 */
	public function remind_admin_of_token_revocation(): void {
		foreach ( $this->get_running_providers() as $provider ) {
			$options   = get_site_option( 'git_updater', [] );
			$config    = self::PROVIDERS[ $provider ];
			$has_token = ! empty( $options[ $config['option_key'] ] );

			// Only remind when the Connect button is actually displayed.
			if ( ! $has_token ) {
				$this->notify_admin_of_token_revocation( $provider );
			}
		}
	}
}
