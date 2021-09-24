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

use Fragen\Git_Updater\Traits\GU_Trait;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Load textdomain.
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'git-updater' );
	}
);

/**
 * Class Bootstrap
 */
class Bootstrap {
	use GU_Trait;

	/**
	 * Holds main plugin file.
	 *
	 * @var $file
	 */
	protected $file;

	/**
	 * Holds main plugin directory.
	 *
	 * @var $dir
	 */
	protected $dir;

	/**
	 * Holds nonce.
	 *
	 * @var $nonce
	 */
	protected static $nonce;

	/**
	 * Constructor.
	 *
	 * @param  string $file Main plugin file.
	 * @return void
	 */
	public function __construct( $file ) {
		$this->file = $file;
		$this->dir  = dirname( $file );
		add_action(
			'plugins_loaded',
			function() {
				static::$nonce = wp_create_nonce( 'git-updater' );
			}
		);
	}

	/**
	 * Deactivate plugin and die as composer autoloader not loaded.
	 *
	 * @return void
	 */
	public function deactivate_die() {
		require_once ABSPATH . '/wp-admin/includes/plugin.php';
		\deactivate_plugins( plugin_basename( $this->file ) );

		$message = sprintf(
			/* translators: %s: documentation URL */
			__( 'Git Updater is missing required composer dependencies. <a href="%s" target="_blank" rel="noopenernoreferer">Learn more.</a>', 'git-updater' ),
			'https://github.com/afragen/git-updater/wiki/Installation'
		);

		wp_die( wp_kses_post( $message ) );
	}

	/**
	 * Run the bootstrap.
	 *
	 * @return void
	 */
	public function run() {
		if ( ! $this->check_requirements() ) {
			return;
		}

		register_deactivation_hook( $this->file, [ $this, 'remove_cron_events' ] );

		( new Init() )->run();

		// Initialize time dissmissible admin notices.
		new \WP_Dismiss_Notice();
	}

	/**
	 * Check PHP requirements and deactivate plugin if not met.
	 *
	 * @return void|bool
	 */
	public function check_requirements() {
		if ( version_compare( phpversion(), '5.6', '<=' ) ) {
			add_action(
				'admin_init',
				function () {
					echo '<div class="error notice is-dismissible"><p>';
					printf(
						/* translators: 1: minimum PHP version required */
						wp_kses_post( __( 'Git Updater cannot run on PHP versions older than %1$s.', 'git-updater' ) ),
						'5.6'
					);
					echo '</p></div>';
					\deactivate_plugins( plugin_basename( $this->file ) );
				}
			);

			return false;
		}

		return true;
	}

	/**
	 * Remove scheduled cron events on deactivation.
	 *
	 * @return void
	 */
	public function remove_cron_events() {
		$crons = [ 'gu_get_remote_plugin', 'gu_get_remote_theme' ];
		foreach ( $crons as $cron ) {
			$timestamp = \wp_next_scheduled( $cron );
			\wp_unschedule_event( $timestamp, $cron );
		}
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
		if ( ! wp_verify_nonce( static::$nonce, 'git-updater' ) ) {
			return;
		}
		$plugin_dir = trailingslashit( WP_PLUGIN_DIR );
		$slug       = isset( $_GET['plugin'] ) ? sanitize_text_field( wp_unslash( $_GET['plugin'] ) ) : false;
		$exploded   = explode( '-', dirname( $slug ) );

		if ( in_array( 'develop', $exploded, true ) ) {
			$options = $this->get_class_vars( 'Base', 'options' );
			update_site_option( 'git_updater', array_merge( $options, [ 'current_branch_git-updater' => 'develop' ] ) );
		}

		if ( $slug && 'git-updater/git-updater.php' !== $slug ) {
			@rename( $plugin_dir . dirname( $slug ), $plugin_dir . 'git-updater' );
		}
	}
}
