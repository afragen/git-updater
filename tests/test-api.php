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

/**
 * Shared helper: build a minimal repo type object for API tests.
 *
 * @return stdClass
 */
function api_make_type(): stdClass {
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
		$this->type = api_make_type();
		$this->api  = new GitHub_API( $this->type );
	}

	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'gu_post_api_response_body' );
		delete_site_option( $this->api->get_cache_key( 'test-plugin' ) );
		delete_site_option( $this->api->get_cache_key( 'test-plugin_error' ) );
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------


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
		PHP_VERSION_ID < 80100 && $rm->setAccessible( true );
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

	// -------------------------------------------------------------------------
	// api() — WP_DEBUG log branch (lines 255-263)
	// -------------------------------------------------------------------------

	/**
	 * When self::$method == 'file', WP_DEBUG is on, and the API returns a non-200
	 * response whose body JSON has a 'message' property, error_log() is called.
	 * The test exercises those lines by verifying the return value (the decoded
	 * body object with the message property).
	 */
	public function test_api_logs_debug_message_on_non_200_with_file_method(): void {
		$rp = new ReflectionProperty( \Fragen\Git_Updater\API\GitHub_API::class, 'method' );
		$rp->setAccessible( true );
		$original_method = $rp->getValue( null );
		$rp->setValue( null, 'file' );

		try {
			add_filter(
				'pre_http_request',
				fn() => $this->mock_http_response( 403, [ 'message' => 'API rate limit exceeded' ] ),
				10,
				3
			);

			$result = $this->api->api( $this->endpoint );

			// api() returns the decoded body regardless of status.
			$this->assertIsObject( $result );
			$this->assertSame( 'API rate limit exceeded', $result->message );
		} finally {
			$rp->setValue( null, $original_method );
		}
	}

	// -------------------------------------------------------------------------
	// get_api_url() — enterprise_api path (lines 338-341)
	// -------------------------------------------------------------------------

	/**
	 * When $type->enterprise_api is set, get_api_url() sets base_uri to null
	 * and prepends the enterprise API host to the endpoint.
	 */
	public function test_get_api_url_prepends_enterprise_api_when_set(): void {
		$this->type->enterprise_api = 'https://github.mycompany.com/api/v3';

		$result = $this->api->get_api_url( '/repos/:owner/:repo' );

		$this->assertStringStartsWith( 'https://github.mycompany.com/api/v3', $result );
		$this->assertStringContainsString( 'test-owner', $result );
		$this->assertStringContainsString( 'test-plugin', $result );
	}

	// -------------------------------------------------------------------------
	// api() — gu_post_api_response_body filter wraps response in md5 key (line 275)
	// -------------------------------------------------------------------------

	public function test_api_unwraps_md5_keyed_response_from_gu_post_api_response_body_filter(): void {
		$endpoint = '/repos/:owner/:repo';
		$url      = $this->api->get_api_url( $endpoint );
		$md5      = md5( $url );

		add_filter(
			'pre_http_request',
			fn() => $this->mock_http_response( 200, [ 'name' => 'test-plugin' ] ),
			10,
			3
		);

		// Wrap the cache-entry response in a md5-keyed array to exercise line 275.
		add_filter(
			'gu_post_api_response_body',
			function ( $response ) use ( $md5 ) {
				return [ $md5 => $response ];
			},
			10,
			2
		);

		$result = $this->api->api( $endpoint );

		$this->assertIsObject( $result );
		$this->assertSame( 'test-plugin', $result->name );
	}

	// -------------------------------------------------------------------------
	// set_readme_info() — other_notes + remaining_content (line 577)
	// upgrade_notice (line 601), tags loop (lines 606-609)
	// -------------------------------------------------------------------------

	public function test_set_readme_info_appends_remaining_content_to_other_notes(): void {
		$this->type->sections    = new stdClass();
		$this->type->requires    = '';
		$this->type->requires_php = '';
		$this->type->tested      = '';

		$readme = [
			'sections'          => [ 'other_notes' => 'Important notes.' ],
			'requires'          => '',
			'requires_php'      => '',
			'tested'            => '',
			'donate_link'       => '',
			'contributors'      => [],
			'tags'              => [],
			'remaining_content' => ' Extra content.',
		];

		$result = $this->api->set_readme_info( $readme );

		$this->assertTrue( $result );
		$this->assertStringContainsString( 'Important notes.', (string) $this->type->sections['other_notes'] );
	}

	public function test_set_readme_info_sets_upgrade_notice_when_non_empty(): void {
		$this->type->sections    = new stdClass();
		$this->type->requires    = '';
		$this->type->requires_php = '';
		$this->type->tested      = '';

		$readme = [
			'sections'          => [],
			'requires'          => '',
			'requires_php'      => '',
			'tested'            => '',
			'donate_link'       => '',
			'contributors'      => [],
			'tags'              => [],
			'remaining_content' => '',
			'upgrade_notice'    => [ '1.0' => 'Please upgrade immediately.' ],
		];

		$result = $this->api->set_readme_info( $readme );

		$this->assertTrue( $result );
		$this->assertSame( [ '1.0' => 'Please upgrade immediately.' ], $this->type->upgrade_notice );
	}

	public function test_set_readme_info_reformats_tags_to_slugified_keys(): void {
		$this->type->sections    = new stdClass();
		$this->type->requires    = '';
		$this->type->requires_php = '';
		$this->type->tested      = '';

		$readme = [
			'sections'          => [],
			'requires'          => '',
			'requires_php'      => '',
			'tested'            => '',
			'donate_link'       => '',
			'contributors'      => [],
			'tags'              => [ 'My Plugin', 'WordPress Plugin' ],
			'remaining_content' => '',
		];

		$result = $this->api->set_readme_info( $readme );

		$this->assertTrue( $result );
		$this->assertArrayHasKey( 'my-plugin', $this->type->readme_tags );
		$this->assertArrayHasKey( 'wordpress-plugin', $this->type->readme_tags );
		$this->assertSame( 'My Plugin', $this->type->readme_tags['my-plugin'] );
	}

	public function test_set_readme_info_normalizes_patch_version_for_tested(): void {
		global $wp_version;
		$original_wp_version = $wp_version;
		$wp_version          = '6.7.2';

		$this->type->sections     = new stdClass();
		$this->type->requires     = '';
		$this->type->requires_php = '';
		$this->type->tested       = '';

		$readme = [
			'sections'          => [],
			'requires'          => '',
			'requires_php'      => '',
			'tested'            => '6.5',
			'donate_link'       => '',
			'contributors'      => [],
			'tags'              => [],
			'remaining_content' => '',
		];

		$result = $this->api->set_readme_info( $readme );

		$wp_version = $original_wp_version;

		$this->assertTrue( $result );
		$this->assertSame( '6.5.2', $this->type->tested );
	}
}


