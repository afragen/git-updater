<?php
/**
 * Tests for Icons.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\Icons;

/**
 * Class Test_Icons
 */
class Test_Icons extends WP_UnitTestCase {

	/** @var bool */
	private bool $fake_core_admin = false;

	public function tear_down(): void {
		if ( function_exists( 'wp_unregister_icon_collection' )
			&& class_exists( 'WP_Icon_Collections_Registry' )
			&& WP_Icon_Collections_Registry::get_instance()->is_registered( 'git-updater' )
		) {
			wp_unregister_icon_collection( 'git-updater' );
		}

		if ( $this->fake_core_admin
			&& function_exists( 'wp_unregister_icon_collection' )
			&& class_exists( 'WP_Icon_Collections_Registry' )
			&& WP_Icon_Collections_Registry::get_instance()->is_registered( 'core-admin' )
		) {
			wp_unregister_icon_collection( 'core-admin' );
		}

		parent::tear_down();
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	private function get_map(): array {
		$method = new ReflectionMethod( Icons::class, 'map' );
		PHP_VERSION_ID < 80100 && $method->setAccessible( true );

		return $method->invoke( null );
	}

	/**
	 * @param string               $key  Icon key.
	 * @param array<string, mixed> $args Args.
	 * @return string
	 */
	private function get_dashicon( string $key, array $args ): string {
		$method = new ReflectionMethod( Icons::class, 'dashicon' );
		PHP_VERSION_ID < 80100 && $method->setAccessible( true );

		return $method->invoke( null, $key, $args );
	}

	public function test_map_defines_expected_keys_and_fallbacks(): void {
		$map = $this->get_map();

		$this->assertSame(
			[ 'plugin', 'theme', 'private', 'dot-org', 'dismiss', 'broken', 'yes' ],
			array_keys( $map )
		);
		$this->assertSame( 'core-admin/plugins', $map['plugin']['icon'] );
		$this->assertSame( 'core-admin/brush', $map['theme']['icon'] );
		$this->assertSame( 'git-updater/lock', $map['private']['icon'] );
		$this->assertSame( 'core-admin/wordpress', $map['dot-org']['icon'] );
		$this->assertSame( 'core/error', $map['dismiss']['icon'] );
		$this->assertSame( 'core/caution', $map['broken']['icon'] );
		$this->assertSame( 'core/check', $map['yes']['icon'] );

		$this->assertSame( 'admin-plugins', $map['plugin']['dashicon'] );
		$this->assertSame( 'admin-appearance', $map['theme']['dashicon'] );
		$this->assertSame( 'lock', $map['private']['dashicon'] );
		$this->assertSame( 'wordpress', $map['dot-org']['dashicon'] );
		$this->assertSame( 'dismiss', $map['dismiss']['dashicon'] );
		$this->assertSame( 'warning', $map['broken']['dashicon'] );
		$this->assertSame( 'yes', $map['yes']['dashicon'] );
	}

	public function test_is_available_matches_core_admin_icon(): void {
		if ( ! function_exists( 'wp_get_icon' ) ) {
			$this->assertFalse( Icons::is_available() );
			return;
		}

		$this->assertSame( '' !== wp_get_icon( 'core-admin/plugins' ), Icons::is_available() );
	}

	public function test_get_returns_empty_for_unknown_key(): void {
		$this->assertSame( '', Icons::get( 'no-such-icon' ) );
	}

	public function test_dashicon_fallback_markup(): void {
		foreach ( $this->get_map() as $key => $icon ) {
			$class  = 'gu-icon gu-icon-' . $key;
			$output = $this->get_dashicon( $key, [ 'class' => $class ] );

			$this->assertStringContainsString( 'dashicons', $output );
			$this->assertStringContainsString( 'dashicons-' . $icon['dashicon'], $output );
			$this->assertStringContainsString( 'gu-icon', $output );
			$this->assertStringContainsString( 'gu-icon-' . $key, $output );
		}
	}

	public function test_get_renders_core_svg_with_classes_and_attributes(): void {
		if ( ! Icons::is_available() ) {
			$this->markTestSkipped( 'WordPress 7.2 core-admin icon set is not available.' );
		}

		foreach ( $this->get_map() as $key => $icon ) {
			$output = Icons::get( $key );

			$this->assertStringContainsString( '<svg', $output, $key );
			$this->assertStringContainsString( 'width="24"', $output, $key );
			$this->assertStringContainsString( 'gu-icon', $output, $key );
			$this->assertStringContainsString( 'gu-icon-' . $key, $output, $key );
			$this->assertStringContainsString( '<path', $output, $key );
			$this->assertStringContainsString( 'd=', $output, $key );
			$this->assertSame( 1, preg_match( '/(?:stroke|fill)="currentColor"/', $output ), $key );
			$this->assertSame( 1, preg_match( '/viewbox/i', $output ), $key );
		}
	}

	public function test_get_uses_icons_api_when_core_admin_is_available(): void {
		if ( ! function_exists( 'wp_register_icon_collection' ) || ! class_exists( 'WP_Icon_Collections_Registry' ) ) {
			$this->markTestSkipped( 'WordPress Icons API requires WordPress 7.1+.' );
		}
		if ( WP_Icon_Collections_Registry::get_instance()->is_registered( 'core-admin' ) ) {
			$this->markTestSkipped( 'The real core-admin collection is registered.' );
		}

		wp_register_icon_collection( 'core-admin', [ 'label' => 'Test Admin' ] );
		wp_register_icon(
			'core-admin/plugins',
			[
				'label'   => 'Plugins',
				'content' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M0 0h24v24H0z"/></svg>',
			]
		);
		$this->fake_core_admin = true;

		$this->assertTrue( Icons::is_available() );
		$this->assertStringContainsString( '<svg', Icons::get( 'plugin' ) );
		$this->assertStringContainsString( 'gu-icon-plugin', Icons::get( 'plugin' ) );
		$this->assertStringContainsString( '<svg', Icons::get( 'private' ) );
		$this->assertStringContainsString( 'gu-icon-private', Icons::get( 'private' ) );
	}

	public function test_kses_preserves_svg_and_strips_unsafe_content(): void {
		$html = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="fill: none" stroke="currentColor" stroke-width="1.5" class="gu-icon" aria-hidden="true"><path d="M0 0h20v20H0z" stroke-linejoin="round"/></svg>'
			. '<script>alert(1)</script><span onclick="evil()">text</span>';

		$output = Icons::kses( $html );

		$this->assertStringContainsString( '<svg', $output );
		$this->assertStringContainsString( '<path', $output );
		$this->assertStringContainsString( 'd=', $output );
		$this->assertStringContainsString( 'class="gu-icon"', $output );
		$this->assertStringContainsString( 'aria-hidden="true"', $output );
		$this->assertStringContainsString( 'stroke="currentColor"', $output );
		$this->assertStringContainsString( 'stroke-width="1.5"', $output );
		$this->assertStringContainsString( 'stroke-linejoin="round"', $output );
		$this->assertStringContainsString( 'style="fill: none"', $output );
		$this->assertStringNotContainsString( '<script', $output );
		$this->assertStringNotContainsString( 'onclick', $output );
	}

	public function test_kses_preserves_red_broken_wrapper(): void {
		$html   = '<span class="gu-repo-broken" style="color:#f00;display:none;">' . Icons::get( 'broken' ) . '</span>';
		$output = Icons::kses( $html );

		$this->assertStringContainsString( 'color:#f00', $output );
		$this->assertStringContainsString( 'display:none', $output );
		$this->assertStringContainsString( 'gu-icon-broken', $output );
	}

	public function test_register_custom_icons_registers_collection_and_lock(): void {
		if ( ! function_exists( 'wp_register_icon_collection' ) || ! class_exists( 'WP_Icon_Collections_Registry' ) ) {
			$this->markTestSkipped( 'WordPress Icons API requires WordPress 7.1+.' );
		}

		$method = new ReflectionMethod( Icons::class, 'register_custom_icons' );
		PHP_VERSION_ID < 80100 && $method->setAccessible( true );
		$method->invoke( null );
		$method->invoke( null );

		$this->assertTrue( WP_Icon_Collections_Registry::get_instance()->is_registered( 'git-updater' ) );
		$this->assertNotSame( '', wp_get_icon( 'git-updater/lock' ) );
	}

	public function test_private_icon_registers_custom_lock_collection(): void {
		if ( ! Icons::is_available() ) {
			$this->markTestSkipped( 'WordPress 7.2 core-admin icon set is not available.' );
		}

		$output = Icons::get( 'private' );

		$this->assertStringContainsString( '<svg', $output );
		$this->assertStringContainsString( 'gu-icon-private', $output );
		$this->assertTrue( WP_Icon_Collections_Registry::get_instance()->is_registered( 'git-updater' ) );
		$this->assertNotSame( '', wp_get_icon( 'git-updater/lock' ) );
	}

	public function test_get_supports_label_arg(): void {
		if ( ! Icons::is_available() ) {
			$this->markTestSkipped( 'WordPress 7.2 core-admin icon set is not available.' );
		}

		$output = Icons::get( 'private', [ 'label' => 'Private repository' ] );

		$this->assertStringContainsString( 'role="img"', $output );
		$this->assertStringContainsString( 'aria-label="Private repository"', $output );
	}
}
