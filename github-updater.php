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
Description:       A plugin to automatically update GitHub hosted plugins and themes into WordPress. Plugin class based upon <a href="https://github.com/codepress/github-plugin-updater">codepress/github-plugin-updater</a>. Theme class based upon <a href="https://github.com/WordPress-Phoenix/whitelabel-framework">Whitelabel Framework</a> modifications.
Version:           2.4.1
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

if ( version_compare(PHP_VERSION, '5.3', '<') ) {
	// PHP version is insufficient
	require_once 'includes/class-plugin-deactivate-self.php';
	new Plugin_Deactivate_Self(
		plugin_basename( __FILE__ ),
		'<strong>GitHub Updater</strong> requires a minimum of PHP 5.3; This plug-in has been <strong>deactivated</strong>.'
	);
} else {
	// Load base classes and Launch
	if ( is_admin() ) {
		require_once 'includes/class-github-updater.php';
		require_once 'includes/class-github-api.php';
		require_once 'includes/class-plugin-updater.php';
		require_once 'includes/class-theme-updater.php';
		new GitHub_Plugin_Updater;
		new GitHub_Theme_Updater;
	}
}
