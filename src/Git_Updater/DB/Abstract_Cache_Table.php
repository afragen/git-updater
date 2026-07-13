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

use wpdb;

/**
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Abstract cache table.
 *
 * Provides table-level (create/modify/drop) and per-entry (column) CRUD plus
 * repository-scope operations against a single, network-wide cache table. The
 * concrete subclass supplies the schema and a singleton instance.
 */
abstract class Abstract_Cache_Table {

	/**
	 * Allowed column names.
	 *
	 * Column identifiers are interpolated into SQL, so they must be drawn from
	 * this whitelist to prevent SQL injection (column names cannot be bound by
	 * $wpdb->prepare()).
	 *
	 * @var string[]
	 */
	protected static $allowed_columns = [
		'repo_headers',
		'repo',
		'tags',
		'changes',
		'readme',
		'meta',
		'branches',
		'assets',
		'release_asset',
		'release_asset_download',
		'release_assets',
		'contents',
		'current_branch',
		'primary_branch',
		'dot_org',
		'release_asset_redirect',
		'languages',
		'addon_api_results',
		'ran',
		'error_cache',
	];

	/**
	 * Return the cache table name (network-wide).
	 *
	 * @return string
	 */
	public function table_name(): string {
		global $wpdb;

		return $wpdb->base_prefix . 'git_updater_cache';
	}

