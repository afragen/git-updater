<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  MIT
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! function_exists( 'move_dir' ) ) {
	/**
	 * Moves a directory from one location to another via the rename() PHP function.
	 * If the renaming failed, falls back to copy_dir().
	 *
	 * Assumes that WP_Filesystem() has already been called and setup.
	 *
	 * @since 6.1.0
	 *
	 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 *
	 * @param string $from        Source directory.
	 * @param string $to          Destination directory.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	function move_dir( $from, $to ) {
		global $wp_filesystem;

		$result = false;

		/**
		 * Fires before move_dir().
		 *
		 * @since 6.1.0
		 */
		do_action( 'pre_move_dir' );

		if ( 'direct' === $wp_filesystem->method ) {
			$wp_filesystem->rmdir( $to );

			$result = @rename( $from, $to );
		}

		// Non-direct filesystems use some version of rename without a fallback.
		if ( 'direct' !== $wp_filesystem->method ) {
			$result = $wp_filesystem->move( $from, $to );
		}

		if ( ! $result ) {
			if ( ! $wp_filesystem->is_dir( $to ) ) {
				if ( ! $wp_filesystem->mkdir( $to, FS_CHMOD_DIR ) ) {
					return new \WP_Error( 'mkdir_failed_move_dir', __( 'Could not create directory.' ), $to );
				}
			}

			$result = copy_dir( $from, $to, [ basename( $to ) ] );

			// Clear the source directory.
			if ( ! is_wp_error( $result ) ) {
				$wp_filesystem->delete( $from, true );
			}
		}

		/**
		 * Fires after move_dir().
		 *
		 * @since 6.1.0
		 */
		do_action( 'post_move_dir' );

		return $result;
	}
}

if ( ! function_exists( 'str_contains' ) ) {
	/**
	 * Polyfill for `str_contains()` function added in PHP 8.0.
	 *
	 * Performs a case-sensitive check indicating if needle is
	 * contained in haystack.
	 *
	 * @since 5.9.0
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The substring to search for in the haystack.
	 * @return bool True if `$needle` is in `$haystack`, otherwise false.
	 */
	function str_contains( $haystack, $needle ) {
		return ( '' === $needle || false !== strpos( $haystack, $needle ) );
	}
}

if ( ! function_exists( 'str_starts_with' ) ) {
	/**
	 * Polyfill for `str_starts_with()` function added in PHP 8.0.
	 *
	 * Performs a case-sensitive check indicating if
	 * the haystack begins with needle.
	 *
	 * @since 5.9.0
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The substring to search for in the `$haystack`.
	 * @return bool True if `$haystack` starts with `$needle`, otherwise false.
	 */
	function str_starts_with( $haystack, $needle ) {
		if ( '' === $needle ) {
			return true;
		}
		return 0 === strpos( $haystack, $needle );
	}
}

if ( ! function_exists( 'str_ends_with' ) ) {
	/**
	 * Polyfill for `str_ends_with()` function added in PHP 8.0.
	 *
	 * Performs a case-sensitive check indicating if
	 * the haystack ends with needle.
	 *
	 * @since 5.9.0
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The substring to search for in the `$haystack`.
	 * @return bool True if `$haystack` ends with `$needle`, otherwise false.
	 */
	function str_ends_with( $haystack, $needle ) {
		if ( '' === $haystack && '' !== $needle ) {
			return false;
		}
		$len = strlen( $needle );
		return 0 === substr_compare( $haystack, $needle, -$len, $len );
	}
}
