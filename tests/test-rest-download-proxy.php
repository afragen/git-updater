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

	private function call_sign_download_url( string $slug, int $ttl = 43200 ): string {
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

	// -------------------------------------------------------------------------
	// Diagnostic: PCLZIP_ERR_BAD_FORMAT root cause investigation
	//
	// These tests probe what happens when the upstream returns non-zip content
	// or when output buffering corrupts the proxy response. They verify that
	// the proxy correctly propagates errors rather than streaming corrupt data.
	// -------------------------------------------------------------------------

	/**
	 * When upstream returns HTML error page on HTTP 200, does proxy_download()
	 * stream the HTML to the client (causing PCLZIP), or return an error?
	 */
	public function test_proxy_streaming_upstream_html_on_200_writes_html_to_file(): void {
		$html    = '<html><body><h1>502 Bad Gateway</h1></body></html>';
		$zip_url = 'https://example.com/package.zip';

		$this->rest->mock_metadata = [ 'download_link' => $zip_url ];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $zip_url, $html ) {
				if ( $url === $zip_url ) {
					// Simulate what happens when upstream returns HTML:
					// wp_remote_get streams to temp file, body returns file path.
					if ( ! empty( $args['filename'] ) ) {
						file_put_contents( $args['filename'], $html );
					}
					return [
						'body'     => $args['filename'] ?? $html,
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

		remove_all_filters( 'pre_http_request' );

		// This is the PCLZIP root cause: proxy_download succeeds and streams
		// the HTML content to the client. WordPress tries to unzip HTML → PCLZIP fails.
		// After fix: returns WP_Error. Before fix: streams HTML to client.
		if ( is_wp_error( $result ) ) {
			$this->assertSame( 'gu_not_a_zip', $result->get_error_code() );
		} else {
			$this->assertNotNull( $this->rest->captured_file, 'send_file_response was called with HTML content' );
			$this->assertFileExists( $this->rest->captured_file );
			$this->assertSame( $html, file_get_contents( $this->rest->captured_file ) );
			$this->assertStringNotContainsString( "PK\x03\x04", $html, 'Captured file does not contain zip magic bytes — this IS the PCLZIP root cause' );
		}
	}

	/**
	 * When upstream returns empty body on HTTP 200, what does proxy_download() do?
	 */
	public function test_proxy_streaming_upstream_empty_body_on_200(): void {
		$zip_url = 'https://example.com/package.zip';

		$this->rest->mock_metadata = [ 'download_link' => $zip_url ];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $zip_url ) {
				if ( $url === $zip_url ) {
					// Simulate upstream returning empty body.
					if ( ! empty( $args['filename'] ) ) {
						file_put_contents( $args['filename'], '' );
					}
					return [
						'body'     => $args['filename'] ?? '',
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

		remove_all_filters( 'pre_http_request' );

		// Empty body on 200 should be an error, not a 0-byte file.
		if ( is_wp_error( $result ) ) {
			$this->assertSame( 'gu_not_a_zip', $result->get_error_code() );
		} else {
			$this->assertNotNull( $this->rest->captured_file );
			$this->assertSame( 0, filesize( $this->rest->captured_file ), 'Empty upstream body produced a 0-byte file — this causes PCLZIP' );
		}
	}

	/**
	 * When output buffering precedes readfile(), does the proxy output get
	 * corrupted with prepended content?
	 */
	public function test_proxy_output_buffering_corrupts_file_output(): void {
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

		// Simulate output buffering that send_file_response's ob_end_clean() would
		// normally strip — but if ob_get_level() is 0, buffering is not cleared.
		ob_start();
		echo 'some prior output';

		$result = $this->rest->proxy_download( $this->make_download_request( self::SLUG, $expires, $signature ) );

		$output = ob_get_clean();

		remove_all_filters( 'pre_http_request' );

		$this->assertNotWPError( $result );

		// The captured file should contain ONLY the zip content, no buffered output.
		$this->assertNotNull( $this->rest->captured_file );
		$captured = file_get_contents( $this->rest->captured_file );
		$this->assertSame( $zip_content, $captured, 'send_file_response received clean content (no output buffer corruption)' );

		// Verify the buffered output was NOT mixed into the file.
		$this->assertStringNotContainsString( 'some prior output', $captured, 'Output buffer was not mixed into file content' );
	}

	/**
	 * When upstream returns a 502 with HTML body, does download_url() on the
	 * client side get a WP_Error (safe) or a file with HTML content (dangerous)?
	 *
	 * This simulates the full proxy flow: proxy_download returns WP_Error for
	 * non-200, which download_url() converts to WP_Error → client returns error.
	 */
	public function test_proxy_error_for_upstream_502_is_propagated_as_wp_error(): void {
		$html    = '<html><body>Bad Gateway</body></html>';
		$zip_url = 'https://example.com/package.zip';

		$this->rest->mock_metadata = [ 'download_link' => $zip_url ];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $zip_url, $html ) {
				if ( $url === $zip_url ) {
					return [
						'body'     => $html,
						'response' => [ 'code' => 502 ],
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

		remove_all_filters( 'pre_http_request' );

		// Non-200 upstream MUST return WP_Error, never stream corrupt content.
		$this->assertWPError( $result, 'Non-200 upstream should return WP_Error, not stream HTML' );
		$this->assertSame( 'gu_upstream_http_error', $result->get_error_code() );
	}

	/**
	 * Verify that the proxy correctly handles upstream returning a 200 with
	 * Content-Type text/html (e.g., a GitHub 404 page served with 200 status).
	 */
	public function test_proxy_does_not_validate_content_type(): void {
		$html    = '<!DOCTYPE html><html><head><title>404</title></head><body>Not Found</body></html>';
		$zip_url = 'https://example.com/package.zip';

		$this->rest->mock_metadata = [ 'download_link' => $zip_url ];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $zip_url, $html ) {
				if ( $url === $zip_url ) {
					if ( ! empty( $args['filename'] ) ) {
						file_put_contents( $args['filename'], $html );
					}
					return [
						'body'     => $args['filename'] ?? $html,
						'response' => [ 'code' => 200 ],
						'headers'  => new WpOrg\Requests\Utility\CaseInsensitiveDictionary( [ 'content-type' => 'text/html' ] ),
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

		remove_all_filters( 'pre_http_request' );

		// Content-Type validation is not checked — zip magic byte validation catches this.
		if ( is_wp_error( $result ) ) {
			$this->assertSame( 'gu_not_a_zip', $result->get_error_code() );
		} else {
			$this->assertNotNull( $this->rest->captured_file );
			$this->assertStringNotContainsString( "PK\x03\x04", file_get_contents( $this->rest->captured_file ),
				'Proxy streamed non-zip content — Content-Type validation is missing' );
		}
	}

	// -------------------------------------------------------------------------
	// Zip magic byte validation (gu_not_a_zip)
	// -------------------------------------------------------------------------

	public function test_proxy_returns_error_when_upstream_returns_html_on_200(): void {
		$html    = '<html><body><h1>502 Bad Gateway</h1></body></html>';
		$zip_url = 'https://example.com/package.zip';

		$this->rest->mock_metadata = [ 'download_link' => $zip_url ];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $zip_url, $html ) {
				if ( $url === $zip_url ) {
					if ( ! empty( $args['filename'] ) ) {
						file_put_contents( $args['filename'], $html );
					}
					return [
						'body'     => $args['filename'] ?? $html,
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

		remove_all_filters( 'pre_http_request' );

		$this->assertWPError( $result );
		$this->assertSame( 'gu_not_a_zip', $result->get_error_code() );
		$this->assertSame( 502, $result->get_error_data()['status'] );
	}

	public function test_proxy_returns_error_when_upstream_returns_empty_file_on_200(): void {
		$zip_url = 'https://example.com/package.zip';

		$this->rest->mock_metadata = [ 'download_link' => $zip_url ];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $zip_url ) {
				if ( $url === $zip_url ) {
					if ( ! empty( $args['filename'] ) ) {
						file_put_contents( $args['filename'], '' );
					}
					return [
						'body'     => $args['filename'] ?? '',
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

		remove_all_filters( 'pre_http_request' );

		$this->assertWPError( $result );
		$this->assertSame( 'gu_not_a_zip', $result->get_error_code() );
	}

	public function test_proxy_returns_error_when_upstream_returns_plaintext_on_200(): void {
		$plain   = 'This is not a zip file';
		$zip_url = 'https://example.com/package.zip';

		$this->rest->mock_metadata = [ 'download_link' => $zip_url ];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $zip_url, $plain ) {
				if ( $url === $zip_url ) {
					if ( ! empty( $args['filename'] ) ) {
						file_put_contents( $args['filename'], $plain );
					}
					return [
						'body'     => $args['filename'] ?? $plain,
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

		remove_all_filters( 'pre_http_request' );

		$this->assertWPError( $result );
		$this->assertSame( 'gu_not_a_zip', $result->get_error_code() );
	}

	public function test_proxy_cleans_up_temp_file_on_zip_validation_failure(): void {
		$html    = '<html><body>Error</body></html>';
		$zip_url = 'https://example.com/package.zip';

		$this->rest->mock_metadata = [ 'download_link' => $zip_url ];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $zip_url, $html ) {
				if ( $url === $zip_url ) {
					if ( ! empty( $args['filename'] ) ) {
						file_put_contents( $args['filename'], $html );
					}
					return [
						'body'     => $args['filename'] ?? $html,
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

		remove_all_filters( 'pre_http_request' );

		$this->assertWPError( $result );
		// Temp file should be cleaned up — no leaked non-zip files on disk.
		$this->assertFileDoesNotExist( $this->rest->captured_file ?? '' );
	}
}
