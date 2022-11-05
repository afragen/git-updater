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
 * Appsero SDK integration.
 * Appsero SDK autoloaded via composer.
 */
class GU_Appsero {

	/**
	 * Plugin file path.
	 *
	 * @var string
	 */
	protected $plugin_file;

	/**
	 * Constructor.
	 *
	 * @param string $file Plugin file path.
	 */
	public function __construct( $file ) {
		$this->plugin_file = $file;
	}

	/**
	 * Let's get going.
	 *
	 * @return void
	 */
	public function init() {
		$this->appsero_init_tracker_git_updater();
	}

	/**
	 * Initialize the plugin tracker.
	 *
	 * @return void
	 */
	public function appsero_init_tracker_git_updater() {
		global $gu_license;

		$client = new \Appsero\Client( 'fcd3d5c3-e40c-4484-9530-037955cef71f', 'Git Updater', $this->plugin_file );

		// Activate insights.
		$client->insights()
			->hide_notice()
			->add_plugin_data()
			->init();
		if ( 'yes' !== get_option( $client->slug . '_allow_tracking' ) ) {
			$client->insights()->optin();
		}

		$gu_license = $client->license();

		// Active license page and checker
		// $parent = is_multisite() ? 'settings.php' : 'options-general.php';
		// $args = array(
		// 'type'        => 'submenu',
		// 'menu_title'  => 'Git Updater License',
		// 'page_title'  => 'Git Updater License Settings',
		// 'menu_slug'   => 'git-updater-license',
		// 'parent_slug' => $parent,
		// );
		// $client->license()->add_settings_page( $args );

		 // Active automatic updater.
		 // $client->updater();
	}
}
