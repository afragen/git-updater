<?php
/**
 * Tests for API methods changed in Tier 1 and Tier 2 PHPStan fixes.
 *
 * Covers:
 * - HTTP request made when no cache exists.
 * - No HTTP request on second call within the 12-hour main cache window.
 * - Non-200 response writes error_cache to its dedicated site option key.
 * - No HTTP request and false returned when error cache is fresh (< 60 min).
 * - HTTP request retried after the error cache has expired (> 60 min).
 * - api() propagates WP_Error when wp_remote_get() itself fails.
 * - validate_response() correctly identifies invalid responses including WP_Error.
 * - set_readme_info() processes a valid readme array (dead is_array check removed).
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

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->type = $this->make_type();
		$this->api  = new GitHub_API( $this->type );
	}

	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		delete_site_option( $this->api->get_cache_key( 'test-plugin' ) );
		delete_site_option( $this->api->get_cache_key( 'test-plugin_error' ) );
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

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
	// api() – caching behaviour (existing tests)
	// -------------------------------------------------------------------------

	public function test_makes_http_request_when_no_cache(): void {
		$call_count = 0;
		$this->intercept_http( $this->mock_http_response( 200, [ 'name' => 'test-plugin' ] ), $call_count );

		$result = $this->api->api( $this->endpoint );

		$this->assertSame( 1, $call_count, 'Expected exactly one HTTP request when no cache exists.' );
		$this->assertIsObject( $result );
		$this->assertSame( 'test-plugin', $result->name );
	}

	public function test_uses_main_cache_on_second_call(): void {
		$call_count = 0;
		$this->intercept_http( $this->mock_http_response( 200, [ 'name' => 'test-plugin' ] ), $call_count );

		$this->api->api( $this->endpoint );
		$result = $this->api->api( $this->endpoint );

		$this->assertSame( 1, $call_count, 'Second call within cache window should not make an HTTP request.' );
		$this->assertIsObject( $result );
	}

	public function test_non_200_response_writes_error_cache_to_dedicated_key(): void {
		$call_count = 0;
		$this->intercept_http( $this->mock_http_response( 403, [ 'message' => 'API rate limit exceeded' ] ), $call_count );

		$this->api->api( $this->endpoint );

		$error_cache = get_site_option( $this->api->get_cache_key( 'test-plugin_error' ), [] );
		$this->assertArrayHasKey( 'error_cache', $error_cache );
	}

	public function test_skips_request_and_returns_false_when_error_cache_is_fresh(): void {
		update_site_option(
			$this->api->get_cache_key( 'test-plugin_error' ),
			[
				'error_cache' => $this->mock_http_response( 403, [ 'message' => 'Rate limited' ] ),
				'timeout'     => strtotime( '+1 hour' ),
			]
		);

		$call_count = 0;
		$this->intercept_http( $this->mock_http_response( 200, [ 'name' => 'test-plugin' ] ), $call_count );

		$result = $this->api->api( $this->endpoint );

		$this->assertSame( 0, $call_count, 'No HTTP request should be made when the error cache is fresh.' );
		$this->assertFalse( $result );
	}

	public function test_retries_request_after_error_cache_expires(): void {
		update_site_option(
			$this->api->get_cache_key( 'test-plugin_error' ),
			[
				'error_cache' => $this->mock_http_response( 403, [ 'message' => 'Rate limited' ] ),
				'timeout'     => strtotime( '-2 hours' ),
			]
		);

		$call_count = 0;
		$this->intercept_http( $this->mock_http_response( 200, [ 'name' => 'test-plugin' ] ), $call_count );

		$result = $this->api->api( $this->endpoint );

		$this->assertSame( 1, $call_count, 'HTTP request should be retried after the error cache expires.' );
		$this->assertIsObject( $result );
	}

	// -------------------------------------------------------------------------
	// get_class_vars() — setAccessible dead code removed (PHP 8.1+ reflection)
	// -------------------------------------------------------------------------

	/**
	 * get_class_vars() must read a known public static property from Base.
	 * Tested via GitHub_API (which is in Fragen\Git_Updater\API namespace) so
	 * the Singleton can resolve 'Base' to Fragen\Git_Updater\Base.
	 * Removing setAccessible(true) must not break the reflection getValue() call.
	 */
	public function test_get_class_vars_reads_public_static_property(): void {
		$git_servers = $this->api->get_class_vars( 'Base', 'git_servers' );
		$this->assertIsArray( $git_servers );
		$this->assertArrayHasKey( 'github', $git_servers );
	}

	/**
	 * get_class_vars() returns false when the requested property does not exist.
	 */
	public function test_get_class_vars_returns_false_for_missing_property(): void {
		$result = $this->api->get_class_vars( 'Base', 'nonexistent_property_xyz' );
		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// api() — WP_Error propagation (new: @return now includes WP_Error)
	// -------------------------------------------------------------------------

	/**
	 * When wp_remote_get() itself fails and returns a WP_Error (e.g. DNS
	 * failure), api() must propagate that WP_Error rather than returning false.
	 */
	public function test_api_propagates_wp_error_from_http_layer(): void {
		$wp_error = new WP_Error( 'http_request_failed', 'cURL error 6: Could not resolve host' );
		add_filter( 'pre_http_request', fn() => $wp_error, 10, 3 );

		$result = $this->api->api( $this->endpoint );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'http_request_failed', $result->get_error_code() );
	}

	// -------------------------------------------------------------------------
	// validate_response() — @param fixed to mixed; is_wp_error kept
	// -------------------------------------------------------------------------

	/**
	 * Access the protected validate_response() via ReflectionMethod.
	 * In PHP 8.1+ setAccessible() is a no-op, so invoke() works directly —
	 * this also exercises the get_reflection_method() PHP_VERSION_ID fix.
	 */
	private function call_validate_response( $value ): bool {
		$rm = new ReflectionMethod( $this->api, 'validate_response' );
		return $rm->invoke( $this->api, $value );
	}

	public function test_validate_response_empty_string_is_invalid(): void {
		$this->assertTrue( $this->call_validate_response( '' ) );
	}

	public function test_validate_response_null_is_invalid(): void {
		$this->assertTrue( $this->call_validate_response( null ) );
	}

	public function test_validate_response_false_is_invalid(): void {
		$this->assertTrue( $this->call_validate_response( false ) );
	}

	public function test_validate_response_object_with_message_is_invalid(): void {
		$response          = new stdClass();
		$response->message = 'No tags found';
		$this->assertTrue( $this->call_validate_response( $response ) );
	}

	public function test_validate_response_object_with_error_is_invalid(): void {
		$response        = new stdClass();
		$response->error = 'Not Found';
		$this->assertTrue( $this->call_validate_response( $response ) );
	}

	/**
	 * WP_Error must be treated as invalid — this guards the is_wp_error()
	 * call that we deliberately kept in validate_response().
	 */
	public function test_validate_response_wp_error_is_invalid(): void {
		$wp_error = new WP_Error( 'http_request_failed', 'Connection timeout' );
		$this->assertTrue( $this->call_validate_response( $wp_error ) );
	}

	/**
	 * A valid stdClass with data (no message/error properties) is not invalid.
	 */
	public function test_validate_response_valid_object_is_not_invalid(): void {
		$response       = new stdClass();
		$response->name = 'my-plugin';
		$this->assertFalse( $this->call_validate_response( $response ) );
	}

	/**
	 * A non-empty array is not invalid (used for branch lists, tag lists, etc.).
	 */
	public function test_validate_response_non_empty_array_is_not_invalid(): void {
		$this->assertFalse( $this->call_validate_response( [ 'tag' => '1.0.0' ] ) );
	}

	// -------------------------------------------------------------------------
	// set_readme_info() — dead is_array() check removed; must still process
	// -------------------------------------------------------------------------

	/**
	 * set_readme_info() must process a valid readme array and populate
	 * $type->requires, ->tested, etc.  The removed dead-code check
	 * (if (!is_array($readme))) cannot interfere because the param is always
	 * an array<string,mixed> by declaration.
	 */
	public function test_set_readme_info_populates_type_properties(): void {
		$this->type->sections = new stdClass();
		$this->type->requires = '';
		$this->type->requires_php = '';
		$this->type->tested   = '';

		$readme = [
			'sections'          => [ 'description' => 'My plugin description.' ],
			'requires'          => '6.0',
			'requires_php'      => '8.1',
			'tested'            => '6.5',
			'donate_link'       => '',
			'contributors'      => [],
			'tags'              => [],
			'remaining_content' => '',
		];

		$result = $this->api->set_readme_info( $readme );

		$this->assertTrue( $result );
		$this->assertSame( '6.0', $this->type->requires );
		$this->assertSame( '8.1', $this->type->requires_php );
	}

	/**
	 * set_readme_info() with an empty sections array must return true and
	 * not throw — sections simply remain empty.
	 */
	public function test_set_readme_info_with_empty_sections(): void {
		$this->type->sections = new stdClass();
		$this->type->requires = '';
		$this->type->requires_php = '';
		$this->type->tested   = '';

		$readme = [
			'sections'          => [],
			'requires'          => '',
			'requires_php'      => '',
			'tested'            => '',
			'donate_link'       => '',
			'contributors'      => [],
			'tags'              => [],
			'remaining_content' => '',
		];

		$result = $this->api->set_readme_info( $readme );
		$this->assertTrue( $result );
	}
}
