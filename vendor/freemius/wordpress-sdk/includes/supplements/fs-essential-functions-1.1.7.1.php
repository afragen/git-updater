<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.1.7
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Find the plugin main file path based on any given file inside the plugin's folder.
	 *
	 * @author Vova Feldman (@svovaf)
	 * @since  1.1.7.1
	 *
	 * @param string $file Absolute path to a file inside a plugin's folder.
	 *
	 * @return string
	 */
	function fs_find_direct_caller_plugin_file( $file ) {
		/**
		 * All the code below will be executed once on activation.
		 * If the user changes the main plugin's file name, the file_exists()
		 * will catch it.
		 */
		$all_plugins = fs_get_plugins( true );

		$file_real_path = fs_normalize_path( realpath( $file ) );

		// Get active plugin's main files real full names (might be symlinks).
		foreach ( $all_plugins as $relative_path => $data ) {
            if ( 0 === strpos( $file_real_path, fs_normalize_path( dirname( realpath( WP_PLUGIN_DIR . '/' . $relative_path ) ) . '/' ) ) ) {
				if ( '.' !== dirname( trailingslashit( $relative_path ) ) ) {
	                return $relative_path;
	            }
			}
		}

		return null;
	}
