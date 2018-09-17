<?php
/**
 * GitHub Updater
 *
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 * @package   github-updater
 */

namespace Fragen\GitHub_Updater;

use Fragen\GitHub_Updater\Traits\GHU_Trait;

/**
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class GHU_Upgrade
 */
class GHU_Upgrade {
	use GHU_Trait;

	/**
	 * DB version.
	 *
	 * @var int
	 */
	private $db_version = 8312;

	/**
	 * Run update check against db_version.
	 */
	public function run() {
		$options    = $this->get_class_vars( 'Base', 'options' );
		$db_version = isset( $options['db_version'] ) ? (int) $options['db_version'] : 6000;

		if ( $db_version === $this->db_version ) {
			return;
		}

		switch ( $db_version ) {
			case $db_version < $this->db_version:
				$this->delete_flush_cache();
				break;
			default:
				break;
		}

		$options = array_merge( (array) $options, [ 'db_version' => (int) $this->db_version ] );
		update_site_option( 'github_updater', $options );
	}

	/**
	 * Flush caches and delete cached options.
	 */
	private function delete_flush_cache() {
		wp_cache_flush();
		$this->delete_all_cached_data();
	}
}
