<?php
/**
 * GitHub Updater
 *
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 * @package   github-updater
 */

namespace Fragen\GitHub_Updater;

use Fragen\GitHub_Updater\Traits\GHU_Trait;

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
	use GHU_Trait;

	/**
	 * Holds WP_Error message.
	 *
	 * @var string
	 */
	public static $error_message = '';

	/**
	 * Display message when API returns other than 200 or 404.
	 *
	 * @param string $type
	 *
	 * @return bool
	 */
	public function create_error_message( $type = '' ) {
		global $pagenow;

		$update_pages   = [ 'update-core.php', 'plugins.php', 'themes.php' ];
		$settings_pages = [ 'settings.php', 'options-general.php' ];

		if (
			( ( ! isset( $_GET['page'] ) || 'github-updater' !== $_GET['page'] ) &&
			in_array( $pagenow, $settings_pages, true ) ) ||
			! in_array( $pagenow, array_merge( $update_pages, $settings_pages ), true )
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
					if ( ! apply_filters( 'github_updater_disable_wpcron', false ) ) {
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
						esc_html_e( 'GitHub Updater Error Code:', 'github-updater' );
						echo ' ' . $repo['code'];
						?>
						<br>
						<?php
						printf(
							/* translators: %s: wait time */
							esc_html__( 'GitHub API&#8217;s rate limit will reset in %s minutes.', 'github-updater' ),
							$repo['wait']
						);
						echo '<br>';
						printf(
							/* translators: %s: GitHub personal access token URL */
							wp_kses_post( __( 'It looks like you are running into GitHub API rate limits. Be sure and configure a <a href="%s">Personal Access Token</a> to avoid this issue.', 'github-updater' ) ),
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
						esc_html_e( 'GitHub Updater Error Code:', 'github-updater' );
						echo ' ' . $repo['code'];
						?>
						<br>
						<?php esc_html_e( 'There is probably an access token or password error on the GitHub Updater Settings page.', 'github-updater' ); ?>
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
				esc_html_e( 'GitHub Updater Error Code:', 'github-updater' );
				echo ' ' . self::$error_message;
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
				<?php esc_html_e( 'GitHub Updater Information', 'github-updater' ); ?>
				<br>
				<?php esc_html_e( 'Please be patient while WP-Cron finishes making API calls.', 'github-updater' ); ?>
			</p>
		</div>
		<?php
	}
}
