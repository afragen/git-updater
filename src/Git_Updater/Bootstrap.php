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

use Fragen\Git_Updater\PRO\Bootstrap as Bootstrap_PRO;

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

		if ( ! gu_fs()->can_use_premium_code() ) {
			$this->allow_self_update();
		}
		if ( gu_fs()->is__premium_only() ) {
			( new Bootstrap_PRO() )->run();
		}
		( new Init() )->run();

		register_deactivation_hook( $this->file, [ $this, 'remove_cron_events' ] );
		gu_fs()->add_action( 'after_uninstall', [ $this, 'gu_fs_uninstall_cleanup' ] );

		/**
		 * Initialize Persist Admin notices Dismissal.
		 *
		 * @link https://github.com/collizo4sky/persist-admin-notices-dismissal
		 */
		add_action( 'admin_init', [ 'PAnD', 'init' ] );
	}

	/**
	 * Check PHP requirements and deactivate plugin if not met.
	 *
	 * @return void|bool
	 */
	public function check_requirements() {
		if ( version_compare( phpversion(), '7.0', '<=' ) ) {
			add_action(
				'admin_init',
				function () {
					echo '<div class="error notice is-dismissible"><p>';
					printf(
						/* translators: 1: minimum PHP version required */
						wp_kses_post( __( 'Git Updater cannot run on PHP versions older than %1$s.', 'git-updater' ) ),
						'7.0'
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
	 * Remove Freemius dashboard update overrides.
	 *
	 * @return void
	 */
	private function allow_self_update() {
		remove_all_filters( 'after_plugin_row_git-updater/git-updater.php' );
		add_action(
			'admin_init',
			function() {
				remove_action( 'admin_footer', [ gu_fs(), '_add_premium_version_upgrade_selection_dialog_box' ] );
			}
		);
	}

	/**
	 * Freemius uninstall cleanup.
	 *
	 * @return void
	 */
	private function gu_fs_uninstall_cleanup() {
		$options = [ 'github_updater', 'github_updater_api_key', 'github_updater_remote_management', 'git_updater', 'git_updater_api_key' ];
		foreach ( $options as $option ) {
			delete_option( $option );
			delete_site_option( $option );
		}

		global $wpdb;
		$table         = is_multisite() ? $wpdb->base_prefix . 'sitemeta' : $wpdb->base_prefix . 'options';
		$column        = is_multisite() ? 'meta_key' : 'option_name';
		$delete_string = 'DELETE FROM ' . $table . ' WHERE ' . $column . ' LIKE %s LIMIT 1000';

		$wpdb->query( $wpdb->prepare( $delete_string, [ '%ghu-%' ] ) ); // phpcs:ignore
	}

	/**
	 * Freemius integration.
	 *
	 * @return array|void
	 */
	public function freemius() {
		if ( ! function_exists( 'gu_fs' ) ) {

			/**
			 * Create a helper function for easy SDK access.
			 *
			 * @return \stdClass
			 */
			function gu_fs() {
				global $gu_fs;

				if ( ! isset( $gu_fs ) ) {
					// Activate multisite network integration.
					if ( ! defined( 'WP_FS__PRODUCT_8195_MULTISITE' ) ) {
						define( 'WP_FS__PRODUCT_8195_MULTISITE', true );
					}

					$gu_fs = fs_dynamic_init(
						[
							'id'               => '8195',
							'slug'             => 'git-updater',
							'premium_slug'     => 'git-updater',
							'type'             => 'plugin',
							'public_key'       => 'pk_2cf29ecaf78f5e10f5543c71f7f8b',
							'is_premium'       => true,
							'is_premium_only'  => true,
							'has_addons'       => true,
							'has_paid_plans'   => true,
							'is_org_compliant' => false,
							'trial'            => [
								'days'               => 14,
								'is_require_payment' => true,
							],
							'menu'             => [
								'slug'    => 'git-updater',
								'contact' => false,
								'support' => false,
								'network' => true,
								'parent'  => [
									'slug' => 'options-general.php',
							],
							],
							// Set the SDK to work in a sandbox mode (for development & testing).
							// IMPORTANT: MAKE SURE TO REMOVE SECRET KEY BEFORE DEPLOYMENT.
							'secret_key'       => '',
						]
					);
				}

				return $gu_fs;
			}

			// Init Freemius.
			gu_fs();
			// Signal that SDK was initiated.
			do_action( 'gu_fs_loaded' );
		}
	}
}
