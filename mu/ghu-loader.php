<?php
/**
 * GitHub Updater
 *
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 * @package   github-updater
 */

/**
 * Plugin Name:       GitHub Updater MU loader
 * Plugin URI:        https://github.com/afragen/github-updater
 * Description:       A plugin to load GitHub Updater as a must-use plugin. Disables normal plugin activation and deletion.
 * Version:           1.5.3
 * Author:            Andy Fragen
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.html
 * GitHub Plugin URI: https://github.com/afragen/github-updater/tree/develop/mu
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/*
 * Load normal plugin.
 */
if ( ! class_exists( 'Fragen\\GitHub_Updater\\Bootstrap' ) ) {
	$ghu_plugin_file = 'github-updater/github-updater.php';
	require trailingslashit( WP_PLUGIN_DIR ) . $ghu_plugin_file;
}

/**
 * Deactivate if plugin in loaded not as mu-plugin.
 *
 * @param string $plugin Plugin slug.
 */
function ghu_deactivate( $plugin ) {
	$ghu_plugin_file = 'github-updater/github-updater.php';
	if ( $ghu_plugin_file === $plugin ) {
		deactivate_plugins( $ghu_plugin_file );
	}
}

/**
 * Label as mu-plugin in plugin view.
 *
 * @param array $actions Link actions.
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

	return array_merge( [ 'mu-plugin' => esc_html__( 'Activated as mu-plugin', 'github-updater' ) ], $actions );
}

/*
 * Deactivate normal plugin as it's loaded as mu-plugin.
 */
add_action( 'activated_plugin', 'ghu_deactivate', 10, 1 );

/*
 * Remove links and checkbox from Plugins page so user can't delete main plugin.
 */
add_filter( 'network_admin_plugin_action_links_' . $ghu_plugin_file, 'ghu_mu_plugin_active' );
add_filter( 'plugin_action_links_' . $ghu_plugin_file, 'ghu_mu_plugin_active' );
add_action(
	'after_plugin_row_' . $ghu_plugin_file,
	function () {
		print '<script>jQuery(".inactive[data-plugin=\'github-updater/github-updater.php\']").attr("class", "active");</script>';
		print '<script>jQuery(".active[data-plugin=\'github-updater/github-updater.php\'] .check-column input").remove();</script>';
	}
);
