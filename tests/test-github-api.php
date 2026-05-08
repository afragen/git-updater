<?php
/**
 * Tests for GitHub_API parse methods, link construction, and endpoint building.
 *
 * Covers:
 * - parse_tag_response()           — extract tag names from API response array
 * - parse_meta_response()          — map API response fields to meta array
 * - parse_changelog_response()     — extract changelog content
 * - parse_branch_response()        — build branch-keyed download + commit data
 * - parse_release_asset_response() — cache the release asset URL
 * - parse_tags()                   — filter prerelease tags, sort descending
 * - parse_contents_response()      — separate files from dirs
 * - parse_asset_dir_response()     — extract download URLs from asset directory
 * - construct_download_link()      — URL assembly for branches, tags, branch-switch
 * - add_endpoints()                — query-arg addition and enterprise prefix
 * - ratelimit_reset()              — header parsing and fallback
 * - remote_install()               — download link for github.com and enterprise
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\API\GitHub_API;
use Fragen\Git_Updater\Base;

/**
 * Class Test_GitHub_API_Parse
 *
 * Pure parse / response-transformation methods.
 */
class Test_GitHub_API_Parse extends WP_UnitTestCase {

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
		$type->release_asset  = false;
		$type->tags           = [];
		$type->newest_tag     = '';
		return $type;
	}

	/**
	 * Invoke a protected or private method via reflection.
	 *
	 * @param string $method Method name.
	 * @param mixed  ...$args Arguments to pass.
	 * @return mixed
	 */
	private function call_protected( string $method, ...$args ) {
		$rm = new ReflectionMethod( $this->api, $method );
		$rm->setAccessible( true );
		return $rm->invoke( $this->api, ...$args );
	}

	// -------------------------------------------------------------------------
	// parse_tag_response()
	// -------------------------------------------------------------------------

	public function test_parse_tag_response_returns_array_of_tag_names(): void {
		$response = [
			(object) [ 'name' => '1.0.0', 'commit' => (object) [ 'sha' => 'abc' ] ],
			(object) [ 'name' => '2.0.0', 'commit' => (object) [ 'sha' => 'def' ] ],
		];
		$result = $this->api->parse_tag_response( $response );
		$this->assertSame( [ '1.0.0', '2.0.0' ], $result );
	}

	public function test_parse_tag_response_with_invalid_response_returns_response_unchanged(): void {
		$response = (object) [ 'message' => 'Not Found' ];
		$result   = $this->api->parse_tag_response( $response );
		$this->assertSame( $response, $result );
	}

	public function test_parse_tag_response_with_empty_array_returns_empty_array(): void {
		$result = $this->api->parse_tag_response( [] );
		$this->assertSame( [], $result );
	}

	// -------------------------------------------------------------------------
	// parse_meta_response()
	// -------------------------------------------------------------------------

	public function test_parse_meta_response_extracts_all_fields(): void {
		$response              = new stdClass();
		$response->private     = true;
		$response->pushed_at   = '2024-01-15T10:00:00Z';
		$response->created_at  = '2023-06-01T00:00:00Z';
		$response->watchers    = 42;
		$response->forks       = 7;
		$response->open_issues = 3;

		$result = $this->api->parse_meta_response( $response );

		$this->assertTrue( $result['private'] );
		$this->assertSame( '2024-01-15T10:00:00Z', $result['last_updated'] );
		$this->assertSame( '2023-06-01T00:00:00Z', $result['added'] );
		$this->assertSame( 42, $result['watchers'] );
		$this->assertSame( 7, $result['forks'] );
		$this->assertSame( 3, $result['open_issues'] );
	}

	public function test_parse_meta_response_uses_defaults_for_missing_fields(): void {
		$response = new stdClass();
		$result   = $this->api->parse_meta_response( $response );
		$this->assertFalse( $result['private'] );
		$this->assertSame( '', $result['last_updated'] );
		$this->assertSame( '', $result['added'] );
		$this->assertSame( 0, $result['watchers'] );
		$this->assertSame( 0, $result['forks'] );
		$this->assertSame( 0, $result['open_issues'] );
	}

	public function test_parse_meta_response_with_invalid_response_returns_response_unchanged(): void {
		$response = (object) [ 'message' => 'Not Found' ];
		$result   = $this->api->parse_meta_response( $response );
		$this->assertSame( $response, $result );
	}

	// -------------------------------------------------------------------------
	// parse_changelog_response()
	// -------------------------------------------------------------------------

	public function test_parse_changelog_response_extracts_content(): void {
		$response          = new stdClass();
		$response->content = base64_encode( "# Changelog\n## 1.0.0\n- Initial release" );

		$result = $this->api->parse_changelog_response( $response );

		$this->assertArrayHasKey( 'changes', $result );
		$this->assertSame( $response->content, $result['changes'] );
	}

	public function test_parse_changelog_response_with_invalid_response_returns_response_unchanged(): void {
		$response = (object) [ 'message' => 'Not Found' ];
		$result   = $this->api->parse_changelog_response( $response );
		$this->assertSame( $response, $result );
	}

	// -------------------------------------------------------------------------
	// parse_branch_response()
	// -------------------------------------------------------------------------

	public function test_parse_branch_response_returns_array_keyed_by_branch_name(): void {
		$branch         = new stdClass();
		$branch->name   = 'develop';
		$branch->commit = (object) [
			'sha' => 'abc123',
			'url' => 'https://api.github.com/repos/test-owner/test-plugin/commits/abc123',
		];

		$result = $this->api->parse_branch_response( [ $branch ] );

		$this->assertArrayHasKey( 'develop', $result );
		$this->assertSame( 'abc123', $result['develop']['commit_hash'] );
		$this->assertStringContainsString( 'develop', $result['develop']['download'] );
	}

	public function test_parse_branch_response_download_contains_owner_and_slug(): void {
		$branch         = new stdClass();
		$branch->name   = 'main';
		$branch->commit = (object) [ 'sha' => 'def456', 'url' => '' ];

		$result = $this->api->parse_branch_response( [ $branch ] );

		$this->assertStringContainsString( 'test-owner', $result['main']['download'] );
		$this->assertStringContainsString( 'test-plugin', $result['main']['download'] );
	}

	public function test_parse_branch_response_with_invalid_response_returns_empty_array(): void {
		$response = (object) [ 'message' => 'Not Found' ];
		$result   = $this->api->parse_branch_response( $response );
		$this->assertSame( [], $result );
	}

	public function test_parse_branch_response_includes_commit_api_url(): void {
		$api_url        = 'https://api.github.com/repos/test-owner/test-plugin/commits/abc123';
		$branch         = new stdClass();
		$branch->name   = 'main';
		$branch->commit = (object) [ 'sha' => 'abc123', 'url' => $api_url ];

		$result = $this->api->parse_branch_response( [ $branch ] );

		$this->assertSame( $api_url, $result['main']['commit_api'] );
	}

	// -------------------------------------------------------------------------
	// parse_release_asset_response()
	// -------------------------------------------------------------------------

	public function test_parse_release_asset_response_caches_url(): void {
		$response      = new stdClass();
		$response->url = 'https://github.com/owner/repo/releases/download/v1.0.0/plugin.zip';

		$this->api->parse_release_asset_response( $response );

		$cache = $this->api->get_repo_cache();
		$this->assertSame( $response->url, $cache['release_asset_download'] );
	}

	public function test_parse_release_asset_response_with_invalid_response_sets_no_cache(): void {
		$response = (object) [ 'message' => 'Not Found' ];
		$this->api->parse_release_asset_response( $response );
		$cache = $this->api->get_repo_cache();
		$this->assertFalse( $cache );
	}

	public function test_parse_release_asset_response_without_url_property_sets_no_cache(): void {
		$response       = new stdClass();
		$response->name = 'plugin.zip';

		$this->api->parse_release_asset_response( $response );

		$cache = $this->api->get_repo_cache();
		$this->assertFalse( $cache );
	}

	// -------------------------------------------------------------------------
	// parse_tags() — protected
	// -------------------------------------------------------------------------

	public function test_parse_tags_excludes_prerelease_tags(): void {
		$repo_type = $this->call_protected( 'return_repo_type' );
		$tags      = [ '1.0.0', '2.0.0-beta', '2.0.0-rc1', '3.0.0-alpha' ];

		$result = $this->call_protected( 'parse_tags', $tags, $repo_type );

		$this->assertArrayHasKey( '1.0.0', $result );
		$this->assertArrayNotHasKey( '2.0.0-beta', $result );
		$this->assertArrayNotHasKey( '2.0.0-rc1', $result );
		$this->assertArrayNotHasKey( '3.0.0-alpha', $result );
	}

	public function test_parse_tags_sorts_descending_by_version(): void {
		$repo_type = $this->call_protected( 'return_repo_type' );
		$tags      = [ '1.0.0', '3.0.0', '2.0.0' ];

		$result = $this->call_protected( 'parse_tags', $tags, $repo_type );
		$keys   = array_keys( $result );

		$this->assertSame( '3.0.0', $keys[0] );
		$this->assertSame( '2.0.0', $keys[1] );
		$this->assertSame( '1.0.0', $keys[2] );
	}

	public function test_parse_tags_download_urls_contain_tag_name(): void {
		$repo_type = $this->call_protected( 'return_repo_type' );
		$result    = $this->call_protected( 'parse_tags', [ '1.2.3' ], $repo_type );

		$this->assertStringContainsString( '1.2.3', $result['1.2.3'] );
	}

	public function test_parse_tags_download_urls_contain_owner_and_slug(): void {
		$repo_type = $this->call_protected( 'return_repo_type' );
		$result    = $this->call_protected( 'parse_tags', [ '1.0.0' ], $repo_type );

		$this->assertStringContainsString( 'test-owner', $result['1.0.0'] );
		$this->assertStringContainsString( 'test-plugin', $result['1.0.0'] );
	}

	// -------------------------------------------------------------------------
	// parse_contents_response() — protected
	// -------------------------------------------------------------------------

	public function test_parse_contents_response_separates_files_and_dirs(): void {
		$response = [
			[ 'name' => 'plugin.php', 'type' => 'file' ],
			[ 'name' => 'assets',     'type' => 'dir'  ],
			[ 'name' => 'readme.txt', 'type' => 'file' ],
			[ 'name' => 'src',        'type' => 'dir'  ],
		];

		$result = $this->call_protected( 'parse_contents_response', $response );

		$this->assertSame( [ 'plugin.php', 'readme.txt' ], $result['files'] );
		$this->assertSame( [ 'assets', 'src' ], $result['dirs'] );
	}

	public function test_parse_contents_response_empty_input_returns_empty_arrays(): void {
		$result = $this->call_protected( 'parse_contents_response', [] );
		$this->assertSame( [ 'files' => [], 'dirs' => [] ], $result );
	}

	public function test_parse_contents_response_files_only(): void {
		$response = [
			[ 'name' => 'plugin.php', 'type' => 'file' ],
		];
		$result = $this->call_protected( 'parse_contents_response', $response );
		$this->assertSame( [ 'plugin.php' ], $result['files'] );
		$this->assertSame( [], $result['dirs'] );
	}

	// -------------------------------------------------------------------------
	// parse_asset_dir_response() — protected
	// -------------------------------------------------------------------------

	public function test_parse_asset_dir_response_returns_file_download_urls(): void {
		$asset               = new stdClass();
		$asset->type         = 'file';
		$asset->name         = 'plugin.zip';
		$asset->download_url = 'https://raw.githubusercontent.com/owner/repo/main/assets/plugin.zip';

		$result = $this->call_protected( 'parse_asset_dir_response', [ $asset ] );

		$this->assertIsArray( $result );
		$this->assertSame( $asset->download_url, $result['plugin.zip'] );
	}

	public function test_parse_asset_dir_response_returns_wp_error_unchanged(): void {
		$error  = new WP_Error( 'not_found', 'Not found' );
		$result = $this->call_protected( 'parse_asset_dir_response', $error );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_parse_asset_dir_response_with_message_property_returns_response_unchanged(): void {
		$response          = new stdClass();
		$response->message = 'Not Found';
		$result            = $this->call_protected( 'parse_asset_dir_response', $response );
		$this->assertSame( $response, $result );
	}

	public function test_parse_asset_dir_response_with_no_files_returns_object_with_message(): void {
		$dir       = new stdClass();
		$dir->type = 'dir';
		$dir->name = 'subdirectory';

		$result = $this->call_protected( 'parse_asset_dir_response', [ $dir ] );

		$this->assertIsObject( $result );
		$this->assertSame( 'No assets found', $result->message );
	}

	public function test_parse_asset_dir_response_skips_dirs_includes_only_files(): void {
		$file               = new stdClass();
		$file->type         = 'file';
		$file->name         = 'asset.zip';
		$file->download_url = 'https://example.com/asset.zip';

		$dir       = new stdClass();
		$dir->type = 'dir';
		$dir->name = 'subdir';

		$result = $this->call_protected( 'parse_asset_dir_response', [ $file, $dir ] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'asset.zip', $result );
		$this->assertArrayNotHasKey( 'subdir', $result );
	}
}

