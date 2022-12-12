<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.1.7.3
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	if ( ! WP_FS__DEBUG_SDK ) {
		return;
	}

	/**
	 * Initialize Freemius custom debug panels.
	 *
	 * @param array $panels Debug bar panels objects
	 *
	 * @return array Debug bar panels with your custom panels
	 */
	function fs_custom_panels_init( $panels ) {
		if ( class_exists( 'Debug_Bar_Panel' ) ) {
			if ( FS_API__LOGGER_ON ) {
				require_once dirname( __FILE__ ) . '/class-fs-debug-bar-panel.php';
				$panels[] = new Freemius_Debug_Bar_Panel();
			}
		}

		return $panels;
	}

	function fs_custom_status_init( $statuses ) {
		if ( class_exists( 'Debug_Bar_Panel' ) ) {
			if ( FS_API__LOGGER_ON ) {
				require_once dirname( __FILE__ ) . '/class-fs-debug-bar-panel.php';
				$statuses[] = array(
					'fs_api_requests',
					fs_text_inline( 'Freemius API' ),
					Freemius_Debug_Bar_Panel::requests_count() . ' ' . fs_text_inline( 'Requests' ) .
					' (' . Freemius_Debug_Bar_Panel::total_time() . ')'
				);
			}
		}

		return $statuses;
	}

	add_filter( 'debug_bar_panels', 'fs_custom_panels_init' );
	add_filter( 'debug_bar_statuses', 'fs_custom_status_init' );