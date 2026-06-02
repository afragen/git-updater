<?php

use Fragen\Git_Updater\REST\REST_API;
use Fragen\Git_Updater\Base;
use Fragen\Git_Updater\Remote_Management;

/**
 * Subclass that captures the file path instead of streaming + exit,
 * and allows overriding build_download_metadata() for isolated proxy tests.
 */
class REST_API_Testable_Download extends REST_API {

	/** @var string|null Captured file path from send_file_response(). */
	public ?string $captured_file = null;

	/** @var string|null Captured filename from send_file_response(). */
	public ?string $captured_filename = null;

	/** @var string|null Captured temp file path. */
	public ?string $captured_temp_file = null;

	/** @var array<string, mixed>|WP_Error|null If set, returned by build_download_metadata(). */
	public array|WP_Error|null $mock_metadata = null;

	protected function send_file_response( string $file, string $filename, string $temp_file ): void {
		$this->captured_file      = $file;
		$this->captured_filename  = $filename;
		$this->captured_temp_file = $temp_file;
	}

	protected function build_download_metadata( string $slug ): array|WP_Error {
		if ( null !== $this->mock_metadata ) {
			return $this->mock_metadata;
		}
		return parent::build_download_metadata( $slug );
	}
}

/**
 * Functional tests for the signed download proxy.
 *
 * Exercises sign_download_url(), verify_download_signature(), and proxy_download().
 */
class Test_REST_Download_Proxy extends GU_Test_Case {

	private const SLUG    = 'test-gu-private';
	private const API_KEY = 'test-download-proxy-key';

	private REST_API_Testable_Download $rest;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->rest = new REST_API_Testable_Download();

