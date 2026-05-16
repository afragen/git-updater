<?php
/**
 * Tests for API methods: get_dot_org_data, exit_no_update, get_local_info,
 * local_file_exists, set_file_info, add_meta_repo_object.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\API\GitHub_API;
use Fragen\Git_Updater\Base;

/**
 * Class Test_API_Dot_Org_Data
 *
 * Covers get_dot_org_data() cache and HTTP paths.
 */
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
		$this->type = $this->make_type();
		$this->api  = new GitHub_API( $this->type );
	}

	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'gu_api_domain' );
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
		return $type;
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
		$this->type = $this->make_type();
		$this->api  = new GitHub_API( $this->type );
	}

	public function tear_down(): void {
		remove_all_filters( 'gu_always_fetch_update' );
		delete_site_transient( 'gu_refresh_cache' );
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
		$this->type = $this->make_type();
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
