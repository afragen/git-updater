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
Plugin Name:       GitHub Updater
Plugin URI:        https://github.com/afragen/github-updater
Description:       A plugin to automatically update GitHub or Bitbucket hosted plugins and themes into WordPress. Plugin class based upon <a href="https://github.com/codepress/github-plugin-updater">codepress/github-plugin-updater</a>. Theme class based upon <a href="https://github.com/WordPress-Phoenix/whitelabel-framework">Whitelabel Framework</a> modifications.
Version:           2.8.0.3
Author:            Andy Fragen
License:           GNU General Public License v2
License URI:       http://www.gnu.org/licenses/gpl-2.0.html
Domain Path:       /languages
Text Domain:       github-updater
GitHub Plugin URI: https://github.com/afragen/github-updater
GitHub Branch:     develop
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

add_action( 'plugins_loaded', 'ghu_load_launch' );

/**
 * Load and launch classes
 *
 * This function required so launching may occur after plugins_loaded
 * this allows for remote update services to function.
 */
function ghu_load_launch() {
	// Load base classes and Launch
	if ( is_admin() && ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) ) {
		if ( ! class_exists( 'GitHub_Updater' ) ) {
			require_once 'includes/class-github-updater.php';
			require_once 'includes/class-github-api.php';
			require_once 'includes/class-bitbucket-api.php';
		}
		if ( ! class_exists( 'GitHub_Plugin_Updater' ) ) {
			require_once 'includes/class-plugin-updater.php';
			new GitHub_Plugin_Updater;
		}
		if ( ! class_exists( 'GitHub_Theme_Updater' ) ) {
			require_once 'includes/class-theme-updater.php';
			new GitHub_Theme_Updater;
		}
	}
}