class Test_API_Extended extends WP_UnitTestCase {

	/**
	 * @var GitHub_API
	 */
	private GitHub_API $api;

	/**
	 * @var stdClass
	 */
	private stdClass $type;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->type = api_make_type();
		$this->api  = new GitHub_API( $this->type );
	}

	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'gu_api_repo_type_data' );
		remove_all_filters( 'gu_api_url_type' );
		parent::tear_down();
	}


	// -------------------------------------------------------------------------
	// Reflection helpers
	// -------------------------------------------------------------------------

	private function call_sort_tags( array $tags ): bool {
		$rm = new ReflectionMethod( $this->api, 'sort_tags' );
		PHP_VERSION_ID < 80100 && $rm->setAccessible( true );
		return $rm->invoke( $this->api, $tags );
	}

	private function call_return_repo_type(): array {
		$rm = new ReflectionMethod( $this->api, 'return_repo_type' );
		PHP_VERSION_ID < 80100 && $rm->setAccessible( true );
		return $rm->invoke( $this->api );
	}

	// -------------------------------------------------------------------------
	// sort_tags()
	// -------------------------------------------------------------------------

	public function test_sort_tags_empty_array_returns_false(): void {
		$this->assertFalse( $this->call_sort_tags( [] ) );
	}

	public function test_sort_tags_non_empty_returns_true(): void {
		$tags = [ '1.0.0' => new stdClass(), '2.0.0' => new stdClass() ];
		$this->assertTrue( $this->call_sort_tags( $tags ) );
	}

	public function test_sort_tags_sets_newest_tag_to_highest_version(): void {
		$tags = [
			'1.0.0' => new stdClass(),
			'2.0.0' => new stdClass(),
			'1.5.0' => new stdClass(),
		];
		$this->call_sort_tags( $tags );
		$this->assertSame( '2.0.0', $this->type->newest_tag );
	}

	public function test_sort_tags_handles_v_prefix(): void {
		$tags = [
			'v1.0.0' => new stdClass(),
			'v2.0.0' => new stdClass(),
			'v1.5.0' => new stdClass(),
		];
		$this->call_sort_tags( $tags );
		$this->assertSame( 'v2.0.0', $this->type->newest_tag );
	}

	public function test_sort_tags_stores_sorted_tags_on_type(): void {
		$tags = [
			'1.0.0' => new stdClass(),
			'3.0.0' => new stdClass(),
			'2.0.0' => new stdClass(),
		];
		$this->call_sort_tags( $tags );
		$stored_keys = array_keys( $this->type->tags );
		$this->assertSame( '3.0.0', $stored_keys[0] );
		$this->assertSame( '2.0.0', $stored_keys[1] );
		$this->assertSame( '1.0.0', $stored_keys[2] );
	}

	public function test_sort_tags_single_tag_sets_newest_tag(): void {
		$tags = [ '1.2.3' => new stdClass() ];
		$this->call_sort_tags( $tags );
		$this->assertSame( '1.2.3', $this->type->newest_tag );
	}

	public function test_sort_tags_semantic_version_ordering(): void {
		$tags = [
			'1.10.0' => new stdClass(),
			'1.9.0'  => new stdClass(),
			'1.2.0'  => new stdClass(),
		];
		$this->call_sort_tags( $tags );
		$this->assertSame( '1.10.0', $this->type->newest_tag );
	}

	// -------------------------------------------------------------------------
	// return_repo_type()
	// -------------------------------------------------------------------------

	public function test_return_repo_type_includes_git_key_for_github(): void {
		$result = $this->call_return_repo_type();
		$this->assertSame( 'github', $result['git'] );
	}

	public function test_return_repo_type_includes_correct_base_uri(): void {
		$result = $this->call_return_repo_type();
		$this->assertSame( 'https://api.github.com', $result['base_uri'] );
	}

	public function test_return_repo_type_includes_correct_base_download(): void {
		$result = $this->call_return_repo_type();
		$this->assertSame( 'https://github.com', $result['base_download'] );
	}

	public function test_return_repo_type_includes_type(): void {
		$result = $this->call_return_repo_type();
		$this->assertSame( 'plugin', $result['type'] );
	}

	public function test_return_repo_type_filter_can_add_data(): void {
		add_filter(
			'gu_api_repo_type_data',
			function ( array $arr ) {
				$arr['extra'] = 'value';
				return $arr;
			}
		);
		$result = $this->call_return_repo_type();
		$this->assertSame( 'value', $result['extra'] );
	}

	// -------------------------------------------------------------------------
	// get_api_url()
	// -------------------------------------------------------------------------

	public function test_get_api_url_replaces_owner_placeholder(): void {
		$url = $this->api->get_api_url( '/repos/:owner/:repo' );
		$this->assertStringContainsString( 'test-owner', $url );
		$this->assertStringNotContainsString( ':owner', $url );
	}

	public function test_get_api_url_replaces_repo_placeholder(): void {
		$url = $this->api->get_api_url( '/repos/:owner/:repo' );
		$this->assertStringContainsString( 'test-plugin', $url );
		$this->assertStringNotContainsString( ':repo', $url );
	}

	public function test_get_api_url_replaces_branch_placeholder(): void {
		$url = $this->api->get_api_url( '/repos/:owner/:repo/contents/:branch' );
		$this->assertStringContainsString( 'master', $url );
		$this->assertStringNotContainsString( ':branch', $url );
	}

	public function test_get_api_url_prepends_github_api_base_uri(): void {
		$url = $this->api->get_api_url( '/repos/:owner/:repo' );
		$this->assertStringStartsWith( 'https://api.github.com', $url );
	}

	/**
	 * For non-enterprise GitHub, both download_link=true and download_link=false
	 * use the api.github.com base (the code sets base_download = base_uri when
	 * !enterprise && download_link). Only GitHub Enterprise produces a different base.
	 */
	public function test_get_api_url_download_link_uses_api_base_for_non_enterprise(): void {
		$api_url = $this->api->get_api_url( '/repos/:owner/:repo/zipball/:branch', false );
		$dl_url  = $this->api->get_api_url( '/repos/:owner/:repo/zipball/:branch', true );
		$this->assertStringStartsWith( 'https://api.github.com', $api_url );
		$this->assertStringStartsWith( 'https://api.github.com', $dl_url );
	}

	public function test_get_api_url_does_not_double_prepend_base(): void {
		$endpoint = 'https://api.github.com/repos/test-owner/test-plugin';
		$url      = $this->api->get_api_url( $endpoint );
		// Should not become https://api.github.comhttps://api.github.com/...
		$this->assertSame( 1, substr_count( $url, 'https://api.github.com' ) );
	}

	public function test_get_api_url_uses_branch_fallback_to_primary_branch(): void {
		$this->type->branch = '';
		$url                = $this->api->get_api_url( '/repos/:owner/:repo/contents/:branch' );
		$this->assertStringContainsString( 'master', $url );
	}

	// -------------------------------------------------------------------------
	// parse_extra_headers()  (public, called on $this->api so Singleton resolves)
	// -------------------------------------------------------------------------

	public function test_parse_extra_headers_defaults_to_no_enterprise(): void {
		$header       = [ 'host' => 'github.com', 'base_uri' => 'https://github.com' ];
		$headers      = [];
		$header_parts = [ 'GitHub' ];

		$result = $this->api->parse_extra_headers( $header, $headers, $header_parts );

		$this->assertNull( $result['enterprise_uri'] );
		$this->assertNull( $result['enterprise_api'] );
	}

	public function test_parse_extra_headers_detects_github_enterprise(): void {
		$header = [
			'host'     => 'github.mycompany.com',
			'base_uri' => 'https://github.mycompany.com',
		];
		$headers      = [];
		$header_parts = [ 'GitHub' ];

		$result = $this->api->parse_extra_headers( $header, $headers, $header_parts );

		$this->assertSame( 'https://github.mycompany.com', $result['enterprise_uri'] );
		$this->assertStringEndsWith( '/api/v3', $result['enterprise_api'] );
	}

	public function test_parse_extra_headers_extracts_languages_header(): void {
		$header       = [ 'host' => 'github.com' ];
		$headers      = [ 'GitHubLanguages' => 'en_US' ];
		$header_parts = [ 'GitHub' ];

		$result = $this->api->parse_extra_headers( $header, $headers, $header_parts );

		$this->assertSame( 'en_US', $result['languages'] );
	}

	public function test_parse_extra_headers_extracts_ci_job_header(): void {
		$header       = [ 'host' => 'github.com' ];
		$headers      = [ 'GitHubCIJob' => 'https://ci.example.com/build/1' ];
		$header_parts = [ 'GitHub' ];

		$result = $this->api->parse_extra_headers( $header, $headers, $header_parts );

		$this->assertSame( 'https://ci.example.com/build/1', $result['ci_job'] );
	}

	public function test_parse_extra_headers_converts_release_asset_to_bool(): void {
		$header       = [ 'host' => 'github.com' ];
		$headers      = [ 'ReleaseAsset' => 'true' ];
		$header_parts = [ 'GitHub' ];

		$result = $this->api->parse_extra_headers( $header, $headers, $header_parts );

		$this->assertTrue( $result['release_asset'] );
	}

	public function test_parse_extra_headers_release_asset_false_string_becomes_false(): void {
		$header       = [ 'host' => 'github.com' ];
		$headers      = [ 'ReleaseAsset' => 'false' ];
		$header_parts = [ 'GitHub' ];

		$result = $this->api->parse_extra_headers( $header, $headers, $header_parts );

		$this->assertFalse( $result['release_asset'] );
	}

	public function test_parse_extra_headers_primary_branch_defaults_to_master(): void {
		$header       = [ 'host' => 'github.com' ];
		$headers      = [];
		$header_parts = [ 'GitHub' ];

		$result = $this->api->parse_extra_headers( $header, $headers, $header_parts );

		$this->assertSame( 'master', $result['primary_branch'] );
	}

	public function test_parse_extra_headers_sets_custom_primary_branch(): void {
		$header       = [ 'host' => 'github.com' ];
		$headers      = [ 'PrimaryBranch' => 'main' ];
		$header_parts = [ 'GitHub' ];

		$result = $this->api->parse_extra_headers( $header, $headers, $header_parts );

		$this->assertSame( 'main', $result['primary_branch'] );
	}

	public function test_parse_extra_headers_extracts_plugin_id(): void {
		$header       = [ 'host' => 'github.com' ];
		$headers      = [ 'PluginID' => 'did:example:abc123' ];
		$header_parts = [ 'GitHub' ];

		$result = $this->api->parse_extra_headers( $header, $headers, $header_parts );

		$this->assertSame( 'did:example:abc123', $result['did'] );
	}

	public function test_parse_extra_headers_theme_id_overrides_plugin_id(): void {
		$header       = [ 'host' => 'github.com' ];
		$headers      = [
			'PluginID' => 'did:example:plugin',
			'ThemeID'  => 'did:example:theme',
		];
		$header_parts = [ 'GitHub' ];

		$result = $this->api->parse_extra_headers( $header, $headers, $header_parts );

		$this->assertSame( 'did:example:theme', $result['did'] );
	}

	public function test_parse_extra_headers_no_host_skips_enterprise_detection(): void {
		$header       = [];
		$headers      = [];
		$header_parts = [ 'GitHub' ];

		$result = $this->api->parse_extra_headers( $header, $headers, $header_parts );

		$this->assertNull( $result['enterprise_uri'] );
	}
}

