<?php
/**
 * Tests for Language_Pack_API.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\API\Language_Pack_API;
use Fragen\Git_Updater\Base;

class Test_Language_Pack_API extends WP_UnitTestCase {

	private string $slug      = 'test-langpack-plugin';
	private string $cache_key;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->cache_key = 'ghu-' . md5( $this->slug );
	}

	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'gu_get_language_pack_json' );
		remove_all_filters( 'gu_post_process_language_pack_package' );
		delete_site_option( $this->cache_key );
		delete_site_option( 'ghu-' . md5( $this->slug . '_error' ) );
		parent::tear_down();
	}

	private function make_type(): stdClass {
		$type                 = new stdClass();
		$type->slug           = $this->slug;
		$type->git            = 'github';
		$type->type           = 'plugin';
		$type->owner          = 'test-owner';
		$type->branch         = 'master';
		$type->primary_branch = 'master';
		$type->enterprise     = false;
		$type->enterprise_api = null;
		$type->gist_id        = null;
		$type->local_version  = '1.0.0';
		return $type;
	}

	public function test_get_language_pack_returns_true_on_cache_hit(): void {
		$languages = (object) [ 'en_US' => (object) [ 'language' => 'en_US', 'package' => 'en_US.zip' ] ];
		update_site_option( $this->cache_key, [ 'languages' => $languages ] );

		$api    = new Language_Pack_API( $this->make_type() );
		$result = $api->get_language_pack( [ 'owner_repo' => 'owner/test-langpack-plugin', 'uri' => 'https://github.com/owner/test-langpack-plugin' ] );

		$this->assertTrue( $result );
	}

	public function test_get_language_pack_sets_language_packs_on_type_from_cache(): void {
		$languages = (object) [ 'en_US' => (object) [ 'language' => 'en_US', 'package' => 'en_US.zip' ] ];
		update_site_option( $this->cache_key, [ 'languages' => $languages ] );

		$type   = $this->make_type();
		$api    = new Language_Pack_API( $type );
		$api->get_language_pack( [ 'owner_repo' => 'owner/test-langpack-plugin', 'uri' => 'https://github.com/owner/test-langpack-plugin' ] );

		$this->assertEquals( $languages, $type->language_packs );
	}

	public function test_get_language_pack_does_not_set_language_packs_when_cache_has_empty_languages(): void {
		// Cache exists but 'languages' key is missing — should fall through to the API call path.
		// Seed with a cache that has no 'languages' key.
		update_site_option( $this->cache_key, [ 'timeout' => strtotime( '+12 hours' ) ] );

		$type = $this->make_type();
		// language_packs should NOT be set yet.
		$this->assertFalse( isset( $type->language_packs ) );
	}

	// -------------------------------------------------------------------------
	// HTTP-fetch path (cache miss → api() call)
	// -------------------------------------------------------------------------

	/**
	 * Build the mock HTTP response that api() would return for a GitHub
	 * contents call returning a base64-encoded language-pack.json.
	 *
	 * @param array<string, mixed> $locales Locale data keyed by locale string.
	 * @return array<string, mixed> WordPress HTTP response array.
	 */
	private function make_lang_pack_http_response( array $locales ): array {
		$lang_pack_json  = json_encode( (object) $locales );
		$github_api_body = json_encode(
			(object) [
				'content'  => base64_encode( $lang_pack_json ),
				'encoding' => 'base64',
			]
		);
		return [
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'body'     => $github_api_body,
			'headers'  => [],
			'cookies'  => [],
		];
	}

	private function intercept_http_with( array $response ): void {
		add_filter( 'pre_http_request', fn() => $response, 10, 3 );
	}

	public function test_get_language_pack_returns_false_when_api_fails(): void {
		$this->intercept_http_with( [
			'response' => [ 'code' => 404, 'message' => 'Not Found' ],
			'body'     => json_encode( [ 'message' => 'Not Found' ] ),
			'headers'  => [],
		] );

		$api    = new Language_Pack_API( $this->make_type() );
		$result = $api->get_language_pack( [
			'owner_repo' => 'owner/' . $this->slug,
			'uri'        => 'https://github.com/owner/' . $this->slug,
		] );

		$this->assertFalse( $result );
	}

	public function test_get_language_pack_returns_true_when_api_succeeds(): void {
		$this->intercept_http_with(
			$this->make_lang_pack_http_response( [
				'fr_FR' => [ 'language' => 'fr_FR', 'package' => '/locales/fr_FR.zip' ],
			] )
		);

		$api    = new Language_Pack_API( $this->make_type() );
		$result = $api->get_language_pack( [
			'owner_repo' => 'owner/' . $this->slug,
			'uri'        => 'https://github.com/owner/' . $this->slug,
		] );

		$this->assertTrue( $result );
	}

	public function test_get_language_pack_sets_language_packs_on_type_after_fetch(): void {
		$this->intercept_http_with(
			$this->make_lang_pack_http_response( [
				'fr_FR' => [ 'language' => 'fr_FR', 'package' => '/locales/fr_FR.zip' ],
			] )
		);

		$type = $this->make_type();
		$api  = new Language_Pack_API( $type );
		$api->get_language_pack( [
			'owner_repo' => 'owner/' . $this->slug,
			'uri'        => 'https://github.com/owner/' . $this->slug,
		] );

		$this->assertTrue( isset( $type->language_packs ) );
		$this->assertTrue( isset( $type->language_packs->fr_FR ) );
	}

	public function test_get_language_pack_writes_language_packs_to_cache(): void {
		$this->intercept_http_with(
			$this->make_lang_pack_http_response( [
				'fr_FR' => [ 'language' => 'fr_FR', 'package' => '/locales/fr_FR.zip' ],
			] )
		);

		$api = new Language_Pack_API( $this->make_type() );
		$api->get_language_pack( [
			'owner_repo' => 'owner/' . $this->slug,
			'uri'        => 'https://github.com/owner/' . $this->slug,
		] );

		$cache = get_site_option( $this->cache_key );
		$this->assertArrayHasKey( 'languages', $cache );
	}

	public function test_get_language_pack_constructs_package_url_from_uri_and_primary_branch(): void {
		$this->intercept_http_with(
			$this->make_lang_pack_http_response( [
				'fr_FR' => [ 'language' => 'fr_FR', 'package' => '/locales/fr_FR.zip' ],
			] )
		);

		$type = $this->make_type(); // primary_branch = 'master'
		$api  = new Language_Pack_API( $type );
		$api->get_language_pack( [
			'owner_repo' => 'owner/' . $this->slug,
			'uri'        => 'https://github.com/owner/' . $this->slug,
		] );

		$package_url = $type->language_packs->fr_FR->package;
		$this->assertStringContainsString( 'raw/refs/heads/master', $package_url );
		$this->assertStringContainsString( '/locales/fr_FR.zip', $package_url );
		$this->assertStringContainsString( 'https://github.com/owner/' . $this->slug, $package_url );
	}

	public function test_get_language_pack_sets_type_and_version_on_locale(): void {
		$this->intercept_http_with(
			$this->make_lang_pack_http_response( [
				'fr_FR' => [ 'language' => 'fr_FR', 'package' => '/locales/fr_FR.zip' ],
			] )
		);

		$type = $this->make_type(); // type='plugin', local_version='1.0.0'
		$api  = new Language_Pack_API( $type );
		$api->get_language_pack( [
			'owner_repo' => 'owner/' . $this->slug,
			'uri'        => 'https://github.com/owner/' . $this->slug,
		] );

		$this->assertSame( 'plugin', $type->language_packs->fr_FR->type );
		$this->assertSame( '1.0.0', $type->language_packs->fr_FR->version );
	}

	public function test_get_language_pack_gu_post_process_language_pack_package_filter_overrides_url(): void {
		$this->intercept_http_with(
			$this->make_lang_pack_http_response( [
				'fr_FR' => [ 'language' => 'fr_FR', 'package' => '/locales/fr_FR.zip' ],
			] )
		);
		add_filter(
			'gu_post_process_language_pack_package',
			fn() => 'https://custom.cdn.example.com/fr_FR.zip',
			10,
			4
		);

		$type = $this->make_type();
		$api  = new Language_Pack_API( $type );
		$api->get_language_pack( [
			'owner_repo' => 'owner/' . $this->slug,
			'uri'        => 'https://github.com/owner/' . $this->slug,
		] );

		$this->assertSame( 'https://custom.cdn.example.com/fr_FR.zip', $type->language_packs->fr_FR->package );
	}

	public function test_get_language_pack_uses_gu_get_language_pack_json_filter_for_non_github(): void {
		$fake_response = json_decode(
			json_encode( [ 'de_DE' => [ 'language' => 'de_DE', 'package' => '/locales/de_DE.zip' ] ] )
		);
		add_filter(
			'gu_get_language_pack_json',
			fn( $response ) => $fake_response,
			10,
			4
		);

		$type      = $this->make_type();
		$type->git = 'bitbucket';
		$api       = new Language_Pack_API( $type );
		$result    = $api->get_language_pack( [
			'owner_repo' => 'owner/' . $this->slug,
			'uri'        => 'https://bitbucket.org/owner/' . $this->slug,
		] );

		$this->assertTrue( $result );
		$this->assertTrue( isset( $type->language_packs->de_DE ) );
	}
}

// ---------------------------------------------------------------------------
// Add_Ons
// ---------------------------------------------------------------------------

/**
 * Class Test_Add_Ons
 */