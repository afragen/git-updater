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
 * Freemius integration.
 */
class GU_Freemius {
	use GU_Trait;

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
					if ( ! defined( 'WP_FS__PRODUCT_8195_MULTISITE' ) ) {
						define( 'WP_FS__PRODUCT_8195_MULTISITE', true );
					}

					$gu_fs = fs_dynamic_init(
						[
							'id'                  => '8195',
							'slug'                => 'git-updater-free',
							'premium_slug'        => 'git-updater',
							'type'                => 'plugin',
							'public_key'          => 'pk_2cf29ecaf78f5e10f5543c71f7f8b',
							'is_premium'          => true,
							'premium_suffix'      => 'PRO',
							// If your plugin is a serviceware, set this option to false.
							'has_premium_version' => true,
							'has_addons'          => true,
							'has_paid_plans'      => true,
							'is_org_compliant'    => false,
							'trial'               => [
								'days'               => 14,
								'is_require_payment' => true,
							],
							'menu'                => [
								'slug'    => 'git-updater',
								'contact' => false,
								'support' => false,
								'network' => true,
								'parent'  => [
									'slug' => 'options-general.php',
								],
							],
							// Set the SDK to work in a sandbox mode (for development & testing).
							// IMPORTANT: MAKE SURE TO REMOVE SECRET KEY BEFORE DEPLOYMENT.
							'secret_key'       => '',
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
		remove_all_filters( 'after_plugin_row_git-updater-free/git-updater.php' );

		add_action(
			'admin_init',
			function() {
				remove_action( 'admin_footer', [ gu_fs(), '_add_premium_version_upgrade_selection_dialog_box' ] );
			}
		);
	}

	/**
	 * Freemius uninstall cleanup.
	 *
	 * @return void
	 */
	public function gu_fs_uninstall_cleanup() {
		$options = [ 'github_updater', 'github_updater_api_key', 'github_updater_remote_management', 'git_updater', 'git_updater_api_key' ];
		foreach ( $options as $option ) {
			delete_option( $option );
			delete_site_option( $option );
		}

		$this->delete_all_cached_data();
	}
}