/**
 * Class Test_GitHub_API_Links
 *
 * Download link construction, endpoint building, rate-limit, and remote install.
 */
class Test_GitHub_API_Links extends WP_UnitTestCase {

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
		remove_all_filters( 'gu_post_construct_download_link' );
		delete_site_option( $this->api->get_cache_key( 'test-plugin' ) );
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
		$type->release_asset  = false;
		$type->tags           = [];
		$type->newest_tag     = '';
		return $type;
	}

	/**
	 * Set the protected static $method property via reflection.
	 */
	private function set_static_method( string $value ): void {
		$rp = new ReflectionProperty( GitHub_API::class, 'method' );
		$rp->setAccessible( true );
		$rp->setValue( null, $value );
	}

	// -------------------------------------------------------------------------
	// construct_download_link()
	// -------------------------------------------------------------------------

	public function test_construct_download_link_uses_branch_when_not_on_primary(): void {
		$this->type->branch         = 'develop';
		$this->type->primary_branch = 'master';

		$link = $this->api->construct_download_link();

		$this->assertStringContainsString( '/zipball/develop', $link );
	}

	public function test_construct_download_link_uses_newest_tag_on_primary_branch_with_tags(): void {
		$this->type->branch         = 'master';
		$this->type->primary_branch = 'master';
		$this->type->tags           = [ '2.0.0' => 'https://example.com/zipball/2.0.0' ];
		$this->type->newest_tag     = '2.0.0';

		$link = $this->api->construct_download_link();

		$this->assertStringContainsString( '/zipball/2.0.0', $link );
	}

	public function test_construct_download_link_uses_branch_on_primary_when_no_tags(): void {
		$this->type->branch         = 'master';
		$this->type->primary_branch = 'master';
		$this->type->tags           = [];

		$link = $this->api->construct_download_link();

		$this->assertStringContainsString( '/zipball/master', $link );
	}

	public function test_construct_download_link_uses_branch_switch_argument(): void {
		$link = $this->api->construct_download_link( 'feature-branch' );
		$this->assertStringContainsString( 'feature-branch', $link );
	}

	public function test_construct_download_link_contains_owner_and_slug(): void {
		$link = $this->api->construct_download_link();
		$this->assertStringContainsString( 'test-owner', $link );
		$this->assertStringContainsString( 'test-plugin', $link );
	}

	public function test_construct_download_link_is_filterable(): void {
		add_filter(
			'gu_post_construct_download_link',
			fn( $link ) => 'https://custom.example.com/download.zip',
			10,
			1
		);

		$link = $this->api->construct_download_link();

		$this->assertSame( 'https://custom.example.com/download.zip', $link );
	}

	// -------------------------------------------------------------------------
	// add_endpoints()
	// -------------------------------------------------------------------------

	public function test_add_endpoints_file_method_adds_ref_query_arg(): void {
		$this->set_static_method( 'file' );
		$result = $this->api->add_endpoints( $this->api, '/repos/owner/repo/contents/plugin.php' );
		$this->assertStringContainsString( 'ref=master', $result );
	}

	public function test_add_endpoints_readme_method_adds_ref_query_arg(): void {
		$this->set_static_method( 'readme' );
		$result = $this->api->add_endpoints( $this->api, '/repos/owner/repo/contents/readme.txt' );
		$this->assertStringContainsString( 'ref=master', $result );
	}

	public function test_add_endpoints_meta_method_returns_endpoint_unchanged(): void {
		$this->set_static_method( 'meta' );
		$endpoint = '/repos/owner/repo';
		$result   = $this->api->add_endpoints( $this->api, $endpoint );
		$this->assertSame( $endpoint, $result );
	}

	public function test_add_endpoints_tags_method_returns_endpoint_unchanged(): void {
		$this->set_static_method( 'tags' );
		$endpoint = '/repos/owner/repo/tags';
		$result   = $this->api->add_endpoints( $this->api, $endpoint );
		$this->assertSame( $endpoint, $result );
	}

	public function test_add_endpoints_branches_method_adds_per_page(): void {
		$this->set_static_method( 'branches' );
		$result = $this->api->add_endpoints( $this->api, '/repos/owner/repo/branches' );
		$this->assertStringContainsString( 'per_page=100', $result );
	}

	public function test_add_endpoints_with_enterprise_api_prepends_enterprise_base(): void {
		$this->set_static_method( 'meta' );
		$this->type->enterprise_api = 'https://github.example.com/api/v3';

		$result = $this->api->add_endpoints( $this->api, '/repos/owner/repo' );

		$this->assertStringStartsWith( 'https://github.example.com/api/v3', $result );
	}

	public function test_add_endpoints_assets_method_adds_ref_query_arg(): void {
		$this->set_static_method( 'assets' );
		$result = $this->api->add_endpoints( $this->api, '/repos/owner/repo/contents/assets' );
		$this->assertStringContainsString( 'ref=master', $result );
	}

	public function test_add_endpoints_changes_method_adds_ref_query_arg(): void {
		$this->set_static_method( 'changes' );
		$result = $this->api->add_endpoints( $this->api, '/repos/owner/repo/contents/CHANGES.md' );
		$this->assertStringContainsString( 'ref=master', $result );
	}

	public function test_add_endpoints_release_asset_method_returns_endpoint_unchanged(): void {
		$this->set_static_method( 'release_asset' );
		$endpoint = '/repos/owner/repo/releases/latest';
		$result   = $this->api->add_endpoints( $this->api, $endpoint );
		$this->assertSame( $endpoint, $result );
	}

	public function test_add_endpoints_translation_method_returns_endpoint_unchanged(): void {
		$this->set_static_method( 'translation' );
		$endpoint = '/repos/owner/repo/releases';
		$result   = $this->api->add_endpoints( $this->api, $endpoint );
		$this->assertSame( $endpoint, $result );
	}

	public function test_add_endpoints_download_link_method_returns_endpoint_unchanged(): void {
		$this->set_static_method( 'download_link' );
		$endpoint = '/repos/owner/repo/zipball/master';
		$result   = $this->api->add_endpoints( $this->api, $endpoint );
		$this->assertSame( $endpoint, $result );
	}

	public function test_add_endpoints_default_method_returns_endpoint_unchanged(): void {
		$this->set_static_method( 'unknown_method_xyz' );
		$endpoint = '/repos/owner/repo';
		$result   = $this->api->add_endpoints( $this->api, $endpoint );
		$this->assertSame( $endpoint, $result );
	}

	// -------------------------------------------------------------------------
	// ratelimit_reset()
	// -------------------------------------------------------------------------

	public function test_ratelimit_reset_returns_60_when_response_has_empty_headers(): void {
		$response = [
			'headers'  => [],
			'body'     => '',
			'response' => [ 'code' => 403 ],
		];

		$result = GitHub_API::ratelimit_reset( $response, 'test-plugin' );

		$this->assertSame( '60', $result );
	}

	public function test_ratelimit_reset_returns_wait_minutes_from_header(): void {
		$reset_time = time() + 300; // 5 minutes from now
		$response   = [
			'headers'  => new WpOrg\Requests\Utility\CaseInsensitiveDictionary(
				[ 'x-ratelimit-reset' => (string) $reset_time ]
			),
			'body'     => '',
			'response' => [ 'code' => 403 ],
		];

		$result = GitHub_API::ratelimit_reset( $response, 'test-plugin' );

		$this->assertIsString( $result );
		$this->assertNotSame( '60', $result, 'Should not fall back to default when header is present.' );
	}

	// -------------------------------------------------------------------------
	// remote_install()
	// -------------------------------------------------------------------------

	public function test_remote_install_constructs_github_com_api_download_link(): void {
		$headers = [
			'host'     => 'github.com',
			'uri'      => 'https://github.com/owner/repo',
			'base_uri' => '',
		];
		$install = [
			'git_updater_repo'   => 'owner/repo',
			'git_updater_branch' => 'main',
		];

		$result = $this->api->remote_install( $headers, $install );

		$this->assertStringContainsString( 'api.github.com', $result['download_link'] );
		$this->assertStringContainsString( 'owner/repo/zipball/main', $result['download_link'] );
	}

	public function test_remote_install_with_empty_host_uses_github_com(): void {
		$headers = [
			'host'     => '',
			'uri'      => 'https://github.com/owner/repo',
			'base_uri' => '',
		];
		$install = [
			'git_updater_repo'   => 'owner/repo',
			'git_updater_branch' => 'main',
		];

		$result = $this->api->remote_install( $headers, $install );

		$this->assertStringContainsString( 'api.github.com', $result['download_link'] );
	}

	public function test_remote_install_constructs_enterprise_download_link(): void {
		$headers = [
			'host'     => 'github.example.com',
			'uri'      => 'https://github.example.com/owner/repo',
			'base_uri' => 'https://github.example.com',
		];
		$install = [
			'git_updater_repo'   => 'owner/repo',
			'git_updater_branch' => 'main',
		];

		$result = $this->api->remote_install( $headers, $install );

		$this->assertStringContainsString( 'github.example.com/api/v3', $result['download_link'] );
		$this->assertStringContainsString( 'owner/repo/zipball/main', $result['download_link'] );
	}

	public function test_remote_install_uses_release_asset_uri_directly(): void {
		$release_url = 'https://github.com/owner/repo/releases/download/v1.0.0/plugin.zip';
		$headers     = [
			'host'     => 'github.com',
			'uri'      => $release_url,
			'base_uri' => '',
		];
		$install     = [
			'git_updater_repo'   => 'owner/repo',
			'git_updater_branch' => 'main',
		];

		$result = $this->api->remote_install( $headers, $install );

		$this->assertSame( $release_url, $result['download_link'] );
	}

	public function test_remote_install_saves_access_token_when_provided(): void {
		$headers = [
			'host'     => 'github.com',
			'uri'      => 'https://github.com/owner/repo',
			'base_uri' => '',
		];
		$install = [
			'git_updater_repo'        => 'owner/repo',
			'git_updater_branch'      => 'main',
			'github_access_token'     => 'ghp_test_token',
			'repo'                    => 'owner/repo',
			'options'                 => [],
		];

		$result = $this->api->remote_install( $headers, $install );

		$this->assertSame( 'ghp_test_token', $result['options']['owner/repo'] );
	}
}

