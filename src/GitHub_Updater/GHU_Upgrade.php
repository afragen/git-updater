<?php
/**
 * GitHub Updater
 *
 * @package   GitHub_Updater
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 */

namespace Fragen\GitHub_Updater;

/**
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class GHU_Upgrade
 *
 * @package Fragen\GitHub_Updater
 */
class GHU_Upgrade extends Base {

	/**
	 * DB version.
	 *
	 * @var int
	 */
	private $db_version = 7000;

	/**
	 * GHU_Upgrade constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->load_options();
	}

	/**
	 * Run update check against db_version.
	 */
	public function run() {
		$db_version = isset( self::$options['db_version'] ) ? self::$options['db_version'] : 6000;

		if ( $db_version === $this->db_version ) {
			return;
		}

		switch ( $db_version ) {
			case ( $db_version < $this->db_version ):
				$this->delete_flush_cache();
				break;
			default:
				break;
		}

		$options = array_merge( (array) self::$options, array( 'db_version' => (int) $this->db_version ) );
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
