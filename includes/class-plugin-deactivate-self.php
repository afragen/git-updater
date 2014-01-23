<?php
/**
 * Plugin Deactivate Self
 *
 * @package   GitHub_Updater
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 */

/**
 * Code for a plugin to deactivate itself
 *
 * @package Plugin_Deactivate_self
 * @author  Sal Ferrarello
 */
class Plugin_Deactivate_Self {

	/**
	 * Path to plugin to be deactivated
	 *
	 * @since 2.4.2
	 *
	 * @var string
	 */
	protected $plugin_basename;

	/**
	 * Admin notice to display after deactivating plugin
	 *
	 * @since 2.4.2
	 *
	 * @var string
	 */
	protected $admin_notice;


	/**
	 * Constructor.
	 *
	 * @since 2.4.2
	 *
	 * @param string $plugin_basename - path to plugin to deactivate
	 * @param string $admin_notice - admin notice after deactivation
	 */
	public function __construct( 
		$plugin_basename, 
		$admin_notice = '<strong>Plug-in</strong> requires a minimum of PHP 5.3; This plug-in has been <strong>deactivated</strong>.' 
	) {
		$this->plugin_basename = $plugin_basename;
		$this->admin_notice = $admin_notice;
		add_action( 'admin_init', array( $this, 'deactivate' ) );
		add_action( 'admin_notices', array( $this, 'admin_notice' ) );
	}

	/**
	 * Deactivate plugin
	 *
	 * @since 2.4.2
	 */

	public function deactivate() {
		deactivate_plugins( $this->plugin_basename );
	}

	/**
	 * Display admin_notice of deactivation
	 *
	 * @since 2.4.2
	 */
	public function admin_notice() {
		echo '<div class="updated"><p>' .
			$this->admin_notice . 
			'</p></div>';
		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}
	}
}
