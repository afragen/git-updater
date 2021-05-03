<?php
/**
 * Git Updater PRO
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
					if ( ! defined( 'WP_FS__PRODUCT_8311_MULTISITE' ) ) {
						define( 'WP_FS__PRODUCT_8311_MULTISITE', true );
					}

					$gu_fs = fs_dynamic_init(
						[
							'id'               => '8311',
							'slug'             => 'git-updater',
							'type'             => 'plugin',
							'public_key'       => 'pk_3576c57a06f23b313b049a78cc886',
							'is_premium'       => false,
							'has_addons'       => false,
							'has_paid_plans'   => false,
							'is_org_compliant' => false,
							'menu'             => [
								'slug'    => 'git-updater',
								'account' => false,
								'contact' => false,
								'support' => false,
								'network' => true,
								'parent'  => [
									'slug' => 'options-general.php',
								],
							],
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
	}

	/**
	 * Remove Freemius dashboard update overrides.
	 *
	 * @return void
	 */
	public function allow_self_update() {
		remove_all_filters( 'after_plugin_row_git-updater/git-updater.php' );
		remove_all_filters( 'after_plugin_row_git-updater-pro/git-updater.php' );

		add_action(
			'admin_init',
			function() {
				remove_action( 'admin_footer', [ gu_fs(), '_add_premium_version_upgrade_selection_dialog_box' ] );
			}
		);
	}
}
