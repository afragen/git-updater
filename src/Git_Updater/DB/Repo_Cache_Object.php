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

/**
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Object-cache tier for the repository cache table.
 *
 * Owns every `wp_cache_*` call and key string for the projected-repo-data and
 * row-timeout cache. Keys are scoped to a per-slug generation counter so a
 * write can invalidate a slug's whole projection set without enumerating keys,
 * safely across requests on a persistent object cache.
 *
 * The object cache is strictly an accelerator: every call returns `false` on a
 * miss (or when the backend is unavailable), the DB is always authoritative,
 * and a non-persistent or unavailable cache only loses speed — never
 * correctness.
 */
final class Repo_Cache_Object {

	/**
	 * WordPress object-cache group.
	 *
	 * Matches the `'git-updater'` group already used by Bootstrap for the
	 * table-existence flag, so no new cache group is introduced.
	 *
	 * @var string
	 */
	private const CACHE_GROUP = 'git-updater';

	/**
	 * Sentinel stored for a single-column `null` result.
	 *
	 * `wp_cache_get()` returns `false` on a miss; a legitimate cached `null`
	 * must not be confused with a miss, so the null result is stored under this
	 * sentinel and mapped back to `null` on read. Never escapes this class.
	 *
	 * @var string
	 */
	private const CACHE_NULL = '__GU_NULL__';

	/**
	 * Static key prefix for all repo object-cache entries.
	 *
	 * @var string
	 */
	private const PREFIX = 'gu_repo';

	/**
	 * Read a projected value from object cache.
	 *
	 * Returns `false` on a miss, `null` for a genuinely cached `null`, and
	 * otherwise the cached value reordered to the caller's requested column
	 * order (for an array projection). The caller only ever compares against
	 * `=== false`; the null sentinel never escapes.
	 *
	 * @param string                    $slug    Repository slug.
	 * @param array<string>|string|null $column  Requested column(s); null = full row.
	 * @param bool                      $is_array Whether this is an array projection.
	 *
	 * @return mixed false on miss, null for a cached null, else the value.
	 */
	public function get( string $slug, $column, bool $is_array ) {
		$key = $this->projection_key( $slug, $column, $is_array );
		$hit = wp_cache_get( $key, self::CACHE_GROUP );
		if ( false === $hit ) {
			return false;
		}
		if ( $is_array ) {
			return $this->reorder_projection( $hit, $column );
		}

		return self::CACHE_NULL === $hit ? null : $hit;
	}

	/**
	 * Read a slug's cached row timeout.
	 *
	 * @param string $slug Repository slug.
	 *
	 * @return int The cached timeout timestamp, or 0 when not cached/unknown.
	 */
	public function get_timeout( string $slug ): int {
		$cached = wp_cache_get( $this->timeout_key( $slug ), self::CACHE_GROUP );

		return false === $cached ? 0 : (int) $cached;
	}

	/**
	 * Cache a projected value with an already-computed TTL.
	 *
	 * Skips caching when the TTL has lapsed (`$ttl <= 0`): a zero TTL is
	 * interpreted by `wp_cache_set()` as "store forever", which would pin an
	 * expired projection and serve it past its window — the one staleness mode
	 * this cache must never permit.
	 *
	 * @param string                     $slug   Repository slug.
	 * @param array<string>|string|null  $column Requested column(s); null = full row.
	 * @param array<string, mixed>|mixed $value  Projected value to cache.
	 * @param int                        $ttl    Remaining expiry seconds.
	 *
	 * @return void
	 */
	public function set( string $slug, $column, $value, int $ttl ): void {
		if ( $ttl <= 0 ) {
			return;
		}
		$is_array = is_array( $column );
		$key      = $this->projection_key( $slug, $column, $is_array );

		if ( $is_array ) {
			$store = $this->canonicalize_projection( $value, $column );
		} else {
			// Store a genuine null under the sentinel so a cached null is never
			// mistaken for a miss (`wp_cache_get` returns false on a miss).
			$store = null === $value ? self::CACHE_NULL : $value;
		}
		wp_cache_set( $key, $store, self::CACHE_GROUP, $ttl );
	}

