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

/**
 * Class Bootstrap
 */
class Bootstrap {
	/**
	 * Holds main plugin file.
	 *
	 * @var $file
	 */
	protected $file;

	/**
	 * Holds main plugin directory.
	 *
	 * @var $dir
	 */
	protected $dir;

	/**
	 * Constructor.
	 *
	 * @param  string $file Main plugin file.
	 * @return void
	 */
	public function __construct( $file ) {
		$this->file = $file;
		$this->dir  = dirname( $file );
	}

	/**
	 * Run the bootstrap.
	 *
	 * @return void
	 */
	public function run() {
		add_action(
			'init',
			function() {
				load_plugin_textdomain( 'github-updater' );
			}
		);

		define( 'GITHUB_UPDATER_DIR', $this->dir );

		// Load Autoloader.
		require_once $this->dir . '/vendor/autoload.php';

		register_activation_hook( $this->file, array( new Init(), 'rename_on_activation' ) );
		( new Init() )->run();

		/**
		 * Initialize Persist Admin notices Dismissal.
		 *
		 * @link https://github.com/collizo4sky/persist-admin-notices-dismissal
		 */
		add_action( 'admin_init', array( 'PAnD', 'init' ) );
	}
}
