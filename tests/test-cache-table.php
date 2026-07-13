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
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $this->table->table_name() );
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
		// Invalid column must not be written to a real column.
		$this->table->add_entry( 'test-plugin', 'bogus_column', 'x' );
		$row = (array) $this->table->get_repo( 'test-plugin' );
		$this->assertArrayNotHasKey( 'bogus_column', $row );
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
}
