<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  GPL-3.0-or-later
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

namespace Fragen\Git_Updater;

use Fragen\Git_Updater\Traits\GU_Trait;
use WP_Dismiss_Notice;
use WP_Error;

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
	 * @param string|WP_Error $type Error type.
	 *
	 * @return bool
	 */
	public function create_error_message( $type = '' ) {
		global $pagenow;

		$update_pages   = [ 'update-core.php', 'plugins.php', 'themes.php' ];
		$settings_pages = [ 'settings.php', 'options-general.php' ];

		if ( ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'gu_settings' ) )
			&& ( ( ( ! isset( $_GET['page'] ) || 'git-updater' !== $_GET['page'] )
			&& in_array( $pagenow, $settings_pages, true ) )
			|| ! in_array( $pagenow, array_merge( $update_pages, $settings_pages ), true ) )
		) {
			return false;
		}

		if ( is_admin() && ! wp_doing_ajax() ) {
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
					if ( ! $disable_wp_cron ) {
						add_action( is_multisite() ? 'network_admin_notices' : 'admin_notices', [ $this, 'waiting' ] );
					}
					// no break.
				case 'git':
				default:
			}
		}

		return true;
	}

	/**
	 * Generate error message for WP_Error.
	 *
	 * @return void
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
	 *
	 * @return void
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
	 * Generate information message to purchase.
	 *
	 * @return void
	 */
	public function get_license() {
		if ( ( ! gu_fs()->is_not_paying() )
			|| ! WP_Dismiss_Notice::is_admin_notice_active( 'license-3' )
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
