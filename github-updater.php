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
Description:       A plugin to automatically update GitHub, Bitbucket or GitLab hosted plugins and themes. It also allows for remote installation of plugins or themes into WordPress. Plugin class based upon <a href="https://github.com/codepress/github-plugin-updater">codepress/github-plugin-updater</a>. Theme class based upon <a href="https://github.com/WordPress-Phoenix/whitelabel-framework">Whitelabel Framework</a> modifications.
Version:           4.5.5
Author:            Andy Fragen
License:           GNU General Public License v2
License URI:       http://www.gnu.org/licenses/gpl-2.0.html
Domain Path:       /languages
Text Domain:       github-updater
Network:           true
GitHub Plugin URI: https://github.com/afragen/github-updater
GitHub Branch:     master
Requires WP:       3.8
Requires PHP:      5.3
*/

/*
 * Exit if called directly.
 * PHP version check and exit.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once ( plugin_dir_path( __FILE__ ) . '/vendor/WPUpdatePhp.php' );
$updatePhp = new WPUpdatePhp( '5.3.0' );
$updatePhp->set_plugin_name( 'GitHub Updater' );

if ( ! $updatePhp->does_it_meet_required_php_version() ) {
	return false;
}

// Load textdomain
load_plugin_textdomain( 'github-updater', false, __DIR__ . '/languages' );

// Plugin namespace root
$root = array( 'Fragen\\GitHub_Updater' => __DIR__ . '/src/GitHub_Updater' );

// Add extra classes
$extra_classes = array(
	'Parsedown'         => __DIR__ . '/vendor/Parsedown.php',
	'WPUpdatePHP'       => __DIR__ . '/vendor/WPUpdatePhp.php',
	'Automattic_Readme' => __DIR__ . '/vendor/parse-readme.php',
	);

// Load Autoloader
require_once( __DIR__ . '/src/GitHub_Updater/Autoloader.php' );
$loader = 'Fragen\\GitHub_Updater\\Autoloader';
new $loader( $root, $extra_classes );

// Instantiate class GitHub_Updater
$instantiate = 'Fragen\\GitHub_Updater\\Base';
new $instantiate;

/*
 * Calls Fragen\GitHub_Updater\Base::init() in init hook so other remote upgrader apps like
 * InfiniteWP, ManageWP, MainWP, and iThemes Sync will load and use all
 * of GitHub_Updater's methods, especially renaming.
 */
add_action( 'init', array( 'Fragen\\GitHub_Updater\\Base', 'init' ) );
