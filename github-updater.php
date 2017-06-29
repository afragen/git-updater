<?php
/**
 * GitHub Updater
 *
 * @package   GitHub_Updater
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 */

/**
 * Plugin Name:       GitHub Updater
 * Plugin URI:        https://github.com/afragen/github-updater
 * Description:       A plugin to automatically update GitHub, Bitbucket, or GitLab hosted plugins, themes, and language packs. It also allows for remote installation of plugins or themes into WordPress.
 * Version:           6.3.4.3
 * Author:            Andy Fragen
 * License:           GNU General Public License v2
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path:       /languages
 * Text Domain:       github-updater
 * Network:           true
 * GitHub Plugin URI: https://github.com/afragen/github-updater
 * GitHub Branch:     develop
 * GitHub Languages:  https://github.com/afragen/github-updater-translations
 * Requires WP:       4.4
 * Requires PHP:      5.3
 */

/*
 * Exit if called directly.
 * PHP version check and exit.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( version_compare( '5.3.0', PHP_VERSION, '>=' ) ) {
	?>
	<div class="error notice is-dismissible">
		<p>
			<?php esc_html_e( 'GitHub Updater cannot run on PHP versions older than 5.3.0. Please contact your hosting provider to update your site.', 'github-updater' ); ?>
		</p>
	</div>
	<?php

	return false;
}

// Load textdomain.
load_plugin_textdomain( 'github-updater' );

// Plugin namespace root.
$root = array( 'Fragen\\GitHub_Updater' => __DIR__ . '/src/GitHub_Updater' );

// Add extra classes.
$extra_classes = array(
	'WordPressdotorg\Plugin_Directory\Readme\Parser' => __DIR__ . '/vendor/class-parser.php',

	'Parsedown' => __DIR__ . '/vendor/parsedown/Parsedown.php',
	'PAnD'      => __DIR__ . '/vendor/persist-admin-notices-dismissal/persist-admin-notices-dismissal.php',
);

// Load Autoloader.
require_once __DIR__ . '/src/Autoloader.php';
$loader = 'Fragen\\Autoloader';
new $loader( $root, $extra_classes );

// Instantiate class GitHub_Updater.
$instantiate = 'Fragen\\GitHub_Updater\\Base';
new $instantiate;

/**
 * Initialize Persist Admin notices Dismissal.
 *
 * @link https://github.com/collizo4sky/persist-admin-notices-dismissal
 */
add_action( 'admin_init', array( 'PAnD', 'init' ) );
