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
	 * Constructor.
	 *
	 * @param  string $file Main plugin file.
	 * @return void
	 */
	public function __construct( $file ) {
		$this->file = $file;
		$this->dir  = dirname( $file );
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
			/* translators: %1: opening tag, %2: closing tag */
			__( 'Git Updater is missing required composer dependencies. %1$sLearn more.%2$s', 'git-updater' ),
			'<a href="https://github.com/afragen/git-updater/wiki/Installation" target="_blank" rel="noreferrer">',
			'</a>'
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

		( new GU_Appsero( $this->file ) )->init();
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
		if ( version_compare( phpversion(), '7.2', '<=' ) ) {
			add_action(
				'admin_init',
				function () {
					echo '<div class="error notice is-dismissible"><p>';
					printf(
						/* translators: 1: minimum PHP version required */
						wp_kses_post( __( 'Git Updater cannot run on PHP versions older than %1$s.', 'git-updater' ) ),
						'7.2'
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
	 * @return void|bool
	 */
	public function rename_on_activation() {
		// Exit if coming from webhook.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['plugin'], $_GET['webhook_source'] ) && 'git-updater' === $_GET['plugin'] ) {
			return;
		}

		$plugin_dir = trailingslashit( WP_PLUGIN_DIR );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$slug     = isset( $_GET['plugin'] ) ? sanitize_text_field( wp_unslash( $_GET['plugin'] ) ) : false;
		$exploded = explode( '-', dirname( $slug ) );

		if ( in_array( 'develop', $exploded, true ) ) {
			$options = $this->get_class_vars( 'Base', 'options' );
			update_site_option( 'git_updater', array_merge( $options, [ 'current_branch_git-updater' => 'develop' ] ) );
		}

		if ( $slug && 'git-updater/git-updater.php' !== $slug ) {
			$result = move_dir( $plugin_dir . dirname( $slug ), $plugin_dir . 'git-updater' );
			if ( \is_wp_error( $result ) ) {
				return $result;
			}
		}
	}
}
