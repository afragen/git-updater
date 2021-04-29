<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  MIT
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

/**
 * Plugin Name:       Git Updater
 * Plugin URI:        https://github.com/afragen/git-updater
 * Description:       A plugin to automatically update GitHub hosted plugins, themes, and language packs. Additional API plugins available for Bitbucket, GitLab, Gitea, and Gist.
 * Version:           9.9.12.3
 * Author:            Andy Fragen
 * License:           MIT
 * Domain Path:       /languages
 * Text Domain:       git-updater
 * Network:           true
 * GitHub Plugin URI: https://github.com/afragen/git-updater
 * GitHub Languages:  https://github.com/afragen/git-updater-translations
 * Requires at least: 5.2
 * Requires PHP:      7.0
 *
 * @fs_premium_only /src/Git_Updater_PRO
 */

namespace Fragen\Git_Updater;

use Fragen\Git_Updater\API\Zipfile_API;

/*
 * Exit if called directly.
 * PHP version check and exit.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Load the Composer autoloader.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
}

// Check for composer autoloader.
if ( ! class_exists( 'Fragen\Git_Updater\Bootstrap' ) ) {
	require_once __DIR__ . '/src/Git_Updater/Bootstrap.php';
	( new Bootstrap( __FILE__ ) )->deactivate_die();
}

( new Zipfile_API() )->load_hooks();
( new GU_Freemius() )->init();
add_action(
	'plugins_loaded',
	function() {
		( new Bootstrap( __FILE__ ) )->run();
	}
);
register_activation_hook( __FILE__, [ new Init(), 'rename_on_activation' ] );
