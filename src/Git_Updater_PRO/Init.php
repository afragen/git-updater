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
		( new Zipfile_API() )->load_hooks();
		( new Bootstrap() )->run();
	}
}
