<?php
/**
 * GitHub Updater
 *
 * @package   GitHub_Updater
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 */

/*
Plugin Name:       GitHub Updater MU loader
Plugin URI:        https://github.com/afragen/github-updater
Description:       A plugin to automatically update GitHub or Bitbucket hosted plugins and themes into WordPress. Disables normal plugin activation and deletion.
Version:           1.1.0
Author:            Andy Fragen
License:           GNU General Public License v2
License URI:       http://www.gnu.org/licenses/gpl-2.0.html
GitHub Plugin URI: https://github.com/afragen/github-updater/tree/develop/mu
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Load normal plugin
if ( ! class_exists( 'GitHub_Updater' ) ) {
	define( 'GHU_PLUGIN_FILE', 'github-updater/github-updater.php' );
	require_once trailingslashit( WP_PLUGIN_DIR ). GHU_PLUGIN_FILE;
}

function ghu_deactivate( $plugin, $network_wide ) {
	if ( GHU_PLUGIN_FILE === $plugin ) {
		deactivate_plugins( GHU_PLUGIN_FILE );
	}
}

function ghu_mu_plugin_active( $actions ) {
	if ( isset( $actions['activate'] ) ) {
		unset( $actions['activate'] );
	}
	if ( isset( $actions['delete'] ) ) {
		unset( $actions['delete'] );
	}

	return array_merge( array( 'mu-plugin' => 'Activated as mu-plugin' ), $actions );
}

// Deactivate normal plugin as it's loaded as mu-plugin
add_action( 'activated_plugin', 'ghu_deactivate', 10, 2 );

// Remove links from Plugins page so user can't delete main plugin
add_filter( 'network_admin_plugin_action_links_' . GHU_PLUGIN_FILE, 'ghu_mu_plugin_active' );
add_filter( 'plugin_action_links_' . GHU_PLUGIN_FILE, 'ghu_mu_plugin_active' );