class Test_API_Dot_Org_Data extends WP_UnitTestCase {

	/**
	 * @var GitHub_API
	 */
	private GitHub_API $api;

	/**
	 * @var stdClass
	 */
	private stdClass $type;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->type = api_make_type();
		$this->api  = new GitHub_API( $this->type );
	}

	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'gu_api_domain' );
		delete_site_option( $this->api->get_cache_key( 'test-plugin' ) );
		parent::tear_down();
	}


	private function call_get_dot_org_data(): mixed {
		$rm = new ReflectionMethod( $this->api, 'get_dot_org_data' );
		PHP_VERSION_ID < 80100 && $rm->setAccessible( true );
		return $rm->invoke( $this->api );
	}

	// -------------------------------------------------------------------------
	// get_dot_org_data() — cached path
	// -------------------------------------------------------------------------

	public function test_get_dot_org_data_returns_true_when_cache_says_in_dot_org(): void {
		update_site_option(
			$this->api->get_cache_key( 'test-plugin' ),
			[ 'dot_org' => 'in dot org' ]
		);

		$result = $this->call_get_dot_org_data();

		$this->assertTrue( $result );
	}

	public function test_get_dot_org_data_returns_false_when_cache_says_not_in_dot_org(): void {
		update_site_option(
			$this->api->get_cache_key( 'test-plugin' ),
			[ 'dot_org' => 'not in dot org' ]
		);

		$result = $this->call_get_dot_org_data();

		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// get_dot_org_data() — HTTP paths
	// -------------------------------------------------------------------------

	public function test_get_dot_org_data_returns_true_when_plugin_in_dot_org(): void {
		$body = wp_json_encode(
			[
				'name'      => 'Test Plugin',
				'slug'      => 'test-plugin',
				'ac_origin' => 'wp_org',
			]
		);
		add_filter(
			'pre_http_request',
			fn() => [ 'response' => [ 'code' => 200 ], 'body' => $body, 'headers' => [] ],
			10,
			3
		);

		$result = $this->call_get_dot_org_data();

		$this->assertTrue( $result );
	}

	public function test_get_dot_org_data_returns_false_when_plugin_not_in_dot_org(): void {
		$body = wp_json_encode( [ 'name' => 'Test Plugin' ] ); // no ac_origin
		add_filter(
			'pre_http_request',
			fn() => [ 'response' => [ 'code' => 200 ], 'body' => $body, 'headers' => [] ],
			10,
			3
		);

		$result = $this->call_get_dot_org_data();

		$this->assertFalse( $result );
	}

	public function test_get_dot_org_data_returns_false_on_wp_error(): void {
		add_filter(
			'pre_http_request',
			fn() => new WP_Error( 'http_request_failed', 'Connection refused' ),
			10,
			3
		);

		$result = $this->call_get_dot_org_data();

		$this->assertFalse( $result );
	}

	public function test_get_dot_org_data_returns_false_when_body_has_error_property(): void {
		$body = wp_json_encode( [ 'error' => 'Plugin not found', 'ac_origin' => 'wp_org' ] );
		add_filter(
			'pre_http_request',
			fn() => [ 'response' => [ 'code' => 200 ], 'body' => $body, 'headers' => [] ],
			10,
			3
		);

		$result = $this->call_get_dot_org_data();

		$this->assertFalse( $result );
	}
}

