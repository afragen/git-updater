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
class GU_Appsero {

	protected $plugin_file;

	public function __construct( $file ) {
		$this->plugin_file = $file;
	}

	public function init() {
		$this->appsero_init_tracker_git_updater();
	}

	/**
	 * Initialize the plugin tracker
	 *
	 * @return void
	 */
	public function appsero_init_tracker_git_updater() {
		global $gu_license;

		$client = new \Appsero\Client( 'fcd3d5c3-e40c-4484-9530-037955cef71f', 'Git Updater', $this->plugin_file );

		$gu_license = $client;

		// Active insights
		//$client->insights()->init();

		// Activate insights and don't show notice.
		$client->insights()->hide_notice()->init();
		$client->insights()->optin();

		// Active automatic updater
		// $client->updater();
	}
}
