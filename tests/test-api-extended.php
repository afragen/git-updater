<?php
/**
 * Extended tests for API-layer methods not covered by test-api.php.
 *
 * Covers:
 * - API::sort_tags()         — version-descending uksort with v-prefix handling
 * - API::get_api_url()       — placeholder replacement + base URI assembly
 * - API::return_repo_type()  — host/git type data for GitHub
 * - GU_Trait::parse_extra_headers() — enterprise URI, Languages, CIJob,
 *                                     ReleaseAsset, PrimaryBranch, DID headers
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\API\GitHub_API;
use Fragen\Git_Updater\Base;

/**
 * Class Test_API_Extended
 */
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
		$this->type = $this->make_type();
		$this->api  = new GitHub_API( $this->type );
	}

	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'gu_api_repo_type_data' );
		remove_all_filters( 'gu_api_url_type' );
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

	// -------------------------------------------------------------------------
	// Reflection helpers
	// -------------------------------------------------------------------------

	private function call_sort_tags( array $tags ): bool {
		$rm = new ReflectionMethod( $this->api, 'sort_tags' );
		return $rm->invoke( $this->api, $tags );
	}

	private function call_return_repo_type(): array {
		$rm = new ReflectionMethod( $this->api, 'return_repo_type' );
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
