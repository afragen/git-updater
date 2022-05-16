<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  MIT
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

/**
 * Loads WP 6.1 modified functions from Rollback.
 */

namespace Fragen\Git_Updater;

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

	/*
	 * Skip the rename() call on VirtualBox environments.
	 * There are some known issues where rename() can fail on shared folders
	 * without reporting an error properly.
	 *
	 * More details:
	 * https://www.virtualbox.org/ticket/8761#comment:24
	 * https://www.virtualbox.org/ticket/17971
	 */
	if ( 'direct' === $wp_filesystem->method && ! is_virtualbox() ) {
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
	}

	return $result;
}

/**
 * Attempt to detect a VirtualBox environment.
 *
 * This attempts all known methods of detecting VirtualBox.
 *
 * @global $wp_filesystem The filesystem.
 *
 * @since 6.1.0
 *
 * @return bool Whether or not VirtualBox was detected.
 */
function is_virtualbox() {
	global $wp_filesystem;
	static $is_virtualbox;

	if ( ! defined( 'WP_RUN_CORE_TESTS' ) && null !== $is_virtualbox ) {
		return $is_virtualbox;
	}

	/**
	 * Filters whether the current environment uses VirtualBox.
	 *
	 * @since 6.1.0
	 *
	 * @param bool Whether the current environment uses VirtualBox.
	 *             Default: false.
	 */
	if ( apply_filters( 'is_virtualbox', false ) ) {
		$is_virtualbox = true;
		return $is_virtualbox;
	}

	// Detection via Composer.
	if ( function_exists( 'getenv' ) && 'virtualbox' === getenv( 'COMPOSER_RUNTIME_ENV' ) ) {
		$is_virtualbox = true;
		return $is_virtualbox;
	}

	$virtualbox_unames = [ 'vvv' ];

	// Detection via `php_uname()`.
	if ( function_exists( 'php_uname' ) && in_array( php_uname( 'n' ), $virtualbox_unames, true ) ) {
		$is_virtualbox = true;
		return $is_virtualbox;
	}

	/*
	 * Vagrant can use alternative providers.
	 * This isn't reliable without some additional check(s).
	 */
	$virtualbox_usernames = [ 'vagrant' ];

	// Detection via user name with POSIX.
	if ( function_exists( 'posix_getpwuid' ) && function_exists( 'posix_geteuid' ) ) {
		$user = posix_getpwuid( posix_geteuid() );
		if ( $user && in_array( $user['name'], $virtualbox_usernames, true ) ) {
			$is_virtualbox = true;
			return $is_virtualbox;
		}
	}

	// Initialize the filesystem if not set.
	if ( ! $wp_filesystem ) {
		require_once ABSPATH . '/wp-admin/includes/file.php';
		WP_Filesystem();
	}

	// Detection via file owner.
	if ( in_array( $wp_filesystem->owner( __FILE__ ), $virtualbox_usernames, true ) ) {
		$is_virtualbox = true;
		return $is_virtualbox;
	}

	// Detection via file group.
	if ( in_array( $wp_filesystem->group( __FILE__ ), $virtualbox_usernames, true ) ) {
		$is_virtualbox = true;
		return $is_virtualbox;
	}

	// Give up.
	$is_virtualbox = false;

	return $is_virtualbox;
}
