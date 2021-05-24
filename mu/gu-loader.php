<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  MIT
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

/**
 * Plugin Name:       Git Updater MU loader
 * Plugin URI:        https://github.com/afragen/git-updater
 * Description:       A plugin to load Git Updater as a must-use plugin. Disables normal plugin activation and deletion.
 * Version:           3.0.0
 * Author:            Andy Fragen
 * License:           MIT
 * GitHub Plugin URI: https://github.com/afragen/git-updater/tree/develop/mu
 * Requires PHP:      7.0
 */

namespace Fragen\Git_Updater;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class MU_Loader
 */
class MU_Loader {
	/**
	 * Holds plugin file.
	 *
	 * @var $plugin_file
	 */
	private static $plugin_file = 'git-updater/git-updater.php';

	/**
	 * Let's get going.
	 * Load the plugin and hooks.
	 *
	 * @return void
	 */
	public function run() {
		define( 'GU_MU_LOADER', true );
		if ( ! class_exists( 'Bootstrap' ) ) {
			require trailingslashit( WP_PLUGIN_DIR ) . self::$plugin_file;
		}
		$this->load_hooks();
	}

	/**
	 * Load action and filter hooks.
	 *
	 * @return void
	 */
	public function load_hooks() {
		// Deactivate normal plugin as it's loaded as mu-plugin.
		add_action( 'activated_plugin', [ $this, 'deactivate' ], 10, 1 );

		/*
		* Remove links and checkbox from Plugins page so user can't delete main plugin.
		*/
		add_filter( 'network_admin_plugin_action_links_' . static::$plugin_file, [ $this, 'mu_plugin_active' ] );
		add_filter( 'plugin_action_links_' . static::$plugin_file, [ $this, 'mu_plugin_active' ] );
		add_action(
			'after_plugin_row_' . static::$plugin_file,
			function () {
				print '<script>jQuery(".inactive[data-plugin=\'git-updater/git-updater.php\']").attr("class", "active");</script>';
				print '<script>jQuery(".active[data-plugin=\'git-updater/git-updater.php\'] .check-column input").remove();</script>';
			}
		);
	}

	/**
	 * Deactivate if plugin in loaded not as mu-plugin.
	 *
	 * @param string $plugin Plugin slug.
	 */
	public function deactivate( $plugin ) {
		if ( static::$plugin_file === $plugin ) {
			deactivate_plugins( static::$plugin_file );
		}
	}

	/**
	 * Label as mu-plugin in plugin view.
	 *
	 * @param array $actions Link actions.
	 *
	 * @return array
	 */
	public function mu_plugin_active( $actions ) {
		if ( isset( $actions['activate'] ) ) {
			unset( $actions['activate'] );
		}
		if ( isset( $actions['delete'] ) ) {
			unset( $actions['delete'] );
		}
		if ( isset( $actions['deactivate'] ) ) {
			unset( $actions['deactivate'] );
		}

		return array_merge( [ 'mu-plugin' => esc_html__( 'Activated as mu-plugin', 'git-updater' ) ], $actions );
	}
}

( new MU_Loader() )->run();
