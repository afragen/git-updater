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
use WP_Error;

/**
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class GU_Upgrade
 */
final class GU_Upgrade {
	use GU_Trait;

	/**
	 * DB version.
	 *
	 * @var int
	 */
	private $db_version = '12.13.0'; // TODO: change number.

	/**
	 * Run update check against db_version.
	 */
	public function run() {
		$options    = $this->get_class_vars( 'Base', 'options' );
		$db_version = isset( $options['db_version'] ) && ! is_integer( $options['db_version'] ) ? $options['db_version'] : '6.0.0';

		if ( version_compare( $db_version, $this->db_version, '=' ) ) {
			return;
		}

		switch ( $db_version ) {
			case version_compare( $db_version, $this->db_version, '<' ):
				$this->delete_flush_cache();
				$this->save_db_version( $options );
				break;
			default:
				break;
		}
	}

	/**
	 * Save $db_version on update.
	 *
	 * @param array $options Array of Git Updater options.
	 *
	 * @return void
	 */
	private function save_db_version( $options ) {
		$options = array_merge(
			(array) $options,
			[ 'db_version' => $this->db_version ]
		);
		update_site_option( 'git_updater', $options );
	}

	/**
	 * Flush caches and delete cached options.
	 */
	private function delete_flush_cache() {
		wp_cache_flush();
		$this->delete_all_cached_data();
	}

	/**
	 * Convert GHU to GU options.
	 *
	 * @since 10.0.0
	 *
	 * @return void
	 */
	public function convert_ghu_options_to_gu_options() {
		$ghu_options = get_site_option( 'github_updater' );
		if ( $ghu_options ) {
			update_site_option( 'git_updater', $ghu_options );
			delete_site_option( 'github_updater' );
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		deactivate_plugins( 'github-updater/git-updater.php' );
	}
}
