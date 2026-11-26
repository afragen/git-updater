<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  GPL-3.0-or-later
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

namespace Fragen\Git_Updater\DB;

use Fragen\Singleton;

/**
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Repository cache table.
 *
 * Network-wide cache of repository API responses, one row per repository.
 */
final class Repo_Cache_Table extends Abstract_Cache_Table {

	/**
	 * Singleton instance.
	 *
	 * @var ?self
	 */
	private static $instance;

	/**
	 * Return a singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self(); // @codeCoverageIgnore
		}

		return self::$instance;
	}

	/**
	 * Return the CREATE TABLE statement.
	 *
	 * @return string
	 */
	protected function schema(): string {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();

		return "CREATE TABLE {$this->table_name()} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			slug varchar(191) NOT NULL,
			repo_headers longtext,
			tags longtext,
			newest_tag longtext,
			changes longtext,
			readme longtext,
			meta longtext,
			branches longtext,
			assets longtext,
			release_asset longtext,
			release_asset_download longtext,
			release_assets longtext,
			contents longtext,
			current_branch longtext,
			primary_branch longtext,
			dot_org longtext,
			release_asset_redirect longtext,
			languages longtext,
			addon_api_results longtext,
			ran longtext,
			error_cache longtext,
			timeout bigint(20) unsigned NOT NULL DEFAULT 0,
			error_timeout bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) $charset ENGINE=InnoDB;";
	}

	/**
	 * Add a single entry (column) for a repo.
	 *
	 * @param string $slug          Repository slug.
	 * @param string $column        Column name (must be in whitelist).
	 * @param mixed  $value         Value to store (serialized automatically).
	 * @param int    $timeout       Row timeout timestamp (0 = preserve existing).
	 * @param int    $error_timeout Error-cache timeout (0 = unchanged).
	 *
	 * @return bool
	 */
	public function add_entry( string $slug, string $column, $value, int $timeout = 0, int $error_timeout = 0 ): bool {
		if ( 'error_cache' === $column ) {
			return $this->set_error_cache( $slug, $value, $error_timeout );
		}

		return $this->upsert( $slug, [ $column => $value ], $timeout, $error_timeout );
	}

	/**
	 * Update a single entry (column) for a repo.
	 *
	 * @param string $slug          Repository slug.
	 * @param string $column        Column name (must be in whitelist).
	 * @param mixed  $value         Value to store (serialized automatically).
	 * @param int    $timeout       Row timeout timestamp (0 = preserve existing).
	 * @param int    $error_timeout Error-cache timeout (0 = unchanged).
	 *
	 * @return bool
	 */
	public function update_entry( string $slug, string $column, $value, int $timeout = 0, int $error_timeout = 0 ): bool {
		return $this->add_entry( $slug, $column, $value, $timeout, $error_timeout );
	}

	/**
	 * Delete a single entry (column) for a repo.
	 *
	 * @param string $slug   Repository slug.
	 * @param string $column Column name (must be in whitelist).
	 *
	 * @return bool
	 */
	public function delete_entry( string $slug, string $column ): bool {
		global $wpdb;

		$column = $this->whitelist( $column );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query(
			$wpdb->prepare( "UPDATE {$this->table_name()} SET `{$column}` = NULL WHERE slug = %s", $slug ) // phpcs:ignore
		);

		$this->invalidate_row_cache( $slug );

		return false !== $result;
	}

	/**
	 * Get a single entry (column) for a repo.
	 *
	 * @param string $slug   Repository slug.
	 * @param string $column Column name (must be in whitelist).
	 *
	 * @return mixed
	 */
	public function get_entry( string $slug, string $column ) {
		return $this->get_repo( $slug, $column );
	}
}
