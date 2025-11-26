<?php
/**
 * MU Loader
 *
 * @author   Andy Fragen, Colin Stewart
 * @license  MIT
 * @package mu-plugins
 */

/**
 * Plugin Name:       MU Loader
 * Plugin URI:        https://gist.github.com/afragen/9117fd930d9be16be8a5f450b809dfa8
 * Description:       An mu-plugin to load plugins as a must-use plugins. Disables normal plugin activation and deletion.
 * Version:           0.8.0
 * Author:            WordPress Upgrade/Install Team
 * License:           MIT
 * Text Domain:       mu-loader
 * GitHub Plugin URI: https://gist.github.com/afragen/9117fd930d9be16be8a5f450b809dfa8
 * Requires PHP:      7.4
 * Requires WP:       5.9
 */

namespace WP_Plugin_Install_Team;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class MU_Loader
 */
class MU_Loader {
	/**
	 * Holds array of plugin files.
	 *
	 * Add filepath to array, 'my-plugin/my-plugin.php'.
	 *
	 * @var array
	 */
	private static $plugin_files = [ 'git-updater/git-updater.php', 'git-updater-f27e06/git-updater.php' ];

	/**
	 * Let's get going.
	 * Load the plugin and hooks.
	 *
	 * @return void
	 */
	public function run() {
		foreach ( static::$plugin_files as $key => $plugin_file ) {
			$plugin_filepath = trailingslashit( WP_PLUGIN_DIR ) . $plugin_file;
			if ( file_exists( $plugin_filepath ) ) {
				require $plugin_filepath;
				$this->load_hooks( $plugin_file );
			} else {
				unset( static::$plugin_files[ $key ] );
			}
		}
		add_filter( 'option_active_plugins', [ $this, 'set_as_active' ], 10, 1 );
	}

	/**
	 * Load action and filter hooks.
	 *
	 * Remove links and disable checkbox from Plugins page so user can't delete main plugin.
	 * Ensure plugin shows as active.
	 *
	 * @param string $plugin_file Plugin file.
	 * @return void
	 */
	public function load_hooks( $plugin_file ) {
		add_filter( 'network_admin_plugin_action_links_' . $plugin_file, [ $this, 'mu_plugin_active' ] );
		add_filter( 'plugin_action_links_' . $plugin_file, [ $this, 'mu_plugin_active' ] );
		add_action( 'after_plugin_row_' . $plugin_file, [ $this, 'after_plugin_row_updates' ] );
		add_action( 'after_plugin_row_meta', [ $this, 'display_as_mu_plugin' ], 10, 1 );
		add_action( 'plugins_list', [ $this, 'put_in_mu_plugin_tab' ], 10, 1 );
	}

	/**
	 * Make plugin row active and disable checkbox.
	 *
	 * @param string $plugin_file Plugin file.
	 * @return void
	 */
	public function after_plugin_row_updates( $plugin_file ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		print "<script>jQuery('.inactive[data-plugin=\"{$plugin_file}\"]').attr('class','active');</script>";
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		print "<script>jQuery('.active[data-plugin=\"{$plugin_file}\"] .check-column input').attr( 'disabled','disabled' );</script>";
	}

	/**
	 * Add 'Activated as mu-plugin' to plugin row meta.
	 *
	 * @param string $plugin_file Plugin file.
	 * @return void
	 */
	public function display_as_mu_plugin( $plugin_file ) {
		if ( in_array( $plugin_file, (array) static::$plugin_files, true ) ) {
			printf(
				'<br><span style="color:#a7aaad;">%s</span>',
				esc_html__( 'Activated as Must-Use plugin', 'mu-loader' )
			);
		}
	}

	/**
	 * Unset action links.
	 *
	 * @param array $actions Link actions.
	 * @return array
	 */
	public function mu_plugin_active( $actions ) {
		unset( $actions['activate'], $actions['delete'], $actions['deactivate'] );

		return $actions;
	}

	/**
	 * Set mu-plugins as active.
	 *
	 * @param array $active_plugins Array of active plugins.
	 * @return array
	 */
	public function set_as_active( $active_plugins ) {
		$active_plugins = array_merge( $active_plugins, static::$plugin_files );

		return array_unique( $active_plugins );
	}

	/**
	 * Move plugins to Must-Use tab.
	 *
	 * @param array $tabs Array of plugin tabs.
	 * @return array
	 */
	public function put_in_mu_plugin_tab( $tabs ) {
		foreach ( static::$plugin_files as $plugin_file ) {
			if ( ! isset( $tabs['all'][ $plugin_file ] ) ) {
				continue;
			}
			$tabs['mustuse'][ $plugin_file ] = $tabs['all'][ $plugin_file ];
			unset( $tabs['all'][ $plugin_file ] );
			unset( $tabs['active'][ $plugin_file ] );
		}
		return $tabs;
	}
}

( new MU_Loader() )->run();
