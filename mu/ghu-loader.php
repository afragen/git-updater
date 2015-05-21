<?php
/**
 * GitHub Updater
 *
 * @package   GitHub_Updater
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 */

use Fragen\GitHub_Updater;

/*
Plugin Name:       GitHub Updater MU loader
Plugin URI:        https://github.com/afragen/github-updater
Description:       A plugin to load GitHub Updater as a must-use plugin. Disables normal plugin activation and deletion.
Version:           1.5.0
Author:            Andy Fragen
License:           GNU General Public License v2
License URI:       http://www.gnu.org/licenses/gpl-2.0.html
GitHub Plugin URI: https://github.com/afragen/github-updater/tree/develop/mu
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/*
 * Load normal plugin.
 */
if ( ! class_exists( '\\Fragen\\GitHub_Updater\\Base' ) ) {
	$ghu_plugin_file = 'github-updater/github-updater.php';
	require trailingslashit( WP_PLUGIN_DIR ). $ghu_plugin_file;
}

/**
 * Deactivate if plugin in loaded not as mu-plugin.
 * @param $plugin
 * @param $network_wide
 */
function ghu_deactivate( $plugin, $network_wide ) {
	$ghu_plugin_file = 'github-updater/github-updater.php';
	if ( $ghu_plugin_file === $plugin ) {
		deactivate_plugins( $ghu_plugin_file );
	}
}

/**
 * Label as mu-plugin in plugin view.
 * @param $actions
 *
 * @return array
 */
function ghu_mu_plugin_active( $actions ) {
	if ( isset( $actions['activate'] ) ) {
		unset( $actions['activate'] );
	}
	if ( isset( $actions['delete'] ) ) {
		unset( $actions['delete'] );
	}
	if ( isset( $actions['deactivate'] ) ) {
		unset( $actions['deactivate'] );
	}

	return array_merge( array( 'mu-plugin' => __('Activated as mu-plugin', 'github-updater' ) ), $actions );
}

/*
 * Deactivate normal plugin as it's loaded as mu-plugin.
 */
add_action( 'activated_plugin', 'ghu_deactivate', 10, 2 );

/*
 * Remove links and checkbox from Plugins page so user can't delete main plugin.
 */
add_filter( 'network_admin_plugin_action_links_' . $ghu_plugin_file, 'ghu_mu_plugin_active' );
add_filter( 'plugin_action_links_' . $ghu_plugin_file, 'ghu_mu_plugin_active' );
add_action( 'after_plugin_row_' . $ghu_plugin_file,
	function() {
		print('<script>jQuery("#github-updater .check-column").html("");</script>');
	} );
