<?php
	/**
	 * @package   Freemius
	 * @copyright Copyright (c) 2025, Freemius, Inc.
	 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since     2.12.2
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class FS_Hook_Snapshot
	 *
	 * This class allows you to take a snapshot of the current actions attached to a WordPress hook, remove them, and restore them later.
	 */
	class FS_Hook_Snapshot {

		private $removed_actions = array();

		/**
		 * Remove all actions from a given hook and store them for later restoration.
		 */
		public function remove( $hook ) {
			global $wp_filter;

			if ( ! empty( $wp_filter ) && isset( $wp_filter[ $hook ] ) ) {
				$this->removed_actions[ $hook ] = $wp_filter[ $hook ];
				unset( $wp_filter[ $hook ] );
			}
		}

		/**
		 * Restore previously removed actions for a given hook.
		 */
		public function restore( $hook ) {
			global $wp_filter;

			if ( ! empty( $wp_filter ) && isset( $this->removed_actions[ $hook ] ) ) {
				$wp_filter[ $hook ] = $this->removed_actions[ $hook ];
				unset( $this->removed_actions[ $hook ] );
			}
		}
	}