/**
 * Class Test_GitHub_API_DownloadLink_ReleaseAsset
 *
 * Release asset paths in construct_download_link().
 */
class Test_GitHub_API_DownloadLink_ReleaseAsset extends WP_UnitTestCase {

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
		remove_all_filters( 'gu_post_construct_download_link' );
		remove_all_filters( 'gu_dev_release_asset' );
		add_filter( 'gu_always_fetch_update', '__return_false' );
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
		$type->release_asset  = true;
		$type->newest_tag     = '1.0.0';
		$type->tags           = [ '1.0.0' => 'https://github.com/test-owner/test-plugin/zipball/1.0.0' ];
		$type->branches       = (object) [ 'master' => [] ];
		return $type;
	}

	private function seed_cache( array $data ): void {
		update_site_option(
			$this->api->get_cache_key( 'test-plugin' ),
			array_merge( [ 'timeout' => strtotime( '+12 hours' ) ], $data )
		);
	}

	// -------------------------------------------------------------------------
	// construct_download_link() — release asset paths
	// -------------------------------------------------------------------------

	/**
	 * When get_release_assets() returns false (no-update gate fires),
	 * construct_download_link() returns an empty string.
	 */
	public function test_construct_download_link_returns_empty_when_release_assets_unavailable(): void {
		// Seed release_assets with a message object so validate_response returns true → get_api_release_assets returns false.
		$no_assets          = new stdClass();
		$no_assets->message = 'No release assets found';
		$this->seed_cache( [ 'release_assets' => $no_assets ] );

		$result = $this->api->construct_download_link();

		$this->assertSame( '', $result );
	}

	/**
	 * When the cache already has a release_asset_download URL,
	 * construct_download_link() returns it immediately.
	 */
	public function test_construct_download_link_returns_cached_release_asset_download(): void {
		$cached_url = 'https://github.com/test-owner/test-plugin/releases/download/v1.0.0/plugin.zip';
		$this->seed_cache(
			[
				'release_assets'         => [
					'assets'     => [ '1.0.0' => $cached_url ],
					'dev_assets' => [],
				],
				'release_asset_download' => $cached_url,
			]
		);

		$result = $this->api->construct_download_link();

		$this->assertSame( $cached_url, $result );
	}

	/**
	 * When release_assets is in cache but release_asset_download is not,
	 * construct_download_link() falls through to get_release_asset_redirect().
	 * With asset=false (empty assets array), get_release_asset_redirect returns false.
	 */
	public function test_construct_download_link_calls_redirect_when_no_cached_download(): void {
		$this->seed_cache(
			[
				'release_assets' => [
					'assets'     => [],
					'dev_assets' => [],
				],
			]
		);

		$result = $this->api->construct_download_link();

		// get_release_asset_redirect(false, true) returns false when !$asset.
		$this->assertFalse( $result );
	}

	/**
	 * When gu_dev_release_asset filter returns true and the dev asset version is
	 * newer than the stable asset version, the dev asset URL is selected (lines 171-174).
	 * The call ultimately returns false because get_release_asset_redirect() exits
	 * via exit_no_update (no gu_always_fetch_update filter set).
	 */
	public function test_construct_download_link_uses_dev_asset_when_dev_release_asset_filter_true(): void {
		$stable_url = 'https://github.com/test-owner/test-plugin/releases/download/v1.0.0/plugin.zip';
		$dev_url    = 'https://github.com/test-owner/test-plugin/releases/download/v2.0.0-beta1/plugin-beta.zip';

		$this->seed_cache(
			[
				'release_assets' => [
					'assets'     => [ '1.0.0' => $stable_url ],
					'dev_assets' => [ '2.0.0-beta1' => $dev_url ],
				],
			]
		);

		add_filter( 'gu_dev_release_asset', '__return_true' );

		$result = $this->api->construct_download_link();

		// exit_no_update fires inside get_release_asset_redirect() → returns false.
		$this->assertFalse( $result );
	}
}
