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
	 * Object-cache tier for projected reads and the row timeout.
	 *
	 * @var Repo_Cache_Object
	 */
	private Repo_Cache_Object $object_cache;

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
		'tags',
		'newest_tag',
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
	 * Initialize the object-cache tier.
	 */
	public function __construct() {
		$this->object_cache = new Repo_Cache_Object();
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

		$table = $this->table_name();
		// Only enumerate slugs when the table exists; a fresh install (or test
		// setUp) may call this before the table is created, so SELECT slug on a
		// missing table must not error.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$slugs = $exists ? (array) $wpdb->get_col( $wpdb->prepare( 'SELECT slug FROM %i', $table ) ) : [];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );

		// Drop the table → all cached rows are now stale. install_table() calls
		// uninstall_table() first, so this also resets state for re-installs
		// (e.g. in test setUp).
		$this->row_cache = [];
		$this->object_cache->invalidate_all( $slugs );
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
	 * Drop a single slug's row from the per-request row cache and invalidate its
	 * object-cache entries.
	 *
	 * Centralised so all write paths stay in lockstep with `get_repo()`'s
	 * memoization. Safe to call for unknown slugs. The row-level write bumps the
	 * slug's object-cache generation, so a write never leaves a stale projection
	 * or a stale timeout served after data changes — across requests on a
	 * persistent object cache too.
	 *
	 * @param string $slug Repository slug.
	 *
	 * @return void
	 */
	protected function invalidate_row_cache( string $slug ): void {
		unset( $this->row_cache[ $slug ] );
		$this->object_cache->invalidate( $slug );
	}

	/**
	 * Invalidate a slug's object-cache entries.
	 *
	 * Exposed for tests and callers that need a cold object-cache start for a
	 * slug without writing to the DB. Bumps the slug's generation.
	 *
	 * @param string $slug Repository slug.
	 *
	 * @return void
	 */
	public function invalidate_object_cache( string $slug ): void {
		$this->object_cache->invalidate( $slug );
	}

	/**
	 * Flush the per-request row cache.
	 *
	 * Primarily intended for test isolation: WP_UnitTestCase wraps each test
	 * in a transaction that gets rolled back, but the row cache lives in PHP
	 * memory on the singleton and survives that rollback. Tests that want
	 * to observe the actual (rolled-back) DB state can call this in setUp.
	 *
	 * Object-cache entries are not cleared here: they are generation-scoped and
	 * invalidated on writes, and the test cache is per-request anyway.
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
	 * Clear a single repository's API-derived data, preserving its active branch.
	 *
	 * Nulls every data column except `current_branch` so the next fetch cycle
	 * re-collects API responses (readme, tags, meta, release assets, error
	 * backoff, etc.) without resetting the user's branch selection.
	 *
	 * @param string $slug Repository slug.
	 *
	 * @return bool
	 */
	public function delete_repo_api_data( string $slug ): bool {
		global $wpdb;

		$clear = [];
		foreach ( static::$allowed_columns as $column ) {
			if ( 'current_branch' !== $column ) {
				$clear[] = "`{$column}` = NULL";
			}
		}
		$set = implode( ', ', $clear );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query(
			$wpdb->prepare( "UPDATE {$this->table_name()} SET {$set} WHERE slug = %s", $slug ) // phpcs:ignore
		);

		$this->invalidate_row_cache( $slug );

		return false !== $result;
	}

	/**
	 * Delete all cached data for every repository (keeps the table).
	 *
	 * @return bool
	 */
	public function delete_all_repos(): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$slugs = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT slug FROM %i', $this->table_name() ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $this->table_name() ) );

		$this->row_cache = [];
		$this->object_cache->invalidate_all( $slugs );

		return true;
	}

	/**
	 * Clear every repository's API-derived data, preserving active branches.
	 *
	 * Nulls all data columns except `current_branch` for every repo so the next
	 * fetch cycle re-collects API responses without resetting branch selections.
	 *
	 * @return bool
	 */
	public function delete_all_api_data(): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$slugs = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT slug FROM %i', $this->table_name() ) );

		$clear = [];
		foreach ( static::$allowed_columns as $column ) {
			if ( 'current_branch' !== $column ) {
				$clear[] = "`{$column}` = NULL";
			}
		}
		$set = implode( ', ', $clear );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query( "UPDATE {$this->table_name()} SET {$set}" );

		$this->row_cache = [];
		$this->object_cache->invalidate_all( $slugs );

		return false !== $result;
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$all_slugs = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT slug FROM %i', $this->table_name() ) );

		$placeholders = implode( ', ', array_fill( 0, count( $live_slugs ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->table_name()} WHERE slug NOT IN ({$placeholders})", $live_slugs ) ); // phpcs:ignore

		// prune_stale may delete arbitrary slugs; invalidate the object cache for
		// every slug that was removed.
		$deleted         = array_diff( $all_slugs, $live_slugs );
		$this->row_cache = [];
		$this->object_cache->invalidate_all( array_values( $deleted ) );

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
			$is_array = is_array( $column );

			// Object-cache hit: the class reorders to caller order and collapses
			// the null sentinel, so it's ready to return as-is.
			$oc_hit = $this->object_cache->get( $slug, $column, $is_array );
			if ( false !== $oc_hit ) {
				return $oc_hit;
			}

			// Requested columns in the caller's order, whitelisted.
			$requested = $is_array ? array_map( [ $this, 'whitelist' ], $column ) : [ $this->whitelist( $column ) ];

			// Always read the `timeout` column (row freshness) and, when the
			// projection involves error data, the `error_timeout` column too, so
			// the object-cache TTL is bounded by the correct expiry window. These
			// tiny BIGINT columns are appended unless already requested and are
			// stripped from the stored/returned projection.
			$needs_timeout       = ! in_array( 'timeout', $requested, true );
			$needs_error_timeout = ! in_array( 'error_timeout', $requested, true );

			// Select columns in canonical (sorted) order so the cached entry is
			// deterministic regardless of caller order; the returned shape is then
			// reordered to match the caller's requested order.
			$sorted = $requested;
			sort( $sorted );
			$col_list = implode( ', ', array_map( static fn( $c ) => $wpdb->prepare( '%i', $c ), $sorted ) );
			if ( $needs_timeout ) {
				$col_list .= ', ' . $wpdb->prepare( '%i', 'timeout' );
			}
			if ( $needs_error_timeout ) {
				$col_list .= ', ' . $wpdb->prepare( '%i', 'error_timeout' );
			}
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

			$row_timeout       = (int) ( $row['timeout'] ?? 0 );
			$row_error_timeout = (int) ( $row['error_timeout'] ?? 0 );

			// Surface the row timeout so get_repo_cache()'s gate stays warm.
			if ( $row_timeout > 0 ) {
				$this->object_cache->set_timeout( $slug, $row_timeout );
			}

			// Build the canonical (sorted-key) projection for cache storage. Only
			// requested columns are included; appended timeout/error_timeout are
			// used solely for TTL and never stored in the projection.
			$projection = [];
			foreach ( $sorted as $col ) {
				$value              = $row[ $col ] ?? null;
				$projection[ $col ] = is_string( $value ) ? maybe_unserialize( $value ) : $value;
			}

			$ttl = $this->object_cache->ttl( $row_timeout, $row_error_timeout, $column );

			if ( $is_array ) {
				// Partial row: do NOT populate $row_cache (same rationale as a
				// single-column projection); it populates object cache instead.
				$this->object_cache->set( $slug, $column, $projection, $ttl );
				return $this->reorder_projection( $projection, $requested );
			}

			$value = $projection[ $column ] ?? null;
			$this->object_cache->set( $slug, $column, $value, $ttl );
			return $value;
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
	 * Reorder a partial-row projection into the caller's requested column order.
	 *
	 * `get_repo()` caches projections canonically (sorted keys) so permutations
	 * of the same column set share one entry; this method rebuilds the array in
	 * the order the caller asked for so each permutation returns the shape it
	 * expects. Columns absent from the source are omitted (matching the shape of
	 * a cold, non-cached read).
	 *
	 * @param array<string, mixed> $row      Canonical-keyed projection.
	 * @param array<int, string>   $requested Requested column order (whitelisted).
	 *
	 * @return array<string, mixed> The projection reordered to $requested.
	 */
	private function reorder_projection( array $row, array $requested ): array {
		$partial = [];
		foreach ( $requested as $col ) {
			if ( array_key_exists( $col, $row ) ) {
				$partial[ $col ] = $row[ $col ];
			}
		}
		return $partial;
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
			$wpdb->prepare( 'SELECT slug, error_cache, error_timeout FROM %i', $this->table_name() ),
			ARRAY_A
		);

		$map = [];
		foreach ( $rows as $row ) {
			$slug = (string) ( $row['slug'] ?? '' );
			if ( '' === $slug ) {
				continue;
			}
			$err = isset( $row['error_cache'] ) && is_string( $row['error_cache'] )
				? maybe_unserialize( $row['error_cache'] )
				: null;
			// Only flag as waiting if error_cache is non-empty AND error_timeout hasn't expired.
			$error_timeout = (int) ( $row['error_timeout'] ?? 0 );
			$map[ $slug ]  = ! empty( $err ) && $error_timeout > 0 && time() < $error_timeout;
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
		// Re-surface the fresh timeout so get_repo_cache()'s gate stays warm.
		$this->object_cache->set_timeout( $slug, $timeout );

		return false !== $result;
	}

	/**
	 * Get a repository's row timeout, preferring the object-cached value.
	 *
	 * The timeout is surfaced in object cache on any projection read or timeout
	 * write, so `get_repo_cache()`'s freshness gate can avoid a DB query on a
	 * warm entry. Falls back to the (now object-cache-backed) scalar read.
	 *
	 * @param string $slug Repository slug.
	 *
	 * @return int The row timeout timestamp (0 when unset/unknown).
	 */
	public function get_repo_timeout( string $slug ): int {
		$cached = $this->object_cache->get_timeout( $slug );
		if ( $cached > 0 ) {
			return $cached;
		}

		return (int) ( $this->get_repo( $slug, 'timeout' ) ?? 0 );
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
	 * Column identifiers are interpolated into SQL, so they must be drawn from
	 * the whitelist to prevent SQL injection (column names cannot be bound by
	 * $wpdb->prepare()). An invalid column is a programming error — fail loudly
	 * rather than silently rewriting it to `slug`, which could corrupt the row
	 * key.
	 *
	 * @param string $column Column name.
	 *
	 * @return string The validated column name.
	 *
	 * @throws \InvalidArgumentException When the column is not whitelisted.
	 */
	protected function whitelist( string $column ): string {
		if ( ! in_array( $column, static::$allowed_columns, true ) ) {
			throw new \InvalidArgumentException( sprintf( 'Invalid cache column: %s', esc_html( $column ) ) );
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

		if ( false !== $result ) {
			$this->object_cache->prime_on_write( $slug, $column_values, $timeout );
		}

		return false !== $result;
	}
}
