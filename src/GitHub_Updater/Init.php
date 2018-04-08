<?php
/**
 * GitHub Updater
 *
 * @package   Fragen\GitHub_Updater
 * @author    Andy Fragen
 * @author    Gary Jones
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 */

namespace Fragen\GitHub_Updater;

use Fragen\Singleton;


/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

class Init extends Base {

	/**
	 * Let's get going.
	 */
	public function run() {
		$this->load_hooks();

		if ( static::is_wp_cli() ) {
			include_once __DIR__ . '/WP-CLI/CLI.php';
			include_once __DIR__ . '/WP-CLI/CLI_Integration.php';
		}
	}

	/**
	 * Load relevant action/filter hooks.
	 * Use 'init' hook for user capabilities.
	 */
	protected function load_hooks() {
		add_action( 'init', array( &$this, 'load' ) );
		add_action( 'init', array( &$this, 'background_update' ) );
		add_action( 'init', array( &$this, 'set_options_filter' ) );
		add_action( 'wp_ajax_github-updater-update', array( &$this, 'ajax_update' ) );
		add_action( 'wp_ajax_nopriv_github-updater-update', array( &$this, 'ajax_update' ) );
		add_action( 'upgrader_process_complete', function() {
			delete_site_option( 'ghu-' . md5( 'repos' ) );
		} );

		// Delete get_plugins() and wp_get_themes() cache.
		add_action( 'deleted_plugin', function() {
			wp_cache_delete( 'plugins', 'plugins' );
			delete_site_option( 'ghu-' . md5( 'repos' ) );
		} );

		// Load hook for shiny updates Basic Authentication headers.
		if ( self::is_doing_ajax() ) {
			Singleton::get_instance( 'Basic_Auth_Loader', $this, self::$options )->load_authentication_hooks();
		}

		add_filter( 'extra_theme_headers', array( &$this, 'add_headers' ) );
		add_filter( 'extra_plugin_headers', array( &$this, 'add_headers' ) );
		add_filter( 'upgrader_source_selection', array( &$this, 'upgrader_source_selection' ), 10, 4 );

		// Needed for updating from update-core.php.
		if ( ! self::is_doing_ajax() ) {
			add_filter( 'upgrader_pre_download',
				array(
					Singleton::get_instance( 'Basic_Auth_Loader', $this, self::$options ),
					'upgrader_pre_download',
				), 10, 3 );
		}

		// The following hook needed to ensure transient is reset correctly after shiny updates.
		add_filter( 'http_response', array( 'Fragen\\GitHub_Updater\\API', 'wp_update_response' ), 10, 3 );
	}

	/**
	 * Checks current user capabilities and admin pages.
	 *
	 * @return bool
	 */
	public function can_update() {
		global $pagenow;

		// WP-CLI access has full capabilities.
		if ( static::is_wp_cli() ) {
			return true;
		}

		$can_user_update = is_multisite()
			? current_user_can( 'manage_network' )
			: current_user_can( 'manage_options' );
		$this->load_options();

		$admin_pages = array(
			'plugins.php',
			'plugin-install.php',
			'themes.php',
			'theme-install.php',
			'update-core.php',
			'update.php',
			'options-general.php',
			'options.php',
			'settings.php',
			'edit.php',
			'admin-ajax.php',
		);

		// Add Settings menu.
		if ( ! apply_filters( 'github_updater_hide_settings', false ) ) {
			add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu',
				array( Singleton::get_instance( 'Settings', $this ), 'add_plugin_page' ) );
		}

		foreach ( array_keys( Settings::$remote_management ) as $key ) {
			// Remote management only needs to be active for admin pages.
			if ( ! empty( self::$options_remote[ $key ] ) && is_admin() ) {
				$admin_pages = array_merge( $admin_pages, array( 'index.php', 'admin-ajax.php' ) );
			}
		}

		return $can_user_update && in_array( $pagenow, array_unique( $admin_pages ), true );
	}

}
