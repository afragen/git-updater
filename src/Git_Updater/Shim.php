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
	global $wp_filesystem;

	if ( ! $wp_filesystem ) {
		require_once ABSPATH . '/wp-admin/includes/file.php';
		WP_Filesystem();
	}

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
	 *
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	function move_dir( $from, $to ) {
		global $wp_filesystem;

		$result = false;

		/**
		 * Fires before move_dir().
		 *
		 * @since 6.2.0
		 */
		do_action( 'pre_move_dir' );

		if ( 'direct' === $wp_filesystem->method ) {
			if ( $wp_filesystem->rmdir( $to ) ) {
				$result = @rename( $from, $to );
				wp_opcache_invalidate_directory( $to );
			}
		} else {
			// Non-direct filesystems use some version of rename without a fallback.
			$result = $wp_filesystem->move( $from, $to );
			wp_opcache_invalidate_directory( $to );
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
		 * @since 6.2.0
		 */
		do_action( 'post_move_dir' );

		return $result;
	}
}

if ( ! function_exists( 'wp_opcache_invalidate_directory' ) ) {
	/**
	 * Invalidate OPcache of directory of files.
	 *
	 * @since 6.2.0
	 *
	 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 *
	 * @param string $dir The path to invalidate.
	 *
	 * @return void
	 */
	function wp_opcache_invalidate_directory( $dir ) {
		global $wp_filesystem;

		if ( ! is_string( $dir ) || '' === trim( $dir ) ) {
			$error_message = sprintf(
			/* translators: %s: The '$dir' argument. */
				__( 'The %s argument must be a non-empty string.', 'git-updater' ),
				'<code>$dir</code>'
			);
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			trigger_error( esc_html( $error_message ) );
			return;
		}

		$dirlist = $wp_filesystem->dirlist( $dir, false, true );

		if ( empty( $dirlist ) ) {
			return;
		}

		/*
		 * Recursively invalidate opcache of nested files.
		 *
		 * @param array  $dirlist Array of file/directory information from WP_Filesystem_Base::dirlist().
		 * @param string $path    Path to directory.
		 */
		$invalidate_directory = function( $dirlist, $path ) use ( &$invalidate_directory ) {
			$path = trailingslashit( $path );

			foreach ( $dirlist as $name => $details ) {
				if ( 'f' === $details['type'] ) {
					wp_opcache_invalidate( $path . $name, true );
					continue;
				}

				if ( is_array( $details['files'] ) && ! empty( $details['files'] ) ) {
					$invalidate_directory( $details['files'], $path . $name );
				}
			}
		};

		$invalidate_directory( $dirlist, $dir );
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
