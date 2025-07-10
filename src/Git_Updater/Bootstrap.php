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

use Fragen\Git_Updater\Additions\Bootstrap as Additions_Bootstrap;
use Fragen\Git_Updater\REST\REST_API;
use Fragen\Git_Updater\Traits\GU_Trait;
use WP_Dismiss_Notice;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Bootstrap
 */
class Bootstrap {
	use GU_Trait;

	/**
	 * Deactivate plugin and die as composer autoloader not loaded.
	 *
	 * @return void
	 */
	public function deactivate_die() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		deactivate_plugins( plugin_basename( PLUGIN_FILE ) );

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
		register_deactivation_hook( PLUGIN_FILE, [ $this, 'remove_cron_events' ] );
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		deactivate_plugins( [ 'git-updater-pro/git-updater-pro.php', 'git-updater-additions/git-updater-additions.php' ] );

		require_once __DIR__ . '/Shim.php';
		( new GU_Freemius() )->init();
		( new REST_API() )->load_hooks();
		( new Additions_Bootstrap() )->run();
		( new Init() )->run();
		( new Messages() )->create_error_message( 'get_license' );

		// Initialize time dissmissible admin notices.
		new WP_Dismiss_Notice();

		// Check for update API redirect.
		add_action( 'init', fn() => $this->check_update_api_redirect(), 0 );
	}

	/**
	 * Remove scheduled cron events on deactivation.
	 *
	 * @return void
	 */
	public function remove_cron_events() {
		$crons = [ 'gu_get_remote_plugin', 'gu_get_remote_theme' ];
		foreach ( $crons as $cron ) {
			$timestamp = wp_next_scheduled( $cron );
			wp_unschedule_event( $timestamp, $cron );
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
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

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

		// Strip hash from slug and re-make file.
		$hook = current_action();
		$file = str_replace( 'activate_', '', $hook );
		$file = $this->get_file_without_did_hash( 'did:plc:afjf7gsjzsqmgc7dlhb553mv', $file );

		if ( $slug && 'git-updater/git-updater.php' !== $file ) {
			require_once __DIR__ . '/Shim.php';
			$result = move_dir( $plugin_dir . dirname( $slug ), $plugin_dir . 'git-updater', true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
	}

	/**
	 * Check for API redirects from AspireUpdate or FAIR Package Manager.
	 *
	 * Run appropriate filter to redefine update API.
	 *
	 * @return void
	 */
	public function check_update_api_redirect() {
		if ( class_exists( '\AspireUpdate\Admin_Settings' ) ) {
			add_filter( 'gu_api_domain', fn () => \AspireUpdate\Admin_Settings::get_instance()->get_setting( 'api_host' ) );
		}

		if ( function_exists( '\Fair\Default_Repo\get_default_repo_domain' ) ) {
			add_filter( 'gu_api_domain', fn () => \FAIR\Default_Repo\get_default_repo_domain() );
		}
	}
}
