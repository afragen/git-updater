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

use Fragen\Singleton,
	Fragen\GitHub_Updater\Traits\GHU_Trait,
	Fragen\GitHub_Updater\Traits\Basic_Auth_Loader;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

class Init extends Base {
	use GHU_Trait, Basic_Auth_Loader;

	public function __construct() {
		parent::__construct();
		$this->load_options();
	}

	/**
	 * Let's get going.
	 */
	public function run() {
		$this->load_hooks();

		if ( static::is_wp_cli() ) {
			include_once __DIR__ . '/WP_CLI/CLI.php';
			include_once __DIR__ . '/WP_CLI/CLI_Integration.php';
		}
	}

	/**
	 * Load relevant action/filter hooks.
	 * Use 'init' hook for user capabilities.
	 */
	protected function load_hooks() {
		add_action( 'init', [ $this, 'load' ] );
		add_action( 'init', [ $this, 'background_update' ] );
		add_action( 'init', [ $this, 'set_options_filter' ] );
		add_action( 'wp_ajax_github-updater-update', [ $this, 'ajax_update' ] );
		add_action( 'wp_ajax_nopriv_github-updater-update', [ $this, 'ajax_update' ] );

		// Load hook for shiny updates Basic Authentication headers.
		if ( self::is_doing_ajax() ) {
			$this->load_authentication_hooks();
		}

		add_filter( 'extra_theme_headers', [ $this, 'add_headers' ] );
		add_filter( 'extra_plugin_headers', [ $this, 'add_headers' ] );
		add_filter( 'upgrader_source_selection', [ $this, 'upgrader_source_selection' ], 10, 4 );

		// Needed for updating from update-core.php.
		if ( ! self::is_doing_ajax() ) {
			add_filter( 'upgrader_pre_download', [ $this, 'upgrader_pre_download', ], 10, 3 );
		}

		// The following hook needed to ensure transient is reset correctly after shiny updates.
		add_filter( 'http_response', [ 'Fragen\\GitHub_Updater\\API', 'wp_update_response' ], 10, 3 );

		if (isset(static::$options['local_servers'])) {
			$this->allow_local_servers();
		}
	}

	/**
	 * In case the developer is running a local instance of a git server.
	 *
	 * @return void
	 */
	public function allow_local_servers() {
		add_filter('http_request_args', function ($r, $url) {
			if (! $r['reject_unsafe_urls']) {
				return $r;
			}
			$host = parse_url($url, PHP_URL_HOST);
			if (preg_match('#^(([1-9]?\d|1\d\d|25[0-5]|2[0-4]\d)\.){3}([1-9]?\d|1\d\d|25[0-5]|2[0-4]\d)$#', $host)) {
				$ip = $host;
			} else {
				return $r;
			}

			$parts = array_map('intval', explode('.', $ip));
			if (127 === $parts[0] || 10 === $parts[0] || 0 === $parts[0]
				|| (172 === $parts[0] && 16 <= $parts[1] && 31 >= $parts[1])
				|| (192 === $parts[0] && 168 === $parts[1])
			) {
				$r['reject_unsafe_urls'] = false;
			}

			return $r;
		}, 10, 2);
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

		$admin_pages = [
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
		];

		/**
		 * Filter $admin_pages to be able to adjust the pages where GitHub Updater runs.
		 *
		 * @since 8.0.0
		 *
		 * @param array $admin_pages Default array of admin pages where GitHub Updater runs.
		 */
		$admin_pages = array_unique( apply_filters( 'github_updater_add_admin_pages', $admin_pages ) );

		return $can_user_update && in_array( $pagenow, $admin_pages, true );
	}

}
