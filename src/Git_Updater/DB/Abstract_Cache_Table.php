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
	 * Per-request row memoization, keyed by slug.
	 *
	 * The value is either an unserialized row array, or a literal `null` to
	 * remember "no row exists" so we don't re-query the DB for missing slugs.
	 *
	 * Lives on the instance, so the `Repo_Cache_Table` singleton shares one
	 * cache across all callers for the duration of a request.
	 *
	 * @var array<string, array<string, mixed>|null>
	 */
	protected $row_cache = [];

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
		'timeout',
		'error_timeout',
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
		// Drop first to guarantee a clean schema: dbDelta is unreliable for
		// adding new columns to an existing table, and production callers
		// (GU_Upgrade::run) flush the cache immediately after install.
		$this->uninstall_table();
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

		// Drop the table → all cached rows are now stale. install_table() calls
		// uninstall_table() first, so this also resets state for re-installs
		// (e.g. in test setUp).
		$this->row_cache = [];
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
	 * Drop a single slug's row from the per-request row cache.
	 *
	 * Centralised so all write paths stay in lockstep with `get_repo()`'s
	 * memoization. Safe to call for unknown slugs.
	 *
	 * @param string $slug Repository slug.
	 *
	 * @return void
	 */
	protected function invalidate_row_cache( string $slug ): void {
		unset( $this->row_cache[ $slug ] );
	}

	/**
	 * Flush the per-request row cache.
	 *
	 * Primarily intended for test isolation: WP_UnitTestCase wraps each test
	 * in a transaction that gets rolled back, but the row cache lives in PHP
	 * memory on the singleton and survives that rollback. Tests that want
	 * to observe the actual (rolled-back) DB state can call this in setUp.
	 *
	 * @return void
	 */
	public function reset_row_cache(): void {
		$this->row_cache = [];
	}

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

		$this->invalidate_row_cache( $slug );

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

		$this->row_cache = [];

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

		// prune_stale may delete arbitrary slugs; safest to drop the whole cache.
		$this->row_cache = [];

		return (int) ( $result ?: 0 );
	}

	/**
	 * Get a single repo row, with columns unserialized.
	 *
	 * When `$column` is given, only the named column(s) are read from the DB and
	 * unserialized — avoiding a full `SELECT *` + unserialize of all 22 LONGTEXT
	 * columns when a caller needs a subset of values. `$column` may be:
	 * - null: full row (SELECT *).
	 * - string: a single column; the unserialized scalar value is returned.
	 * - array: a list of columns; a partial row array keyed by column is returned.
	 * Column names are validated against the whitelist so the interpolated
	 * identifiers are safe.
	 *
	 * @param string                    $slug    Repository slug.
	 * @param array<string>|string|null $column  Columns to project. null = full row.
	 *
	 * @return array<string, mixed>|mixed|null For a full row: the row array (or null).
	 *                                         For a single column: the unserialized value
	 *                                         (or null if missing/no row).
	 *                                         For multiple columns: partial row array.
	 */
	public function get_repo( string $slug, $column = null ) {
		if ( array_key_exists( $slug, $this->row_cache ) ) {
			$cached = $this->row_cache[ $slug ];
			if ( null === $column ) {
				return $cached;
			}
			if ( is_array( $column ) ) {
				if ( null === $cached ) {
					return null;
				}
				$partial = [];
				foreach ( $column as $col ) {
					if ( array_key_exists( $col, $cached ) ) {
						$partial[ $col ] = $cached[ $col ];
					}
				}
				return $partial;
			}
			return null === $cached ? null : ( $cached[ $column ] ?? null );
		}

		global $wpdb;

		if ( null !== $column ) {
			if ( is_array( $column ) ) {
				$cols     = array_map( [ $this, 'whitelist' ], $column );
				$col_list = implode( ', ', array_map( static fn( $c ) => $wpdb->prepare( '%i', $c ), $cols ) );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$row = $wpdb->get_row(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->prepare( "SELECT {$col_list} FROM %i WHERE slug = %s", $this->table_name(), $slug ),
					ARRAY_A
				);

				if ( ! is_array( $row ) ) {
					$this->row_cache[ $slug ] = null;
					return null;
				}
				foreach ( $row as $key => $value ) {
					if ( is_string( $value ) ) {
						$row[ $key ] = maybe_unserialize( $value );
					}
				}
				// Partial row: do NOT populate $row_cache (same rationale as single-
				// column projection); a later full read re-queries for the complete row.
				return $row;
			}

			$column = $this->whitelist( $column );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row(
				$wpdb->prepare( 'SELECT %i FROM %i WHERE slug = %s', $column, $this->table_name(), $slug ),
				ARRAY_N
			);

			if ( ! is_array( $row ) ) {
				// No row. Remember the miss so we don't re-query.
				$this->row_cache[ $slug ] = null;
				return null;
			}

			// get_row( ARRAY_N ) distinguishes NULL from '' — get_var() coerces
			// empty-string column values to null, losing legitimate '' values.
			// A NULL column returns null (miss); '' returns the empty string.
			// Projected reads do NOT populate $row_cache: doing so would store a
			// partial row that a later full read would wrongly treat as complete.
			return null === $row[0] ? null : maybe_unserialize( $row[0] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE slug = %s', $this->table_name(), $slug ), ARRAY_A );

		if ( ! is_array( $row ) ) {
			$this->row_cache[ $slug ] = null;
			return null;
		}

		foreach ( $row as $key => $value ) {
			if ( is_string( $value ) ) { // @codeCoverageIgnore
				// wpdb returns all column values as strings; the non-string branch
				// is unreachable from the table layer.
				$row[ $key ] = maybe_unserialize( $value );
			}
		}

		$this->row_cache[ $slug ] = $row;
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
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i', $this->table_name() ), ARRAY_A );

		foreach ( $rows as $row ) {
			foreach ( $row as $key => $value ) {
				if ( is_string( $value ) ) {
					$row[ $key ] = maybe_unserialize( $value );
				}
			}
			$this->row_cache[ (string) $row['slug'] ] = $row;
		}

		return $rows;
	}

	/**
	 * Return a map of slug => unserialized `ran` column for every cached repo.
	 *
	 * Projects only the `slug` and `ran` columns (not the full LONGTEXT payload),
	 * so per-repo readme/changes/contents/meta are never read or unserialized.
	 * Used to decide fetch-cycle completeness without materializing full rows.
	 *
	 * @return array<string, array<int, string>|null>
	 */
	public function get_cached_ran(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare( 'SELECT slug, ran FROM %i', $this->table_name() ),
			ARRAY_A
		);

		$map = [];
		foreach ( $rows as $row ) {
			$slug = (string) ( $row['slug'] ?? '' );
			if ( '' === $slug ) {
				continue;
			}
			$ran          = isset( $row['ran'] ) && is_string( $row['ran'] )
				? maybe_unserialize( $row['ran'] )
				: null;
			$map[ $slug ] = is_array( $ran ) ? $ran : null;
		}

		return $map;
	}

	/**
	 * Return a map of slug => whether the repo currently has a non-empty error_cache.
	 *
	 * Projects only `slug` and `error_cache` (not the full LONGTEXT payload) so per-repo
	 * data is never materialized. A non-empty error_cache means a fetch step errored and is
	 * still pending a retry (see set_error_cache()'s independent short timeout).
	 *
	 * @return array<string, bool>
	 */
	public function get_cached_error_flags(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare( 'SELECT slug, error_cache FROM %i', $this->table_name() ),
			ARRAY_A
		);

		$map = [];
		foreach ( $rows as $row ) {
			$slug = (string) ( $row['slug'] ?? '' );
			if ( '' === $slug ) {
				continue;
			}
			$err          = isset( $row['error_cache'] ) && is_string( $row['error_cache'] )
				? maybe_unserialize( $row['error_cache'] )
				: null;
			$map[ $slug ] = ! empty( $err );
		}

		return $map;
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

		$this->invalidate_row_cache( $slug );

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
		return $this->get_repo( $slug, 'error_cache' );
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

		$this->invalidate_row_cache( $slug );

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

		$this->invalidate_row_cache( $slug );

		return false !== $result;
	}
}
