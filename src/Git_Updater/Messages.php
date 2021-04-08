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

		if (
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			( ( ! isset( $_GET['page'] ) || 'git-updater' !== $_GET['page'] )
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
							'show_403_error_message',
						]
					);
					add_action(
						is_multisite() ? 'network_admin_notices' : 'admin_notices',
						[
							$this,
							'show_401_error_message',
						]
					);
			}
		}

		return true;
	}

	/**
	 * Create error message for 403 error.
	 * Usually 403 as API rate limit max out.
	 */
	public function show_403_error_message() {
		$_403       = false;
		$error_code = $this->get_error_codes();
		foreach ( (array) $error_code as $repo ) {
			if ( ( ! $_403 && isset( $repo['code'], $repo['git'] ) )
				&& 403 === $repo['code'] && 'github' === $repo['git'] ) {
				$_403 = true;
				if ( ! \PAnD::is_admin_notice_active( '403-error-1' ) ) {
					return;
				} ?>
				<div data-dismissible="403-error-1" class="notice-error notice is-dismissible">
					<p>
						<?php
						esc_html_e( 'Git Updater Error Code:', 'git-updater' );
						echo ' ' . esc_attr( $repo['code'] );
						?>
						<br>
						<?php
						printf(
							/* translators: %s: wait time */
							esc_html__( 'GitHub API&#8217;s rate limit will reset in %s minutes.', 'git-updater' ),
							esc_attr( $repo['wait'] )
						);
						echo '<br>';
						printf(
							/* translators: %s: GitHub personal access token URL */
							wp_kses_post( __( 'It looks like you are running into GitHub API rate limits. Be sure and configure a <a href="%s">Personal Access Token</a> to avoid this issue.', 'git-updater' ) ),
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
	 */
	public function show_401_error_message() {
		$_401       = false;
		$error_code = $this->get_error_codes();
		foreach ( (array) $error_code as $repo ) {
			if ( ( ! $_401 && isset( $repo['code'] ) ) && 401 === $repo['code'] ) {
				$_401 = true;
				if ( ! \PAnD::is_admin_notice_active( '401-error-1' ) ) {
					return;
				}
				?>
				<div data-dismissible="401-error-1" class="notice-error notice is-dismissible">
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
	 * Git Updater PRO upsell notice.
	 *
	 * @return void
	 */
	public function show_upsell() {
		if ( $this->is_pro_running() ) {
			return;
		}
		?>
		<div class="notice-info notice">
			<p>
				<?php esc_html_e( 'Git Updater PRO', 'git-updater' ); ?>
				<br>
				<?php
				printf(
					/* translators: %1: opening href tag, %2: closing href tag */
					esc_html__( 'Unlock PRO features like remote installation, branch switching, REST API, WP-CLI, and more. Information at %1$sgit-updater.com%2$s.', 'git-updater' ),
					'<a href="https://git-updater.com">',
					'</a>'
				);
				?>
			</p>
		</div>
		<?php
	}
}
