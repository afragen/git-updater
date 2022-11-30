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

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Freemius integration.
 * Freemius 'start.php' autoloaded via composer.
 */
class GU_Freemius {

	/**
	 * Freemius integration.
	 *
	 * @return array|void
	 */
	public function init() {
		if ( ! function_exists( 'gu_fs' ) ) {

			/**
			 * Create a helper function for easy SDK access.
			 *
			 * @return \stdClass
			 */
			function gu_fs() {
				global $gu_fs;

				if ( ! isset( $gu_fs ) ) {
					// Activate multisite network integration.
					if ( ! defined( 'WP_FS__PRODUCT_11525_MULTISITE' ) ) {
						define( 'WP_FS__PRODUCT_11525_MULTISITE', true );
					}

					// Init Freemius SDK.
					require_once dirname( __DIR__, 2 ) . '/vendor/freemius/wordpress-sdk/start.php';

					$gu_fs = fs_dynamic_init(
						[
							'id'               => '11525',
							'slug'             => 'git-updater',
							'premium_slug'     => 'git-updater',
							'type'             => 'plugin',
							'public_key'       => 'pk_aaa04d83b4c42470937266f9b4fca',
							'is_premium'       => true,
							'is_premium_only'  => true,
							'has_addons'       => false,
							'has_paid_plans'   => true,
							'is_org_compliant' => false,
							'trial'            => [
								'days'               => 14,
								'is_require_payment' => true,
							],
							'menu'             => [
								'slug'    => 'git-updater',
								'support' => false,
								'network' => true,
								'parent'  => [
									'slug' => is_multisite() ? 'settings.php' : 'options-general.php',
								],
							],
							// Set the SDK to work in a sandbox mode (for development & testing).
							// IMPORTANT: MAKE SURE TO REMOVE SECRET KEY BEFORE DEPLOYMENT.
							'secret_key'       => 'sk_Jj*#qrkDgrf&tMvR+oW?4ad?l^Q>U',
						]
					);
				}

				return $gu_fs;
			}

			// Init Freemius.
			gu_fs();
			// Signal that SDK was initiated.
			do_action( 'gu_fs_loaded' );
		}
		gu_fs()->add_filter( 'plugin_icon', [ $this, 'add_icon' ] );
		gu_fs()->add_filter( 'is_submenu_visible', [ $this, 'is_submenu_visible' ], 10, 2 );
	}

	/**
	 * Add custom plugin icon to update notice.
	 *
	 * @return string
	 */
	public function add_icon() {
		return dirname( __DIR__, 2 ) . '/assets/icon.svg';
	}

	/**
	 * Show the contact submenu item only when the user have a valid non-expired license.
	 *
	 * @param $is_visible The filtered value. Whether the submenu item should be visible or not.
	 * @param $menu_id    The ID of the submenu item.
	 *
	 * @return bool If true, the menu item should be visible.
	 */
	public function is_submenu_visible( $is_visible, $menu_id ) {
		if ( 'contact' != $menu_id ) {
			return $is_visible;
		}

		return gu_fs()->can_use_premium_code();
	}

}
