<?php
/**
 * Tests for the isolated object-cache tier.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\DB\Repo_Cache_Object;

/**
 * Tests for Repo_Cache_Object.
 *
 * The WP_UnitTestCase cache is non-persistent and flushed per test, so these
 * exercise the per-request behaviour, which is what a non-persistent host sees.
 */
class Test_Repo_Cache_Object extends WP_UnitTestCase {

	/**
	 * Object under test.
	 *
	 * @var Repo_Cache_Object
	 */
	private Repo_Cache_Object $cache;

	public function set_up(): void {
		parent::set_up();
		$this->cache = new Repo_Cache_Object();
	}

	public function test_set_then_get_round_trips(): void {
		$this->cache->set( 'test-plugin', 'tags', [ '1.0.0' ], 3600 );
		$this->assertSame( [ '1.0.0' ], $this->cache->get( 'test-plugin', 'tags', false ) );
	}

	public function test_array_projection_returns_requested_order(): void {
		$this->cache->set( 'test-plugin', [ 'release_asset', 'timeout' ], [ 'release_asset' => 'url', 'timeout' => 123 ], 3600 );
		$this->assertSame(
			[ 'timeout' => 123, 'release_asset' => 'url' ],
			$this->cache->get( 'test-plugin', [ 'timeout', 'release_asset' ], true )
		);
	}

	public function test_get_miss_returns_false(): void {
		$this->assertFalse( $this->cache->get( 'no-such-plugin', 'tags', false ) );
	}

	public function test_cached_null_maps_back_to_null(): void {
		$this->cache->set( 'test-plugin', 'readme', null, 3600 );
		$this->assertNull( $this->cache->get( 'test-plugin', 'readme', false ) );
	}

	public function test_set_with_zero_ttl_stores_nothing(): void {
		$this->cache->set( 'test-plugin', 'tags', [ '1.0.0' ], 0 );
		$this->assertFalse( $this->cache->get( 'test-plugin', 'tags', false ) );
	}

	public function test_invalidate_makes_cached_value_unreachable(): void {
		$this->cache->set( 'test-plugin', 'tags', [ '1.0.0' ], 3600 );
		$this->assertSame( [ '1.0.0' ], $this->cache->get( 'test-plugin', 'tags', false ) );

		$this->cache->invalidate( 'test-plugin' );

		// The generation changed, so the old key is unreachable → miss.
		$this->assertFalse( $this->cache->get( 'test-plugin', 'tags', false ) );
	}

	public function test_invalidate_is_per_slug(): void {
		// Invalidating one slug must not disturb another.
		$this->cache->set( 'plugin-a', 'tags', [ '1.0.0' ], 3600 );
		$this->cache->set( 'plugin-b', 'tags', [ '2.0.0' ], 3600 );

		$this->cache->invalidate( 'plugin-a' );

		$this->assertFalse( $this->cache->get( 'plugin-a', 'tags', false ) );
		$this->assertSame( [ '2.0.0' ], $this->cache->get( 'plugin-b', 'tags', false ) );
	}

	public function test_invalidate_all_bumps_each_slug(): void {
		$this->cache->set( 'plugin-a', 'tags', [ '1.0.0' ], 3600 );
		$this->cache->set( 'plugin-b', 'tags', [ '2.0.0' ], 3600 );

		$this->cache->invalidate_all( [ 'plugin-a', 'plugin-b' ] );

		$this->assertFalse( $this->cache->get( 'plugin-a', 'tags', false ) );
		$this->assertFalse( $this->cache->get( 'plugin-b', 'tags', false ) );
	}

	public function test_invalidate_all_skips_empty_slugs(): void {
		$this->cache->set( 'plugin-a', 'tags', [ '1.0.0' ], 3600 );

		// Empty/null slugs must not throw.
		$this->cache->invalidate_all( [ 'plugin-a', '', null ] );

		$this->assertFalse( $this->cache->get( 'plugin-a', 'tags', false ) );
	}

	public function test_timeout_round_trips(): void {
		$this->cache->set_timeout( 'test-plugin', 12345 );
		$this->assertSame( 12345, $this->cache->get_timeout( 'test-plugin' ) );
	}

	public function test_get_timeout_unknown_returns_zero(): void {
		$this->assertSame( 0, $this->cache->get_timeout( 'no-such-plugin' ) );
	}

	public function test_ttl_bounded_by_error_timeout_for_error_projection(): void {
		$row_timeout       = time() + 3600;
		$row_error_timeout = time() + 60;
		$ttl               = $this->cache->ttl( $row_timeout, $row_error_timeout, [ 'error_cache', 'error_timeout' ] );
		$this->assertGreaterThan( 0, $ttl );
		$this->assertLessThanOrEqual( 60, $ttl );
	}

	public function test_ttl_bounded_by_row_timeout_for_data_projection(): void {
		$row_timeout       = time() + 3600;
		$row_error_timeout = 0;
		$ttl               = $this->cache->ttl( $row_timeout, $row_error_timeout, [ 'tags' ] );
		$this->assertGreaterThan( 0, $ttl );
		$this->assertLessThanOrEqual( 3600, $ttl );
	}

	public function test_ttl_zero_when_row_timeout_expired(): void {
		$this->assertSame( 0, $this->cache->ttl( time() - 3600, 0, [ 'tags' ] ) );
	}

	public function test_prime_on_write_caches_column_and_timeout(): void {
		$this->cache->prime_on_write( 'test-plugin', [ 'tags' => [ '1.0.0' ] ], time() + 3600 );

		$this->assertSame( [ '1.0.0' ], $this->cache->get( 'test-plugin', 'tags', false ) );
		$this->assertGreaterThan( time(), $this->cache->get_timeout( 'test-plugin' ) );
	}

	public function test_prime_on_write_zero_timeout_does_nothing(): void {
		$this->cache->prime_on_write( 'test-plugin', [ 'tags' => [ '1.0.0' ] ], 0 );

		$this->assertFalse( $this->cache->get( 'test-plugin', 'tags', false ) );
	}

	public function test_unknown_column_throws(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->cache->set( 'test-plugin', 'bogus', 'x', 3600 );
	}
}
