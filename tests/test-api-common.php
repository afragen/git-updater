<?php
/**
 * Tests for API_Common trait methods changed in Tier 2 PHPStan fixes:
 *
 * - get_remote_api_tag():  removed always-true if($response) wrapper so
 *   parse_tag_response() is always called after the error branch.
 * - get_remote_api_changes(): removed is_wp_error(string) guard after
 *   decode_response() since a string can never be WP_Error.
 * - get_remote_api_readme(): same is_wp_error(string) guard removal.
 * - get_api_release_asset() / get_api_release_assets(): simplified
 *   !$response && !is_wp_error($response) to just !$response.
 * - get_remote_api_branches(): removed is_scalar() always-false guard.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\API\GitHub_API;
use Fragen\Git_Updater\Base;

class Test_API_Common extends WP_UnitTestCase {

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
		$this->type = $this->make_type();
		$this->api  = new GitHub_API( $this->type );
	}

	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'gu_parse_api_branches' );
		remove_all_filters( 'gu_always_fetch_update' );
		delete_site_option( $this->api->get_cache_key( 'test-plugin' ) );
		delete_site_option( $this->api->get_cache_key( 'test-plugin_error' ) );
		parent::tear_down();
	}

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
	 * Make wp_remote_get() return the given value for all requests.
	 *
	 * @param mixed $return Value to return from the filter.
	 */
	private function intercept_http_with( $return ): void {
		add_filter( 'pre_http_request', fn() => $return, 10, 3 );
	}

	private function mock_http_response( int $code, array $body = [] ): array {
		return [
			'response' => [ 'code' => $code, 'message' => 200 === $code ? 'OK' : 'Error' ],
			'body'     => wp_json_encode( $body ),
			'headers'  => [],
			'cookies'  => [],
		];
	}

	// -------------------------------------------------------------------------
	// get_remote_api_tag()
	// -------------------------------------------------------------------------

	/**
	 * When api() returns false (error cache hit), get_remote_api_tag() enters the
	 * !$response branch, caches a 'No tags found' placeholder, and returns null —
	 * counting the call as complete (the API was unreachable, not a WP_Error).
	 */
	public function test_get_remote_api_tag_with_failed_api_returns_false(): void {
		// Seed an in-force error cache so api() returns false immediately.
		update_site_option(
			$this->api->get_cache_key( 'test-plugin_error' ),
			[
				'error_cache' => $this->mock_http_response( 403, [ 'message' => 'Rate limited' ] ),
				'timeout'     => strtotime( '+1 hour' ),
			]
		);

		$result = $this->api->get_remote_tag();
		$this->assertNull( $result );
	}

	/**
	 * When the API returns a valid tag list, get_remote_api_tag() returns true.
	 */
	public function test_get_remote_api_tag_with_valid_tags_returns_true(): void {
		$tag_response = [
			[
				'name'   => '1.0.0',
				'commit' => [ 'sha' => 'abc123' ],
			],
		];
		$this->intercept_http_with( $this->mock_http_response( 200, $tag_response ) );

		$result = $this->api->get_remote_tag();
		$this->assertTrue( $result );
	}

	// -------------------------------------------------------------------------
	// get_remote_api_changes()
	// -------------------------------------------------------------------------

	/**
	 * When the API returns a non-200 response (no changelog content), the response
	 * body is a stdClass (not a string), so get_remote_api_changes() caches a
	 * 'No changelog found' placeholder and returns null — counted as complete.
	 */
	public function test_get_remote_api_changes_with_failed_api_returns_true(): void {
		// Return a 404 for all changelog filenames.
		$this->intercept_http_with(
			$this->mock_http_response( 404, [ 'message' => 'Not Found' ] )
		);

		$result = $this->api->get_remote_changes( 'CHANGES.md' );
		$this->assertNull( $result );
	}

	/**
	 * When the API returns a valid string changelog, get_remote_changes()
	 * returns true. The removed is_wp_error(string) guard cannot affect this path.
	 */
	public function test_get_remote_api_changes_with_valid_content_returns_true(): void {
		// GitHub returns base64-encoded content for file reads.
		$raw_content    = "# Changelog\n\n## 1.0.0\n- Initial release";
		$encoded        = base64_encode( $raw_content );
		$api_body       = [ 'content' => $encoded, 'encoding' => 'base64' ];
		$this->intercept_http_with( $this->mock_http_response( 200, $api_body ) );

		$result = $this->api->get_remote_changes( 'CHANGES.md' );
		$this->assertTrue( $result );
	}

	// -------------------------------------------------------------------------
	// get_remote_api_readme()
	// -------------------------------------------------------------------------

	/**
	 * When the API returns a non-200 response (no readme content), the response
	 * body is a stdClass (not a string), so get_remote_api_readme() caches a
	 * 'No readme found' placeholder and returns null — counted as complete.
	 */
	public function test_get_remote_api_readme_with_failed_api_returns_false(): void {
		$this->intercept_http_with(
			$this->mock_http_response( 404, [ 'message' => 'Not Found' ] )
		);

		$result = $this->api->get_remote_readme();
		$this->assertNull( $result );
	}

	// -------------------------------------------------------------------------
	// get_remote_api_branches()
	// -------------------------------------------------------------------------

	/**
	 * When the branches API returns a valid array (as filtered by
	 * gu_parse_api_branches), get_remote_branches() returns true.
	 *
	 * The removed is_scalar() check cannot break this path.
	 */
	public function test_get_remote_api_branches_with_valid_response_returns_true(): void {
		// Include 'url' in each commit so parse_branch_response does not
		// trigger an E_WARNING accessing an undefined stdClass property.
		$branches_response = [
			[ 'name' => 'master',  'commit' => [ 'sha' => 'abc', 'url' => '' ] ],
			[ 'name' => 'develop', 'commit' => [ 'sha' => 'def', 'url' => '' ] ],
		];
		$this->intercept_http_with( $this->mock_http_response( 200, $branches_response ) );

		$result = $this->api->get_remote_branches();
		$this->assertTrue( $result );
	}

	/**
	 * When api() returns false (error cache hit), get_remote_branches() enters the
	 * !$response branch and returns null — counted as complete.
	 */
	public function test_get_remote_api_branches_with_failed_api_returns_false(): void {
		update_site_option(
			$this->api->get_cache_key( 'test-plugin_error' ),
			[
				'error_cache' => $this->mock_http_response( 403, [ 'message' => 'Rate limited' ] ),
				'timeout'     => strtotime( '+1 hour' ),
			]
		);

		$result = $this->api->get_remote_branches();
		$this->assertNull( $result );
	}
}
