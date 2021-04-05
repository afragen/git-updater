<?php
/**
 * Git Updater PRO
 *
 * @author   Andy Fragen
 * @license  MIT
 * @link     https://github.com/afragen/git-updater-pro
 * @package  git-updater-pro
 */

namespace Fragen\Git_Updater\PRO;

use Fragen\Git_Updater\API\Zipfile_API;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Init.
 */
class Init {
	/**
	 * Load hooks.
	 *
	 * @return void
	 */
	public function load_hooks() {
		define( 'GIT_UPDATER_PRO', true );

		( new Zipfile_API() )->load_hooks();

		add_filter(
			'gu_pro_dl_package',
			function ( $response, $repo ) {
				return array_merge( $response, [ 'package' => $repo->download_link ] );
			},
			10,
			2
		);

		add_action(
			'plugins_loaded',
			function () {
				// Bail if Git Updater not active.
				if ( ! class_exists( '\\Fragen\\Git_Updater\\Bootstrap' ) ) {
					return false;
				}
				( new Bootstrap() )->run();
			}
		);
	}
}
