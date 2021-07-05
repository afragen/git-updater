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

					// Init Freemius SDK.
					require_once Shim::dirname( __DIR__, 2 ) . '/vendor/freemius/wordpress-sdk/start.php';

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
								'first-path' => 'plugins.php',
								'account'    => false,
								'contact'    => false,
								'support'    => false,
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
		gu_fs()->add_filter( 'plugin_icon', [ $this, 'add_icon' ] );
	}

	/**
	 * Add custom plugin icon to update notice.
	 *
	 * @return string
	 */
	public function add_icon() {
		return Shim::dirname( __DIR__, 2 ) . '/assets/icon.svg';
	}
}
