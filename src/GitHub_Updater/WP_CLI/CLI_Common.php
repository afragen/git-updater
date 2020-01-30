<?php
/**
 * GitHub Updater
 *
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 * @package   github-updater
 */

namespace Fragen\GitHub_Updater\WP_CLI;

/**
 * Class CLI_Common
 */
class CLI_Common {
	/**
	 * Delete all `ghu-` prefixed data from options table.
	 *
	 * @return bool
	 */
	public function delete_all_cached_data() {
		global $wpdb;

		$table         = is_multisite() ? $wpdb->base_prefix . 'sitemeta' : $wpdb->base_prefix . 'options';
		$column        = is_multisite() ? 'meta_key' : 'option_name';
		$delete_string = 'DELETE FROM ' . $table . ' WHERE ' . $column . ' LIKE %s LIMIT 1000';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $wpdb->prepare( $delete_string, [ '%ghu-%' ] ) );

		return true;
	}
}
