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

	public function test_get_columns_returns_exactly_six_columns(): void {
		$this->assertCount( 6, $this->table->get_columns() );
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
		$item = $this->make_item( [ 'release_asset' => '<span>yes</span>' ] );
		$this->assertSame( '<span>yes</span>', $this->table->column_default( $item, 'release_asset' ) );
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
		$_REQUEST['_wpnonce_row_action_delete'] = wp_create_nonce( 'delete_row_item' );
		$_REQUEST['action']                     = 'edit';

		$this->expectException( WPDieException::class );
		$this->table->process_bulk_action();
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
}

// ---------------------------------------------------------------------------
// Add_Ons — add_admin_page and addons_page_init
// ---------------------------------------------------------------------------

/**
 * Class Test_Add_Ons_Admin_Page_And_Init
 */