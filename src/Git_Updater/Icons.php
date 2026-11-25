<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  GPL-3.0-or-later
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

namespace Fragen\Git_Updater;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die; // @codeCoverageIgnore
}

/**
 * Class Icons
 *
 * Renders Git Updater admin icons with the WordPress Icons API where
 * available and falls back to the original Dashicon markup otherwise.
 *
 * The WordPress 7.2 `core-admin` collection is used directly so the
 * icons match the rest of the admin. On older WordPress (or when an
 * icon is unavailable) the equivalent Dashicon is rendered instead.
 *
 * All returned markup is passed through KSES with an SVG-aware allowlist.
 */
final class Icons {

	/**
	 * Map of icons: WordPress Icons API name and Dashicon fallback.
	 *
	 * @return array<string, array<string, string>>
	 */
	private static function map(): array {
		return [
			'plugin'  => [
				'icon'     => 'core-admin/plugins',
				'dashicon' => 'admin-plugins',
			],
			'theme'   => [
				'icon'     => 'core-admin/brush',
				'dashicon' => 'admin-appearance',
			],
			'private' => [
				'icon'     => 'git-updater/lock',
				'dashicon' => 'lock',
			],
			'dot-org' => [
				'icon'     => 'core-admin/wordpress',
				'dashicon' => 'wordpress',
			],
			'dismiss' => [
				'icon'     => 'core/error',
				'dashicon' => 'dismiss',
			],
			'broken'  => [
				'icon'     => 'core/caution',
				'dashicon' => 'warning',
			],
			'yes'     => [
				'icon'     => 'core/check',
				'dashicon' => 'yes',
			],
		];
	}

	/**
	 * Whether the WordPress 7.2+ admin icon set is available.
	 *
	 * `core-admin` ships in WordPress 7.2. Checking for one of its icons is
	 * more reliable than version parsing (and is also false on 7.1, whose
	 * icon sanitizer strips the stroke attributes used by this icon set).
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		return function_exists( 'wp_get_icon' ) && '' !== wp_get_icon( 'core-admin/plugins' );
	}

	/**
	 * KSES allowlist for Git Updater output: post tags plus the SVG subset used here.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function allowed_html(): array {
		$svg = [
			'class'             => true,
			'xmlns'             => true,
			'width'             => true,
			'height'            => true,
			'viewbox'           => true,
			'aria-hidden'       => true,
			'aria-label'        => true,
			'role'              => true,
			'focusable'         => true,
			'fill'              => true,
			'fill-rule'         => true,
			'clip-rule'         => true,
			'style'             => true,
			'stroke'            => true,
			'stroke-width'      => true,
			'stroke-linecap'    => true,
			'stroke-linejoin'   => true,
			'stroke-miterlimit' => true,
			'vector-effect'     => true,
			'opacity'           => true,
			'transform'         => true,
			'd'                 => true,
			'points'            => true,
			'x'                 => true,
			'y'                 => true,
			'rx'                => true,
			'ry'                => true,
			'cx'                => true,
			'cy'                => true,
			'r'                 => true,
		];

		$allowed            = wp_kses_allowed_html( 'post' );
		$allowed['svg']     = $svg;
		$allowed['path']    = $svg;
		$allowed['g']       = $svg;
		$allowed['polygon'] = $svg;
		$allowed['rect']    = $svg;
		$allowed['circle']  = $svg;

		return $allowed;
	}

	/**
	 * Sanitize icon or row markup while preserving SVG.
	 *
	 * @param string $html Markup.
	 *
	 * @return string
	 */
	public static function kses( $html ): string {
		return wp_kses( (string) $html, self::allowed_html() );
	}

	/**
	 * Render an icon.
	 *
	 * @param string               $key  Icon key.
	 * @param array<string, mixed> $args Optional. wp_get_icon() arguments.
	 *
	 * @return string KSES-sanitized HTML.
	 */
	public static function get( $key, $args = [] ): string {
		$map = self::map();
		if ( ! isset( $map[ $key ] ) ) {
			return '';
		}

		$args = wp_parse_args(
			$args,
			[
				'size'  => 24,
				'class' => 'gu-icon gu-icon-' . sanitize_html_class( $key ),
			]
		);

		if ( self::is_available() && function_exists( 'wp_get_icon' ) ) {
			if ( str_starts_with( $map[ $key ]['icon'], 'git-updater/' ) ) {
				self::register_custom_icons();
			}

			$svg = wp_get_icon( $map[ $key ]['icon'], $args );
			if ( '' !== $svg ) {
				return self::kses( $svg );
			}
		}

		return self::kses( self::dashicon( $key, $args ) );
	}

	/**
	 * Register the Git Updater icon collection and icons not present in core.
	 *
	 * `core-admin` has no lock icon; this uses the WordPress `lock-outline`
	 * artwork so it matches the 7.2 core icon style. The registry is the guard,
	 * so the icons are re-registered on demand if removed.
	 *
	 * @return void
	 */
	private static function register_custom_icons(): void {
		if ( ! function_exists( 'wp_register_icon_collection' ) || ! function_exists( 'wp_register_icon' ) ) {
			return; // @codeCoverageIgnore
		}

		if ( class_exists( 'WP_Icon_Collections_Registry' )
			&& \WP_Icon_Collections_Registry::get_instance()->is_registered( 'git-updater' )
		) {
			return;
		}

		wp_register_icon_collection(
			'git-updater',
			[
				'label'       => __( 'Git Updater', 'git-updater' ),
				'description' => __( 'Icons used by Git Updater admin screens.', 'git-updater' ),
			]
		);

		wp_register_icon(
			'git-updater/lock',
			[
				'label'     => __( 'Private repository', 'git-updater' ),
				'file_path' => dirname( __DIR__, 2 ) . '/assets/icons/lock.svg',
			]
		);
	}

	/**
	 * Build the Dashicon fallback markup.
	 *
	 * @param string               $key  Icon key.
	 * @param array<string, mixed> $args Arguments from get().
	 *
	 * @return string
	 */
	private static function dashicon( $key, $args ): string {
		return sprintf(
			'<span class="%s dashicons dashicons-%s"></span>',
			esc_attr( (string) $args['class'] ),
			esc_attr( self::map()[ $key ]['dashicon'] )
		);
	}
}