	/**
	 * Cache a slug's row timeout in object cache (generation-scoped).
	 *
	 * @param string $slug    Repository slug.
	 * @param int    $timeout Row timeout timestamp.
	 *
	 * @return void
	 */
	public function set_timeout( string $slug, int $timeout ): void {
		wp_cache_set( $this->timeout_key( $slug ), $timeout, self::CACHE_GROUP );
	}

	/**
	 * Invalidate a slug's object-cache entries by bumping its generation.
	 *
	 * All existing projection/timeout keys for the slug embed the old
	 * generation, so after this bump they are unreachable and naturally expire
	 * by their TTL. A later read uses the new generation and re-queries the DB.
	 *
	 * @param string $slug Repository slug.
	 *
	 * @return void
	 */
	public function invalidate( string $slug ): void {
		$this->bump_generation( $slug );
	}

	/**
	 * Invalidate object-cache entries for several slugs.
	 *
	 * Empty or null slugs (e.g. rows with a blank `slug`) are skipped.
	 *
	 * @param array<int, string|null> $slugs Repository slugs.
	 *
	 * @return void
	 */
	public function invalidate_all( array $slugs ): void {
		foreach ( $slugs as $slug ) {
			if ( '' === $slug || null === $slug ) {
				continue;
			}
			$this->bump_generation( $slug );
		}
	}

	/**
	 * Compute the object-cache TTL for a projected entry.
	 *
	 * The TTL is bounded by the projection's own freshness window: error data
	 * (`error_cache`/`error_timeout`) is governed by the independent
	 * `error_timeout` column (which can be valid even when the row timeout is 0,
	 * the state `set_error_cache()` leaves behind), falling back to the row
	 * timeout when there is no pending error so healthy repos still cache. Every
	 * other projection is bounded by the row `timeout`.
	 *
	 * @param int                       $row_timeout       Row timeout (0 = unset/expired).
	 * @param int                       $row_error_timeout Error-cache timeout (0 = no pending error).
	 * @param array<string>|string|null $column            Projected column(s).
	 *
	 * @return int Seconds until the entry expires (0 = do not cache).
	 */
	public function ttl( int $row_timeout, int $row_error_timeout, $column ): int {
		$requested = is_array( $column ) ? $column : [ $column ];
		$is_error  = in_array( 'error_cache', $requested, true ) || in_array( 'error_timeout', $requested, true );

		if ( $is_error && $row_error_timeout > time() ) {
			return max( $row_error_timeout - time(), 0 );
		}

		return max( $row_timeout - time(), 0 );
	}

	/**
	 * Populate object cache after a successful write.
	 *
	 * Re-surfaces the row timeout and caches the just-written column(s) so the
	 * next projected read of those columns is served from object cache with the
	 * extended timeout, instead of forcing a cold DB read. Only runs when an
	 * explicit non-zero row timeout accompanies the write; otherwise the row
	 * timeout is unknown here (preserved from the existing row) and the next
	 * projected read repopulates from the DB.
	 *
	 * @param string               $slug          Repository slug.
	 * @param array<string, mixed> $column_values Map of column => raw (unserialized) value.
	 * @param int                  $timeout       Row timeout timestamp (0 = preserve existing).
	 *
	 * @return void
	 */
	public function prime_on_write( string $slug, array $column_values, int $timeout ): void {
		if ( $timeout <= 0 ) {
			return;
		}
		$this->set_timeout( $slug, $timeout );

		$ttl = max( $timeout - time(), 0 );
		if ( $ttl <= 0 ) {
			return;
		}
		foreach ( $column_values as $column => $value ) {
			$this->set( $slug, $column, $value, $ttl );
		}
	}

	/**
	 * Object-cache key for a projected column read (generation-scoped).
	 *
	 * A scalar request and a single-element array request remain distinct
	 * (`s:` vs `a:` prefix) because they return different shapes.
	 *
	 * @param string                    $slug    Repository slug.
	 * @param array<string>|string|null $column  Projected column(s).
	 * @param bool                      $is_array Whether this is an array projection.
	 *
	 * @return string The object-cache key.
	 */
	private function projection_key( string $slug, $column, bool $is_array ): string {
		$cols = $is_array ? array_map( [ $this, 'whitelist' ], $column ) : [ $this->whitelist( $column ) ];
		sort( $cols );
		$proj = $is_array ? 'a:' . implode( ',', $cols ) : 's:' . $cols[0];

		return self::PREFIX . ':proj:' . $this->generation( $slug ) . ':' . hash( 'sha256', $slug . '|' . $proj );
	}

