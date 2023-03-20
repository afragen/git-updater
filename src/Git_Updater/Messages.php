<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  MIT
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

namespace Fragen\Git_Updater;

use Fragen\Git_Updater\Traits\GU_Trait;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Messages
 */
class Messages {
	use GU_Trait;

	/**
	 * Holds WP_Error message.
	 *
	 * @var string
	 */
	public static $error_message = '';

	/**
	 * Display message when API returns other than 200 or 404.
	 *
	 * @param string|\WP_Error $type Error type.
	 *
	 * @return bool
	 */
	public function create_error_message( $type = '' ) {
		global $pagenow;

		$update_pages   = [ 'update-core.php', 'plugins.php', 'themes.php' ];
		$settings_pages = [ 'settings.php', 'options-general.php' ];

		if ( ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'gu_settings' ) )
			&& ( ( ! isset( $_GET['page'] ) || 'git-updater' !== $_GET['page'] )
			&& in_array( $pagenow, $settings_pages, true ) )
			|| ! in_array( $pagenow, array_merge( $update_pages, $settings_pages ), true )
		) {
			return false;
		}

		if ( is_admin() && ! static::is_doing_ajax() ) {
			switch ( $type ) {
				case is_wp_error( $type ):
					self::$error_message = $type->get_error_message();
					add_action(
						is_multisite() ? 'network_admin_notices' : 'admin_notices',
						[
							$this,
							'show_wp_error',
						]
					);
					break;
				case 'get_license':
					add_action(
						is_multisite() ? 'network_admin_notices' : 'admin_notices',
						[
							$this,
							'get_license',
						]
					);
					break;
				case 'waiting':
					$disable_wp_cron = (bool) apply_filters( 'gu_disable_wpcron', false );
					$disable_wp_cron = $disable_wp_cron ?: (bool) apply_filters_deprecated( 'github_updater_disable_wpcron', [ false ], '10.0.0', 'gu_disable_wpcron' );

					if ( ! $disable_wp_cron ) {
						add_action( is_multisite() ? 'network_admin_notices' : 'admin_notices', [ $this, 'waiting' ] );
					}
					// no break.
				case 'git':
				default:
					add_action(
						is_multisite() ? 'network_admin_notices' : 'admin_notices',
						[
							$this,
							'show_ratelimit_error_message',
						]
					);
					add_action(
						is_multisite() ? 'network_admin_notices' : 'admin_notices',
						[
							$this,
							'show_authentication_error_message',
						]
					);
			}
		}

		return true;
	}

	/**
	 * Create error message for 403 error.
	 * GitHub uses 403 error as API rate limit max out.
	 */
	public function show_ratelimit_error_message() {
		$_ratelimit = false;
		$error_code = $this->get_error_codes();
		foreach ( (array) $error_code as $repo ) {
			if ( ( ! $_ratelimit && isset( $repo['code'], $repo['git'], $repo['wait'] ) )
				&& in_array( $repo['code'], [ 403, 404 ], true )
			) {
				$_ratelimit = true;
				$git_server = $this->get_class_vars( 'Base', 'git_servers' )[ $repo['git'] ];
				if ( ! \WP_Dismiss_Notice::is_admin_notice_active( 'ratelimit-error-1' ) ) {
					return;
				} ?>
				<div data-dismissible="ratelimit-error-1" class="notice-error notice is-dismissible">
					<p>
						<?php
						esc_html_e( 'Git Updater Error Code:', 'git-updater' );
						echo ' ' . esc_attr( $repo['code'] );
						?>
						<br>
						<?php
						printf(
							/* translators: %1$s: git server, %2$s: wait time */
							esc_html__( '%1$s API&#8217;s rate limit will reset in %2$s minutes.', 'git-updater' ),
							esc_attr( $git_server ),
							esc_attr( $repo['wait'] )
						);
						echo '<br>';
						printf(
							/* translators: %1$s: git server, %2$s: GitHub personal access token URL */
							wp_kses_post( __( 'It looks like you are running into %1$s API rate limits. Be sure and configure a <a href="%2$s">Personal Access Token</a> to avoid this issue.', 'git-updater' ) ),
							esc_attr( $git_server ),
							esc_url( 'https://help.github.com/articles/creating-an-access-token-for-command-line-use/' )
						);
						?>
					</p>
				</div>
				<?php
			}
		}
	}

	/**
	 * Create error message or 401 (Authentication Error) error.
	 * Usually 401 as private repo with no token set or incorrect user/pass.
	 * GitHub uses a 404 error as an authentication error.
	 */
	public function show_authentication_error_message() {
		$_authentication = false;
		$error_code      = $this->get_error_codes();
		foreach ( (array) $error_code as $repo ) {
			if ( ( ! $_authentication && isset( $repo['code'] ) ) && in_array( $repo['code'], [ 401, 404 ], true ) ) {
				$_authentication = true;
				if ( ! \WP_Dismiss_Notice::is_admin_notice_active( 'authentication-error-1' ) ) {
					return;
				}
				?>
				<div data-dismissible="authentication-error-1" class="notice-error notice is-dismissible">
					<p>
						<?php
						esc_html_e( 'Git Updater Error Code:', 'git-updater' );
						echo ' ' . esc_attr( $repo['code'] );
						?>
						<br>
						<?php esc_html_e( 'There is probably an access token or password error on the Git Updater Settings page.', 'git-updater' ); ?>
					</p>
				</div>
				<?php
			}
		}
	}

	/**
	 * Generate error message for WP_Error.
	 */
	public function show_wp_error() {
		?>
		<div class="notice-error notice">
			<p>
				<?php
				esc_html_e( 'Git Updater Error Code:', 'git-updater' );
				echo ' ' . esc_html( self::$error_message );
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Generate information message when waiting for WP-Cron to finish.
	 */
	public function waiting() {
		?>
		<div class="notice-info notice is-dismissible">
			<p>
				<?php esc_html_e( 'Git Updater Information', 'git-updater' ); ?>
				<br>
				<?php esc_html_e( 'Please be patient while WP-Cron finishes making API calls.', 'git-updater' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Log and error message when using deprecated filters.
	 *
	 * @uses `_deprecated_hook`.
	 *
	 * @param string $hook        The hook that was called.
	 * @param string $replacement The hook that should be used as a replacement.
	 * @param string $version     The version of WordPress that deprecated the argument used.
	 * @param string $message     A message regarding the change.
	 *
	 * @return void
	 */
	public function deprecated_error_message( $hook, $replacement, $version, $message ) {
		$options = $this->get_class_vars( 'Base', 'options' );
		if ( ! isset( $options['deprecated_error_logging'] ) ) {
			return;
		}
		if ( $replacement ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
					'%1$s is **deprecated** since version %2$s! Use %3$s instead.',
					$hook,
					$version,
					$replacement
				) . '&nbsp;' . $message
			);
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
					'%1$s is **deprecated** since version %2$s with no alternative available.',
					$hook,
					$version
				) . '&nbsp;' . $message
			);
		}
	}

	/**
	 * Generate information message to purchase.
	 */
	public function get_license() {
		if ( ( ! gu_fs()->is_not_paying() )
			|| ! \WP_Dismiss_Notice::is_admin_notice_active( 'license-3' )
		) {
			return;
		}

		?>
		<div data-dismissible="license-5" class="notice-info notice is-dismissible">
			<p>
				<?php esc_html_e( 'Please consider purchasing a Git Updater license for authenticated API requests and to support continued development.', 'git-updater' ); ?>
				<br>
				<?php esc_html_e( 'Only $19.99 for an unlimited yearly license.', 'git-updater' ); ?>
				<br><br>
				<a class="button primary-button regular" href="https://git-updater.com/store/"><?php esc_html_e( 'Purchase from Store', 'git-updater' ); ?></a>
			</p>
		</div>
		<?php
	}
}
