<?php
/**
 * Tests for the Additions Add Package page.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\Additions\Settings as Additions_Settings;

class Test_Additions_New_Page extends WP_UnitTestCase {

	private Additions_Settings $settings;

	private int $admin_id = 0;

	public function set_up(): void {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		$this->admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		if ( is_multisite() ) {
			grant_super_admin( $this->admin_id );
		}
		wp_set_current_user( $this->admin_id );
		Additions_Settings::$options_additions = [];
		$this->settings = new Additions_Settings();
	}

	public function tear_down(): void {
		unset( $_POST['_wpnonce'], $_GET['view'] );
		delete_site_option( 'git_updater_additions' );
		delete_site_transient( 'gu_additions_notice_' . $this->admin_id );
		remove_all_filters( 'gu_save_redirect' );
		remove_all_filters( 'gu_save_redirect_args' );
		Additions_Settings::$options_additions = [];
		$this->reset_form_values();
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	private function reset_form_values(): void {
		$prop = new ReflectionProperty( Additions_Settings::class, 'form_values' );
		$prop->setAccessible( true );
		$prop->setValue( null, [] );
	}

	private function save( array $fields ): void {
		$_POST['_wpnonce'] = wp_create_nonce( 'git_updater_additions-options' );
		$this->settings->save_settings(
			[
				'option_page'           => 'git_updater_additions',
				'git_updater_additions' => $fields,
			]
		);
	}

	private function valid_fields(): array {
		return [
			'type' => 'github_plugin',
			'slug' => 'new-plugin/new-plugin.php',
			'uri'  => 'https://github.com/owner/new-plugin',
		];
	}

	private function render( string $view = '' ): string {
		if ( '' !== $view ) {
			$_GET['view'] = $view;
		}
		ob_start();
		$this->settings->add_admin_page( 'git_updater_additions', 'options.php' );
		return (string) ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// add_addition()
	// -------------------------------------------------------------------------

	public function test_add_addition_saves_and_returns_addition(): void {
		$result = $this->settings->add_addition( $this->valid_fields() + [ 'release_asset' => '1' ] );

		$this->assertIsArray( $result );
		$this->assertSame( md5( 'new-plugin/new-plugin.php' ), $result['ID'] );
		$this->assertTrue( $result['release_asset'] );
		$this->assertCount( 1, get_site_option( 'git_updater_additions' ) );
	}

	public function test_add_addition_validates_slug_format_for_first_addition(): void {
		$result = $this->settings->add_addition( [ 'slug' => 'no-file' ] + $this->valid_fields() );
		$this->assertWPError( $result );
		$this->assertSame( 'gu_addition_slug', $result->get_error_code() );
		$this->assertFalse( get_site_option( 'git_updater_additions' ) );
	}

	public function test_add_addition_rejects_unregistered_type(): void {
		$result = $this->settings->add_addition( [ 'type' => 'evil_plugin' ] + $this->valid_fields() );
		$this->assertWPError( $result );
		$this->assertSame( 'gu_addition_type', $result->get_error_code() );
	}

	public function test_add_addition_rejects_duplicate(): void {
		$this->settings->add_addition( $this->valid_fields() );
		$result = $this->settings->add_addition( $this->valid_fields() );
		$this->assertWPError( $result );
		$this->assertSame( 'gu_addition_duplicate', $result->get_error_code() );
	}

	// -------------------------------------------------------------------------
	// save_settings() redirect
	// -------------------------------------------------------------------------

	public function test_save_success_redirects_to_list(): void {
		$this->save( $this->valid_fields() );
		$args = apply_filters( 'gu_save_redirect_args', [ 'updated' => true ] );

		$this->assertArrayNotHasKey( 'view', $args );
		$this->assertFalse( $args['updated'] );
	}

	public function test_save_error_redirects_back_to_new_page(): void {
		$this->save( [ 'uri' => '' ] + $this->valid_fields() );
		$args = apply_filters( 'gu_save_redirect_args', [ 'updated' => true ] );

		$this->assertSame( 'new', $args['view'] );
		$this->assertFalse( $args['updated'] );
	}

	// -------------------------------------------------------------------------
	// add_admin_page() list view
	// -------------------------------------------------------------------------

	public function test_list_view_shows_add_package_button(): void {
		$output = $this->render();

		$this->assertStringContainsString( 'class="page-title-action"', $output );
		$this->assertStringContainsString( 'view=new', $output );
		$this->assertStringContainsString( 'Add Package', $output );
	}

	public function test_list_view_no_longer_shows_add_form(): void {
		$output = $this->render();
		$this->assertStringNotContainsString( 'name="git_updater_additions[slug]"', $output );
	}

	public function test_list_view_shows_success_notice_once(): void {
		$this->save( $this->valid_fields() );

		$output = $this->render();
		$this->assertStringContainsString( 'notice-success', $output );
		$this->assertStringContainsString( '<code>new-plugin/new-plugin.php</code>', $output );

		$this->assertStringNotContainsString( 'notice-success', $this->render() );
	}

	// -------------------------------------------------------------------------
	// add_admin_page() new view
	// -------------------------------------------------------------------------

	public function test_new_view_renders_form_without_list_table(): void {
		$output = $this->render( 'new' );

		$this->assertStringContainsString( 'name="git_updater_additions[slug]"', $output );
		$this->assertStringContainsString( 'name="git_updater_additions[type]"', $output );
		$this->assertStringContainsString( 'value="Add Package"', $output );
		$this->assertStringNotContainsString( 'id="gu-addition-inline-edit"', $output );
	}

	public function test_new_view_shows_error_and_refills_values(): void {
		$this->save(
			[
				'type'          => 'github_theme',
				'slug'          => 'with/slash',
				'uri'           => 'https://github.com/owner/"theme',
				'release_asset' => '1',
			]
		);

		$output = $this->render( 'new' );
		$this->assertStringContainsString( 'notice-error', $output );
		$this->assertStringContainsString( 'A theme slug must be the theme directory name only.', $output );
		$this->assertStringContainsString( 'value="with/slash"', $output );
		$this->assertStringContainsString( 'value="https://github.com/owner/&quot;theme"', $output );
		$this->assertMatchesRegularExpression( '/value="github_theme"\s+selected/', $output );
		$this->assertMatchesRegularExpression( '/release_asset\]" value="1"\s+checked/', $output );
	}
}
