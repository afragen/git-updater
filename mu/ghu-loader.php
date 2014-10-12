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

// deactivate normal plugin
function GHU_deactivate_normal_plugin() {
    $normal_plugin = 'github-updater/github-updater.php';
    if ( is_plugin_active( $normal_plugin ) )
        deactivate_plugins( $normal_plugin );
}
add_action( 'admin_init', 'GHU_deactivate_normal_plugin' );

// Load normal plugin
if ( ! class_exists( 'GitHub_Updater' ) ) {
	require_once 'github-updater/github-updater.php';
}
