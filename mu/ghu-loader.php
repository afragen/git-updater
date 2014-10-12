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
Description:       A plugin to automatically update GitHub or Bitbucket hosted plugins and themes into WordPress. Plugin class based upon <a href="https://github.com/codepress/github-plugin-updater">codepress/github-plugin-updater</a>. Theme class based upon <a href="https://github.com/WordPress-Phoenix/whitelabel-framework">Whitelabel Framework</a> modifications.
Version:           1.0.0
Author:            Andy Fragen
License:           GNU General Public License v2
License URI:       http://www.gnu.org/licenses/gpl-2.0.html
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Load normal plugin
if ( ! class_exists( 'GitHub_Updater' ) ) {
	require_once WP_PLUGIN_DIR . '/github-updater/github-updater.php';
}

function ghu_deactivate() {
	deactivate_plugins( 'github-updater/github-updater.php' );
}

function ghu_mu_plugin_active() {
	return array( 'Activated as mu-plugin' );
}

//deactivate normal plugin as it's loaded as mu-plugin
add_action( 'admin_init', 'ghu_deactivate' );

//remove links from plugins.php so user can't delete main plugin
add_filter( 'network_admin_plugin_action_links_github-updater/github-updater.php', 'ghu_mu_plugin_active' );
add_filter( 'plugin_action_links_github-updater/github-updater.php', 'ghu_mu_plugin_active' );
