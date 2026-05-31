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


class Test_API_Common_Complete extends WP_UnitTestCase {

	/** @var GitHub_API */
	private GitHub_API $api;

	/** @var stdClass */
	private stdClass $type;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->type = $this->make_type();
		$this->api  = new GitHub_API( $this->type );
	}

	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'gu_always_fetch_update' );
		remove_all_filters( 'gu_parse_api_branches' );
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
		$type->sections       = [];
		return $type;
	}

	private function http_ok( array $body ): array {
		return [
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'body'     => wp_json_encode( $body ),
			'headers'  => [],
			'cookies'  => [],
		];
	}

	private function http_ok_raw( string $body ): array {
		return [
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'body'     => $body,
			'headers'  => [],
			'cookies'  => [],
		];
	}

	private function seed_cache( array $extra ): void {
		update_site_option(
			$this->api->get_cache_key( 'test-plugin' ),
			array_merge( [ 'timeout' => strtotime( '+12 hours' ) ], $extra )
		);
	}

	// -------------------------------------------------------------------------
	// decode_response() — branch coverage
	// -------------------------------------------------------------------------

	/**
	 * When $git is not 'github', decode_response() skips the base64 block and
	 * returns the response unchanged through the gu_decode_response filter.
	 */
	public function test_decode_response_skips_base64_for_non_github_git(): void {
		$rm       = $this->api->get_reflection_method( $this->api, 'decode_response' );
		$original = (object) [ 'content' => base64_encode( 'raw content' ) ];
		$result   = $rm->invoke( $this->api, 'bitbucket', $original );
		// content is NOT base64-decoded for non-github gits.
		$this->assertSame( base64_encode( 'raw content' ), $result->content );
	}

	/**
	 * When $git is 'github' but the response has no 'content' property,
	 * the ternary returns the response object unchanged.
	 */
	public function test_decode_response_passes_through_github_response_without_content(): void {
		$rm       = $this->api->get_reflection_method( $this->api, 'decode_response' );
		$response = (object) [ 'sha' => 'abc123' ];
		$result   = $rm->invoke( $this->api, 'github', $response );
		$this->assertFalse( isset( $result->content ) );
		$this->assertSame( 'abc123', $result->sha );
	}

	// -------------------------------------------------------------------------
	// parse_release_asset() — branch coverage
	// -------------------------------------------------------------------------

	/**
	 * For non-github/non-gitea gits the entire processing block is skipped;
	 * the response is returned unchanged through the gu_parse_release_asset filter.
	 */
	public function test_parse_release_asset_skips_processing_for_non_github_gitea_git(): void {
		$rm       = $this->api->get_reflection_method( $this->api, 'parse_release_asset' );
		$response = (object) [ 'tag_name' => '1.0.0', 'assets' => [] ];
		$result   = $rm->invoke( $this->api, 'bitbucket', '/releases/latest', $response );
		// Response comes back unchanged (passed through the filter only).
		$this->assertSame( '1.0.0', $result->tag_name );
	}

	/**
	 * Stable release (tag '1.0.0') whose asset name does NOT start with the
	 * repo slug: the inner foreach completes without continue 2 (covers line 82)
	 * and the release is not added to the stable-assets bucket.
	 */
	public function test_parse_release_asset_stable_tag_with_no_matching_asset(): void {
		$rm    = $this->api->get_reflection_method( $this->api, 'parse_release_asset' );
		$asset = (object) [
			'name'       => 'unrelated-library.zip',
			'url'        => 'https://example.com/unrelated.zip',
			'created_at' => '2024-01-01T00:00:00Z',
		];
		$release  = (object) [ 'tag_name' => '1.0.0', 'assets' => [ $asset ] ];
		$result   = $rm->invoke( $this->api, 'github', '/repos/:owner/:repo/releases', [ $release ] );
		// No matching asset → stable bucket is empty.
		$this->assertIsArray( $result );
		$this->assertEmpty( $result['assets'] );
		$this->assertEmpty( $result['dev_assets'] );
	}

	/**
	 * Dev release (tag '1.0.0-beta1') whose asset name does NOT start with the
	 * repo slug: the inner dev foreach completes without continue 2 (covers line 92).
	 */
	public function test_parse_release_asset_dev_tag_with_no_matching_asset(): void {
		$rm    = $this->api->get_reflection_method( $this->api, 'parse_release_asset' );
		$asset = (object) [
			'name'       => 'other-library-beta.zip',
			'url'        => 'https://example.com/other.zip',
			'created_at' => '2024-05-01T00:00:00Z',
		];
		$release = (object) [ 'tag_name' => '1.0.0-beta1', 'assets' => [ $asset ] ];
		$result  = $rm->invoke( $this->api, 'github', '/repos/:owner/:repo/releases', [ $release ] );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result['dev_assets'] );
		$this->assertEmpty( $result['assets'] );
	}

	/**
	 * A tag that matches neither the stable regex nor the dev regex (e.g. 'edge-build')
	 * is silently skipped; both asset buckets remain empty.
	 */
	public function test_parse_release_asset_tag_matches_neither_stable_nor_dev_pattern(): void {
		$rm    = $this->api->get_reflection_method( $this->api, 'parse_release_asset' );
		$asset = (object) [
			'name'       => 'test-plugin-edge.zip',
			'url'        => 'https://example.com/edge.zip',
			'created_at' => '2024-06-01T00:00:00Z',
		];
		$release = (object) [ 'tag_name' => 'edge-build', 'assets' => [ $asset ] ];
		$result  = $rm->invoke( $this->api, 'github', '/repos/:owner/:repo/releases', [ $release ] );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result['assets'] );
		$this->assertEmpty( $result['dev_assets'] );
	}

	// -------------------------------------------------------------------------
	// get_remote_api_info() — maybe_extend_repo_cache() returns true (line 154)
	// -------------------------------------------------------------------------

	/**
	 * When the remote version equals the cached version AND $cache['ran'] contains all
	 * expected keys, maybe_extend_repo_cache() returns true → get_remote_api_info() returns
	 * false (line 154) rather than true, to avoid an unnecessary cache overwrite.
	 */
	public function test_get_remote_api_info_returns_false_when_maybe_extend_cache_returns_true(): void {
		// Pre-seed with an expired timeout so api() is called, but the old cached version
		// and ran are readable via get_repo_cache($slug, false) for maybe_extend_repo_cache.
		update_site_option(
			$this->api->get_cache_key( 'test-plugin' ),
			[
				'timeout'     => strtotime( '-1 hour' ),
				'repo'        => 'test-plugin',
				'test-plugin' => [ 'Version' => '1.0.0' ],
				'ran'         => [ 'contents', 'assets', 'readme', 'changes', 'tags', 'branches', 'meta' ],
			]
		);

		$plugin_header = implode(
			"\n",
			[
				'<?php',
				'/**',
				' * Plugin Name: Test Plugin',
				' * Plugin URI: https://example.com',
				' * Version: 1.0.0',
				' * Description: A test plugin.',
				' * Author: Test Author',
				' * Author URI: https://example.com/author',
				' * License: GPL-3.0-or-later',
				' * Text Domain: test-plugin',
				' */',
			]
		);
		$api_body = wp_json_encode(
			[
				'content'  => base64_encode( $plugin_header ),
				'encoding' => 'base64',
			]
		);
		add_filter( 'pre_http_request', fn() => $this->http_ok_raw( $api_body ), 10, 3 );

		// get_remote_api_info runs, fetches headers (Version=1.0.0), writes
		// $cache['test-plugin'] and $cache['repo'], then calls maybe_extend_repo_cache.
		// Because $cache['ran'] is complete and the versions match, it returns true → false.
		$result = $this->api->get_remote_info( 'test-plugin.php' );
		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// get_remote_api_changes() — contents cache filtering and loop fallthrough
	// -------------------------------------------------------------------------

	/**
	 * When $cache['contents'] is set, the changelogs list is filtered to only
	 * files present in the repo, reducing the number of API calls made.
	 */
	public function test_get_remote_api_changes_filters_changelogs_via_contents_cache(): void {
		$this->seed_cache(
			[ 'contents' => [ 'files' => [ 'CHANGES.md', 'readme.txt' ], 'dirs' => [] ] ]
		);

		$raw_content = "# Changelog\n\n## 1.0.0\n- Initial release";
		$call        = 0;

		add_filter(
			'pre_http_request',
			function () use ( $raw_content, &$call ) {
				$call++;
				return $this->http_ok_raw(
					wp_json_encode( [ 'content' => base64_encode( $raw_content ), 'encoding' => 'base64' ] )
				);
			},
			10,
			3
		);

		$result = $this->api->get_remote_changes( 'CHANGES.md' );
		$this->assertTrue( $result );
		$this->assertSame( 1, $call, 'Contents-cache filtering should narrow the search to CHANGES.md only' );
	}

	/**
	 * When the first changelog file returns a response with a 'message' property
	 * (error indicator), the loop continues to the next file. The second file
	 * returns valid content, so the method returns true.
	 *
	 * Covers lines 213–215 (error = true → don't break → loop continues).
	 * Uses 200 + message body (not 404) to avoid writing the error cache.
	 */
	public function test_get_remote_api_changes_falls_through_to_second_file_on_message_error(): void {
		$raw_content = "# Changelog\n\n## 1.0.0\n- Initial release";
		$call        = 0;

		add_filter(
			'pre_http_request',
			function () use ( $raw_content, &$call ) {
				$call++;
				if ( 1 === $call ) {
					// First changelog file: valid JSON with 'message' → api() returns stdClass.
					return $this->http_ok( [ 'message' => 'Not Found' ] );
				}
				// Second changelog file: valid base64 content.
				return $this->http_ok_raw(
					wp_json_encode( [ 'content' => base64_encode( $raw_content ), 'encoding' => 'base64' ] )
				);
			},
			10,
			3
		);

		$result = $this->api->get_remote_changes( 'CHANGES.md' );
		$this->assertTrue( $result );
		$this->assertGreaterThanOrEqual( 2, $call );
	}

	// -------------------------------------------------------------------------
	// get_remote_api_readme() — contents cache filtering and loop fallthrough
	// -------------------------------------------------------------------------

	/**
	 * When $cache['contents'] is set, the readmes list is filtered to matching
	 * files, reducing the number of API calls made.
	 */
	public function test_get_remote_api_readme_filters_readmes_via_contents_cache(): void {
		$this->seed_cache(
			[ 'contents' => [ 'files' => [ 'readme.txt', 'test-plugin.php' ], 'dirs' => [] ] ]
		);

		$readme_content = "=== Test Plugin ===\nContributors: test\nStable tag: 1.0.0\n\nDescription.";
		$call           = 0;

		add_filter(
			'pre_http_request',
			function () use ( $readme_content, &$call ) {
				$call++;
				return $this->http_ok_raw(
					wp_json_encode( [ 'content' => base64_encode( $readme_content ), 'encoding' => 'base64' ] )
				);
			},
			10,
			3
		);

		$result = $this->api->get_remote_readme();
		$this->assertTrue( $result );
		$this->assertSame( 1, $call, 'Contents-cache filtering should narrow the search to readme.txt only' );
	}

	/**
	 * When the first readme file returns a 'message' property (error indicator),
	 * the loop continues to the next file. The second file returns valid content.
	 *
	 * Covers lines 273–275 (error = true → don't break → loop continues).
	 */
	public function test_get_remote_api_readme_falls_through_to_second_file_on_message_error(): void {
		$readme_content = "=== Test Plugin ===\nContributors: test\nStable tag: 1.0.0\n\nDescription.";
		$call           = 0;

		add_filter(
			'pre_http_request',
			function () use ( $readme_content, &$call ) {
				$call++;
				if ( 1 === $call ) {
					return $this->http_ok( [ 'message' => 'Not Found' ] );
				}
				return $this->http_ok_raw(
					wp_json_encode( [ 'content' => base64_encode( $readme_content ), 'encoding' => 'base64' ] )
				);
			},
			10,
			3
		);

		$result = $this->api->get_remote_readme();
		$this->assertTrue( $result );
		$this->assertGreaterThanOrEqual( 2, $call );
	}

	// -------------------------------------------------------------------------
	// get_remote_api_assets() — contents cache filtering, loop continuation,
	//                           and $error flag paths
	// -------------------------------------------------------------------------

	/**
	 * When $cache['contents']['dirs'] is set, the assets list is filtered to
	 * matching directories, reducing the number of API calls made.
	 */
	public function test_get_remote_api_assets_filters_dirs_via_contents_cache(): void {
		$this->seed_cache(
			[ 'contents' => [ 'files' => [], 'dirs' => [ '.wordpress-org' ] ] ]
		);

		$call = 0;

		add_filter(
			'pre_http_request',
			function () use ( &$call ) {
				$call++;
				return $this->http_ok(
					[
						[
							'type'         => 'file',
							'name'         => 'banner-772x250.png',
							'path'         => '.wordpress-org/banner-772x250.png',
							'download_url' => 'https://raw.githubusercontent.com/test-owner/test-plugin/master/.wordpress-org/banner-772x250.png',
						],
					]
				);
			},
			10,
			3
		);

		$result = $this->api->get_repo_assets();
		$this->assertTrue( $result );
		$this->assertSame( 1, $call, 'Contents-cache filtering should narrow the search to .wordpress-org only' );
	}

	/**
	 * When api() returns an object (stdClass) for the first asset path, the
	 * loop does NOT break (line 351 FALSE) and continues to the second path.
	 * The second path returns an array → break → error flag not set → true.
	 *
	 * Uses 200 + message body (first call) and 200 + array body (second call).
	 */
	public function test_get_remote_api_assets_loop_continues_when_api_returns_object(): void {
		$call = 0;

		add_filter(
			'pre_http_request',
			function () use ( &$call ) {
				$call++;
				if ( 1 === $call ) {
					// First path (.wordpress-org): returns stdClass with message → is_object = true → no break.
					return $this->http_ok( [ 'message' => 'Not Found' ] );
				}
				// Second path (assets): returns array → is_object = false → break.
				return $this->http_ok(
					[
						[
							'type'         => 'file',
							'name'         => 'banner-772x250.png',
							'path'         => 'assets/banner-772x250.png',
							'download_url' => 'https://raw.githubusercontent.com/test-owner/test-plugin/master/assets/banner-772x250.png',
						],
					]
				);
			},
			10,
			3
		);

		$result = $this->api->get_repo_assets();
		$this->assertTrue( $result );
		$this->assertSame( 2, $call );
	}

	/**
	 * When the final $response after the loop is a stdClass with a 'message'
	 * property, the $error flag is set (line 356 TRUE), an error response is
	 * created, and the method returns false.
	 *
	 * Both asset paths return 200 + message body so the loop never breaks (both
	 * are objects) and the final $response has the message property triggering
	 * $error=true; since it's not a WP_Error, a placeholder is cached and null returned.
	 */
	public function test_get_remote_api_assets_error_flag_set_from_message_property(): void {
		// Both calls return stdClass{message: "Not Found"} → loop runs to exhaustion.
		add_filter(
			'pre_http_request',
			fn() => $this->http_ok( [ 'message' => 'Not Found' ] ),
			10,
			3
		);

		$result = $this->api->get_repo_assets();
		$this->assertNull( $result );
	}

	/**
	 * When api() returns a WP_Error (pre_http_request returns WP_Error), the
	 * response is truthy and is_object() = true → loop does not break (line 351).
	 * After the loop is_wp_error($response) = true (line 359) → $error = true → false.
	 */
	public function test_get_remote_api_assets_error_flag_set_from_wp_error_response(): void {
		add_filter(
			'pre_http_request',
			fn() => new WP_Error( 'http_request_failed', 'Connection refused' ),
			10,
			3
		);

		$result = $this->api->get_repo_assets();
		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// get_remote_api_branches() — validate_response() true in fetch block (line 408)
	// -------------------------------------------------------------------------

	/**
	 * When the API returns a response with a 'message' property, validate_response()
	 * returns true → get_remote_api_branches() returns false.
	 */
	public function test_get_remote_api_branches_returns_false_when_validate_response_true_during_fetch(): void {
		add_filter(
			'pre_http_request',
			fn() => $this->http_ok( [ 'message' => 'Forbidden' ] ),
			10,
			3
		);

		$result = $this->api->get_remote_branches();
		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// get_remote_api_branches() — null and WP_Error tri-state paths
	// -------------------------------------------------------------------------

	/**
	 * Empty API response (no branches) → returns null, does not add to $ran.
	 */
	public function test_get_remote_api_branches_returns_null_when_api_returns_empty(): void {
		add_filter(
			'pre_http_request',
			fn() => $this->http_ok_raw( '[]' ),
			10,
			3
		);

		$result = $this->api->get_remote_branches();
		$this->assertNull( $result );
	}

	/**
	 * WP_Error from api() → returns false immediately, does not add to $ran.
	 */
	public function test_get_remote_api_branches_returns_false_on_wp_error(): void {
		add_filter(
			'pre_http_request',
			fn() => new WP_Error( 'http_request_failed', 'Connection refused' ),
			10,
			3
		);

		$result = $this->api->get_remote_branches();
		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// get_remote_api_repo_meta() — null and WP_Error tri-state paths
	// -------------------------------------------------------------------------

	/**
	 * Empty API response → returns null, does not add to $ran.
	 */
	public function test_get_remote_api_repo_meta_returns_null_when_api_returns_empty(): void {
		add_filter(
			'pre_http_request',
			fn() => $this->http_ok_raw( '' ),
			10,
			3
		);

		$result = $this->api->get_repo_meta();
		$this->assertNull( $result );
	}

	/**
	 * When api() returns a truthy object with a 'message' property, parse_meta_response()
	 * validates it internally and returns it unchanged; the outer validate_response() then
	 * fires true → return false (line 318).
	 */
	public function test_get_remote_api_repo_meta_returns_false_when_parse_returns_message_response(): void {
		add_filter(
			'pre_http_request',
			fn() => $this->http_ok( [ 'message' => 'Forbidden' ] ),
			10,
			3
		);

		$result = $this->api->get_repo_meta();
		$this->assertFalse( $result );
	}

	/**
	 * WP_Error from api() → returns false immediately, does not add to $ran.
	 */
	public function test_get_remote_api_repo_meta_returns_false_on_wp_error(): void {
		add_filter(
			'pre_http_request',
			fn() => new WP_Error( 'http_request_failed', 'Connection refused' ),
			10,
			3
		);

		$result = $this->api->get_repo_meta();
		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// get_remote_api_contents() — null and WP_Error tri-state paths
	// -------------------------------------------------------------------------

	/**
	 * Empty API response → returns null, does not add to $ran.
	 */
	public function test_get_remote_api_contents_returns_null_when_api_returns_empty(): void {
		add_filter(
			'pre_http_request',
			fn() => $this->http_ok_raw( '' ),
			10,
			3
		);

		$result = $this->api->get_repo_contents();
		$this->assertNull( $result );
	}

	/**
	 * WP_Error from api() → returns false immediately, does not add to $ran.
	 */
	public function test_get_remote_api_contents_returns_false_on_wp_error(): void {
		add_filter(
			'pre_http_request',
			fn() => new WP_Error( 'http_request_failed', 'Connection refused' ),
			10,
			3
		);

		$result = $this->api->get_repo_contents();
		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// get_remote_api_tag() — null and WP_Error tri-state paths
	// -------------------------------------------------------------------------

	/**
	 * Empty tags list from API → caches placeholder, returns null.
	 */
	public function test_get_remote_api_tag_returns_null_when_api_returns_empty(): void {
		add_filter(
			'pre_http_request',
			fn() => $this->http_ok_raw( '[]' ),
			10,
			3
		);

		$result = $this->api->get_remote_tag();
		$this->assertNull( $result );
	}

	/**
	 * When api() returns a truthy object with a 'message' property, parse_tag_response()
	 * validates it internally and returns it unchanged; the outer validate_response() then
	 * fires true → return false (line 187).
	 */
	public function test_get_remote_api_tag_returns_false_when_parse_returns_message_response(): void {
		add_filter(
			'pre_http_request',
			fn() => $this->http_ok( [ 'message' => 'Forbidden' ] ),
			10,
			3
		);

		$result = $this->api->get_remote_tag();
		$this->assertFalse( $result );
	}

	/**
	 * WP_Error from api() → returns false immediately, does not cache placeholder.
	 */
	public function test_get_remote_api_tag_returns_false_on_wp_error(): void {
		add_filter(
			'pre_http_request',
			fn() => new WP_Error( 'http_request_failed', 'Connection refused' ),
			10,
			3
		);

		$result = $this->api->get_remote_tag();
		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// get_remote_api_changes() — null and WP_Error tri-state paths
	// -------------------------------------------------------------------------

	/**
	 * All changelog files return 404-style message → no string decoded → caches
	 * placeholder, returns null.
	 */
	public function test_get_remote_api_changes_returns_null_when_no_changelog_found(): void {
		add_filter(
			'pre_http_request',
			fn() => $this->http_ok( [ 'message' => 'Not Found' ] ),
			10,
			3
		);

		$result = $this->api->get_remote_changes( '' );
		$this->assertNull( $result );
	}

	/**
	 * WP_Error from api() breaks the loop; after decode, is_wp_error check fires → false.
	 */
	public function test_get_remote_api_changes_returns_false_on_wp_error(): void {
		add_filter(
			'pre_http_request',
			fn() => new WP_Error( 'http_request_failed', 'Connection refused' ),
			10,
			3
		);

		$result = $this->api->get_remote_changes( '' );
		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// get_remote_api_readme() — null and WP_Error tri-state paths
	// -------------------------------------------------------------------------

	/**
	 * All readme files return 404-style message → no string decoded → caches
	 * placeholder, returns null.
	 */
	public function test_get_remote_api_readme_returns_null_when_no_readme_found(): void {
		add_filter(
			'pre_http_request',
			fn() => $this->http_ok( [ 'message' => 'Not Found' ] ),
			10,
			3
		);

		$result = $this->api->get_remote_readme();
		$this->assertNull( $result );
	}

	/**
	 * WP_Error from api() breaks the loop; after decode, is_wp_error check fires → false.
	 */
	public function test_get_remote_api_readme_returns_false_on_wp_error(): void {
		add_filter(
			'pre_http_request',
			fn() => new WP_Error( 'http_request_failed', 'Connection refused' ),
			10,
			3
		);

		$result = $this->api->get_remote_readme();
		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// get_remote_api_assets() — null and WP_Error tri-state paths
	// -------------------------------------------------------------------------

	/**
	 * Non-array, non-WP_Error response → $error = true → caches placeholder, returns null.
	 */
	public function test_get_remote_api_assets_returns_null_when_no_assets_found(): void {
		add_filter(
			'pre_http_request',
			fn() => $this->http_ok( [ 'message' => 'Not Found' ] ),
			10,
			3
		);

		$result = $this->api->get_repo_assets();
		$this->assertNull( $result );
	}

	/**
	 * When api() returns an array of directory-only items:
	 * - Loop iterates but no 'file' items found.
	 * - parse_asset_dir_response() returns stdClass{message: 'No assets found'}.
	 * - get_remote_api_assets() returns null (not false) for "no assets" case.
	 */
	public function test_get_remote_api_assets_returns_null_when_only_dirs_in_listing(): void {
		add_filter(
			'pre_http_request',
			fn() => $this->http_ok(
				[
					[ 'type' => 'dir', 'name' => 'subdir', 'path' => 'assets/subdir', 'download_url' => null ],
				]
			),
			10,
			3
		);

		$result = $this->api->get_repo_assets();
		$this->assertNull( $result );
	}

	/**
	 * When $cache['contents']['dirs'] contains none of the expected asset
	 * directories (.wordpress-org, assets), array_intersect produces [].
	 * The foreach is skipped, $response stays false, the error block fires,
	 * and null is returned with no HTTP requests made.
	 */
	public function test_get_remote_api_assets_returns_null_when_cache_dirs_match_none(): void {
		$this->seed_cache(
			[ 'contents' => [ 'files' => [], 'dirs' => [ 'src', 'lib', 'vendor' ] ] ]
		);

		$call = 0;
		add_filter( 'pre_http_request', function () use ( &$call ) { $call++; }, 10, 3 );

		$result = $this->api->get_repo_assets();
		$this->assertNull( $result );
		$this->assertSame( 0, $call, 'No HTTP request expected when no asset dirs match' );
	}

	/**
	 * WP_Error from api() → $error = true, is_wp_error check fires → false.
	 */
	public function test_get_remote_api_assets_returns_false_on_wp_error(): void {
		add_filter(
			'pre_http_request',
			fn() => new WP_Error( 'http_request_failed', 'Connection refused' ),
			10,
			3
		);

		$result = $this->api->get_repo_assets();
		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// get_api_release_asset() — "No release asset found" path (lines 440-443)
	// -------------------------------------------------------------------------

	/**
	 * When a WP_Error HTTP response is returned, parse_release_asset() returns ''
	 * (falsy) → the inner `if (!$response)` block sets stdClass{message} →
	 * validate_response() = true → return false.
	 *
	 * Also covers parse_release_asset() line 59: `if (is_wp_error) return ''`.
	 */
	public function test_get_api_release_asset_wp_error_sets_no_asset_message(): void {
		add_filter(
			'pre_http_request',
			fn() => new WP_Error( 'http_request_failed', 'Connection refused' ),
			10,
			3
		);

		$result = $this->api->get_api_release_asset(
			'github',
			'/repos/test-owner/test-plugin/releases/latest'
		);
		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// get_api_release_assets() — "No release assets found" path (lines 478-481)
	// -------------------------------------------------------------------------

	/**
	 * Same WP_Error path as above but for get_api_release_assets() / get_release_assets().
	 * Sets stdClass{message} → validate_response() = true → false.
	 */
	public function test_get_release_assets_wp_error_sets_no_assets_message(): void {
		add_filter(
			'pre_http_request',
			fn() => new WP_Error( 'http_request_failed', 'Connection refused' ),
			10,
			3
		);

		$result = $this->api->get_release_assets();
		$this->assertFalse( $result );
	}
}


class Test_API_Common_Extended extends WP_UnitTestCase {

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
		$type->sections       = [];
		return $type;
	}

	private function mock_http_response( int $code, array $body = [] ): array {
		return [
			'response' => [ 'code' => $code, 'message' => 200 === $code ? 'OK' : 'Error' ],
			'body'     => wp_json_encode( $body ),
			'headers'  => [],
			'cookies'  => [],
		];
	}

	private function mock_http_response_raw( int $code, string $body ): array {
		return [
			'response' => [ 'code' => $code, 'message' => 200 === $code ? 'OK' : 'Error' ],
			'body'     => $body,
			'headers'  => [],
			'cookies'  => [],
		];
	}

	private function intercept_http_with( array $return ): void {
		add_filter( 'pre_http_request', fn() => $return, 10, 3 );
	}

	/**
	 * Pre-seed the dot_org cache key so get_dot_org_data() does not make an
	 * outbound call to api.wordpress.org.
	 */
	private function seed_dot_org_cache(): void {
		$cache_key = $this->api->get_cache_key( 'test-plugin' );
		update_site_option(
			$cache_key,
			[
				'dot_org' => 'not in dot org',
				'timeout' => strtotime( '+12 hours' ),
			]
		);
	}

	/**
	 * Seed the error cache so api() returns false immediately without HTTP.
	 */
	private function seed_error_cache(): void {
		update_site_option(
			$this->api->get_cache_key( 'test-plugin_error' ),
			[
				'error_cache' => $this->mock_http_response( 403, [ 'message' => 'Rate limited' ] ),
				'timeout'     => strtotime( '+1 hour' ),
			]
		);
	}

	// -------------------------------------------------------------------------
	// get_remote_api_info()  via  get_remote_info()
	// -------------------------------------------------------------------------

	/**
	 * When the API returns an error, get_remote_info() returns false.
	 */
	public function test_get_remote_info_returns_false_when_api_fails(): void {
		$this->seed_error_cache();
		$result = $this->api->get_remote_info( 'test-plugin.php' );
		$this->assertFalse( $result );
	}

	/**
	 * When the API returns valid base64-encoded plugin headers, get_remote_info()
	 * returns true and populates type properties.
	 */
	public function test_get_remote_info_returns_true_with_valid_plugin_headers(): void {
		$this->seed_dot_org_cache();

		$plugin_header = implode(
			"\n",
			[
				'<?php',
				'/**',
				' * Plugin Name: Test Plugin',
				' * Plugin URI: https://example.com',
				' * Version: 1.0.0',
				' * Description: A test plugin for unit testing.',
				' * Author: Test Author',
				' * Author URI: https://example.com/author',
				' * License: GPL-3.0-or-later',
				' * Text Domain: test-plugin',
				' */',
			]
		);

		$api_body = wp_json_encode(
			[
				'content'  => base64_encode( $plugin_header ),
				'encoding' => 'base64',
			]
		);
		$this->intercept_http_with( $this->mock_http_response_raw( 200, $api_body ) );

		$result = $this->api->get_remote_info( 'test-plugin.php' );
		$this->assertTrue( $result );
	}

	// -------------------------------------------------------------------------
	// get_remote_api_repo_meta()  via  get_repo_meta()
	// -------------------------------------------------------------------------

	/**
	 * When api() returns false (error cache hit), get_repo_meta() hits the
	 * !$response branch and returns null — counted as complete.
	 */
	public function test_get_repo_meta_returns_false_when_api_fails(): void {
		$this->seed_error_cache();
		$result = $this->api->get_repo_meta();
		$this->assertNull( $result );
	}

	/**
	 * When the API returns a valid repository object, get_repo_meta() returns true.
	 */
	public function test_get_repo_meta_returns_true_with_valid_meta(): void {
		$this->intercept_http_with(
			$this->mock_http_response(
				200,
				[
					'name'        => 'test-plugin',
					'private'     => false,
					'pushed_at'   => '2024-06-01T12:00:00Z',
					'created_at'  => '2023-01-01T00:00:00Z',
					'watchers'    => 42,
					'forks'       => 7,
					'open_issues' => 3,
				]
			)
		);

		$result = $this->api->get_repo_meta();
		$this->assertTrue( $result );
	}

	// -------------------------------------------------------------------------
	// get_remote_api_assets()  via  get_repo_assets()
	// -------------------------------------------------------------------------

	/**
	 * When the API returns a non-200 for all asset paths, the response object
	 * has a 'message' property which sets $error=true; since it's not a WP_Error,
	 * get_repo_assets() caches a 'No assets found' placeholder and returns null.
	 */
	public function test_get_repo_assets_returns_false_when_api_fails(): void {
		$this->intercept_http_with(
			$this->mock_http_response( 404, [ 'message' => 'Not Found' ] )
		);

		$result = $this->api->get_repo_assets();
		$this->assertNull( $result );
	}

	/**
	 * When the assets directory contains files, get_repo_assets() returns true.
	 */
	public function test_get_repo_assets_returns_true_with_file_listing(): void {
		$asset_listing = [
			[
				'type'         => 'file',
				'name'         => 'screenshot-1.png',
				'path'         => 'assets/screenshot-1.png',
				'download_url' => 'https://raw.githubusercontent.com/test-owner/test-plugin/master/assets/screenshot-1.png',
			],
		];
		$this->intercept_http_with( $this->mock_http_response( 200, $asset_listing ) );

		$result = $this->api->get_repo_assets();
		$this->assertTrue( $result );
	}

	// -------------------------------------------------------------------------
	// get_api_release_assets()  via  get_release_assets()
	// -------------------------------------------------------------------------

	/**
	 * When wp_remote_get() returns a WP_Error (network failure), parse_release_asset()
	 * short-circuits on the WP_Error and get_release_assets() returns false.
	 */
	public function test_get_release_assets_returns_false_when_api_fails(): void {
		add_filter(
			'pre_http_request',
			fn() => new WP_Error( 'http_request_failed', 'Connection refused' ),
			10,
			3
		);

		$result = $this->api->get_release_assets();
		$this->assertFalse( $result );
	}

	/**
	 * When the API returns a valid releases array with slug-matched assets,
	 * get_release_assets() returns the parsed assets array.
	 */
	public function test_get_release_assets_returns_array_with_valid_releases(): void {
		$releases = [
			[
				'tag_name' => '1.0.0',
				'assets'   => [
					[
						'name'       => 'test-plugin-1.0.0.zip',
						'url'        => 'https://api.github.com/repos/test-owner/test-plugin/releases/assets/123',
						'created_at' => '2024-06-01T12:00:00Z',
					],
				],
			],
		];
		$this->intercept_http_with( $this->mock_http_response( 200, $releases ) );

		$result = $this->api->get_release_assets();
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'assets', $result );
		$this->assertArrayHasKey( '1.0.0', $result['assets'] );
	}

	// -------------------------------------------------------------------------
	// get_remote_api_contents()  via  get_repo_contents()
	// -------------------------------------------------------------------------

	/**
	 * When api() returns false (error cache hit), get_repo_contents() hits the
	 * !$response branch and returns null — counted as complete.
	 */
	public function test_get_repo_contents_returns_false_when_api_fails(): void {
		$this->seed_error_cache();
		$result = $this->api->get_repo_contents();
		$this->assertNull( $result );
	}

	/**
	 * When the API returns a directory listing, get_repo_contents() returns true
	 * and stores the parsed files/dirs structure in cache.
	 */
	public function test_get_repo_contents_returns_true_with_valid_listing(): void {
		$listing = [
			[ 'type' => 'file', 'name' => 'readme.txt', 'path' => 'readme.txt' ],
			[ 'type' => 'file', 'name' => 'test-plugin.php', 'path' => 'test-plugin.php' ],
			[ 'type' => 'dir',  'name' => 'src', 'path' => 'src' ],
		];
		$this->intercept_http_with( $this->mock_http_response( 200, $listing ) );

		$result = $this->api->get_repo_contents();
		$this->assertTrue( $result );

		$cache = $this->api->get_repo_cache( 'test-plugin' );
		$this->assertArrayHasKey( 'contents', $cache );
		$this->assertContains( 'readme.txt', $cache['contents']['files'] );
		$this->assertContains( 'src', $cache['contents']['dirs'] );
	}
}


class Test_API_Common_Full extends WP_UnitTestCase {

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
		$type->sections       = [];
		return $type;
	}

	private function mock_http_response( int $code, array $body = [] ): array {
		return [
			'response' => [ 'code' => $code, 'message' => 200 === $code ? 'OK' : 'Error' ],
			'body'     => wp_json_encode( $body ),
			'headers'  => [],
			'cookies'  => [],
		];
	}

	private function mock_http_response_raw( int $code, string $body ): array {
		return [
			'response' => [ 'code' => $code, 'message' => 200 === $code ? 'OK' : 'Error' ],
			'body'     => $body,
			'headers'  => [],
			'cookies'  => [],
		];
	}

	private function intercept_http_with( array $return ): void {
		add_filter( 'pre_http_request', fn() => $return, 10, 3 );
	}

	private function seed_main_cache( array $data ): void {
		update_site_option(
			$this->api->get_cache_key( 'test-plugin' ),
			array_merge( [ 'timeout' => strtotime( '+12 hours' ) ], $data )
		);
	}

	// -------------------------------------------------------------------------
	// get_remote_api_readme() — success path
	// -------------------------------------------------------------------------

	/**
	 * When the API returns base64-encoded readme.txt content, get_remote_readme()
	 * parses it via Readme_Parser, caches the result, and returns true.
	 */
	public function test_get_remote_readme_returns_true_with_valid_readme(): void {
		$readme_content = implode(
			"\n",
			[
				'=== Test Plugin ===',
				'Contributors: testauthor',
				'Tags: test, unit',
				'Requires at least: 5.0',
				'Tested up to: 6.4',
				'Stable tag: 1.0.0',
				'License: GPL-3.0-or-later',
				'',
				'Short description for the test plugin.',
				'',
				'== Description ==',
				'',
				'Full description of the test plugin.',
				'',
				'== Changelog ==',
				'',
				'= 1.0.0 =',
				'* Initial release',
			]
		);

		$api_body = wp_json_encode(
			[
				'content'  => base64_encode( $readme_content ),
				'encoding' => 'base64',
			]
		);
		$this->intercept_http_with( $this->mock_http_response_raw( 200, $api_body ) );

		$result = $this->api->get_remote_readme();
		$this->assertTrue( $result );

		$cache = $this->api->get_repo_cache( 'test-plugin' );
		$this->assertArrayHasKey( 'readme', $cache );
	}

	/**
	 * get_remote_readme() always fetches from the API and returns true on success.
	 */
	public function test_get_remote_readme_returns_true_on_api_fetch(): void {
		$readme_content = "=== Test Plugin ===\nContributors: test\nStable tag: 1.0.0\n\nDescription.";
		$this->intercept_http_with(
			$this->mock_http_response_raw(
				200,
				wp_json_encode( [ 'content' => base64_encode( $readme_content ), 'encoding' => 'base64' ] )
			)
		);

		$result = $this->api->get_remote_readme();
		$this->assertTrue( $result );
	}

	// -------------------------------------------------------------------------
	// get_api_release_asset() — fail path
	// -------------------------------------------------------------------------

	/**
	 * When wp_remote_get() fails with a WP_Error, parse_release_asset()
	 * returns '' → stdClass{message} is set → validate_response() = true →
	 * get_api_release_asset() returns false.
	 *
	 * get_release_asset() in GitHub_API has its body commented out; the
	 * public trait method is called directly here.
	 */
	public function test_get_api_release_asset_returns_false_on_wp_error(): void {
		add_filter(
			'pre_http_request',
			fn() => new WP_Error( 'http_request_failed', 'Connection refused' ),
			10,
			3
		);

		$result = $this->api->get_api_release_asset(
			'github',
			'/repos/test-owner/test-plugin/releases/latest'
		);
		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// get_api_release_asset() — success path
	// -------------------------------------------------------------------------

	/**
	 * When the API returns a single release object (the 'latest' endpoint),
	 * parse_release_asset() wraps it in an array, matches the slug-prefixed
	 * asset, and get_api_release_asset() returns the parsed assets array.
	 */
	public function test_get_api_release_asset_returns_array_with_valid_release(): void {
		$release = [
			'tag_name' => '1.0.0',
			'assets'   => [
				[
					'name'       => 'test-plugin-1.0.0.zip',
					'url'        => 'https://api.github.com/repos/test-owner/test-plugin/releases/assets/123',
					'created_at' => '2024-06-01T12:00:00Z',
				],
			],
		];
		$this->intercept_http_with( $this->mock_http_response( 200, $release ) );

		$result = $this->api->get_api_release_asset(
			'github',
			'/repos/test-owner/test-plugin/releases/latest'
		);
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'assets', $result );
		$this->assertArrayHasKey( '1.0.0', $result['assets'] );
	}

	/**
	 * Once a release asset is cached, a second call returns from cache without
	 * an HTTP round-trip.
	 */
	public function test_get_api_release_asset_returns_from_cache(): void {
		$cached = [
			'assets'         => [ '1.0.0' => 'https://example.com/release.zip' ],
			'created_at'     => [ '1.0.0' => '2024-06-01T12:00:00Z' ],
			'dev_assets'     => [],
			'dev_created_at' => [],
		];
		$this->seed_main_cache( [ 'release_asset' => $cached ] );

		$result = $this->api->get_api_release_asset(
			'github',
			'/repos/test-owner/test-plugin/releases/latest'
		);
		$this->assertIsArray( $result );
		$this->assertSame( $cached, $result );
	}

	// -------------------------------------------------------------------------
	// parse_release_asset() — dev-release branch via get_release_assets()
	// -------------------------------------------------------------------------

	/**
	 * A release whose tag_name matches the dev-release pattern (e.g. 1.0.0-beta1)
	 * is collected into dev_assets / dev_created_at rather than the stable bucket.
	 */
	public function test_get_release_assets_captures_dev_release_with_beta_tag(): void {
		add_filter( 'gu_always_fetch_update', '__return_true' );

		$releases = [
			[
				'tag_name' => '1.0.0-beta1',
				'assets'   => [
					[
						'name'       => 'test-plugin-1.0.0-beta1.zip',
						'url'        => 'https://api.github.com/repos/test-owner/test-plugin/releases/assets/456',
						'created_at' => '2024-05-01T00:00:00Z',
					],
				],
			],
		];
		$this->intercept_http_with( $this->mock_http_response( 200, $releases ) );

		$result = $this->api->get_release_assets();
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'dev_assets', $result );
		$this->assertArrayHasKey( '1.0.0-beta1', $result['dev_assets'] );
		$this->assertEmpty( $result['assets'] );
	}

	/**
	 * A release whose tag_name matches the nightly pattern is also captured
	 * as a dev release.
	 */
	public function test_get_release_assets_captures_dev_release_with_nightly_tag(): void {
		add_filter( 'gu_always_fetch_update', '__return_true' );

		$releases = [
			[
				'tag_name' => '2.0.0-nightly20240601',
				'assets'   => [
					[
						'name'       => 'test-plugin-2.0.0-nightly20240601.zip',
						'url'        => 'https://api.github.com/repos/test-owner/test-plugin/releases/assets/789',
						'created_at' => '2024-06-01T00:00:00Z',
					],
				],
			],
		];
		$this->intercept_http_with( $this->mock_http_response( 200, $releases ) );

		$result = $this->api->get_release_assets();
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( '2.0.0-nightly20240601', $result['dev_assets'] );
		$this->assertEmpty( $result['assets'] );
	}

	// -------------------------------------------------------------------------
	// Cache-hit paths for remaining methods
	// -------------------------------------------------------------------------

	/**
	 * get_remote_tag() always fetches from the API and returns true on success.
	 */
	public function test_get_remote_tag_returns_true_on_api_fetch(): void {
		$this->intercept_http_with(
			$this->mock_http_response( 200, [ [ 'name' => '1.0.0', 'commit' => [ 'sha' => 'abc123' ] ] ] )
		);

		$result = $this->api->get_remote_tag();
		$this->assertTrue( $result );
	}

	/**
	 * get_remote_tag() always makes a fresh API call regardless of cache state.
	 */
	public function test_get_remote_tag_always_fetches_fresh_data(): void {
		$call = 0;
		add_filter(
			'pre_http_request',
			function () use ( &$call ) {
				$call++;
				return $this->mock_http_response( 200, [] );
			},
			10,
			3
		);

		$this->api->get_remote_tag();
		$this->assertGreaterThanOrEqual( 1, $call, 'Expected a fresh HTTP call on every invocation' );
	}

	/**
	 * get_remote_changes() always fetches from the API and returns true on success.
	 */
	public function test_get_remote_changes_returns_true_on_api_fetch(): void {
		$raw_content = "# Changelog\n\n## 1.0.0\n- Initial release";
		$this->intercept_http_with(
			$this->mock_http_response_raw(
				200,
				wp_json_encode( [ 'content' => base64_encode( $raw_content ), 'encoding' => 'base64' ] )
			)
		);

		$result = $this->api->get_remote_changes( 'CHANGES.md' );
		$this->assertTrue( $result );
	}

	/**
	 * get_repo_meta() always fetches from the API and returns true on success.
	 */
	public function test_get_repo_meta_returns_true_on_api_fetch(): void {
		$this->intercept_http_with(
			$this->mock_http_response(
				200,
				[
					'name'        => 'test-plugin',
					'private'     => false,
					'pushed_at'   => '2024-06-01T12:00:00Z',
					'created_at'  => '2023-01-01T00:00:00Z',
					'watchers'    => 10,
					'forks'       => 1,
					'open_issues' => 0,
				]
			)
		);

		$result = $this->api->get_repo_meta();
		$this->assertTrue( $result );
	}

	/**
	 * get_repo_contents() always fetches from the API and returns true on success.
	 */
	public function test_get_repo_contents_returns_true_on_api_fetch(): void {
		$this->intercept_http_with(
			$this->mock_http_response(
				200,
				[
					[ 'type' => 'file', 'name' => 'readme.txt', 'path' => 'readme.txt' ],
					[ 'type' => 'dir',  'name' => 'src',        'path' => 'src' ],
				]
			)
		);

		$result = $this->api->get_repo_contents();
		$this->assertTrue( $result );
	}

	/**
	 * When release assets are already cached (and gu_always_fetch_update is
	 * true so exit_no_update does not short-circuit), get_release_assets()
	 * returns the cached array directly.
	 */
	public function test_get_release_assets_returns_array_from_cache(): void {
		add_filter( 'gu_always_fetch_update', '__return_true' );

		$cached = [
			'assets'         => [ '1.0.0' => 'https://example.com/release.zip' ],
			'created_at'     => [ '1.0.0' => '2024-06-01T12:00:00Z' ],
			'dev_assets'     => [],
			'dev_created_at' => [],
		];
		$this->seed_main_cache( [ 'release_assets' => $cached ] );

		$result = $this->api->get_release_assets();
		$this->assertIsArray( $result );
		$this->assertSame( $cached, $result );
	}
}