	/**
	 * Object-cache key holding a slug's cached row timeout (generation-scoped).
	 *
	 * @param string $slug Repository slug.
	 *
	 * @return string The object-cache key.
	 */
	private function timeout_key( string $slug ): string {
		return self::PREFIX . ':timeout:' . $this->generation( $slug ) . ':' . $slug;
	}

	/**
	 * Current cache generation for a slug.
	 *
	 * A monotonically increasing integer stored in object cache under the
	 * generation key. It is embedded in every projection/timeout key so an
	 * unchanged generation is a cache hit across requests, while a bumped
	 * generation invalidates all of a slug's cached entries. The generation is
	 * seeded lazily on first use.
	 *
	 * @param string $slug Repository slug.
	 *
	 * @return int The current generation (>= 1).
	 */
	private function generation( string $slug ): int {
		$key = self::PREFIX . ':gen:' . $slug;
		$gen = wp_cache_get( $key, self::CACHE_GROUP );
		if ( false !== $gen ) {
			return (int) $gen;
		}

		// Seed to 1 on first use; a concurrent seed wins and is returned next.
		wp_cache_add( $key, 1, self::CACHE_GROUP );

		return (int) wp_cache_get( $key, self::CACHE_GROUP );
	}

	/**
	 * Invalidate a slug's object-cache entries by bumping its generation.
	 *
	 * @param string $slug Repository slug.
	 *
	 * @return void
	 */
	private function bump_generation( string $slug ): void {
		$key = self::PREFIX . ':gen:' . $slug;
		$inc = wp_cache_incr( $key, 1, self::CACHE_GROUP );
		if ( false === $inc ) {
			// No generation key yet (nothing cached, or it expired/was flushed).
			// Seed to 2 so any reader that seeded 1 earlier is invalidated.
			wp_cache_add( $key, 2, self::CACHE_GROUP );
		}
	}

	/**
	 * Canonicalize an array projection into sorted-key order for storage.
	 *
	 * Stored projections use a deterministic (sorted) key order so permutations
	 * of the same column set share one cache entry; the caller's requested
	 * order is restored on read.
	 *
	 * @param array<string, mixed> $value  Projected value keyed by column.
	 * @param array<int, string>   $column Requested column order (whitelisted).
	 *
	 * @return array<string, mixed> The projection keyed by sorted column.
	 */
	private function canonicalize_projection( array $value, array $column ): array {
		$projection = [];
		foreach ( $column as $col ) {
			if ( array_key_exists( $col, $value ) ) {
				$projection[ $col ] = $value[ $col ];
			}
		}
		return $projection;
	}

	/**
	 * Reorder a stored projection into the caller's requested column order.
	 *
	 * @param array<string, mixed> $row    Canonical-keyed projection.
	 * @param array<int, string>   $column Requested column order (whitelisted).
	 *
	 * @return array<string, mixed> The projection reordered to $column.
	 */
	private function reorder_projection( array $row, array $column ): array {
		$partial = [];
		foreach ( $column as $col ) {
			if ( array_key_exists( $col, $row ) ) {
				$partial[ $col ] = $row[ $col ];
			}
		}
		return $partial;
	}

	/**
	 * Validate a column name against the cache-table whitelist.
	 *
	 * Identical to the table's `whitelist()` so the object-cache class can build
	 * stable keys without depending on injected columns.
	 *
	 * @param string $column Column name.
	 *
	 * @return string The validated column name.
	 *
	 * @throws \InvalidArgumentException When the column is not whitelisted.
	 */
	private function whitelist( string $column ): string {
		$allowed = [
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
		if ( ! in_array( $column, $allowed, true ) ) {
			throw new \InvalidArgumentException( sprintf( 'Invalid cache column: %s', esc_html( $column ) ) );
		}

		return $column;
	}
}