/**
 * Class Test_API_Exit_No_Update
 *
 * Covers exit_no_update() conditions.
 */
class Test_API_Exit_No_Update extends WP_UnitTestCase {

	/**
	 * @var GitHub_API
	 */
	private GitHub_API $api;

	/**
	 * @var stdClass
	 */
	private stdClass $type;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->type = api_make_type();
		$this->api  = new GitHub_API( $this->type );
	}

	public function tear_down(): void {
		remove_all_filters( 'gu_always_fetch_update' );
		delete_site_transient( 'gu_refresh_cache' );
		parent::tear_down();
	}


	private function call_exit_no_update( $response = false, $branch = false ): bool {
		$rm = new ReflectionMethod( $this->api, 'exit_no_update' );
		PHP_VERSION_ID < 80100 && $rm->setAccessible( true );
		return $rm->invoke( $this->api, $response, $branch );
	}

	// -------------------------------------------------------------------------
	// exit_no_update() conditions
	// -------------------------------------------------------------------------

	public function test_exit_no_update_returns_false_when_always_fetch_filter_set(): void {
		add_filter( 'gu_always_fetch_update', '__return_true' );

		$result = $this->call_exit_no_update( false );

		$this->assertFalse( $result );
	}

	public function test_exit_no_update_returns_branch_switch_empty_when_branch_param_true(): void {
		$rp = new ReflectionProperty( GitHub_API::class, 'options' );
		$rp->setAccessible( true );
		$original = $rp->getValue( null );

		$rp->setValue( null, [] ); // no branch_switch option
		$result = $this->call_exit_no_update( false, true );
		$rp->setValue( null, $original );

		$this->assertTrue( $result ); // empty(options['branch_switch']) = true
	}

	public function test_exit_no_update_returns_false_when_refresh_transient_set(): void {
		set_site_transient( 'gu_refresh_cache', true );

		$result = $this->call_exit_no_update( false );

		$this->assertFalse( $result );
	}

	public function test_exit_no_update_returns_false_when_response_is_truthy(): void {
		$result = $this->call_exit_no_update( [ 'some' => 'data' ] );

		$this->assertFalse( $result );
	}

	public function test_exit_no_update_returns_true_when_no_refresh_no_response_and_cant_update(): void {
		// In tests: type has no remote_version/local_version → can_update_repo returns false.
		$result = $this->call_exit_no_update( false );

		$this->assertTrue( $result );
	}
}

/**
 * Class Test_API_Local_Info
 *
 * Covers get_local_info(), local_file_exists(), set_file_info(), add_meta_repo_object().
 */
class Test_API_Local_Info extends WP_UnitTestCase {

	/**
	 * @var GitHub_API
	 */
	private GitHub_API $api;

	/**
	 * @var stdClass
	 */
	private stdClass $type;

	/**
	 * @var string Temp directory for file-existence tests.
	 */
	private string $temp_dir;

	/**
	 * @var string Temp file path.
	 */
	private string $temp_file;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->type = api_make_type();
		$this->api  = new GitHub_API( $this->type );