		update_site_option( 'git_updater_api_key', self::API_KEY );
		$prop = ( new ReflectionClass( Remote_Management::class ) )->getProperty( 'api_key' );
		$prop->setAccessible( true );
		$prop->setValue( null, self::API_KEY );
	}

	public function tear_down(): void {
		delete_site_option( 'git_updater_api_key' );
		delete_site_option( 'git_updater_additions' );

		if ( $this->rest->captured_temp_file && file_exists( $this->rest->captured_temp_file ) ) {
			wp_delete_file( $this->rest->captured_temp_file );
		}
		if ( $this->rest->captured_file && file_exists( $this->rest->captured_file ) ) {
			wp_delete_file( $this->rest->captured_file );
		}

		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// sign_download_url() — private, accessed via reflection
	// -------------------------------------------------------------------------

	public function test_sign_download_url_returns_valid_rest_url(): void {
		$url = $this->call_sign_download_url( self::SLUG );

		$this->assertStringContainsString( 'rest_route=', $url );
		$this->assertStringContainsString( rawurlencode( '/git-updater/v1/download/' . self::SLUG ), $url );
		$this->assertStringContainsString( 'expires=', $url );
		$this->assertStringContainsString( 'signature=', $url );
	}

	public function test_sign_download_url_expires_in_future(): void {
		$params = $this->parse_signed_url( $this->call_sign_download_url( self::SLUG ) );

		$this->assertArrayHasKey( 'expires', $params );
		$this->assertGreaterThan( time(), (int) $params['expires'] );
	}

	public function test_sign_download_url_signature_is_64_hex_chars(): void {
		$params = $this->parse_signed_url( $this->call_sign_download_url( self::SLUG ) );

		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $params['signature'] );
	}

	public function test_sign_download_url_custom_ttl(): void {
		$params  = $this->parse_signed_url( $this->call_sign_download_url( self::SLUG, 600 ) );
		$minimum = time() + 599;

		$this->assertGreaterThanOrEqual( $minimum, (int) $params['expires'] );
	}

	// -------------------------------------------------------------------------
	// verify_download_signature() — private, accessed via reflection
	// -------------------------------------------------------------------------

	public function test_verify_valid_signature_returns_true(): void {
		$expires   = time() + 300;
		$signature = $this->generate_signature( self::SLUG, $expires );

		$this->assertTrue( $this->call_verify( self::SLUG, $expires, $signature ) );
	}

	public function test_verify_expired_signature_returns_false(): void {
		$expires   = time() - 10;
		$signature = $this->generate_signature( self::SLUG, $expires );

		$this->assertFalse( $this->call_verify( self::SLUG, $expires, $signature ) );
	}

	public function test_verify_tampered_signature_returns_false(): void {
		$expires   = time() + 300;
		$signature = $this->generate_signature( self::SLUG, $expires );
		$tampered  = substr( $signature, 0, -1 ) . ( $signature[-1] === 'a' ? 'b' : 'a' );

		$this->assertFalse( $this->call_verify( self::SLUG, $expires, $tampered ) );
	}

	public function test_verify_wrong_slug_returns_false(): void {
		$expires   = time() + 300;
		$signature = $this->generate_signature( self::SLUG, $expires );

		$this->assertFalse( $this->call_verify( 'different-slug', $expires, $signature ) );
	}

	public function test_sign_then_verify_round_trips(): void {
		$params = $this->parse_signed_url( $this->call_sign_download_url( self::SLUG, 120 ) );

		$this->assertTrue(
			$this->call_verify( self::SLUG, (int) $params['expires'], $params['signature'] )
		);
	}

	public function test_different_slugs_produce_different_signatures(): void {
		$expires = time() + 300;

		$this->assertNotSame(
			$this->generate_signature( 'slug-a', $expires ),
			$this->generate_signature( 'slug-b', $expires )
		);
	}

	public function test_different_expires_produce_different_signatures(): void {
		$this->assertNotSame(
			$this->generate_signature( self::SLUG, time() + 100 ),
			$this->generate_signature( self::SLUG, time() + 200 )
		);
	}

	// -------------------------------------------------------------------------
	// proxy_download() — error paths
	// -------------------------------------------------------------------------

	public function test_proxy_returns_403_for_expired_signature(): void {
		$expires   = time() - 10;
		$signature = $this->generate_signature( self::SLUG, $expires );

		$result = $this->rest->proxy_download( $this->make_download_request( self::SLUG, $expires, $signature ) );

		$this->assertWPError( $result );
		$this->assertSame( 'gu_invalid_signature', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	public function test_proxy_returns_403_for_invalid_signature(): void {
		$result = $this->rest->proxy_download( $this->make_download_request( self::SLUG, time() + 300, str_repeat( '0', 64 ) ) );

		$this->assertWPError( $result );
		$this->assertSame( 'gu_invalid_signature', $result->get_error_code() );
	}

	public function test_proxy_passes_through_metadata_error(): void {
		$this->rest->mock_metadata = new WP_Error( 'gu_repo_not_found', 'Specified repo does not exist.', [ 'status' => 404 ] );

		$expires   = time() + 300;
		$signature = $this->generate_signature( self::SLUG, $expires );
		$result    = $this->rest->proxy_download( $this->make_download_request( self::SLUG, $expires, $signature ) );

		$this->assertWPError( $result );
		$this->assertSame( 'gu_repo_not_found', $result->get_error_code() );
	}

	public function test_proxy_returns_404_when_no_download_link(): void {
		$this->rest->mock_metadata = [ 'download_link' => '' ];

		$expires   = time() + 300;
		$signature = $this->generate_signature( self::SLUG, $expires );
		$result    = $this->rest->proxy_download( $this->make_download_request( self::SLUG, $expires, $signature ) );

		$this->assertWPError( $result );
		$this->assertSame( 'gu_no_download_link', $result->get_error_code() );
		$this->assertSame( 404, $result->get_error_data()['status'] );
	}

	public function test_proxy_returns_502_on_upstream_wp_error(): void {
		$this->rest->mock_metadata = [ 'download_link' => 'https://example.com/package.zip' ];
		add_filter( 'pre_http_request', fn() => new WP_Error( 'http_request_failed', 'Connection refused' ) );

		$expires   = time() + 300;
		$signature = $this->generate_signature( self::SLUG, $expires );
		$result    = $this->rest->proxy_download( $this->make_download_request( self::SLUG, $expires, $signature ) );

		$this->assertWPError( $result );
		$this->assertSame( 'gu_upstream_error', $result->get_error_code() );
		$this->assertSame( 502, $result->get_error_data()['status'] );

		remove_all_filters( 'pre_http_request' );
	}

	public function test_proxy_returns_502_on_non_200_upstream_status(): void {
		$this->rest->mock_metadata = [ 'download_link' => 'https://example.com/package.zip' ];
		add_filter(
			'pre_http_request',
			fn() => [
				'body'     => 'Not Found',
				'response' => [ 'code' => 404 ],
				'headers'  => new WpOrg\Requests\Utility\CaseInsensitiveDictionary(),
			]
		);

		$expires   = time() + 300;
		$signature = $this->generate_signature( self::SLUG, $expires );
		$result    = $this->rest->proxy_download( $this->make_download_request( self::SLUG, $expires, $signature ) );

		$this->assertWPError( $result );
		$this->assertSame( 'gu_upstream_http_error', $result->get_error_code() );
		$this->assertSame( 502, $result->get_error_data()['status'] );

		remove_all_filters( 'pre_http_request' );
	}

	// -------------------------------------------------------------------------
	// proxy_download() — happy path with mocked metadata + HTTP
	// -------------------------------------------------------------------------

	public function test_proxy_streams_upstream_file_on_valid_signature(): void {
		$zip_content = $this->create_dummy_zip( 'plugin-file.php', '<?php // test plugin' );
		$zip_url     = 'https://example.com/package.zip';

		$this->rest->mock_metadata = [ 'download_link' => $zip_url ];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $zip_url, $zip_content ) {
				if ( $url === $zip_url ) {
					if ( ! empty( $args['filename'] ) ) {
						file_put_contents( $args['filename'], $zip_content );
					}
					return [
						'body'     => $args['filename'] ?? $zip_content,
						'response' => [ 'code' => 200 ],
						'headers'  => new WpOrg\Requests\Utility\CaseInsensitiveDictionary(),
					];
				}
				return $preempt;
			},
			10,
			3
		);

		$expires   = time() + 300;
		$signature = $this->generate_signature( self::SLUG, $expires );
		$result    = $this->rest->proxy_download( $this->make_download_request( self::SLUG, $expires, $signature ) );

		$this->assertNotWPError( $result, 'Expected success but got: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
		$this->assertNotNull( $this->rest->captured_file, 'send_file_response was not called' );
		$this->assertFileExists( $this->rest->captured_file );
		$this->assertSame( self::SLUG . '.zip', $this->rest->captured_filename );
		$this->assertSame( $zip_content, file_get_contents( $this->rest->captured_file ) );

		remove_all_filters( 'pre_http_request' );
	}

	public function test_proxy_passes_auth_headers_to_upstream(): void {
		$zip_content   = $this->create_dummy_zip( 'test.php', '<?php' );
		$zip_url       = 'https://example.com/package.zip';
		$captured_args = null;

		$this->rest->mock_metadata = [
			'download_link' => $zip_url,
			'auth_header'   => [
				'headers' => [ 'Authorization' => 'token ghp_test123' ],
			],
		];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $zip_url, $zip_content, &$captured_args ) {
				if ( $url === $zip_url ) {
					$captured_args = $args;
					if ( ! empty( $args['filename'] ) ) {
						file_put_contents( $args['filename'], $zip_content );
					}
					return [
						'body'     => $args['filename'] ?? $zip_content,
						'response' => [ 'code' => 200 ],
						'headers'  => new WpOrg\Requests\Utility\CaseInsensitiveDictionary(),
					];
				}
				return $preempt;
			},
			10,
			3
		);

		$expires   = time() + 300;
		$signature = $this->generate_signature( self::SLUG, $expires );
		$this->rest->proxy_download( $this->make_download_request( self::SLUG, $expires, $signature ) );

		$this->assertNotNull( $captured_args, 'Upstream request was never made' );
		$this->assertArrayHasKey( 'headers', $captured_args );
		$this->assertSame( 'token ghp_test123', $captured_args['headers']['Authorization'] );

		remove_all_filters( 'pre_http_request' );
	}

	public function test_proxy_merges_auth_header_with_stream_args(): void {
		$zip_content   = $this->create_dummy_zip( 'test.php', '<?php' );
		$zip_url       = 'https://example.com/package.zip';
		$captured_args = null;

		$this->rest->mock_metadata = [
			'download_link' => $zip_url,
			'auth_header'   => [
				'headers' => [
					'Authorization' => 'Bearer glpat-abc123',
					'Accept'        => 'application/octet-stream',
				],
			],
		];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $zip_url, $zip_content, &$captured_args ) {
				if ( $url === $zip_url ) {
					$captured_args = $args;
					if ( ! empty( $args['filename'] ) ) {
						file_put_contents( $args['filename'], $zip_content );
					}
					return [
						'body'     => $args['filename'] ?? $zip_content,
						'response' => [ 'code' => 200 ],
						'headers'  => new WpOrg\Requests\Utility\CaseInsensitiveDictionary(),
					];
				}
				return $preempt;
			},
			10,
			3
		);

		$expires   = time() + 300;
		$signature = $this->generate_signature( self::SLUG, $expires );
		$this->rest->proxy_download( $this->make_download_request( self::SLUG, $expires, $signature ) );

		$this->assertNotNull( $captured_args );
		$this->assertTrue( $captured_args['stream'] );
		$this->assertNotEmpty( $captured_args['filename'] );
		$this->assertSame( 'Bearer glpat-abc123', $captured_args['headers']['Authorization'] );
		$this->assertSame( 'application/octet-stream', $captured_args['headers']['Accept'] );

		remove_all_filters( 'pre_http_request' );
	}

	public function test_proxy_sends_temp_file_as_response_body(): void {
		$zip_content = $this->create_dummy_zip( 'test.php', '<?php' );
		$zip_url     = 'https://example.com/package.zip';

		$this->rest->mock_metadata = [ 'download_link' => $zip_url ];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $zip_url, $zip_content ) {
				if ( $url === $zip_url ) {
					if ( ! empty( $args['filename'] ) ) {
						file_put_contents( $args['filename'], $zip_content );
					}
					return [
						'body'     => $args['filename'] ?? $zip_content,
						'response' => [ 'code' => 200 ],
						'headers'  => new WpOrg\Requests\Utility\CaseInsensitiveDictionary(),
					];
				}
				return $preempt;
			},
			10,
			3
		);

		$expires   = time() + 300;
		$signature = $this->generate_signature( self::SLUG, $expires );
		$this->rest->proxy_download( $this->make_download_request( self::SLUG, $expires, $signature ) );

		// When streaming, wp_remote_retrieve_body returns the temp file path,
		// so send_file_response receives the same path for both $file and $temp_file.
		$this->assertNotNull( $this->rest->captured_file );
		$this->assertNotNull( $this->rest->captured_temp_file );
		$this->assertSame( $zip_content, file_get_contents( $this->rest->captured_file ) );

		remove_all_filters( 'pre_http_request' );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function call_sign_download_url( string $slug, int $ttl = 300 ): string {
		$method = new ReflectionMethod( REST_API::class, 'sign_download_url' );
		$method->setAccessible( true );
		return $method->invoke( $this->rest, $slug, $ttl );
	}

	private function call_verify( string $slug, int $expires, string $signature ): bool {
		$method = new ReflectionMethod( REST_API::class, 'verify_download_signature' );
		$method->setAccessible( true );
		return $method->invoke( $this->rest, $slug, $expires, $signature );
	}

	private function generate_signature( string $slug, int $expires ): string {
		return hash_hmac( 'sha256', $slug . '|' . $expires, wp_salt( 'auth' ) );
	}

	private function make_download_request( string $slug, int $expires, string $signature ): WP_REST_Request {
		$request = new WP_REST_Request( 'GET', '/git-updater/v1/download/' . $slug );
		$request->set_param( 'slug', $slug );
		$request->set_param( 'expires', $expires );
		$request->set_param( 'signature', $signature );
		return $request;
	}

	private function parse_signed_url( string $url ): array {
		parse_str( parse_url( $url, PHP_URL_QUERY ) ?? '', $params );
		return $params;
	}

	private function create_dummy_zip( string $filename, string $content ): string {
		$tmp = tempnam( sys_get_temp_dir(), 'gu_zip_test_' );
		$zip = new ZipArchive();
		$zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		$zip->addFromString( $filename, $content );
		$zip->close();
		$data = file_get_contents( $tmp );
		wp_delete_file( $tmp );
		return $data;
	}
}
