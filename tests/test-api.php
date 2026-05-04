<?php
/**
 * Tests for API::api() caching behaviour.
 *
 * Covers:
 * - HTTP request made when no cache exists.
 * - No HTTP request on second call within the 12-hour main cache window.
 * - Non-200 response writes error_cache to its dedicated site option key.
 * - No HTTP request and false returned when error cache is fresh (< 60 min).
 * - HTTP request retried after the error cache has expired (> 60 min).
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\API\GitHub_API;
use Fragen\Git_Updater\Base;

/**
 * Class Test_API
 */
class Test_API extends WP_UnitTestCase {

	/**
	 * GitHub API instance under test.
	 *
	 * @var GitHub_API
	 */
	private GitHub_API $api;

	/**
	 * Minimal repo type object.
	 *
	 * @var stdClass
	 */
	private stdClass $type;

	/**
	 * Endpoint passed to api() — placeholders resolved by get_api_url().
	 *
	 * @var string
	 */
	private string $endpoint = '/repos/:owner/:repo';

	/**
	 * Set up a fresh GitHub_API instance before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->type = $this->make_type();
		$this->api  = new GitHub_API( $this->type );
	}

	/**
	 * Remove filters and cached site options after each test.
	 */
	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		delete_site_option( $this->api->get_cache_key( 'test-plugin' ) );
		delete_site_option( $this->api->get_cache_key( 'test-plugin_error' ) );
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a minimal repo type object accepted by GitHub_API.
	 */
	private function make_type(): stdClass {
		$type                 = new stdClass();
		$type->slug           = 'test-plugin';
		$type->git            = 'github';
		$type->type           = 'plugin';
		$type->owner          = 'test-owner';
		$type->branch         = 'master';
		$type->primary_branch = 'master';
		$type->enterprise     = false;
		$type->enterprise_api = null;
		$type->gist_id        = null;

		return $type;
	}

	/**
	 * Return a minimal wp_remote_get()-compatible response array.
	 *
	 * @param int   $code HTTP status code.
	 * @param array $body Associative array serialised as the response body.
	 */
	private function mock_http_response( int $code, array $body = [] ): array {
		return [
			'response' => [
				'code'    => $code,
				'message' => 200 === $code ? 'OK' : 'Error',
			],
			'body'     => wp_json_encode( $body ),
			'headers'  => [],
			'cookies'  => [],
		];
	}

	/**
	 * Hook pre_http_request to return $response and increment $count.
	 *
	 * @param array $response Mock response to return.
	 * @param int   $count    Reference counter incremented on each intercepted call.
	 */
	private function intercept_http( array $response, int &$count ): void {
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $response, &$count ) {
				$count++;
				return $response;
			},
			10,
			3
		);
	}

	// -------------------------------------------------------------------------
	// Tests
	// -------------------------------------------------------------------------

	/**
	 * With no cache in place, api() must make exactly one HTTP request
	 * and return the decoded response body.
	 */
	public function test_makes_http_request_when_no_cache(): void {
		$call_count = 0;
		$this->intercept_http(
			$this->mock_http_response( 200, [ 'name' => 'test-plugin' ] ),
			$call_count
		);

		$result = $this->api->api( $this->endpoint );

		$this->assertSame( 1, $call_count, 'Expected exactly one HTTP request when no cache exists.' );
		$this->assertIsObject( $result );
		$this->assertSame( 'test-plugin', $result->name );
	}

	/**
	 * A second call within the 12-hour main cache window must not make
	 * another HTTP request and must return the same decoded body.
	 */
	public function test_uses_main_cache_on_second_call(): void {
		$call_count = 0;
		$this->intercept_http(
			$this->mock_http_response( 200, [ 'name' => 'test-plugin' ] ),
			$call_count
		);

		$this->api->api( $this->endpoint ); // primes the cache.
		$result = $this->api->api( $this->endpoint ); // must serve from cache.

		$this->assertSame( 1, $call_count, 'Second call within cache window should not make an HTTP request.' );
		$this->assertIsObject( $result );
		$this->assertSame( 'test-plugin', $result->name );
	}

	/**
	 * A non-200 response must write error_cache to the dedicated
	 * slug_error site option key, not the main cache key.
	 */
	public function test_non_200_response_writes_error_cache_to_dedicated_key(): void {
		$call_count = 0;
		$this->intercept_http(
			$this->mock_http_response( 403, [ 'message' => 'API rate limit exceeded' ] ),
			$call_count
		);

		$this->api->api( $this->endpoint );

		$this->assertSame( 1, $call_count, 'Expected exactly one HTTP request.' );

		$error_cache = get_site_option( $this->api->get_cache_key( 'test-plugin_error' ), [] );
		$this->assertArrayHasKey(
			'error_cache',
			$error_cache,
			'error_cache must be written to the dedicated slug_error site option key.'
		);
	}

	/**
	 * When a fresh error cache exists (within the 60-minute window) and the
	 * main cache has expired, api() must not make an HTTP request and must
	 * return false.
	 */
	public function test_skips_request_and_returns_false_when_error_cache_is_fresh(): void {
		// Seed the error cache directly — no main cache entry — to simulate the
		// scenario after the 12-hour main cache has expired.
		update_site_option(
			$this->api->get_cache_key( 'test-plugin_error' ),
			[
				'error_cache' => $this->mock_http_response( 403, [ 'message' => 'Rate limited' ] ),
				'timeout'     => strtotime( '+1 hour' ),
			]
		);

		$call_count = 0;
		$this->intercept_http(
			$this->mock_http_response( 200, [ 'name' => 'test-plugin' ] ),
			$call_count
		);

		$result = $this->api->api( $this->endpoint );

		$this->assertSame( 0, $call_count, 'No HTTP request should be made when the error cache is fresh.' );
		$this->assertFalse( $result, 'api() must return false when the error cache is fresh.' );
	}

	/**
	 * Once the 60-minute error cache has expired, api() must make a fresh
	 * HTTP request and return the decoded response.
	 */
	public function test_retries_request_after_error_cache_expires(): void {
		// Seed an already-expired error cache entry.
		update_site_option(
			$this->api->get_cache_key( 'test-plugin_error' ),
			[
				'error_cache' => $this->mock_http_response( 403, [ 'message' => 'Rate limited' ] ),
				'timeout'     => strtotime( '-2 hours' ),
			]
		);

		$call_count = 0;
		$this->intercept_http(
			$this->mock_http_response( 200, [ 'name' => 'test-plugin' ] ),
			$call_count
		);

		$result = $this->api->api( $this->endpoint );

		$this->assertSame( 1, $call_count, 'HTTP request should be retried after the error cache expires.' );
		$this->assertIsObject( $result );
		$this->assertSame( 'test-plugin', $result->name );
	}
}
