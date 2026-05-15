<?php
/**
 * Complete coverage tests for API_Common trait — branches and lines not exercised
 * by test-api-common.php, test-api-common-extended.php, or test-api-common-full.php.
 *
 * Critical line-coverage targets:
 *  - Line 82:  inner stable-asset foreach closes normally (no matching asset → no continue 2)
 *  - Line 92:  inner dev-asset foreach closes normally (no matching asset → no continue 2)
 *  - Line 154: return false when maybe_extend_repo_cache() returns true
 *  - Line 408: return false when validate_response() is true inside the branches fetch block
 *
 * Additional branch-coverage targets:
 *  - decode_response()        non-github git / github without content
 *  - parse_release_asset()    non-github/gitea git; tag matching neither stable nor dev regex
 *  - get_remote_api_changes() contents-cache filtering; fallthrough to second changelog
 *  - get_remote_api_readme()  contents-cache filtering; fallthrough to second readme
 *  - get_remote_api_assets()  contents-cache filtering; loop continues (object response);
 *                             $error flag set via message and via WP_Error
 *
 * HTTP is always mocked via pre_http_request.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\API\GitHub_API;
use Fragen\Git_Updater\Base;

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
		// Pre-seed ran so maybe_extend_repo_cache confirms all API calls completed.
		// The 'test-plugin' key is intentionally absent; api() will fetch it.
		$this->seed_cache(
			[
				'dot_org' => 'not in dot org',
				'ran'     => [ 'contents', 'assets', 'readme', 'changes', 'tags', 'branches', 'meta' ],
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
	// get_api_release_assets() — gate fires (lines 469-470)
	// -------------------------------------------------------------------------

	/**
	 * With no cache, no gu_always_fetch_update, and can_update_repo() = false
	 * (make_type() sets no remote_version/local_version), the gate
	 * `! $response && exit_no_update($response)` returns true → false at line 470.
	 * No HTTP request is made.
	 */
	public function test_get_release_assets_returns_false_from_no_update_gate(): void {
		$result = $this->api->get_release_assets();
		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// get_api_release_asset() — "No release asset found" path (lines 440-443)
	// -------------------------------------------------------------------------

	/**
	 * With gu_always_fetch_update = true (gate bypassed) and a WP_Error HTTP
	 * response, parse_release_asset() returns '' (falsy) → the inner
	 * `if (!$response)` block (lines 440-443) sets stdClass{message} →
	 * validate_response() = true → return false.
	 *
	 * Also covers parse_release_asset() line 59: `if (is_wp_error) return ''`.
	 */
	public function test_get_api_release_asset_wp_error_sets_no_asset_message(): void {
		add_filter( 'gu_always_fetch_update', '__return_true' );
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
	 * Lines 478-481 set stdClass{message} → validate_response() = true → false.
	 */
	public function test_get_release_assets_wp_error_sets_no_assets_message(): void {
		add_filter( 'gu_always_fetch_update', '__return_true' );
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
