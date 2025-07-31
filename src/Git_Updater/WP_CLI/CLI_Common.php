<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  MIT
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

namespace Fragen\Git_Updater\WP_CLI;

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

		$table              = is_multisite() ? $wpdb->base_prefix . 'sitemeta' : $wpdb->base_prefix . 'options';
		$column             = is_multisite() ? 'meta_key' : 'option_name';
		$delete_string      = 'DELETE FROM ' . $table . ' WHERE ' . $column . ' LIKE %s LIMIT 1000';
		$get_options_string = 'SELECT * FROM ' . $table . ' WHERE ' . $column . ' LIKE %s';

		$ghu_options = $wpdb->get_results( $wpdb->prepare( $get_options_string, [ '%ghu-%' ] ) ); // phpcs:ignore
		foreach ( $ghu_options as $option ) {
			delete_site_option( $option->option_name );
		}

		$wpdb->query( $wpdb->prepare( $delete_string, [ '%ghu-%' ] ) ); // phpcs:ignore

		return true;
	}
}
