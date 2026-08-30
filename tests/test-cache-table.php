<?php
/**
 * Tests for the repository cache table classes.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\DB\Abstract_Cache_Table;
use Fragen\Git_Updater\DB\Repo_Cache_Table;

class Test_Cache_Table extends WP_UnitTestCase {

	private Repo_Cache_Table $table;

	public function set_up(): void {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$this->table = Repo_Cache_Table::instance();
		$this->table->install_table();
	}

	public function tear_down(): void {
		$this->table->uninstall_table();
		parent::tear_down();
	}

	public function test_instance_returns_same_object(): void {
		$this->assertSame( $this->table, Repo_Cache_Table::instance() );
	}

	public function test_add_entry_creates_row_and_get_entry_reads_it(): void {
		$this->assertTrue( $this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ] ) );
		$this->assertSame( [ '1.0.0' ], $this->table->get_entry( 'test-plugin', 'tags' ) );
	}

	public function test_add_entry_stores_scalar(): void {
		$this->table->add_entry( 'test-plugin', 'repo_headers', 'my-value' );
		$this->assertSame( 'my-value', $this->table->get_entry( 'test-plugin', 'repo_headers' ) );
	}

	public function test_update_entry_overwrites_value(): void {
		$this->table->add_entry( 'test-plugin', 'meta', 'first' );
		$this->table->update_entry( 'test-plugin', 'meta', 'second' );
		$this->assertSame( 'second', $this->table->get_entry( 'test-plugin', 'meta' ) );
	}

	public function test_add_entry_preserves_existing_columns(): void {
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ] );
		$this->table->add_entry( 'test-plugin', 'meta', 'meta-value' );
		$this->assertSame( [ '1.0.0' ], $this->table->get_entry( 'test-plugin', 'tags' ) );
		$this->assertSame( 'meta-value', $this->table->get_entry( 'test-plugin', 'meta' ) );
	}

	public function test_delete_entry_nulls_column(): void {
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ] );
		$this->table->delete_entry( 'test-plugin', 'tags' );
		$this->assertNull( $this->table->get_entry( 'test-plugin', 'tags' ) );
	}

	public function test_delete_repo_removes_row(): void {
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ] );
		$this->assertTrue( $this->table->delete_repo( 'test-plugin' ) );
		$this->assertNull( $this->table->get_repo( 'test-plugin' ) );
	}

	public function test_delete_repo_returns_false_when_row_absent(): void {
		$this->assertFalse( $this->table->delete_repo( 'no-such-slug' ) );
	}

	public function test_get_repo_with_column_returns_single_value(): void {
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ] );
		$this->table->add_entry( 'test-plugin', 'readme', 'readme body' );

		$this->assertSame( [ '1.0.0' ], $this->table->get_repo( 'test-plugin', 'tags' ) );

		// A later full read still returns the complete row (row_cache merged).
		$full = $this->table->get_repo( 'test-plugin' );
		$this->assertSame( [ '1.0.0' ], $full['tags'] );
		$this->assertSame( 'readme body', $full['readme'] );
	}

	public function test_get_repo_with_column_missing_returns_null(): void {
		$this->assertNull( $this->table->get_repo( 'no-such-slug', 'tags' ) );

		// A present row but absent column → null (not the full row).
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ] );
		$this->assertNull( $this->table->get_repo( 'test-plugin', 'readme' ) );
	}

	public function test_get_repo_with_columns_returns_partial_row(): void {
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ] );
		$this->table->add_entry( 'test-plugin', 'readme', 'readme body' );
		$this->table->add_entry( 'test-plugin', 'meta', [ 'foo' => 'bar' ] );

		$partial = $this->table->get_repo( 'test-plugin', [ 'tags', 'readme' ] );

		$this->assertSame( [ 'tags' => [ '1.0.0' ], 'readme' => 'readme body' ], $partial );
		// Other columns are NOT present in the projection.
		$this->assertArrayNotHasKey( 'meta', $partial );

		// A later full read still returns the complete row (row_cache not polluted).
		$full = $this->table->get_repo( 'test-plugin' );
		$this->assertSame( [ '1.0.0' ], $full['tags'] );
		$this->assertSame( 'readme body', $full['readme'] );
		$this->assertSame( [ 'foo' => 'bar' ], $full['meta'] );
	}

	public function test_array_projection_slices_from_warm_row_cache(): void {
		// After a full-row read warms $row_cache, an array projection slices from
		// the memoized row without a DB query.
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ] );
		$this->table->add_entry( 'test-plugin', 'readme', 'readme body' );
		$this->table->get_repo( 'test-plugin' ); // warm $row_cache.

		$queries = $this->count_cache_table_queries( function () {
			$partial = $this->table->get_repo( 'test-plugin', [ 'tags', 'readme' ] );
			$this->assertSame( [ 'tags' => [ '1.0.0' ], 'readme' => 'readme body' ], $partial );
		} );

		$this->assertSame( 0, $queries, 'array projection should slice from a warm row cache' );
	}

	public function test_get_repo_with_columns_missing_row_returns_null(): void {
		$this->assertNull( $this->table->get_repo( 'no-such-slug', [ 'tags', 'readme' ] ) );
	}

	public function test_delete_repo_is_isolated(): void {
		$this->table->add_entry( 'plugin-a', 'tags', [ '1.0.0' ] );
		$this->table->add_entry( 'plugin-b', 'tags', [ '2.0.0' ] );
		$this->table->delete_repo( 'plugin-a' );
		$this->assertNull( $this->table->get_repo( 'plugin-a' ) );
		$this->assertSame( [ '2.0.0' ], $this->table->get_entry( 'plugin-b', 'tags' ) );
	}

	public function test_delete_all_repos_empties_table(): void {
		$this->table->add_entry( 'plugin-a', 'tags', [ '1.0.0' ] );
		$this->table->add_entry( 'plugin-b', 'tags', [ '2.0.0' ] );
		$this->assertTrue( $this->table->delete_all_repos() );
		$this->assertSame( [], $this->table->get_all_rows() );
	}

	public function test_delete_all_api_data_preserves_current_branch(): void {
		$this->table->add_entry( 'plugin-a', 'tags', [ '1.0.0' ] );
		$this->table->add_entry( 'plugin-a', 'current_branch', 'develop' );
		$this->table->add_entry( 'plugin-a', 'primary_branch', 'main' );

		$this->assertTrue( $this->table->delete_all_api_data() );

		// API-derived columns are nulled...
		$this->assertNull( $this->table->get_repo( 'plugin-a', 'tags' ) );
		// ...primary_branch is re-derivable and cleared...
		$this->assertNull( $this->table->get_repo( 'plugin-a', 'primary_branch' ) );
		// ...but the user's current_branch survives.
		$this->assertSame( 'develop', $this->table->get_repo( 'plugin-a', 'current_branch' ) );
	}

	public function test_delete_repo_api_data_preserves_current_branch(): void {
		$this->table->add_entry( 'plugin-a', 'readme', 'readme body' );
		$this->table->add_entry( 'plugin-a', 'current_branch', 'develop' );

		$this->assertTrue( $this->table->delete_repo_api_data( 'plugin-a' ) );

		$this->assertNull( $this->table->get_repo( 'plugin-a', 'readme' ) );
		$this->assertSame( 'develop', $this->table->get_repo( 'plugin-a', 'current_branch' ) );
	}

	public function test_delete_repo_api_data_unknown_slug_is_noop(): void {
		// A no-op UPDATE (0 rows) returns true, consistent with other table methods.
		$this->assertTrue( $this->table->delete_repo_api_data( 'no-such-slug' ) );
	}

	public function test_prune_stale_removes_only_absent_rows(): void {
		$this->table->add_entry( 'plugin-a', 'tags', [ '1.0.0' ] );
		$this->table->add_entry( 'plugin-b', 'tags', [ '2.0.0' ] );
		$this->table->add_entry( 'plugin-c', 'tags', [ '3.0.0' ] );
		$deleted = $this->table->prune_stale( [ 'plugin-a', 'plugin-b' ] );
		$this->assertSame( 1, $deleted );
		$this->assertNull( $this->table->get_repo( 'plugin-c' ) );
		$this->assertNotNull( $this->table->get_repo( 'plugin-a' ) );
	}

	public function test_prune_stale_keeps_reserved_rows(): void {
		$this->table->add_entry( 'ghu', 'languages', [] );
		$this->table->add_entry( 'gu_addon_api_results', 'addon_api_results', [] );
		$this->table->add_entry( 'plugin-a', 'tags', [ '1.0.0' ] );
		$deleted = $this->table->prune_stale( [] );
		$this->assertSame( 1, $deleted );
		$this->assertNotNull( $this->table->get_repo( 'ghu' ) );
		$this->assertNotNull( $this->table->get_repo( 'gu_addon_api_results' ) );
	}

	public function test_set_repo_timeout_and_get_repo(): void {
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ] );
		$timeout = time() + 3600;
		$this->table->set_repo_timeout( 'test-plugin', $timeout );
		$row = (array) $this->table->get_repo( 'test-plugin' );
		$this->assertSame( (string) $timeout, $row['timeout'] );
	}

	public function test_error_cache_uses_independent_timeout(): void {
		// Set a row timeout, then set an error cache with a short timeout.
		$row_timeout = time() + 3600;
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ], $row_timeout );
		$this->table->set_error_cache( 'test-plugin', [ 'http_code' => 404 ], 300 );
		$row = (array) $this->table->get_repo( 'test-plugin' );
		// Row timeout preserved, error_timeout short.
		$this->assertSame( (string) $row_timeout, $row['timeout'] );
		$this->assertGreaterThan( time(), (int) $row['error_timeout'] );
		$this->assertLessThan( time() + 600, (int) $row['error_timeout'] );
		$this->assertSame( [ 'http_code' => 404 ], $row['error_cache'] );
	}

	public function test_get_error_cache_returns_value(): void {
		$this->table->set_error_cache( 'test-plugin', [ 'http_code' => 500 ], 300 );
		$this->assertSame( [ 'http_code' => 500 ], $this->table->get_error_cache( 'test-plugin' ) );
	}

	public function test_get_error_cache_returns_null_when_absent(): void {
		$this->assertNull( $this->table->get_error_cache( 'missing-slug' ) );
	}

	public function test_get_repo_returns_null_for_unknown_slug(): void {
		$this->assertNull( $this->table->get_repo( 'nope' ) );
	}

	public function test_whitelist_rejects_unknown_column(): void {
		// An invalid column is a programming error and must fail loudly instead
		// of being silently rewritten to `slug` (which could corrupt the row key).
		$this->expectException( InvalidArgumentException::class );
		$this->table->add_entry( 'test-plugin', 'bogus_column', 'x' );
	}

	public function test_schema_contains_slug_unique_index(): void {
		$ref    = new ReflectionMethod( Repo_Cache_Table::class, 'schema' );
		$ref->setAccessible( true );
		$schema = $ref->invoke( Repo_Cache_Table::instance() );
		$this->assertStringContainsString( 'UNIQUE KEY slug', $schema );
		$this->assertStringContainsString( 'error_timeout', $schema );
	}

	public function test_uninstall_table_runs_without_error_and_table_remains_usable(): void {
		// The DROP may be a no-op in test environments that restrict DDL
		// privileges, but the call must succeed and the table must remain
		// in a usable state.
		$this->table->uninstall_table();
		$this->assertTrue( $this->table->add_entry( 'after-uninstall', 'tags', [ '1.0.0' ] ) );
		$this->assertSame( [ '1.0.0' ], $this->table->get_entry( 'after-uninstall', 'tags' ) );
	}

	public function test_add_entry_passes_error_timeout_to_upsert_for_non_error_cache_column(): void {
		$this->assertTrue(
			$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ], 0, 60 )
		);
		$row = (array) $this->table->get_repo( 'test-plugin' );
		$this->assertGreaterThan( time(), (int) $row['error_timeout'] );
		$this->assertLessThan( time() + 120, (int) $row['error_timeout'] );
	}

	public function test_add_entry_routes_error_cache_column_to_set_error_cache(): void {
		// add_entry( $slug, 'error_cache', ... ) must delegate to set_error_cache(),
		// writing both error_cache and error_timeout columns.
		$this->assertTrue(
			$this->table->add_entry( 'test-plugin', 'error_cache', [ 'http_code' => 503 ], 0, 300 )
		);
		$cache = $this->table->get_error_cache( 'test-plugin' );
		$this->assertSame( [ 'http_code' => 503 ], $cache );
		$row = (array) $this->table->get_repo( 'test-plugin' );
		$this->assertGreaterThan( time(), (int) $row['error_timeout'] );
		$this->assertLessThan( time() + 600, (int) $row['error_timeout'] );
	}

	public function test_modify_table_is_callable_and_idempotent(): void {
		// modify_table() runs dbDelta(schema). Idempotent — calling it a second
		// time must not throw and must leave the table usable.
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ] );
		$this->table->modify_table();
		$this->table->modify_table();
		// Existing row preserved; table still functional.
		$this->assertSame( [ '1.0.0' ], $this->table->get_entry( 'test-plugin', 'tags' ) );
	}

	/**
	 * Count queries against the cache table during the callback.
	 *
	 * Hooks the wpdb `query` action to filter to just the git_updater_cache
	 * table. Used to assert the row-cache memoization actually short-circuits
	 * DB hits within a single request.
	 *
	 * @param callable $fn Callback to execute.
	 *
	 * @return int Number of queries against the cache table during $fn().
	 */
	private function count_cache_table_queries( callable $fn ): int {
		global $wpdb;
		$count      = 0;
		$table_name = $this->table->table_name();
		$filter     = function ( $query ) use ( &$count, $table_name ) {
			if ( false !== strpos( $query, $table_name ) ) {
				++$count;
			}
			return $query;
		};
		add_filter( 'query', $filter );
		try {
			$fn();
		} finally {
			remove_filter( 'query', $filter );
		}
		return $count;
	}

	public function test_get_repo_memoizes_within_request(): void {
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ] );

		$queries = $this->count_cache_table_queries( function () {
			$this->table->get_repo( 'test-plugin' );
			$this->table->get_repo( 'test-plugin' );
			$this->table->get_repo( 'test-plugin' );
		} );

		$this->assertSame( 1, $queries, 'get_repo() must hit the DB at most once per slug per request' );
	}

	public function test_get_repo_returns_same_array_on_cache_hit(): void {
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ] );

		$first  = $this->table->get_repo( 'test-plugin' );
		$second = $this->table->get_repo( 'test-plugin' );

		$this->assertSame( $first, $second );
	}

	public function test_get_repo_caches_negative_lookups(): void {
		// First call records "no row"; second call must not re-query.
		$queries = $this->count_cache_table_queries( function () {
			$this->assertNull( $this->table->get_repo( 'no-such-slug' ) );
			$this->assertNull( $this->table->get_repo( 'no-such-slug' ) );
		} );

		$this->assertSame( 1, $queries );
	}

	public function test_get_repo_cache_survives_get_entry(): void {
		// get_entry() issues a projected (single-column) read: on a cold row
		// cache, each distinct column is its own small DB hit instead of one
		// shared full-row SELECT *.
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ] );

		$queries = $this->count_cache_table_queries( function () {
			$this->table->get_entry( 'test-plugin', 'tags' );
			$this->table->get_entry( 'test-plugin', 'meta' );
		} );

		$this->assertSame( 2, $queries );
	}

	public function test_get_entry_adds_no_queries_when_row_is_warm(): void {
		// Once get_repo() has memoized the full row, get_entry() slices from
		// the memoized row without querying again.
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ] );
		$this->table->get_repo( 'test-plugin' );

		$queries = $this->count_cache_table_queries( function () {
			$this->assertSame( [ '1.0.0' ], $this->table->get_entry( 'test-plugin', 'tags' ) );
			$this->table->get_entry( 'test-plugin', 'meta' );
		} );

		$this->assertSame( 0, $queries );
	}

	public function test_add_entry_invalidates_row_cache(): void {
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ] );
		$this->assertSame( [ '1.0.0' ], $this->table->get_repo( 'test-plugin' )['tags'] );

		$this->table->add_entry( 'test-plugin', 'tags', [ '2.0.0' ] );

		$this->assertSame( [ '2.0.0' ], $this->table->get_repo( 'test-plugin' )['tags'] );
	}

	public function test_update_entry_invalidates_row_cache(): void {
		$this->table->add_entry( 'test-plugin', 'meta', 'first' );
		$this->assertSame( 'first', $this->table->get_repo( 'test-plugin' )['meta'] );

		$this->table->update_entry( 'test-plugin', 'meta', 'second' );

		$this->assertSame( 'second', $this->table->get_repo( 'test-plugin' )['meta'] );
	}

	public function test_delete_entry_invalidates_row_cache(): void {
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ] );
		$this->assertSame( [ '1.0.0' ], $this->table->get_repo( 'test-plugin' )['tags'] );

		$this->table->delete_entry( 'test-plugin', 'tags' );

		$this->assertNull( $this->table->get_repo( 'test-plugin' )['tags'] );
	}

	public function test_delete_repo_invalidates_row_cache(): void {
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ] );
		$this->assertNotNull( $this->table->get_repo( 'test-plugin' ) );

		$this->table->delete_repo( 'test-plugin' );

		$this->assertNull( $this->table->get_repo( 'test-plugin' ) );
	}

	public function test_set_repo_timeout_invalidates_row_cache(): void {
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ] );
		$old_timeout = (int) $this->table->get_repo( 'test-plugin' )['timeout'];

		$new_timeout = time() + 7200;
		$this->table->set_repo_timeout( 'test-plugin', $new_timeout );

		$row = $this->table->get_repo( 'test-plugin' );
		$this->assertSame( (string) $new_timeout, $row['timeout'] );
		$this->assertNotSame( (string) $old_timeout, $row['timeout'] );
	}

	public function test_set_error_cache_invalidates_row_cache(): void {
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ] );
		$this->assertNull( $this->table->get_error_cache( 'test-plugin' ) );

		$this->table->set_error_cache( 'test-plugin', [ 'http_code' => 500 ], 300 );

		$this->assertSame( [ 'http_code' => 500 ], $this->table->get_error_cache( 'test-plugin' ) );
	}

	public function test_get_all_rows_populates_per_slug_cache(): void {
		$this->table->add_entry( 'plugin-a', 'tags', [ '1.0.0' ] );
		$this->table->add_entry( 'plugin-b', 'tags', [ '2.0.0' ] );
		$this->table->add_entry( 'plugin-c', 'tags', [ '3.0.0' ] );

		$this->table->get_all_rows();

		$queries = $this->count_cache_table_queries( function () {
			$this->assertSame( [ '1.0.0' ], $this->table->get_repo( 'plugin-a' )['tags'] );
			$this->assertSame( [ '2.0.0' ], $this->table->get_repo( 'plugin-b' )['tags'] );
			$this->assertSame( [ '3.0.0' ], $this->table->get_repo( 'plugin-c' )['tags'] );
		} );

		$this->assertSame( 0, $queries, 'get_all_rows() should warm the per-slug cache' );
	}

	public function test_get_cached_ran_returns_slug_to_ran_map(): void {
		$complete = [ 'contents', 'assets', 'readme', 'changes', 'tags', 'branches', 'meta' ];
		$partial  = [ 'tags', 'branches' ];

		$this->table->add_entry( 'plugin-a', 'ran', $complete );
		$this->table->add_entry( 'plugin-b', 'ran', $partial );
		$this->table->add_entry( 'plugin-c', 'tags', [ '1.0.0' ] ); // no ran column

		$map = $this->table->get_cached_ran();

		$this->assertSame( $complete, $map['plugin-a'] );
		$this->assertSame( $partial, $map['plugin-b'] );
		$this->assertNull( $map['plugin-c'] );
	}

	public function test_get_cached_ran_omits_absent_repo(): void {
		$this->table->add_entry( 'plugin-a', 'ran', [ 'tags' ] );

		$map = $this->table->get_cached_ran();

		$this->assertArrayHasKey( 'plugin-a', $map );
		$this->assertArrayNotHasKey( 'plugin-missing', $map );
	}

	public function test_get_cached_error_flags_maps_slug_to_bool(): void {
		$this->table->add_entry( 'plugin-a', 'ran', [ 'tags' ] );
		$this->table->set_error_cache( 'plugin-a', [ 'http_code' => 500 ], 300 );
		$this->table->add_entry( 'plugin-b', 'ran', [ 'tags' ] ); // no error_cache
		$this->table->add_entry( 'plugin-c', 'tags', [ '1.0.0' ] ); // no row relevant

		$map = $this->table->get_cached_error_flags();

		$this->assertTrue( $map['plugin-a'] );
		$this->assertFalse( $map['plugin-b'] );
		$this->assertArrayNotHasKey( 'plugin-missing', $map );
	}

	public function test_get_cached_ran_skips_empty_slug_rows(): void {
		// A row with an empty slug must be skipped (the `continue` guard),
		// so it never appears in the returned map.
		$this->table->add_entry( '', 'ran', [ 'tags' ] );
		$this->table->add_entry( 'plugin-a', 'ran', [ 'tags' ] );

		$map = $this->table->get_cached_ran();

		$this->assertArrayHasKey( 'plugin-a', $map );
		$this->assertArrayNotHasKey( '', $map );
	}

	public function test_get_cached_error_flags_skips_empty_slug_rows(): void {
		$this->table->add_entry( '', 'error_cache', [ 'http_code' => 500 ] );
		$this->table->set_error_cache( 'plugin-a', [ 'http_code' => 500 ], 300 );

		$map = $this->table->get_cached_error_flags();

		$this->assertArrayHasKey( 'plugin-a', $map );
		$this->assertArrayNotHasKey( '', $map );
	}

	public function test_delete_all_repos_flushes_row_cache(): void {
		$this->table->add_entry( 'plugin-a', 'tags', [ '1.0.0' ] );
		$this->table->add_entry( 'plugin-b', 'tags', [ '2.0.0' ] );
		$this->table->get_repo( 'plugin-a' );
		$this->table->get_repo( 'plugin-b' );

		$this->table->delete_all_repos();

		$this->assertNull( $this->table->get_repo( 'plugin-a' ) );
		$this->assertNull( $this->table->get_repo( 'plugin-b' ) );
	}

	public function test_prune_stale_flushes_row_cache(): void {
		$this->table->add_entry( 'plugin-a', 'tags', [ '1.0.0' ] );
		$this->table->add_entry( 'plugin-stale', 'tags', [ '2.0.0' ] );
		$this->table->get_repo( 'plugin-a' );
		$this->table->get_repo( 'plugin-stale' );

		$this->table->prune_stale( [ 'plugin-a' ] );

		$this->assertNotNull( $this->table->get_repo( 'plugin-a' ) );
		$this->assertNull( $this->table->get_repo( 'plugin-stale' ) );
	}

	public function test_row_cache_does_not_leak_across_writes_for_unrelated_slugs(): void {
		// Invalidating one slug must not poison the cache for another.
		$this->table->add_entry( 'plugin-a', 'tags', [ '1.0.0' ] );
		$this->table->add_entry( 'plugin-b', 'tags', [ '2.0.0' ] );
		$row_a = $this->table->get_repo( 'plugin-a' );
		$row_b = $this->table->get_repo( 'plugin-b' );

		$this->table->add_entry( 'plugin-a', 'tags', [ '9.9.9' ] );

		$this->assertSame( [ '9.9.9' ], $this->table->get_repo( 'plugin-a' )['tags'] );
		$this->assertSame( $row_b, $this->table->get_repo( 'plugin-b' ) );
	}

	// -------------------------------------------------------------------------
	// Object-cache tier (projected reads backed by wp_cache_)
	// -------------------------------------------------------------------------

	public function test_add_entry_with_valid_timeout_primes_projection_in_object_cache(): void {
		// A write carrying a valid row timeout primes the written column in object
		// cache, so the next projected read is served without a DB query.
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ], time() + 3600 );

		$queries = $this->count_cache_table_queries( function () {
			$this->assertSame( [ '1.0.0' ], $this->table->get_repo( 'test-plugin', 'tags' ) );
		} );

		$this->assertSame( 0, $queries, 'a write with a valid timeout should prime the projection in object cache' );
	}

	public function test_projected_read_populates_object_cache_on_cold_miss(): void {
		// A cold projected read (row not already in $row_cache, and object cache
		// invalidated) fetches from the DB once, then serves subsequent reads
		// from object cache without re-querying.
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ], time() + 3600 );
		$this->table->invalidate_object_cache( 'test-plugin' ); // Drop the primed cache.
		$this->table->reset_row_cache(); // Clear the per-request row cache.

		$queries = $this->count_cache_table_queries( function () {
			$this->assertSame( [ '1.0.0' ], $this->table->get_repo( 'test-plugin', 'tags' ) );
			$this->assertSame( [ '1.0.0' ], $this->table->get_repo( 'test-plugin', 'tags' ) );
		} );

		$this->assertSame( 1, $queries, 'a cold projection read should hit the DB once then read from object cache' );
	}

	public function test_null_column_result_is_cached_and_round_trips(): void {
		// A NULL column with a valid row timeout is cached (via a sentinel) so the
		// next read returns null without re-querying.
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ], time() + 3600 );
		$this->table->reset_row_cache();

		$queries = $this->count_cache_table_queries( function () {
			$this->assertNull( $this->table->get_repo( 'test-plugin', 'readme' ) );
			$this->assertNull( $this->table->get_repo( 'test-plugin', 'readme' ) );
		} );

		$this->assertSame( 1, $queries, 'a NULL column should be cached after its first read' );
	}

	public function test_expired_timeout_projection_is_not_cached(): void {
		// When the row timeout has lapsed, no object-cache entry is written, so a
		// repeated read still queries the DB rather than serving a pinned result.
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ], strtotime( '-1 hour' ) );
		$this->table->reset_row_cache();

		$queries = $this->count_cache_table_queries( function () {
			$this->assertSame( [ '1.0.0' ], $this->table->get_repo( 'test-plugin', 'tags' ) );
			$this->assertSame( [ '1.0.0' ], $this->table->get_repo( 'test-plugin', 'tags' ) );
		} );

		$this->assertSame( 2, $queries, 'an expired-timeout projection must not be object-cached' );
	}

	public function test_metadata_timeout_column_read_does_not_corrupt_cached_timeout(): void {
		// Reading the `timeout` column must surface it correctly and leave the
		// object-cached row timeout intact (so get_repo_cache(true) stays warm) —
		// not overwrite it with 0, which would make the row look expired.
		$timeout = time() + 3600;
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ], $timeout );

		$this->assertSame( (string) $timeout, (string) $this->table->get_repo( 'test-plugin', 'timeout' ) );
		$this->assertSame( $timeout, $this->table->get_repo_timeout( 'test-plugin' ) );
	}

	public function test_get_repo_timeout_returns_object_cached_value(): void {
		$timeout = time() + 3600;
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ], $timeout );

		$this->assertSame( $timeout, $this->table->get_repo_timeout( 'test-plugin' ) );
	}

	public function test_scalar_timeout_read_is_object_cached(): void {
		// A standalone `timeout` read must be cached, not re-query the DB.
		$timeout = time() + 3600;
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ], $timeout );
		$this->table->reset_row_cache();

		$queries = $this->count_cache_table_queries( function () {
			$this->assertSame( (string) ( time() + 3600 ), (string) $this->table->get_repo( 'test-plugin', 'timeout' ) );
			$this->assertSame( (string) ( time() + 3600 ), (string) $this->table->get_repo( 'test-plugin', 'timeout' ) );
		} );

		$this->assertSame( 1, $queries, 'a scalar timeout read should be cached after the first DB hit' );
	}

	public function test_scalar_error_timeout_read_is_object_cached(): void {
		// A standalone `error_timeout` read must be cached too.
		$this->table->set_error_cache( 'test-plugin', [ 'http_code' => 500 ], 300 );
		$this->table->reset_row_cache();

		$rows = $this->count_cache_table_queries( function () {
			$this->assertGreaterThan( time(), (int) $this->table->get_repo( 'test-plugin', 'error_timeout' ) );
			$this->assertGreaterThan( time(), (int) $this->table->get_repo( 'test-plugin', 'error_timeout' ) );
		} );

		$this->assertSame( 1, $rows, 'a scalar error_timeout read should be cached after the first DB hit' );
	}

	public function test_error_cache_timeout_combo_is_object_cached_on_error_row(): void {
		// The hottest path (API.php:190) reads ['error_cache','error_timeout'] and
		// must be cached even though a pure error row has row timeout = 0 — the
		// error_timeout is the governing expiry, not the row timeout.
		$this->table->set_error_cache( 'test-plugin', [ 'http_code' => 500 ], 300 );
		$this->table->reset_row_cache();

		$queries = $this->count_cache_table_queries( function () {
			$row = $this->table->get_repo( 'test-plugin', [ 'error_cache', 'error_timeout' ] );
			$this->assertSame( [ 'http_code' => 500 ], $row['error_cache'] );
			$this->assertGreaterThan( time(), (int) $row['error_timeout'] );

			$row2 = $this->table->get_repo( 'test-plugin', [ 'error_cache', 'error_timeout' ] );
			$this->assertSame( [ 'http_code' => 500 ], $row2['error_cache'] );
		} );

		$this->assertSame( 1, $queries, 'the error_cache/error_timeout combo must be cached after the first DB hit, even with row timeout 0' );
	}

	public function test_timeout_with_other_columns_combo_is_object_cached(): void {
		// API.php:691 reads ['timeout','release_asset','release_asset_redirect'],
		// which must be cached as a unit.
		$timeout = time() + 3600;
		$this->table->add_entry( 'test-plugin', 'release_asset', 'asset-url', $timeout );
		$this->table->reset_row_cache();

		$queries = $this->count_cache_table_queries( function () use ( $timeout ) {
			$row = $this->table->get_repo( 'test-plugin', [ 'timeout', 'release_asset' ] );
			$this->assertSame( (string) $timeout, (string) $row['timeout'] );
			$this->assertSame( 'asset-url', $row['release_asset'] );

			$this->table->get_repo( 'test-plugin', [ 'timeout', 'release_asset' ] );
		} );

		$this->assertSame( 1, $queries, 'a timeout+data projection must be cached after the first DB hit' );
	}

	public function test_healthy_row_scalar_error_cache_is_object_cached(): void {
		// On a healthy row (valid row timeout, no error), a scalar error_cache read
		// falls back to the row timeout and caches rather than re-querying.
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ], time() + 3600 );
		$this->table->reset_row_cache();

		$queries = $this->count_cache_table_queries( function () {
			$this->assertNull( $this->table->get_repo( 'test-plugin', 'error_cache' ) );
			$this->assertNull( $this->table->get_repo( 'test-plugin', 'error_cache' ) );
		} );

		$this->assertSame( 1, $queries, 'a scalar error_cache read on a healthy row should be cached' );
	}

	public function test_add_entry_invalidates_object_cache_projection(): void {
		// After a write, the previously-cached projection is discarded and the next
		// read reflects the freshly-written value from the DB.
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ], time() + 3600 );
		$this->assertSame( [ '1.0.0' ], $this->table->get_repo( 'test-plugin', 'tags' ) );

		$this->table->add_entry( 'test-plugin', 'tags', [ '2.0.0' ], time() + 3600 );

		$this->assertSame( [ '2.0.0' ], $this->table->get_repo( 'test-plugin', 'tags' ) );
	}

	public function test_delete_repo_api_data_invalidates_object_cache_projection(): void {
		$this->table->add_entry( 'test-plugin', 'tags', [ '1.0.0' ], time() + 3600 );
		$this->assertSame( [ '1.0.0' ], $this->table->get_repo( 'test-plugin', 'tags' ) );

		$this->table->delete_repo_api_data( 'test-plugin' );

		$this->assertNull( $this->table->get_repo( 'test-plugin', 'tags' ) );
	}

	public function test_projection_cache_is_order_independent(): void {
		// The same logical column set requested in a different order must share
		// one cache entry (no re-query), and each permutation must get its own
		// requested key order back.
		$this->table->add_entry( 'test-plugin', 'release_asset', 'asset-url', time() + 3600 );
		$this->table->reset_row_cache();

		$queries = $this->count_cache_table_queries( function () {
			$a = $this->table->get_repo( 'test-plugin', [ 'timeout', 'release_asset' ] );
			$this->assertSame( [ 'timeout', 'release_asset' ], array_keys( $a ) );
			$this->assertSame( 'asset-url', $a['release_asset'] );

			$b = $this->table->get_repo( 'test-plugin', [ 'release_asset', 'timeout' ] );
			$this->assertSame( [ 'release_asset', 'timeout' ], array_keys( $b ) );
			$this->assertSame( 'asset-url', $b['release_asset'] );
		} );

		$this->assertSame( 1, $queries, 'permutations of the same column set must share one cache entry' );
	}

	public function test_error_combo_cache_is_order_independent(): void {
		// The order of error_cache/error_timeout must not matter for caching, and
		// each permutation returns its own key order.
		$this->table->set_error_cache( 'test-plugin', [ 'http_code' => 500 ], 300 );
		$this->table->reset_row_cache();

		$queries = $this->count_cache_table_queries( function () {
			$a = $this->table->get_repo( 'test-plugin', [ 'error_cache', 'error_timeout' ] );
			$this->assertSame( [ 'error_cache', 'error_timeout' ], array_keys( $a ) );
			$this->assertSame( [ 'http_code' => 500 ], $a['error_cache'] );

			$b = $this->table->get_repo( 'test-plugin', [ 'error_timeout', 'error_cache' ] );
			$this->assertSame( [ 'error_timeout', 'error_cache' ], array_keys( $b ) );
			$this->assertSame( [ 'http_code' => 500 ], $b['error_cache'] );
		} );

		$this->assertSame( 1, $queries, 'the error_cache/error_timeout combo must cache regardless of order' );
	}

	// -------------------------------------------------------------------------
	// Abstract_Cache_Table::__construct() — line 100
	// -------------------------------------------------------------------------

	/**
	 * Instantiate a concrete subclass directly so the inherited constructor
	 * (which news up Repo_Cache_Object) is attributed to the abstract base.
	 * Covers Abstract_Cache_Table.php:99-101.
	 */
	public function test_abstract_constructor_initializes_object_cache(): void {
		$instance = new class() extends Abstract_Cache_Table {
			protected function schema(): string {
				return 'CREATE TABLE IF NOT EXISTS test_ct ()';
			}

			public function add_entry( string $slug, string $column, $value, int $timeout = 0, int $error_timeout = 0 ): bool {
				return true;
			}

			public function update_entry( string $slug, string $column, $value, int $timeout = 0, int $error_timeout = 0 ): bool {
				return true;
			}

			public function delete_entry( string $slug, string $column ): bool {
				return true;
			}

			public function get_entry( string $slug, string $column ) {
				return null;
			}
		};

		$this->assertInstanceOf( Abstract_Cache_Table::class, $instance );
	}
}
