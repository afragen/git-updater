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
	 * Rename on activation.
	 *
	 * Correctly renames the slug when Git Updater is installed
	 * via FTP or from plugin upload.
	 *
	 * Set current branch to `develop` if appropriate.
	 *
	 * `rename()` causes activation to fail.
	 *
	 * @return void
	 */
	public function rename_on_activation() {
		$plugin_dir = trailingslashit( WP_PLUGIN_DIR );
		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$slug     = isset( $_GET['plugin'] ) ? sanitize_text_field( wp_unslash( $_GET['plugin'] ) ) : false;
		$exploded = explode( '-', dirname( $slug ) );

		if ( in_array( 'develop', $exploded, true ) ) {
			$options = $this->get_class_vars( 'Base', 'options' );
			update_site_option( 'git_updater', array_merge( $options, [ 'current_branch_git-updater' => 'develop' ] ) );
		}

		if ( $slug && 'git-updater/git-updater.php' !== $slug ) {
			@rename( $plugin_dir . dirname( $slug ), $plugin_dir . 'git-updater' );
		}
	}

	/**
	 * Let's get going.
	 */
	public function run() {
		if ( ! static::is_heartbeat() ) {
			$this->load_hooks();
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
