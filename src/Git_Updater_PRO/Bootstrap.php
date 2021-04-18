<?php
/**
 * Git Updater PRO
 *
 * @author    Andy Fragen
 * @license   MIT
 * @link      https://github.com/afragen/git-updater-pro
 * @package   git-updater-pro
 */

namespace Fragen\Git_Updater\PRO;

use Fragen\Singleton;
use Fragen\Git_Updater\Traits\GU_Trait;
use Fragen\Git_Updater\PRO\REST\REST_API;

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
	use GU_Trait;

	/**
	 * Run the bootstrap.
	 *
	 * @return bool|void
	 */
	public function run() {
		$this->load_hooks();

		if ( static::is_wp_cli() ) {
			include_once __DIR__ . '/WP_CLI/CLI.php';
			include_once __DIR__ . '/WP_CLI/CLI_Integration.php';
		}

		// Need to ensure these classes are activated here for hooks to fire.
		if ( $this->is_current_page( [ 'options.php', 'options-general.php', 'settings.php', 'edit.php' ] ) ) {
			Singleton::get_instance( 'Install', $this )->run();
			Singleton::get_instance( 'Remote_Management', $this )->load_hooks();
		}
	}

	/**
	 * Load hooks.
	 *
	 * @return void
	 */
	public function load_hooks() {
		add_action( 'rest_api_init', [ new REST_API(), 'register_endpoints' ] );

		// Deprecated AJAX request.
		add_action( 'wp_ajax_git-updater-update', [ Singleton::get_instance( 'REST\Rest_Update', $this ), 'process_request' ] );
		add_action( 'wp_ajax_nopriv_git-updater-update', [ Singleton::get_instance( 'REST\Rest_Update', $this ), 'process_request' ] );
	}
}
