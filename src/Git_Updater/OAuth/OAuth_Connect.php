<?php
/**
 * Git Updater OAuth Connect
 *
 * Handles OAuth token acquisition via connector service.
 *
 * @package Git_Updater
 */

namespace Fragen\Git_Updater\OAuth;

use Fragen\Singleton;

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
			'option_key' => 'github_access_token',
			'label'      => 'GitHub',
		],
		'gitlab'    => [
			'option_key' => 'gitlab_access_token',
			'label'      => 'GitLab',
		],
		'bitbucket' => [
			'option_key' => 'bitbucket_access_token',
			'label'      => 'Bitbucket',
		],
		'gitea'     => [
			'option_key' => 'gitea_access_token',
			'label'      => 'Gitea',
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

		// Build the authorize URL on the connector
		$authorize_url = $connector . 'git-updater/' . $provider . '/oauth/authorize';
		$authorize_url = add_query_arg( 'redirect', rawurlencode( $callback_url ), $authorize_url );

		// Add Gitea-specific parameters if needed
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

		$token = $this->fetch_token_from_connector( $provider, $exchange_code );

		if ( $token ) {
			$this->save_token( $provider, $token );
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
	 * Fetch token from connector using exchange code.
	 *
	 * @param string $provider      Provider slug.
	 * @param string $exchange_code Exchange code from connector.
	 * @return string|null Token or null on failure.
	 */
	private function fetch_token_from_connector( string $provider, string $exchange_code ): ?string {
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
		return ! empty( $body['access_token'] ) ? sanitize_text_field( $body['access_token'] ) : null;
	}

	/**
	 * Save token to options.
	 *
	 * @param string $provider Provider slug.
	 * @param string $token    Access token.
	 * @return void
	 */
	private function save_token( string $provider, string $token ): void {
		$config  = self::PROVIDERS[ $provider ];
		$options = get_site_option( 'git_updater', [] );

		$options[ $config['option_key'] ] = $token;
		update_site_option( 'git_updater', $options );
	}

	/**
	 * Delete token from options.
	 *
	 * @param string $provider Provider slug.
	 * @return void
	 */
	private function delete_token( string $provider ): void {
		$config  = self::PROVIDERS[ $provider ];
		$options = get_site_option( 'git_updater', [] );

		unset( $options[ $config['option_key'] ] );
		update_site_option( 'git_updater', $options );
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