	/**
	 * Install (create if needed) the cache table.
	 *
	 * @return void
	 */
	public function install_table(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $this->schema() ); // @codeCoverageIgnore
	}

	/**
	 * Drop the cache table.
	 *
	 * @return void
	 */
	public function uninstall_table(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $this->table_name() ) );
	}

	/**
	 * Modify the cache table (future schema bumps).
	 *
	 * @return void
	 */
	public function modify_table(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $this->schema() ); // @codeCoverageIgnore
	}

	/**
	 * Return the CREATE TABLE statement.
	 *
	 * @return string
	 */
	abstract protected function schema(): string;

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
	abstract public function add_entry( string $slug, string $column, $value, int $timeout = 0, int $error_timeout = 0 ): bool;

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
	abstract public function update_entry( string $slug, string $column, $value, int $timeout = 0, int $error_timeout = 0 ): bool;

	/**
	 * Delete a single entry (column) for a repo.
	 *
	 * @param string $slug   Repository slug.
	 * @param string $column Column name (must be in whitelist).
	 *
	 * @return bool
	 */
	abstract public function delete_entry( string $slug, string $column ): bool;

	/**
	 * Get a single entry (column) for a repo.
	 *
	 * @param string $slug   Repository slug.
	 * @param string $column Column name (must be in whitelist).
	 *
	 * @return mixed
	 */
	abstract public function get_entry( string $slug, string $column );

	/**
	 * Delete all cached data for a single repository.
	 *
	 * @param string $slug Repository slug.
	 *
	 * @return bool
	 */
	public function delete_repo( string $slug ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete( $this->table_name(), [ 'slug' => $slug ], [ '%s' ] );

		// Return false when no row existed so callers can distinguish a no-op flush.
		return false !== $result && $result > 0;
	}

	/**
	 * Delete all cached data for every repository (keeps the table).
	 *
	 * @return bool
	 */
	public function delete_all_repos(): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $this->table_name() ) );

		return true;
	}

	/**
	 * Delete cached rows whose slug is no longer in the live repo set.
	 *
	 * The reserved `ghu` and `gu_addon_api_results` rows are always retained.
	 *
	 * @param array<int, string> $live_slugs Slugs of currently installed repos.
	 *
	 * @return int Number of rows deleted.
	 */
	public function prune_stale( array $live_slugs ): int {
		global $wpdb;

		$live_slugs[] = 'ghu';
		$live_slugs[] = 'gu_addon_api_results';
		$live_slugs   = array_values( array_unique( $live_slugs ) );

		$placeholders = implode( ', ', array_fill( 0, count( $live_slugs ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->table_name()} WHERE slug NOT IN ({$placeholders})", $live_slugs ) ); // phpcs:ignore

		return (int) ( $result ?: 0 );
	}

	/**
	 * Get a single repo row, with columns unserialized.
	 *
	 * @param string $slug Repository slug.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_repo( string $slug ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE slug = %s', $this->table_name(), $slug ), ARRAY_A );

		if ( ! is_array( $row ) ) {
			return null;
		}

		foreach ( $row as $key => $value ) {
			if ( is_string( $value ) ) { // @codeCoverageIgnore
				// wpdb returns all column values as strings; the non-string branch
				// is unreachable from the table layer.
				$row[ $key ] = maybe_unserialize( $value );
			}
		}

		return $row;
	}

	/**
	 * Get all repo rows, with columns unserialized.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all_rows(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i', $this->table_name() ), ARRAY_A );
	}

	/**
	 * Set the row timeout for a repository.
	 *
	 * @param string $slug    Repository slug.
	 * @param int    $timeout Timeout timestamp.
	 *
	 * @return bool
	 */
	public function set_repo_timeout( string $slug, int $timeout ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update( $this->table_name(), [ 'timeout' => $timeout ], [ 'slug' => $slug ], [ '%d' ], [ '%s' ] );

		return false !== $result;
	}

	/**
	 * Get the error-cache value for a repository.
	 *
	 * @param string $slug Repository slug.
	 *
	 * @return mixed
	 */
	public function get_error_cache( string $slug ) {
		$row = $this->get_repo( $slug );

		return ( null === $row || ! isset( $row['error_cache'] ) ) ? null : $row['error_cache'];
	}

	/**
	 * Set the error-cache value (and its independent short timeout) for a repo.
	 *
	 * @param string $slug                  Repository slug.
	 * @param mixed  $value                 Error-cache payload.
	 * @param int    $error_timeout_seconds Seconds from now until expiry.
	 *
	 * @return bool
	 */
	public function set_error_cache( string $slug, $value, int $error_timeout_seconds ): bool {
		global $wpdb;

		$error_timeout = time() + $error_timeout_seconds;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"INSERT INTO {$this->table_name()} (slug, error_cache, error_timeout, timeout)
				VALUES (%s, %s, %d, %d)
				ON DUPLICATE KEY UPDATE error_cache = VALUES(error_cache), error_timeout = VALUES(error_timeout)",
				$slug,
				maybe_serialize( $value ),
				$error_timeout,
				0
			)
		);

		return false !== $result;
	}

	/**
	 * Validate a column name against the whitelist.
	 *
	 * @param string $column Column name.
	 *
	 * @return string Validated column name (falls back to `slug` if invalid).
	 */
	protected function whitelist( string $column ): string {
		if ( ! in_array( $column, static::$allowed_columns, true ) ) {
			return 'slug'; // @codeCoverageIgnore
		}

		return $column;
	}

	/**
	 * Upsert a set of column values for a repository.
	 *
	 * @param string               $slug          Repository slug.
	 * @param array<string, mixed> $column_values Map of column => value.
	 * @param int                  $timeout       Row timeout timestamp (0 = preserve existing).
	 * @param int                  $error_timeout Error-cache timeout (0 = unchanged).
	 *
	 * @return bool
	 */
	protected function upsert( string $slug, array $column_values, int $timeout = 0, int $error_timeout = 0 ): bool {
		global $wpdb;

		$values = [ 'slug' => $slug ];
		foreach ( $column_values as $column => $value ) {
			$values[ $this->whitelist( $column ) ] = maybe_serialize( $value );
		}
		if ( $timeout > 0 ) {
			$values['timeout'] = $timeout;
		}
		if ( $error_timeout > 0 ) {
			$values['error_timeout'] = time() + $error_timeout;
		}

		$columns      = array_keys( $values );
		$placeholders = implode( ', ', array_fill( 0, count( $columns ), '%s' ) );
		$column_sql   = implode( ', ', $columns );
		$update_sql   = implode( ', ', array_map( static fn( $c ) => "{$c} = VALUES({$c})", $columns ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				"INSERT INTO {$this->table_name()} ({$column_sql}) VALUES ({$placeholders}) ON DUPLICATE KEY UPDATE {$update_sql}",
				array_values( $values )
			)
		);

		return false !== $result;
	}
}
