<?php
/**
 * Tests for Repo_List_Table_Methods.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\Base;
use Fragen\Git_Updater\Add_Ons;
use Fragen\Git_Updater\Additions\Additions;
use Fragen\Git_Updater\Additions\Settings;
use Fragen\Git_Updater\Additions\Bootstrap;
use Fragen\Git_Updater\Additions\Repo_List_Table;

class Test_Repo_List_Table_Methods extends WP_UnitTestCase {

	private Repo_List_Table $table;

	public function set_up(): void {
		parent::set_up();
		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}
		$this->table = new Repo_List_Table( [] );
	}

	public function tear_down(): void {
		unset( $_REQUEST['order'], $_REQUEST['orderby'] );
		\Fragen\Git_Updater\DB\Repo_Cache_Table::instance()->delete_repo( 'served-repo' );
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// get_columns()
	// -------------------------------------------------------------------------

	public function test_get_columns_includes_slug(): void {
		$this->assertArrayHasKey( 'slug', $this->table->get_columns() );
	}

	public function test_get_columns_includes_uri(): void {
		$this->assertArrayHasKey( 'uri', $this->table->get_columns() );
	}

	public function test_get_columns_includes_type(): void {
		$this->assertArrayHasKey( 'type', $this->table->get_columns() );
	}

	public function test_get_columns_includes_primary_branch(): void {
		$this->assertArrayHasKey( 'primary_branch', $this->table->get_columns() );
	}

	public function test_get_columns_includes_release_asset(): void {
		$this->assertArrayHasKey( 'release_asset', $this->table->get_columns() );
	}

	public function test_get_columns_includes_private_package(): void {
		$this->assertArrayHasKey( 'private_package', $this->table->get_columns() );
	}

	public function test_get_columns_returns_exactly_eight_columns(): void {
		$this->assertCount( 8, $this->table->get_columns() );
	}

	public function test_get_columns_includes_version(): void {
		$this->assertArrayHasKey( 'version', $this->table->get_columns() );
	}

	// -------------------------------------------------------------------------
	// column_version()
	// -------------------------------------------------------------------------

	public function test_column_version_returns_cached_remote_version(): void {
		\Fragen\Git_Updater\DB\Repo_Cache_Table::instance()->add_entry( 'served-repo', 'repo_headers', [ 'Version' => '2.4.1' ] );
		$item = [ 'uri' => 'https://github.com/owner/served-repo' ];
		$this->assertStringContainsString( '2.4.1', $this->table->column_version( $item ) );
	}

	public function test_column_version_ignores_expired_cache(): void {
		\Fragen\Git_Updater\DB\Repo_Cache_Table::instance()->add_entry( 'served-repo', 'repo_headers', [ 'Version' => '2.4.1' ], strtotime( '-1 hour' ) );
		$item = [ 'uri' => 'https://github.com/owner/served-repo' ];
		$this->assertStringContainsString( '2.4.1', $this->table->column_version( $item ) );
	}

	public function test_column_version_lowercases_version_like_update_api(): void {
		\Fragen\Git_Updater\DB\Repo_Cache_Table::instance()->add_entry( 'served-repo', 'repo_headers', [ 'Version' => '3.0.0-RC1' ] );
		$item = [ 'uri' => 'https://github.com/owner/served-repo' ];
		$this->assertStringContainsString( '3.0.0-rc1', $this->table->column_version( $item ) );
	}

	public function test_column_version_handles_trailing_slash_uri(): void {
		\Fragen\Git_Updater\DB\Repo_Cache_Table::instance()->add_entry( 'served-repo', 'repo_headers', [ 'Version' => '1.0.0' ] );
		$item = [ 'uri' => 'https://bitbucket.org/owner/served-repo/' ];
		$this->assertStringContainsString( '1.0.0', $this->table->column_version( $item ) );
	}

	public function test_column_version_escapes_html(): void {
		\Fragen\Git_Updater\DB\Repo_Cache_Table::instance()->add_entry( 'served-repo', 'repo_headers', [ 'Version' => '<b>1.0</b>' ] );
		$item = [ 'uri' => 'https://github.com/owner/served-repo' ];
		$this->assertStringNotContainsString( '<b>', $this->table->column_version( $item ) );
	}

	public function test_column_version_shows_dash_when_not_cached(): void {
		$item   = [ 'uri' => 'https://github.com/owner/served-repo' ];
		$result = $this->table->column_version( $item );
		$this->assertStringContainsString( '&#8212;', $result );
		$this->assertStringContainsString( 'screen-reader-text', $result );
	}

	public function test_column_version_shows_dash_for_placeholder_version(): void {
		\Fragen\Git_Updater\DB\Repo_Cache_Table::instance()->add_entry( 'served-repo', 'repo_headers', [ 'Version' => '0.0.0' ] );
		$item = [ 'uri' => 'https://github.com/owner/served-repo' ];
		$this->assertStringContainsString( '&#8212;', $this->table->column_version( $item ) );
	}

	public function test_column_version_shows_dash_when_uri_missing(): void {
		$this->assertStringContainsString( '&#8212;', $this->table->column_version( [ 'uri' => null ] ) );
	}

	public function test_column_version_includes_refresh_button(): void {
		$item   = [
			'slug' => 'served-repo/served-repo.php',
			'uri'  => 'https://github.com/owner/served-repo',
		];
		$result = $this->table->column_version( $item );
		$this->assertStringContainsString( 'gu-refresh-addition', $result );
		$this->assertStringContainsString( 'gu-icon-refresh', $result );
		$this->assertStringContainsString( 'aria-label="Refresh &#8220;served-repo/served-repo.php&#8221; from the git host"', $result );
	}

	// -------------------------------------------------------------------------
	// get_sortable_columns()
	// -------------------------------------------------------------------------

	public function test_get_sortable_columns_includes_slug(): void {
		$this->assertArrayHasKey( 'slug', $this->table->get_sortable_columns() );
	}

	public function test_get_sortable_columns_includes_type(): void {
		$this->assertArrayHasKey( 'type', $this->table->get_sortable_columns() );
	}

	// -------------------------------------------------------------------------
	// get_bulk_actions()
	// -------------------------------------------------------------------------

	public function test_get_bulk_actions_includes_delete(): void {
		$this->assertArrayHasKey( 'delete', $this->table->get_bulk_actions() );
	}

	// -------------------------------------------------------------------------
	// column_default()
	// -------------------------------------------------------------------------

	private function make_item( array $overrides = [] ): array {
		return array_merge(
			[
				'slug'            => 'test-plugin/test-plugin.php',
				'uri'             => 'https://github.com/owner/test-plugin',
				'type'            => 'github_plugin',
				'primary_branch'  => 'master',
				'release_asset'   => false,
				'private_package' => false,
			],
			$overrides
		);
	}

	public function test_column_default_returns_slug_for_slug_column(): void {
		$item = $this->make_item( [ 'slug' => 'my-plugin/my-plugin.php' ] );
		$this->assertSame( 'my-plugin/my-plugin.php', $this->table->column_default( $item, 'slug' ) );
	}

	public function test_column_default_returns_uri_for_uri_column(): void {
		$item = $this->make_item( [ 'uri' => 'https://github.com/owner/repo' ] );
		$this->assertSame( 'https://github.com/owner/repo', $this->table->column_default( $item, 'uri' ) );
	}

	public function test_column_default_returns_type_for_type_column(): void {
		$item = $this->make_item( [ 'type' => 'bitbucket_plugin' ] );
		$this->assertSame( 'bitbucket_plugin', $this->table->column_default( $item, 'type' ) );
	}

	public function test_column_default_returns_primary_branch_value(): void {
		$item = $this->make_item( [ 'primary_branch' => 'develop' ] );
		$this->assertSame( 'develop', $this->table->column_default( $item, 'primary_branch' ) );
	}

	public function test_column_default_returns_release_asset_value(): void {
		$item = $this->make_item( [ 'release_asset' => true ] );
		$this->assertStringContainsString( 'gu-icon-yes', $this->table->column_default( $item, 'release_asset' ) );
	}

	public function test_column_default_escapes_html_in_field_values(): void {
		$item = $this->make_item( [ 'uri' => '<script>alert(1)</script>' ] );
		$result = $this->table->column_default( $item, 'uri' );
		$this->assertStringNotContainsString( '<script>', $result );
		$this->assertStringContainsString( '&lt;script&gt;', $result );
	}

	public function test_column_slug_escapes_html_in_slug(): void {
		$item   = $this->make_item( [ 'slug' => '<img src=x onerror=alert(1)>', 'ID' => md5( 'xss-test' ) ] );
		$result = $this->table->column_slug( $item );
		$this->assertStringNotContainsString( '<img', $result );
		$this->assertStringContainsString( '&lt;img', $result );
	}

	// -------------------------------------------------------------------------
	// column_cb()
	// -------------------------------------------------------------------------

	public function test_column_cb_outputs_checkbox_input(): void {
		$item = [ 'ID' => md5( 'my-plugin' ) ];
		$this->assertStringContainsString( 'type="checkbox"', $this->table->column_cb( $item ) );
	}

	public function test_column_cb_uses_item_id_as_checkbox_value(): void {
		$id   = md5( 'my-plugin' );
		$item = [ 'ID' => $id ];
		$this->assertStringContainsString( "value=\"{$id}\"", $this->table->column_cb( $item ) );
	}

	// -------------------------------------------------------------------------
	// usort_reorder()
	// -------------------------------------------------------------------------

	public function test_usort_reorder_sorts_ascending_by_slug_by_default(): void {
		$a = [ 'slug' => 'alpha-plugin' ];
		$b = [ 'slug' => 'beta-plugin' ];
		// alpha < beta → strcmp result < 0 (a before b ascending)
		$this->assertLessThan( 0, $this->table->usort_reorder( $a, $b ) );
	}

	public function test_usort_reorder_reverses_result_when_order_is_desc(): void {
		$_REQUEST['order']   = 'desc';
		$_REQUEST['orderby'] = 'slug';
		$a = [ 'slug' => 'alpha-plugin' ];
		$b = [ 'slug' => 'beta-plugin' ];
		// Descending: alpha should sort after beta → result > 0
		$this->assertGreaterThan( 0, $this->table->usort_reorder( $a, $b ) );
	}

	public function test_usort_reorder_uses_orderby_request_param(): void {
		$_REQUEST['orderby'] = 'type';
		$_REQUEST['order']   = 'asc';
		$a = [ 'type' => 'github_plugin',    'slug' => 'zzz' ];
		$b = [ 'type' => 'bitbucket_plugin', 'slug' => 'aaa' ];
		// bitbucket < github → b before a when sorted by type asc → result > 0 (b < a)
		$this->assertGreaterThan( 0, $this->table->usort_reorder( $a, $b ) );
	}
}

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------


class Test_Repo_List_Table_Extended extends WP_UnitTestCase {

	private Repo_List_Table $table;

	public function set_up(): void {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		$this->table = new Repo_List_Table( [] );
	}

	public function tear_down(): void {
		unset(
			$_REQUEST['_wpnonce_row_action_delete'],
			$_REQUEST['slug'],
			$_REQUEST['action'],
			$_REQUEST['page'],
			$_REQUEST['tab']
		);
		delete_site_option( 'git_updater_additions' );
		parent::tear_down();
	}

	private function make_admin(): int {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		// On multisite only super admins pass the manage_network_options check.
		if ( is_multisite() ) {
			grant_super_admin( $user_id );
		}
		return $user_id;
	}

	private function make_item( array $overrides = [] ): array {
		return array_merge(
			[
				'ID'              => md5( 'test-plugin/test-plugin.php' ),
				'slug'            => 'test-plugin/test-plugin.php',
				'uri'             => 'https://github.com/owner/test-plugin',
				'type'            => 'github_plugin',
				'primary_branch'  => 'master',
				'release_asset'   => false,
				'private_package' => false,
				'source'          => md5( home_url() ),
			],
			$overrides
		);
	}

	public function test_column_default_returns_print_r_for_unknown_column(): void {
		$item   = $this->make_item();
		$result = $this->table->column_default( $item, 'unknown_column' );
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
	}

	public function test_column_slug_contains_slug_text(): void {
		$item   = $this->make_item( [ 'slug' => 'my-plugin/my-plugin.php' ] );
		$result = $this->table->column_slug( $item );
		$this->assertStringContainsString( 'my-plugin/my-plugin.php', $result );
	}

	public function test_column_slug_contains_item_id(): void {
		$id     = md5( 'my-plugin/my-plugin.php' );
		$item   = $this->make_item( [ 'slug' => 'my-plugin/my-plugin.php', 'ID' => $id ] );
		$result = $this->table->column_slug( $item );
		$this->assertStringContainsString( $id, $result );
	}

	public function test_column_slug_contains_delete_link(): void {
		$item   = $this->make_item();
		$result = $this->table->column_slug( $item );
		$this->assertStringContainsString( 'Delete', $result );
	}

	public function test_process_bulk_action_returns_without_nonce(): void {
		unset( $_REQUEST['_wpnonce_row_action_delete'] );
		$this->table->process_bulk_action();
		$this->assertFalse( get_site_option( 'git_updater_additions' ) );
	}

	public function test_process_bulk_action_deletes_matching_entry(): void {
		wp_set_current_user( $this->make_admin() );

		$id     = md5( 'test-plugin/test-plugin.php' );
		$option = $this->make_item( [ 'ID' => $id ] );
		$table  = new Repo_List_Table( [ $option ] );
		update_site_option( 'git_updater_additions', [ $option ] );

		$_REQUEST['_wpnonce_row_action_delete'] = wp_create_nonce( 'delete_row_item' );
		$_REQUEST['slug']                       = $id;

		$table->process_bulk_action();

		$this->assertEmpty( get_site_option( 'git_updater_additions' ) );
	}

	public function test_process_bulk_action_edit_action_dies(): void {
		wp_set_current_user( $this->make_admin() );

		$_REQUEST['_wpnonce_row_action_delete'] = wp_create_nonce( 'delete_row_item' );
		$_REQUEST['action']                     = 'edit';

		$this->expectException( WPDieException::class );
		$this->table->process_bulk_action();
	}

	public function test_process_bulk_action_returns_on_invalid_nonce(): void {
		wp_set_current_user( $this->make_admin() );

		$_REQUEST['_wpnonce_row_action_delete'] = 'not-a-valid-nonce';
		$_REQUEST['slug']                       = 'some-id';

		$this->table->process_bulk_action();

		// Invalid nonce must short-circuit without deleting anything.
		$this->assertFalse( get_site_option( 'git_updater_additions' ) );
	}

	public function test_process_bulk_action_dies_without_capability(): void {
		// Subscriber lacks the required capability; the delete action must be forbidden.
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$_REQUEST['_wpnonce_row_action_delete'] = wp_create_nonce( 'delete_row_item' );
		$_REQUEST['slug']                       = 'some-id';

		$this->expectException( WPDieException::class );
		$this->table->process_bulk_action();
	}

	public function test_process_bulk_action_deletes_array_of_slugs(): void {
		wp_set_current_user( $this->make_admin() );

		$id_one = md5( 'plugin-one/plugin-one.php' );
		$id_two = md5( 'plugin-two/plugin-two.php' );
		$keep   = md5( 'plugin-keep/plugin-keep.php' );
		$options = [
			$this->make_item( [ 'ID' => $id_one, 'slug' => 'plugin-one/plugin-one.php' ] ),
			$this->make_item( [ 'ID' => $id_two, 'slug' => 'plugin-two/plugin-two.php' ] ),
			$this->make_item( [ 'ID' => $keep, 'slug' => 'plugin-keep/plugin-keep.php' ] ),
		];
		$table = new Repo_List_Table( $options );
		update_site_option( 'git_updater_additions', $options );

		$_REQUEST['_wpnonce_row_action_delete'] = wp_create_nonce( 'delete_row_item' );
		$_REQUEST['slug']                       = [ $id_one, $id_two ];

		$table->process_bulk_action();

		$saved = get_site_option( 'git_updater_additions' );
		$saved = array_values( $saved );
		$this->assertCount( 1, $saved );
		$this->assertSame( $keep, $saved[0]['ID'] );
	}

	public function test_render_list_table_omits_orphan_nonce(): void {
		ob_start();
		$this->table->render_list_table();
		$output = ob_get_clean();
		$this->assertStringNotContainsString( '_wpnonce_list', $output );
	}

	public function test_prepare_items_sets_items_to_array(): void {
		$this->table->prepare_items();
		$this->assertIsArray( $this->table->items );
	}

	public function test_render_list_table_outputs_wrap_div(): void {
		ob_start();
		$this->table->render_list_table();
		$output = ob_get_clean();
		$this->assertStringContainsString( '<div class="wrap">', $output );
	}

	// -------------------------------------------------------------------------
	// uses_lite column
	// -------------------------------------------------------------------------

	public function test_get_columns_includes_uses_lite(): void {
		$this->assertArrayHasKey( 'uses_lite', $this->table->get_columns() );
	}

	public function test_column_default_renders_uses_lite_icon(): void {
		$item = [
			'slug' => 'test-plugin',
			'uri' => 'https://github.com/test/plugin',
			'type' => 'github_plugin',
			'primary_branch' => 'master',
			'release_asset' => false,
			'private_package' => false,
			'uses_lite' => true,
		];

		$result = $this->table->column_default( $item, 'uses_lite' );
		$this->assertStringContainsString( 'gu-icon-yes', $result );
		if ( \Fragen\Git_Updater\Icons::is_available() ) {
			$this->assertStringContainsString( '<svg', $result );
		}
	}

	public function test_constructor_keeps_flag_columns_boolean(): void {
		$option = [
			'ID' => 'abc',
			'type' => 'github_plugin',
			'slug' => 'test-plugin/test-plugin.php',
			'uri' => 'https://github.com/test/plugin',
			'source' => 'github',
			'primary_branch' => 'main',
			'release_asset' => true,
			'private_package' => true,
			'uses_lite' => true,
		];
		$table = new Repo_List_Table( [ $option ] );
		$table->prepare_items();
		$items = $table->items;
		$item  = reset( $items );

		$this->assertTrue( $item['release_asset'] );
		$this->assertTrue( $item['private_package'] );
		$this->assertTrue( $item['uses_lite'] );
	}

	public function test_column_default_renders_uses_lite_false(): void {
		$item = [
			'slug' => 'test-plugin',
			'uri' => 'https://github.com/test/plugin',
			'type' => 'github_plugin',
			'primary_branch' => 'master',
			'release_asset' => false,
			'private_package' => false,
			'uses_lite' => false,
		];

		$result = $this->table->column_default( $item, 'uses_lite' );
		$this->assertFalse( $result );
	}
}
