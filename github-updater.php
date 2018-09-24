<?php
/**
 * GitHub Updater
 *
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 * @package    github-updater
 */

/**
 * Plugin Name:       GitHub Updater
 * Plugin URI:        https://github.com/afragen/github-updater
 * Description:       A plugin to automatically update GitHub, Bitbucket, GitLab, or Gitea hosted plugins, themes, and language packs. It also allows for remote installation of plugins or themes into WordPress.
 * Version:           8.3.1.4
 * Author:            Andy Fragen
 * License:           GNU General Public License v2
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path:       /languages
 * Text Domain:       github-updater
 * Network:           true
 * GitHub Plugin URI: https://github.com/afragen/github-updater
 * GitHub Languages:  https://github.com/afragen/github-updater-translations
 * Requires WP:       4.6
 * Requires PHP:      5.6
 */

/*
 * Exit if called directly.
 * PHP version check and exit.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( version_compare( '5.6.0', PHP_VERSION, '>=' ) ) {
	echo '<div class="error notice is-dismissible"><p>';
	printf(
		/* translators: 1: minimum PHP version required, 2: Upgrade PHP URL */
		wp_kses_post( __( 'GitHub Updater cannot run on PHP versions older than %1$s. <a href="%2$s">Learn about upgrading your PHP.</a>', 'github-updater' ) ),
		'5.6.0',
		esc_url( __( 'https://wordpress.org/support/upgrade-php/' ) )
	);
	echo '</p></div>';

	return false;
}

define( 'GITHUB_UPDATER_FILE', __FILE__ );
define( 'GITHUB_UPDATER_DIR', __DIR__ );

// Setup plugin loading.
require_once __DIR__ . '/src/GitHub_Updater/Bootstrap.php';
