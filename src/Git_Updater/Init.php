<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  MIT
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

namespace Fragen\Git_Updater;

use Fragen\Singleton;
use Fragen\Git_Updater\Traits\GU_Trait;
use Fragen\Git_Updater\Traits\Basic_Auth_Loader;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Init
 */
class Init {
	use GU_Trait, Basic_Auth_Loader;

	/**
	 * Holds Class Base object.
	 *
	 * @var Base $base
	 */
	protected $base;

	/**
	 * Constuctor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->load_options();
		$this->base = Singleton::get_instance( 'Base', $this );
	}

	/**
	 * Let's get going.
	 */
	public function run() {
		if ( ! static::is_heartbeat() ) {
			$this->load_hooks();
		}

		if ( static::is_wp_cli() ) {
			include_once __DIR__ . '/WP_CLI/CLI.php';
			include_once __DIR__ . '/WP_CLI/CLI_Integration.php';

			Singleton::get_instance( 'Plugin', $this )->get_remote_plugin_meta();
			add_filter( 'site_transient_update_plugins', [ Singleton::get_instance( 'Plugin', $this ), 'update_site_transient' ], 15, 1 );

			Singleton::get_instance( 'Theme', $this )->get_remote_theme_meta();
			add_filter( 'site_transient_update_themes', [ Singleton::get_instance( 'Theme', $this ), 'update_site_transient' ], 15, 1 );
		}
	}

	/**
	 * Load relevant action/filter hooks.
	 * Use 'init' hook for user capabilities.
	 */
	protected function load_hooks() {
		add_action( 'init', [ $this->base, 'load' ] );
		add_action( 'init', [ $this->base, 'background_update' ] );
		add_action( 'init', [ $this->base, 'set_options_filter' ] );

		add_action( 'deprecated_hook_run', [ new Messages(), 'deprecated_error_message' ], 10, 4 );

		// `wp_get_environment_type()` added in WordPress 5.5.
		if ( function_exists( 'wp_get_environment_type' ) && 'development' === wp_get_environment_type() ) {
			add_filter( 'deprecated_hook_trigger_error', '__return_false' );
		}

		// Load hook for adding authentication headers for download packages.
		add_filter(
			'upgrader_pre_download',
			function() {
				add_filter( 'http_request_args', [ $this, 'download_package' ], 15, 2 );
				return false; // upgrader_pre_download filter default return value.
			}
		);
		add_filter( 'upgrader_source_selection', [ $this->base, 'upgrader_source_selection' ], 10, 4 );

		// Add git host icons.
		add_filter( 'plugin_row_meta', [ $this->base, 'row_meta_icons' ], 15, 2 );
		add_filter( 'theme_row_meta', [ $this->base, 'row_meta_icons' ], 15, 2 );

		// Check for deletion of cron event.
		add_filter( 'pre_unschedule_event', [ Singleton::get_instance( 'GU_Upgrade', $this ), 'pre_unschedule_event' ], 10, 3 );
	}

	/**
	 * Checks current user capabilities.
	 *
	 * @return bool
	 */
	public function can_update() {
		// WP-CLI access has full capabilities.
		if ( static::is_wp_cli() ) {
			return true;
		}

		$can_user_update = current_user_can( 'update_plugins' ) && current_user_can( 'update_themes' );

		/**
		 * Filter $admin_pages to be able to adjust the pages where Git Updater runs.
		 *
		 * @since 8.0.0
		 * @deprecated 9.1.0
		 *
		 * @param array $admin_pages Default array of admin pages where Git Updater runs.
		 */
		apply_filters_deprecated( 'github_updater_add_admin_pages', [ null ], '9.1.0' );

		return $can_user_update;
	}
}
