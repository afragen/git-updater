<?php
/**
 * Tests for API::get_release_asset_redirect() and set_redirect().
 *
 * Covers all non-ignored branches:
 * - !$asset early return
 * - AWS timeout unset path
 * - $_REQUEST['key'] slug matching
 * - exit_no_update gate → return false
 * - HTTP call path with no redirect → return $response
 * - Cached release_asset_redirect → return cached URL
 * - set_redirect() stores the location
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\API\GitHub_API;
use Fragen\Git_Updater\Base;

/**
 * Class Test_API_Release_Asset_Redirect
 */
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
		$this->type = $this->make_type();
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
