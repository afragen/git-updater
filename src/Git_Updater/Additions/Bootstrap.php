<?php
/**
 * Git Updater Additions
 *
 * @author    Andy Fragen
 * @license   GPL-3.0-or-later
 * @link      https://github.com/afragen/git-updater-additions
 * @package   git-updater-additions
 */

namespace Fragen\Git_Updater\Additions;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Bootstrap
 */
class Bootstrap {
	/**
	 * Run the bootstrap.
	 *
	 * @return bool|void
	 */
	public function run() {
		( new Settings() )->load_hooks();
	}
}
