<?php
/**
 * GitHub Updater
 *
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 * @package   github-updater
 */

namespace Fragen\GitHub_Updater;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

( new Bootstrap() )->run( GITHUB_UPDATER_FILE, GITHUB_UPDATER_DIR );

/**
 * Class Bootstrap
 */
class Bootstrap {

	/**
	 * Run the bootstrap.
	 *
	 * @param string $file Path to main plugin file.
	 * @param string $dir Path to main plugin directory.
	 * @return void
	 */
	public function run( $file, $dir ) {
		add_action(
			'init',
			function() {
				load_plugin_textdomain( 'github-updater' );
			}
		);

		// Plugin namespace root.
		$root = array( 'Fragen\\GitHub_Updater' => __DIR__ );

		// Add extra classes.
		$extra_classes = array(
			'WordPressdotorg\Plugin_Directory\Readme\Parser' => $dir . '/vendor/class-parser.php',
			'Fragen\Singleton' => $dir . '/src/Singleton.php',
			'Parsedown'        => $dir . '/vendor/parsedown/Parsedown.php',
			'PAnD'             => $dir . '/vendor/persist-admin-notices-dismissal/persist-admin-notices-dismissal.php',
		);

		// Load Autoloader.
		require_once $dir . '/src/Autoloader.php';
		new \Fragen\Autoloader( $root, $extra_classes );

		register_activation_hook( $file, array( new Init(), 'rename_on_activation' ) );
		( new Init() )->run();

		/**
		 * Initialize Persist Admin notices Dismissal.
		 *
		 * @link https://github.com/collizo4sky/persist-admin-notices-dismissal
		 */
		add_action( 'admin_init', array( 'PAnD', 'init' ) );
	}
}