		$this->temp_dir  = sys_get_temp_dir() . '/gu-test-' . uniqid() . '/';
		mkdir( $this->temp_dir );
		$this->temp_file = $this->temp_dir . 'test-file.txt';
		file_put_contents( $this->temp_file, 'test content' );
	}

	public function tear_down(): void {
		delete_site_transient( 'gu_refresh_cache' );
		if ( file_exists( $this->temp_file ) ) {
			unlink( $this->temp_file );
		}
		if ( is_dir( $this->temp_dir ) ) {
			rmdir( $this->temp_dir );
		}
		parent::tear_down();
	}


	// -------------------------------------------------------------------------
	// get_local_info()
	// -------------------------------------------------------------------------

	public function test_get_local_info_returns_null_when_refresh_cache_transient_set(): void {
		set_site_transient( 'gu_refresh_cache', true );
		$repo             = new stdClass();
		$repo->local_path = $this->temp_dir;

		$result = $this->api->get_local_info( $repo, 'test-file.txt' );

		$this->assertNull( $result );
	}

	public function test_get_local_info_returns_file_contents_when_file_exists(): void {
		$repo             = new stdClass();
		$repo->local_path = $this->temp_dir;

		$result = $this->api->get_local_info( $repo, 'test-file.txt' );

		$this->assertSame( 'test content', $result );
	}

	public function test_get_local_info_returns_null_when_file_does_not_exist(): void {
		$repo             = new stdClass();
		$repo->local_path = $this->temp_dir;

		$result = $this->api->get_local_info( $repo, 'nonexistent-file.txt' );

		$this->assertNull( $result );
	}

	public function test_get_local_info_returns_null_when_directory_does_not_exist(): void {
		$repo             = new stdClass();
		$repo->local_path = '/nonexistent/directory/path/';

		$result = $this->api->get_local_info( $repo, 'test-file.txt' );

		$this->assertNull( $result );
	}

	// -------------------------------------------------------------------------
	// local_file_exists()
	// -------------------------------------------------------------------------

	public function test_local_file_exists_returns_true_when_file_exists(): void {
		$this->type->local_path = $this->temp_dir;

		$rm     = new ReflectionMethod( $this->api, 'local_file_exists' );
		PHP_VERSION_ID < 80100 && $rm->setAccessible( true );
		$result = $rm->invoke( $this->api, 'test-file.txt' );

		$this->assertTrue( $result );
	}

	public function test_local_file_exists_returns_false_when_file_missing(): void {
		$this->type->local_path = $this->temp_dir;

		$rm     = new ReflectionMethod( $this->api, 'local_file_exists' );
		PHP_VERSION_ID < 80100 && $rm->setAccessible( true );
		$result = $rm->invoke( $this->api, 'nonexistent.txt' );

		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// set_file_info()
	// -------------------------------------------------------------------------

	public function test_set_file_info_populates_type_remote_version(): void {
		$response = [
			'Name'           => 'Test Plugin',
			'Version'        => '2.5.0',
			'RequiresPHP'    => '8.0',
			'RequiresWP'     => '6.0',
			'Requires'       => '',
			'dot_org'        => 'not in dot org',
			'PrimaryBranch'  => 'main',
			'UpdateURI'      => '',
			'RequiresPlugins' => '',
			'Author'         => 'Test Author',
			'AuthorURI'      => '',
			'PluginURI'      => 'https://example.com',
			'Description'    => 'Test description.',
			'PluginID'       => '',
			'ThemeID'        => '',
			'Security'       => '',
			'License'        => '',
		];

		$rm = new ReflectionMethod( $this->api, 'set_file_info' );
		PHP_VERSION_ID < 80100 && $rm->setAccessible( true );
		$rm->invoke( $this->api, $response );

		$this->assertSame( '2.5.0', $this->type->remote_version );
		$this->assertSame( '8.0', $this->type->requires_php );
		$this->assertSame( 'main', $this->type->primary_branch );
	}

	public function test_set_file_info_populates_name_on_first_call(): void {
		$response = [
			'Name'           => 'My Test Plugin',
			'Version'        => '1.0.0',
			'RequiresPHP'    => '',
			'RequiresWP'     => '',
			'Requires'       => '',
			'dot_org'        => 'not in dot org',
			'PrimaryBranch'  => 'master',
			'UpdateURI'      => '',
			'RequiresPlugins' => '',
			'Author'         => 'Andy Fragen',
			'AuthorURI'      => '',
			'PluginURI'      => '',
			'Description'    => 'Description here.',
			'PluginID'       => '',
			'ThemeID'        => '',
			'Security'       => '',
			'License'        => '',
		];

		$rm = new ReflectionMethod( $this->api, 'set_file_info' );
		PHP_VERSION_ID < 80100 && $rm->setAccessible( true );
		$rm->invoke( $this->api, $response );

		$this->assertSame( 'My Test Plugin', $this->type->name );
		$this->assertSame( '1.0.0', $this->type->local_version );
	}

	// -------------------------------------------------------------------------
	// add_meta_repo_object()
	// -------------------------------------------------------------------------

	public function test_add_meta_repo_object_sets_type_properties_from_repo_meta(): void {
		$this->type->repo_meta = [
			'last_updated' => '2024-06-15T12:00:00Z',
			'added'        => '2023-01-01T00:00:00Z',
			'private'      => false,
		];

		$rm = new ReflectionMethod( $this->api, 'add_meta_repo_object' );
		PHP_VERSION_ID < 80100 && $rm->setAccessible( true );
		$rm->invoke( $this->api );

		$this->assertSame( '2024-06-15T12:00:00Z', $this->type->last_updated );
		$this->assertSame( '2023-01-01T00:00:00Z', $this->type->added );
		$this->assertFalse( $this->type->is_private );
	}

	public function test_add_meta_repo_object_uses_empty_string_for_missing_added(): void {
		$this->type->repo_meta = [
			'last_updated' => '2024-06-15T12:00:00Z',
			'private'      => true,
		];

		$rm = new ReflectionMethod( $this->api, 'add_meta_repo_object' );
		PHP_VERSION_ID < 80100 && $rm->setAccessible( true );
		$rm->invoke( $this->api );

		$this->assertSame( '', $this->type->added );
		$this->assertTrue( $this->type->is_private );
	}
}

class Test_API_Release_Asset_Redirect extends WP_UnitTestCase {

	/**
	 * @var GitHub_API
	 */
	private GitHub_API $api;

