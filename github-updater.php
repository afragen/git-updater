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
Version:           3.2.6.2
Author:            Andy Fragen
License:           GNU General Public License v2
License URI:       http://www.gnu.org/licenses/gpl-2.0.html
Domain Path:       /languages
Text Domain:       github-updater
GitHub Plugin URI: https://github.com/afragen/github-updater
GitHub Branch:     develop
Requires WP:       3.8
Requires PHP:      5.3
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Load textdomain
load_plugin_textdomain( 'github-updater', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

// Plugin namespace root
$root = array( 'Fragen\GitHub_Updater' => __DIR__ . '/classes/GitHub_Updater' );

// Add compat classes
$compatibility = array( 'Fragen\GitHub_Updater\Parsedown' => __DIR__ . '/classes/Parsedown.php' );

// Load Autoloader
require_once( __DIR__ . '/classes/GitHub_Updater/Autoloader.php' );
$class_loader = 'Fragen\GitHub_Updater\Autoloader';
new $class_loader( $root, $compatibility );

// Instantiate class GitHub_Updater
new GitHub_Updater__Base;

/**
 * Calls GitHub_Updater__Base::init() in init hook so other remote upgrader apps like
 * InfiniteWP, ManageWP, MainWP, and iThemes Sync will load and use all
 * of GitHub_Updater's methods, especially renaming.
 */
add_action( 'init', array( 'GitHub_Updater__Base', 'init' ) );
