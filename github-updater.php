<?php
/**
 * GitHub Updater
 *
 * @author  Andy Fragen
 * @license GPL-2.0+
 * @link    https://github.com/afragen/github-updater
 * @package github-updater
 */

/**
 * Plugin Name:       GitHub Updater
 * Plugin URI:        https://github.com/afragen/github-updater
 * Description:       A plugin to automatically update GitHub, Bitbucket, GitLab, or Gitea hosted plugins, themes, and language packs. It also allows for remote installation of plugins or themes into WordPress.
 * Version:           9.3.2
 * Author:            Andy Fragen
 * License:           GNU General Public License v2
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path:       /languages
 * Text Domain:       github-updater
 * Network:           true
 * GitHub Plugin URI: https://github.com/afragen/github-updater
 * GitHub Languages:  https://github.com/afragen/github-updater-translations
 * Requires at least: 4.6
 * Requires PHP:      5.6
 */

namespace Fragen\GitHub_Updater;

/*
 * Exit if called directly.
 * PHP version check and exit.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Load Autoloader.
require_once __DIR__ . '/vendor/autoload.php';

( new Bootstrap( __FILE__ ) )->run();