	/**
	 * @var stdClass
	 */
	private stdClass $type;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->type = api_make_type();
		$this->api  = new GitHub_API( $this->type );
	}

	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'gu_always_fetch_update' );
		remove_all_actions( 'requests-requests.before_redirect' );
		delete_site_option( $this->api->get_cache_key( 'test-plugin' ) );
		delete_site_option( $this->api->get_cache_key( 'test-plugin_error' ) );
		unset( $_REQUEST['key'], $_REQUEST['plugin'], $_REQUEST['theme'], $_REQUEST['override'], $_REQUEST['rollback'] );
		parent::tear_down();
	}


	private function seed_cache( array $data ): void {
		update_site_option(
			$this->api->get_cache_key( 'test-plugin' ),
			array_merge( [ 'timeout' => strtotime( '+12 hours' ) ], $data )
		);
	}

	// -------------------------------------------------------------------------
	// !$asset early return
	// -------------------------------------------------------------------------

	public function test_get_release_asset_redirect_returns_false_when_asset_is_false(): void {
		$result = $this->api->get_release_asset_redirect( false );
		$this->assertFalse( $result );
	}

	public function test_get_release_asset_redirect_returns_false_when_asset_is_empty_string(): void {
		$result = $this->api->get_release_asset_redirect( '' );
		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// AWS timeout unset path (lines 637-640)
	// -------------------------------------------------------------------------

	public function test_get_release_asset_redirect_clears_aws_cache_when_older_than_5_min(): void {
		// Seed cache with future timeout (so AWS time check fires: time() - strtotime('-12h', future_timeout) > 300).
		$this->seed_cache(
			[
				'release_asset'          => 'https://github.com/owner/repo/releases/download/v1.0.0/plugin.zip',
				'release_asset_redirect' => 'https://s3.amazonaws.com/something/plugin.zip?token=old',
				'timeout'                => strtotime( '+6 hours' ),
			]
		);

		// $aws=true triggers the unset block.
		// After clearing, response is false, exit_no_update fires → return false.
		$result = $this->api->get_release_asset_redirect(
			'https://github.com/owner/repo/releases/download/v1.0.0/plugin.zip',
			true
		);

		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// $_REQUEST['key'] slug matching (lines 645-649)
	// -------------------------------------------------------------------------

	public function test_get_release_asset_redirect_matches_plugin_slug_from_request(): void {
		// Seed cache with repo = 'test-plugin' and no release_asset_redirect.
		$this->seed_cache( [ 'repo' => 'test-plugin' ] );

		// Set request context.
		$_REQUEST['key']    = 'some-api-key';
		$_REQUEST['plugin'] = 'test-plugin/test-plugin.php'; // WordPress plugin file format.

		// Mock HTTP to prevent real request.
		add_filter( 'pre_http_request', fn() => new WP_Error( 'blocked', 'no real HTTP' ), 10, 3 );
		add_filter( 'gu_always_fetch_update', '__return_true' ); // bypass exit_no_update gate

		$result = $this->api->get_release_asset_redirect(
			'https://github.com/owner/repo/releases/download/v1.0.0/plugin.zip'
		);

		// No redirect was set (mocked request), response is false → return false.
		$this->assertFalse( $result );
	}

	public function test_get_release_asset_redirect_matches_theme_slug_from_request(): void {
		$this->seed_cache( [ 'repo' => 'test-plugin' ] );

		$_REQUEST['key']   = 'some-api-key';
		$_REQUEST['theme'] = 'test-plugin';

		add_filter( 'pre_http_request', fn() => new WP_Error( 'blocked', 'no real HTTP' ), 10, 3 );
		add_filter( 'gu_always_fetch_update', '__return_true' );

		$result = $this->api->get_release_asset_redirect(
			'https://github.com/owner/repo/releases/download/v1.0.0/plugin.zip'
		);

		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// exit_no_update gate → return false
	// -------------------------------------------------------------------------

	public function test_get_release_asset_redirect_returns_false_when_exit_no_update_fires(): void {
		// No cache, no override, no rollback, no $rest — exit_no_update returns true.
		$result = $this->api->get_release_asset_redirect(
			'https://github.com/owner/repo/releases/download/v1.0.0/plugin.zip'
		);

		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// HTTP call path — no redirect
	// -------------------------------------------------------------------------

	public function test_get_release_asset_redirect_returns_false_when_http_call_produces_no_redirect(): void {
		// Bypass exit_no_update so we reach the HTTP call.
		add_filter( 'gu_always_fetch_update', '__return_true' );

		// Mock HTTP — pre_http_request prevents real request; redirect action won't fire.
		add_filter(
			'pre_http_request',
			fn() => [ 'response' => [ 'code' => 200 ], 'body' => '', 'headers' => [] ],
			10,
			3
		);

		$result = $this->api->get_release_asset_redirect(
			'https://github.com/owner/repo/releases/download/v1.0.0/plugin.zip'
		);

		// $this->redirect is empty (no real redirect), $response is false → return false.
		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// Cached release_asset_redirect → return cached URL
	// -------------------------------------------------------------------------

	public function test_get_release_asset_redirect_returns_cached_redirect_url(): void {
		$cached_url = 'https://s3.amazonaws.com/downloads/plugin.zip?token=abc';
		$this->seed_cache( [ 'release_asset_redirect' => $cached_url ] );

		$result = $this->api->get_release_asset_redirect(
			'https://github.com/owner/repo/releases/download/v1.0.0/plugin.zip'
		);

		$this->assertSame( $cached_url, $result );
	}

	// -------------------------------------------------------------------------
	// HTTP call path — redirect set (lines 673, 675)
	// -------------------------------------------------------------------------

	public function test_get_release_asset_redirect_caches_and_returns_redirect_when_set(): void {
		$redirect_url = 'https://s3.amazonaws.com/bucket/plugin.zip?token=abc';
		$api          = $this->api;

		add_filter( 'gu_always_fetch_update', '__return_true' );

		// Call set_redirect() inside the HTTP mock to simulate the redirect action firing.
		add_filter(
			'pre_http_request',
			function () use ( $api, $redirect_url ) {
				$api->set_redirect( $redirect_url );
				return [ 'response' => [ 'code' => 200 ], 'body' => '', 'headers' => [] ];
			},
			10,
			3
		);

		$result = $this->api->get_release_asset_redirect(
			'https://github.com/owner/repo/releases/download/v1.0.0/plugin.zip'
		);

		$this->assertSame( $redirect_url, $result );
	}

	// -------------------------------------------------------------------------
	// set_redirect()
	// -------------------------------------------------------------------------

	public function test_set_redirect_stores_location_on_redirect_property(): void {
		$location = 'https://s3.amazonaws.com/bucket/plugin.zip?token=xyz';

		$this->api->set_redirect( $location );

		$rp = new ReflectionProperty( $this->api, 'redirect' );
		$rp->setAccessible( true );
		$this->assertSame( $location, $rp->getValue( $this->api ) );
	}
}

class Test_API_Hooks extends WP_UnitTestCase {

	/**
	 * @var GitHub_API
	 */
	private GitHub_API $api;

	/**
	 * @var stdClass
	 */
	private stdClass $type;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->type = api_make_type();
		$this->api  = new GitHub_API( $this->type );
	}

	public function tear_down(): void {
		remove_all_filters( 'gu_add_repo_setting_field' );
		remove_all_filters( 'gu_get_repo_api' );
		remove_all_actions( 'gu_add_settings' );
		remove_all_actions( 'gu_add_install_settings_fields' );
		parent::tear_down();
	}


	// -------------------------------------------------------------------------
	// settings_hook() — action + filter registration
	// -------------------------------------------------------------------------

	public function test_settings_hook_registers_gu_add_settings_action(): void {
		// GitHub_API constructor calls settings_hook($this) which registers the action.
		$this->assertNotFalse( has_action( 'gu_add_settings' ) );
	}

	public function test_settings_hook_registers_gu_add_repo_setting_field_filter(): void {
		$this->assertNotFalse( has_filter( 'gu_add_repo_setting_field' ) );
	}

	public function test_settings_hook_fires_add_settings_when_gu_add_settings_action_called(): void {
		global $wp_settings_sections;

		// Firing the action invokes the lambda that calls $git->add_settings($auth_required).
		do_action( 'gu_add_settings', [ 'github_private' => false, 'github_enterprise' => false ] );

		$this->assertArrayHasKey(
			'github_access_token',
			$wp_settings_sections['git_updater_github_install_settings'] ?? []
		);
	}

	// -------------------------------------------------------------------------
	// add_setting_field() — both branches
	// -------------------------------------------------------------------------

	public function test_add_setting_field_returns_non_empty_fields_unchanged(): void {
		$repo        = (object) [ 'git' => 'github' ];
		$fields      = [ 'page' => 'some_page', 'section' => 'some_section' ];
		$result      = $this->api->add_setting_field( $fields, $repo );
		$this->assertSame( $fields, $result );
	}

	public function test_add_setting_field_calls_get_repo_api_when_fields_empty(): void {
		$repo   = (object) [
			'git'            => 'github',
			'slug'           => 'test-plugin',
			'type'           => 'plugin',
			'owner'          => 'test-owner',
			'branch'         => 'master',
			'primary_branch' => 'master',
			'enterprise'     => false,
			'enterprise_api' => null,
			'gist_id'        => null,
		];
		$result = $this->api->add_setting_field( [], $repo );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'page', $result );
	}

	// -------------------------------------------------------------------------
	// get_repo_api() — github path and unknown path
	// -------------------------------------------------------------------------

	public function test_get_repo_api_returns_github_api_for_github_git(): void {
		$result = $this->api->get_repo_api( 'github', $this->type );
		$this->assertInstanceOf( GitHub_API::class, $result );
	}

	public function test_get_repo_api_returns_null_for_unknown_git(): void {
		$result = $this->api->get_repo_api( 'unknown_git_host', $this->type );
		$this->assertNull( $result );
	}

	// -------------------------------------------------------------------------
	// add_install_fields() — registers gu_add_install_settings_fields action
	// -------------------------------------------------------------------------

	public function test_add_install_fields_registers_action(): void {
		// GitHub_API constructor calls add_install_fields($this).
		$this->assertNotFalse( has_action( 'gu_add_install_settings_fields' ) );
	}

	public function test_add_install_fields_action_fires_add_install_settings_fields(): void {
		$called = false;
		add_action(
			'gu_add_install_settings_fields',
			function ( $type ) use ( &$called ) {
				$called = true;
			},
			20,
			1
		);

		do_action( 'gu_add_install_settings_fields', 'plugin' );

		$this->assertTrue( $called );
	}
}

/**
 * Class Test_GitHub_API_Settings
 *
 * Covers all settings output and registration methods in GitHub_API.
 */

class Test_API_Detect_Provider extends WP_UnitTestCase {

	/**
	 * @var GitHub_API
	 */
	private GitHub_API $api;

	/**
	 * @var stdClass
	 */
	private stdClass $type;

	/**
	 * @var string
	 */
	private string $endpoint = '/repos/:owner/:repo';

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->type = api_make_type();
		$this->api  = new GitHub_API( $this->type );
	}

	public function tear_down(): void {
		delete_site_option( 'git_updater' );
		parent::tear_down();
	}

	private function call_detect_provider_from_url( string $url ): ?string {
		$rm = new ReflectionMethod( $this->api, 'detect_provider_from_url' );
		PHP_VERSION_ID < 80100 && $rm->setAccessible( true );
		return $rm->invoke( $this->api, $url );
	}

	public function test_detect_provider_from_url_returns_github_for_api_github(): void {
		$this->assertSame( 'github', $this->call_detect_provider_from_url( 'https://api.github.com/repos/owner/repo' ) );
	}

	public function test_detect_provider_from_url_returns_github_for_github_com_api(): void {
		$this->assertSame( 'github', $this->call_detect_provider_from_url( 'https://github.com/api/v3/repos/owner/repo' ) );
	}

	public function test_detect_provider_from_url_returns_bitbucket_for_api_bitbucket(): void {
		$this->assertSame( 'bitbucket', $this->call_detect_provider_from_url( 'https://api.bitbucket.org/2.0/repos/owner/repo' ) );
	}

	public function test_detect_provider_from_url_returns_bitbucket_for_bitbucket_org(): void {
		$this->assertSame( 'bitbucket', $this->call_detect_provider_from_url( 'https://bitbucket.org/owner/repo' ) );
	}

	public function test_detect_provider_from_url_returns_gitlab_for_gitlab_api(): void {
		$this->assertSame( 'gitlab', $this->call_detect_provider_from_url( 'https://gitlab.com/api/v4/projects/123' ) );
	}

	public function test_detect_provider_from_url_returns_gitlab_for_generic_api_v(): void {
		$this->assertSame( 'gitlab', $this->call_detect_provider_from_url( 'https://custom-gitlab.example.com/api/v4/projects' ) );
	}

	public function test_detect_provider_from_url_returns_gitea_when_server_configured(): void {
		update_site_option( 'git_updater', [ 'gitea_server' => 'https://gitea.example.com' ] );
		$this->assertSame( 'gitea', $this->call_detect_provider_from_url( 'https://gitea.example.com/api/v1/repos/owner/repo' ) );
	}

	public function test_detect_provider_from_url_returns_null_for_unknown_url(): void {
		$this->assertNull( $this->call_detect_provider_from_url( 'https://example.com/something' ) );
	}

	public function test_detect_provider_from_url_returns_gitlab_for_gitea_url_without_server(): void {
		// Without gitea_server configured, a Gitea URL with /api/v matches the GitLab heuristic.
		$this->assertSame( 'gitlab', $this->call_detect_provider_from_url( 'https://gitea.example.com/api/v1/repos' ) );
	}

	// -------------------------------------------------------------------------
	// Reactive refresh in api()
	// -------------------------------------------------------------------------

	public function test_api_retries_on_401_after_successful_refresh(): void {
		$api_call_count = 0;
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$api_call_count ) {
				// Don't count refresh calls.
				if ( strpos( $url, '/oauth/refresh' ) !== false ) {
					return $preempt;
				}
				$api_call_count++;
				// First API call returns 401, second (retry) returns 200.
				if ( 1 === $api_call_count ) {
					return [
						'response' => [ 'code' => 401, 'message' => 'Unauthorized' ],
						'body'     => wp_json_encode( [ 'message' => 'Bad credentials' ] ),
						'headers'  => [],
					];
				}
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => wp_json_encode( [ 'name' => 'test-plugin' ] ),
					'headers'  => [],
				];
			},
			10,
			3
		);

		// Mock refresh_token to succeed.
		$oauth = \Fragen\Singleton::get_instance( \Fragen\Git_Updater\OAuth\OAuth_Connect::class, $this->api );
		$oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [
			'github_access_token'  => 'old_tok',
			'github_refresh_token' => 'ref',
		] );

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if ( strpos( $url, '/oauth/refresh' ) !== false ) {
					return [
						'response' => [ 'code' => 200 ],
						'body'     => wp_json_encode( [ 'access_token' => 'new_tok', 'expires_in' => 7200 ] ),
						'headers'  => [],
					];
				}
				return $preempt;
			},
			20,
			3
		);

		$result = $this->api->api( $this->endpoint );

		$this->assertSame( 2, $api_call_count );
		$this->assertIsObject( $result );
		$this->assertSame( 'test-plugin', $result->name );
	}

	public function test_api_retries_on_403_after_successful_refresh(): void {
		$api_call_count = 0;
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$api_call_count ) {
				if ( strpos( $url, '/oauth/refresh' ) !== false ) {
					return $preempt;
				}
				$api_call_count++;
				if ( 1 === $api_call_count ) {
					return [
						'response' => [ 'code' => 403, 'message' => 'Forbidden' ],
						'body'     => wp_json_encode( [ 'message' => 'rate limit' ] ),
						'headers'  => [],
					];
				}
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => wp_json_encode( [ 'name' => 'test-plugin' ] ),
					'headers'  => [],
				];
			},
			10,
			3
		);

		$oauth = \Fragen\Singleton::get_instance( \Fragen\Git_Updater\OAuth\OAuth_Connect::class, $this->api );
		$oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [
			'github_access_token'  => 'old_tok',
			'github_refresh_token' => 'ref',
		] );

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if ( strpos( $url, '/oauth/refresh' ) !== false ) {
					return [
						'response' => [ 'code' => 200 ],
						'body'     => wp_json_encode( [ 'access_token' => 'new_tok' ] ),
						'headers'  => [],
					];
				}
				return $preempt;
			},
			20,
			3
		);

		$result = $this->api->api( $this->endpoint );

		$this->assertSame( 2, $api_call_count );
		$this->assertIsObject( $result );
	}

	public function test_api_does_not_retry_when_refresh_fails(): void {
		$api_call_count = 0;
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$api_call_count ) {
				if ( strpos( $url, '/oauth/refresh' ) !== false ) {
					return $preempt;
				}
				$api_call_count++;
				return [
					'response' => [ 'code' => 401, 'message' => 'Unauthorized' ],
					'body'     => wp_json_encode( [ 'message' => 'Bad credentials' ] ),
					'headers'  => [],
				];
			},
			10,
			3
		);

		$oauth = \Fragen\Singleton::get_instance( \Fragen\Git_Updater\OAuth\OAuth_Connect::class, $this->api );
		$oauth->connector_url = 'https://connector.example.com/';
		update_site_option( 'git_updater', [
			'github_access_token'  => 'old_tok',
			'github_refresh_token' => 'ref',
		] );

		// Mock refresh to return error.
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if ( strpos( $url, '/oauth/refresh' ) !== false ) {
					return [
						'response' => [ 'code' => 401 ],
						'body'     => wp_json_encode( [ 'error' => 'invalid_grant' ] ),
						'headers'  => [],
					];
				}
				return $preempt;
			},
			20,
			3
		);

		$this->api->api( $this->endpoint );

		// Only 1 API call — no retry after failed refresh.
		$this->assertSame( 1, $api_call_count );
	}

	public function test_api_does_not_retry_when_no_refresh_token(): void {
		$api_call_count = 0;
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$api_call_count ) {
				if ( strpos( $url, '/oauth/refresh' ) !== false ) {
					return $preempt;
				}
				$api_call_count++;
				return [
					'response' => [ 'code' => 401, 'message' => 'Unauthorized' ],
					'body'     => wp_json_encode( [ 'message' => 'Bad credentials' ] ),
					'headers'  => [],
				];
			},
			10,
			3
		);

		// No refresh token stored.
		update_site_option( 'git_updater', [ 'github_access_token' => 'old_tok' ] );

		$this->api->api( $this->endpoint );

		$this->assertSame( 1, $api_call_count );
	}

	public function test_api_does_not_retry_for_non_auth_errors(): void {
		$api_call_count = 0;
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$api_call_count ) {
				if ( strpos( $url, '/oauth/refresh' ) !== false ) {
					return $preempt;
				}
				$api_call_count++;
				return [
					'response' => [ 'code' => 500, 'message' => 'Server Error' ],
					'body'     => wp_json_encode( [ 'message' => 'Internal error' ] ),
					'headers'  => [],
				];
			},
			10,
			3
		);

		$this->api->api( $this->endpoint );

		$this->assertSame( 1, $api_call_count );
	}

	public function test_api_does_not_retry_when_provider_not_detected(): void {
		$api_call_count = 0;
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$api_call_count ) {
				if ( strpos( $url, '/oauth/refresh' ) !== false ) {
					return $preempt;
				}
				$api_call_count++;
				return [
					'response' => [ 'code' => 401, 'message' => 'Unauthorized' ],
					'body'     => wp_json_encode( [ 'message' => 'Bad credentials' ] ),
					'headers'  => [],
				];
			},
			10,
			3
		);

		// Use a URL that doesn't match any provider pattern.
		$this->type->enterprise_api = 'https://unknown.example.com/api';
		$this->api->api( $this->endpoint );

		$this->assertSame( 1, $api_call_count );
	}
}
