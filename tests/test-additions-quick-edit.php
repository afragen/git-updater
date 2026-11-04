<?php
/**
 * Tests for Additions Quick Edit.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\Additions\Settings as Additions_Settings;
use Fragen\Git_Updater\Additions\Repo_List_Table;
use Fragen\Git_Updater\DB\Repo_Cache_Table;
use Fragen\Git_Updater\Plugin;
use Fragen\Git_Updater\Theme;
use Fragen\Singleton;

class Test_Additions_Quick_Edit extends WP_UnitTestCase {

	private Additions_Settings $settings;

	/** @var array<string, array<string, stdClass>> */
	private array $saved_configs = [];

	public function set_up(): void {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		Additions_Settings::$options_additions = [];
		$this->settings = new Additions_Settings();

		foreach ( [ Plugin::class, Theme::class ] as $class ) {
			$rp                              = new ReflectionProperty( $class, 'config' );
			PHP_VERSION_ID < 80100 && $rp->setAccessible( true );
			$this->saved_configs[ $class ] = $rp->getValue( Singleton::get_instance( $class, $this ) ) ?? [];
		}
	}

	public function tear_down(): void {
		unset( $_POST['_ajax_nonce'], $_POST['original_id'], $_POST['git_updater_additions'], $_REQUEST['_ajax_nonce'] );
		remove_all_filters( 'gua_addition_types' );
		remove_all_filters( 'wp_doing_ajax' );
		remove_all_filters( 'wp_die_ajax_handler' );
		remove_all_actions( 'wp_ajax_gu_additions_quick_edit' );
		delete_site_option( 'git_updater_additions' );
		Repo_Cache_Table::instance()->delete_repo( 'qe-plugin' );
		Repo_Cache_Table::instance()->delete_repo( 'qe-renamed' );
		Repo_Cache_Table::instance()->delete_repo( 'qe-theme' );
		unset( $_POST['id'] );
		remove_all_actions( 'wp_ajax_gu_additions_refresh' );
		remove_all_actions( 'get_remote_repo_meta' );
		remove_filter( 'pre_http_request', [ $this, 'mock_http' ] );
		foreach ( $this->saved_configs as $class => $config ) {
			$this->inject_config( $class, $config );
		}
		Additions_Settings::$options_additions = [];
		wp_dequeue_script( 'gu-additions-quick-edit' );
		wp_deregister_script( 'gu-additions-quick-edit' );
		parent::tear_down();
	}

	private function make_admin(): int {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		if ( is_multisite() ) {
			grant_super_admin( $user_id );
		}
		return $user_id;
	}

	private function make_item( array $overrides = [] ): array {
		return array_merge(
			[
				'ID'              => md5( 'qe-plugin/qe-plugin.php' ),
				'type'            => 'github_plugin',
				'slug'            => 'qe-plugin/qe-plugin.php',
				'uri'             => 'https://github.com/owner/qe-plugin',
				'primary_branch'  => 'master',
				'release_asset'   => false,
				'private_package' => false,
				'uses_lite'       => false,
				'source'          => 'original-source',
			],
			$overrides
		);
	}

	private function seed( array $items ): void {
		update_site_option( 'git_updater_additions', $items );
	}

	/**
	 * Replace a Plugin or Theme singleton's repo config.
	 *
	 * @param string                  $class  Plugin::class or Theme::class.
	 * @param array<string, stdClass> $config Repo configs keyed by repo slug.
	 */
	private function inject_config( string $class, array $config ): void {
		$rp = new ReflectionProperty( $class, 'config' );
		PHP_VERSION_ID < 80100 && $rp->setAccessible( true );
		$rp->setValue( Singleton::get_instance( $class, $this ), $config );
	}

	private function make_repo( string $slug, string $type = 'plugin' ): stdClass {
		return (object) [
			'slug'           => $slug,
			'type'           => $type,
			'git'            => 'github',
			'file'           => 'plugin' === $type ? "{$slug}/{$slug}.php" : $slug,
			'owner'          => 'owner',
			'repo'           => $slug,
			'branch'         => 'master',
			'primary_branch' => 'master',
			'uri'            => "https://github.com/owner/{$slug}",
			'release_asset'  => false,
			'enterprise'     => null,
			'enterprise_api' => null,
			'gist_id'        => null,
		];
	}

	public function mock_http(): array {
		return [
			'headers'  => [],
			'body'     => '[]',
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'cookies'  => [],
			'filename' => null,
		];
	}

	/**
	 * Stub remote calls and record a fetch that writes the given version and ran set.
	 *
	 * @param string             $version Remote Version header to cache.
	 * @param array<int, string> $ran     Ran steps to write.
	 */
	private function stub_fetch( string $version, array $ran ): void {
		add_filter( 'pre_http_request', [ $this, 'mock_http' ] );
		add_action(
			'get_remote_repo_meta',
			static function ( $repo ) use ( $version, $ran ) {
				$table = Repo_Cache_Table::instance();
				$table->add_entry( $repo->slug, 'repo_headers', [ 'Version' => $version ], strtotime( '+12 hours' ) );
				$table->add_entry( $repo->slug, 'ran', $ran, strtotime( '+12 hours' ) );
			}
		);
	}

	/**
	 * @return array<int, string>
	 */
	private function complete_ran(): array {
		return [ 'contents', 'assets', 'readme', 'changes', 'tags', 'branches', 'meta' ];
	}

	/**
	 * Run the AJAX handler, returning the decoded JSON response.
	 */
	private function run_ajax( string $handler = 'ajax_quick_edit' ): array {
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function (): callable {
				return static function ( $msg ): void {
					throw new WPDieException( (string) $msg );
				};
			}
		);

		ob_start();
		try {
			$this->settings->$handler();
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$output = (string) ob_get_clean();

		return (array) json_decode( $output, true );
	}

	// -------------------------------------------------------------------------
	// Repo_List_Table
	// -------------------------------------------------------------------------

	public function test_column_slug_contains_quick_edit_button(): void {
		$table  = new Repo_List_Table( [] );
		$result = $table->column_slug( $this->make_item() );
		$this->assertStringContainsString( 'gu-quick-edit', $result );
		$this->assertStringContainsString( 'Quick Edit', $result );
	}

	public function test_render_row_outputs_row_with_id_and_data(): void {
		$item  = $this->make_item( [ 'release_asset' => '1' ] );
		$table = new Repo_List_Table( [ $item ] );
		$row   = $table->render_row( $item );

		$this->assertStringContainsString( 'id="gu-addition-' . $item['ID'] . '"', $row );
		$this->assertStringContainsString( 'qe-plugin/qe-plugin.php', $row );

		preg_match( '/data-gu-addition="([^"]*)"/', $row, $matches );
		$data = json_decode( html_entity_decode( $matches[1] ), true );
		$this->assertSame( 'https://github.com/owner/qe-plugin', $data['uri'] );
		$this->assertTrue( $data['release_asset'] );
		$this->assertArrayNotHasKey( 'source', $data );
	}

	public function test_render_row_escapes_data_attribute(): void {
		$item  = $this->make_item( [ 'primary_branch' => '"><script>x</script>' ] );
		$table = new Repo_List_Table( [ $item ] );
		$row   = $table->render_row( $item );
		$this->assertStringNotContainsString( '<script>', $row );
	}

	public function test_render_list_table_includes_inline_edit_template(): void {
		$table = new Repo_List_Table( [ $this->make_item() ] );
		ob_start();
		$table->render_list_table();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="gu-addition-inline-edit"', $output );
		$this->assertStringContainsString( 'data-field="uses_lite"', $output );
		$this->assertStringContainsString( 'colspan="' . count( $table->get_columns() ) . '"', $output );
	}

	public function test_inline_edit_respects_gua_addition_types_filter(): void {
		add_filter(
			'gua_addition_types',
			static function ( $types ) {
				return array_merge( $types, [ 'gitlab_plugin' ] );
			}
		);
		$table = new Repo_List_Table( [] );
		ob_start();
		$table->inline_edit();
		$output = (string) ob_get_clean();
		$this->assertStringContainsString( 'value="gitlab_plugin"', $output );
	}

	// -------------------------------------------------------------------------
	// update_addition()
	// -------------------------------------------------------------------------

	public function test_update_addition_saves_changed_fields(): void {
		$item = $this->make_item();
		$this->seed( [ $item ] );

		$result = $this->settings->update_addition(
			$item['ID'],
			[
				'type'           => 'github_plugin',
				'slug'           => 'qe-plugin/qe-plugin.php',
				'uri'            => 'https://github.com/owner/qe-plugin/',
				'primary_branch' => 'main',
				'release_asset'  => '1',
				'uses_lite'      => '1',
			]
		);

		$this->assertIsArray( $result );
		$saved = get_site_option( 'git_updater_additions' );
		$this->assertCount( 1, $saved );
		$this->assertSame( 'main', $saved[0]['primary_branch'] );
		$this->assertSame( 'https://github.com/owner/qe-plugin', $saved[0]['uri'] );
		$this->assertTrue( $saved[0]['release_asset'] );
		$this->assertTrue( $saved[0]['uses_lite'] );
		$this->assertFalse( $saved[0]['private_package'] );
		$this->assertSame( 'original-source', $saved[0]['source'] );
	}

	public function test_update_addition_unchecked_boxes_become_false(): void {
		$item = $this->make_item(
			[
				'release_asset'   => true,
				'private_package' => true,
				'uses_lite'       => true,
			]
		);
		$this->seed( [ $item ] );

		$this->settings->update_addition( $item['ID'], [ 'type' => $item['type'], 'slug' => $item['slug'], 'uri' => $item['uri'] ] );

		$saved = get_site_option( 'git_updater_additions' );
		$this->assertFalse( $saved[0]['release_asset'] );
		$this->assertFalse( $saved[0]['private_package'] );
		$this->assertFalse( $saved[0]['uses_lite'] );
	}

	public function test_update_addition_changing_slug_recomputes_id_and_keeps_position(): void {
		$other = $this->make_item(
			[
				'ID'   => md5( 'other/other.php' ),
				'slug' => 'other/other.php',
			]
		);
		$item  = $this->make_item();
		$this->seed( [ $other, $item ] );

		$result = $this->settings->update_addition(
			$item['ID'],
			[
				'type' => 'github_plugin',
				'slug' => 'qe-renamed/qe-renamed.php',
				'uri'  => $item['uri'],
			]
		);

		$this->assertSame( md5( 'qe-renamed/qe-renamed.php' ), $result['ID'] );
		$saved = get_site_option( 'git_updater_additions' );
		$this->assertCount( 2, $saved );
		$this->assertSame( 'other/other.php', $saved[0]['slug'] );
		$this->assertSame( 'qe-renamed/qe-renamed.php', $saved[1]['slug'] );
	}

	public function test_update_addition_rejects_unknown_id(): void {
		$this->seed( [ $this->make_item() ] );
		$result = $this->settings->update_addition( 'nope', [ 'slug' => 'a/a.php', 'uri' => 'https://github.com/o/a' ] );
		$this->assertWPError( $result );
		$this->assertSame( 'gu_addition_not_found', $result->get_error_code() );
	}

	public function test_update_addition_rejects_empty_uri(): void {
		$item = $this->make_item();
		$this->seed( [ $item ] );
		$result = $this->settings->update_addition( $item['ID'], [ 'type' => 'github_plugin', 'slug' => $item['slug'], 'uri' => '' ] );
		$this->assertWPError( $result );
		$this->assertSame( 'gu_addition_required', $result->get_error_code() );
		$this->assertSame( [ $item ], get_site_option( 'git_updater_additions' ) );
	}

	public function test_update_addition_rejects_plugin_slug_without_file(): void {
		$item = $this->make_item();
		$this->seed( [ $item ] );
		$result = $this->settings->update_addition( $item['ID'], [ 'type' => 'github_plugin', 'slug' => 'qe-plugin', 'uri' => $item['uri'] ] );
		$this->assertWPError( $result );
		$this->assertSame( 'gu_addition_slug', $result->get_error_code() );
	}

	public function test_update_addition_rejects_theme_slug_with_slash(): void {
		$item = $this->make_item();
		$this->seed( [ $item ] );
		$result = $this->settings->update_addition( $item['ID'], [ 'type' => 'github_theme', 'slug' => $item['slug'], 'uri' => $item['uri'] ] );
		$this->assertWPError( $result );
		$this->assertSame( 'gu_addition_slug', $result->get_error_code() );
	}

	public function test_update_addition_rejects_unregistered_type(): void {
		$item = $this->make_item();
		$this->seed( [ $item ] );
		$result = $this->settings->update_addition( $item['ID'], [ 'type' => 'evil_plugin', 'slug' => $item['slug'], 'uri' => $item['uri'] ] );
		$this->assertWPError( $result );
		$this->assertSame( 'gu_addition_type', $result->get_error_code() );
	}

	public function test_update_addition_allows_unchanged_unregistered_type(): void {
		$item = $this->make_item( [ 'type' => 'gitlab_plugin' ] );
		$this->seed( [ $item ] );
		$result = $this->settings->update_addition( $item['ID'], [ 'type' => 'gitlab_plugin', 'slug' => $item['slug'], 'uri' => $item['uri'] ] );
		$this->assertIsArray( $result );
	}

	public function test_update_addition_rejects_duplicate_slug(): void {
		$other = $this->make_item(
			[
				'ID'   => md5( 'other/other.php' ),
				'slug' => 'other/other.php',
			]
		);
		$item  = $this->make_item();
		$this->seed( [ $other, $item ] );

		$result = $this->settings->update_addition( $item['ID'], [ 'type' => 'github_plugin', 'slug' => 'other/other.php', 'uri' => $item['uri'] ] );
		$this->assertWPError( $result );
		$this->assertSame( 'gu_addition_duplicate', $result->get_error_code() );
	}

	public function test_update_addition_ignores_unknown_and_array_fields(): void {
		$item = $this->make_item();
		$this->seed( [ $item ] );
		$result = $this->settings->update_addition(
			$item['ID'],
			[
				'type'           => 'github_plugin',
				'slug'           => $item['slug'],
				'uri'            => $item['uri'],
				'primary_branch' => [ 'bad' ],
				'injected'       => 'value',
			]
		);
		$this->assertArrayNotHasKey( 'injected', $result );
		$this->assertSame( 'master', $result['primary_branch'] );
	}

	public function test_update_addition_flushes_old_and_new_repo_cache(): void {
		$cache = Repo_Cache_Table::instance();
		$cache->add_entry( 'qe-plugin', 'current_branch', 'develop' );
		$cache->add_entry( 'qe-renamed', 'current_branch', 'develop' );

		$item = $this->make_item();
		$this->seed( [ $item ] );
		$this->settings->update_addition( $item['ID'], [ 'type' => 'github_plugin', 'slug' => $item['slug'], 'uri' => 'https://github.com/owner/qe-renamed' ] );

		$this->assertEmpty( $cache->get_entry( 'qe-plugin', 'current_branch' ) );
		$this->assertEmpty( $cache->get_entry( 'qe-renamed', 'current_branch' ) );
	}

	// -------------------------------------------------------------------------
	// ajax_quick_edit()
	// -------------------------------------------------------------------------

	public function test_load_hooks_registers_ajax_action(): void {
		$this->settings->load_hooks();
		$this->assertSame( 10, has_action( 'wp_ajax_gu_additions_quick_edit', [ $this->settings, 'ajax_quick_edit' ] ) );
	}

	public function test_ajax_quick_edit_rejects_bad_nonce(): void {
		wp_set_current_user( $this->make_admin() );
		$_REQUEST['_ajax_nonce'] = 'bad';

		$response = $this->run_ajax();
		$this->assertSame( [], $response );
	}

	public function test_ajax_quick_edit_rejects_non_admin(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'gu_additions_quick_edit' );

		$response = $this->run_ajax();
		$this->assertFalse( $response['success'] );
	}

	public function test_ajax_quick_edit_returns_error_message(): void {
		wp_set_current_user( $this->make_admin() );
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'gu_additions_quick_edit' );
		$_POST['original_id']    = 'missing';

		$response = $this->run_ajax();
		$this->assertFalse( $response['success'] );
		$this->assertNotEmpty( $response['data']['message'] );
	}

	public function test_ajax_quick_edit_returns_rendered_row(): void {
		wp_set_current_user( $this->make_admin() );
		$item = $this->make_item();
		$this->seed( [ $item ] );

		$_REQUEST['_ajax_nonce']        = wp_create_nonce( 'gu_additions_quick_edit' );
		$_POST['original_id']           = $item['ID'];
		$_POST['git_updater_additions'] = [
			'type'           => 'github_plugin',
			'slug'           => $item['slug'],
			'uri'            => $item['uri'],
			'primary_branch' => 'develop',
		];

		$response = $this->run_ajax();
		$this->assertTrue( $response['success'] );
		$this->assertStringContainsString( 'id="gu-addition-' . $item['ID'] . '"', $response['data']['row'] );
		$this->assertStringContainsString( 'develop', $response['data']['row'] );
		$this->assertSame( 'develop', get_site_option( 'git_updater_additions' )[0]['primary_branch'] );
	}

	// -------------------------------------------------------------------------
	// Refresh
	// -------------------------------------------------------------------------

	public function test_refresh_button_is_in_version_column_not_row_actions(): void {
		$table = new Repo_List_Table( [] );
		$this->assertStringContainsString( 'gu-refresh-addition', $table->column_version( $this->make_item() ) );
		$this->assertStringNotContainsString( 'gu-refresh-addition', $table->column_slug( $this->make_item() ) );
	}

	public function test_load_hooks_registers_refresh_ajax_action(): void {
		$this->settings->load_hooks();
		$this->assertSame( 10, has_action( 'wp_ajax_gu_additions_refresh', [ $this->settings, 'ajax_refresh' ] ) );
	}

	public function test_refresh_addition_errors_when_repo_not_loaded(): void {
		$this->inject_config( Plugin::class, [] );

		$result = $this->settings->refresh_addition( $this->make_item() );
		$this->assertWPError( $result );
		$this->assertSame( 'gu_addition_not_loaded', $result->get_error_code() );
	}

	public function test_refresh_addition_errors_when_uri_missing(): void {
		$this->inject_config( Plugin::class, [ 'qe-plugin' => $this->make_repo( 'qe-plugin' ) ] );

		$result = $this->settings->refresh_addition( $this->make_item( [ 'uri' => '' ] ) );
		$this->assertWPError( $result );
	}

	public function test_refresh_addition_flushes_and_refetches_plugin(): void {
		Repo_Cache_Table::instance()->add_entry( 'qe-plugin', 'repo_headers', [ 'Version' => '1.0.0' ], strtotime( '+12 hours' ) );
		$this->inject_config( Plugin::class, [ 'qe-plugin' => $this->make_repo( 'qe-plugin' ) ] );
		$this->stub_fetch( '2.0.0', $this->complete_ran() );

		$this->assertTrue( $this->settings->refresh_addition( $this->make_item() ) );
		$this->assertSame( '2.0.0', Repo_Cache_Table::instance()->get_repo( 'qe-plugin', 'repo_headers' )['Version'] );
	}

	public function test_refresh_addition_uses_theme_configs_for_themes(): void {
		$this->inject_config( Plugin::class, [] );
		$this->inject_config( Theme::class, [ 'qe-theme' => $this->make_repo( 'qe-theme', 'theme' ) ] );
		$this->stub_fetch( '3.0.0', $this->complete_ran() );

		$item = $this->make_item(
			[
				'type' => 'github_theme',
				'slug' => 'qe-theme',
				'uri'  => 'https://github.com/owner/qe-theme',
			]
		);
		$this->assertTrue( $this->settings->refresh_addition( $item ) );
	}

	public function test_refresh_addition_errors_when_fetch_incomplete(): void {
		$this->inject_config( Plugin::class, [ 'qe-plugin' => $this->make_repo( 'qe-plugin' ) ] );
		$this->stub_fetch( '2.0.0', [ 'contents' ] );

		$result = $this->settings->refresh_addition( $this->make_item() );
		$this->assertWPError( $result );
		$this->assertSame( 'gu_addition_refresh_incomplete', $result->get_error_code() );
	}

	public function test_refresh_addition_does_not_leave_wpcron_filter(): void {
		$this->inject_config( Plugin::class, [ 'qe-plugin' => $this->make_repo( 'qe-plugin' ) ] );
		$this->stub_fetch( '2.0.0', $this->complete_ran() );

		$this->settings->refresh_addition( $this->make_item() );
		$this->assertFalse( has_filter( 'gu_disable_wpcron', '__return_false' ) );
	}

	public function test_ajax_refresh_rejects_bad_nonce(): void {
		wp_set_current_user( $this->make_admin() );
		$_REQUEST['_ajax_nonce'] = 'bad';

		$this->assertSame( [], $this->run_ajax( 'ajax_refresh' ) );
	}

	public function test_ajax_refresh_rejects_non_admin(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'gu_additions_refresh' );

		$response = $this->run_ajax( 'ajax_refresh' );
		$this->assertFalse( $response['success'] );
	}

	public function test_ajax_refresh_errors_for_unknown_addition(): void {
		wp_set_current_user( $this->make_admin() );
		$this->seed( [ $this->make_item() ] );
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'gu_additions_refresh' );
		$_POST['id']             = 'missing';

		$response = $this->run_ajax( 'ajax_refresh' );
		$this->assertFalse( $response['success'] );
		$this->assertArrayNotHasKey( 'row', $response['data'] );
	}

	public function test_ajax_refresh_returns_row_with_served_version(): void {
		wp_set_current_user( $this->make_admin() );
		$item = $this->make_item();
		$this->seed( [ $item ] );
		$this->inject_config( Plugin::class, [ 'qe-plugin' => $this->make_repo( 'qe-plugin' ) ] );
		$this->stub_fetch( '2.0.0', $this->complete_ran() );
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'gu_additions_refresh' );
		$_POST['id']             = $item['ID'];

		$response = $this->run_ajax( 'ajax_refresh' );
		$this->assertTrue( $response['success'] );
		$this->assertStringContainsString( 'id="gu-addition-' . $item['ID'] . '"', $response['data']['row'] );
		$this->assertStringContainsString( '2.0.0', $response['data']['row'] );
	}

	public function test_ajax_refresh_returns_row_and_message_when_incomplete(): void {
		wp_set_current_user( $this->make_admin() );
		$item = $this->make_item();
		$this->seed( [ $item ] );
		$this->inject_config( Plugin::class, [ 'qe-plugin' => $this->make_repo( 'qe-plugin' ) ] );
		$this->stub_fetch( '2.0.0', [ 'contents' ] );
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'gu_additions_refresh' );
		$_POST['id']             = $item['ID'];

		$response = $this->run_ajax( 'ajax_refresh' );
		$this->assertFalse( $response['success'] );
		$this->assertNotEmpty( $response['data']['message'] );
		$this->assertStringContainsString( 'id="gu-addition-' . $item['ID'] . '"', $response['data']['row'] );
	}

	// -------------------------------------------------------------------------
	// add_admin_page()
	// -------------------------------------------------------------------------

	public function test_add_admin_page_enqueues_quick_edit_script(): void {
		ob_start();
		$this->settings->add_admin_page( 'git_updater_additions', admin_url() );
		ob_end_clean();
		$this->assertTrue( wp_script_is( 'gu-additions-quick-edit', 'enqueued' ) );
	}
}
