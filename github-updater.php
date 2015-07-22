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
Description:       A plugin to automatically update GitHub, Bitbucket or GitLab hosted plugins and themes. It also allows for remote installation of plugins or themes into WordPress.
Version:           4.6.2
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
