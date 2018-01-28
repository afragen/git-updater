<?php

/**
 * Persist Admin notices Dismissal
 *
 * Copyright (C) 2016  Agbonghama Collins <http://w3guy.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package Persist Admin notices Dismissal
 * @author  Agbonghama Collins
 * @author  Andy Fragen
 * @license http://www.gnu.org/licenses GNU General Public License
 * @version 1.3.2
 */

/**
 * Exit if called directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! class_exists( 'PAnD' ) ) {

	/**
	 * Class PAnD
	 */
	class PAnD {

		/**
		 * Init hooks.
		 */
		public static function init() {
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'load_script' ) );
			add_action( 'wp_ajax_dismiss_admin_notice', array( __CLASS__, 'dismiss_admin_notice' ) );
		}

		/**
		 * Enqueue javascript and variables.
		 */
		public static function load_script() {

		    if(is_customize_preview()) return;

			wp_enqueue_script(
				'dismissible-notices',
				plugins_url( 'dismiss-notice.js', __FILE__ ),
				array( 'jquery', 'common' ),
				false,
				true
			);

			wp_localize_script(
				'dismissible-notices',
				'dismissible_notice',
				array(
					'nonce' => wp_create_nonce( 'dismissible-notice' ),
				)
			);
		}

		/**
		 * Handles Ajax request to persist notices dismissal.
		 * Uses check_ajax_referer to verify nonce.
		 */
		public static function dismiss_admin_notice() {
			$option_name        = sanitize_text_field( $_POST['option_name'] );
			$dismissible_length = sanitize_text_field( $_POST['dismissible_length'] );
			$transient          = 0;

			if ( 'forever' != $dismissible_length ) {
				// If $dismissible_length is not an integer default to 1
				$dismissible_length = ( 0 == absint( $dismissible_length ) ) ? 1 : $dismissible_length;
				$transient          = absint( $dismissible_length ) * DAY_IN_SECONDS;
				$dismissible_length = strtotime( absint( $dismissible_length ) . ' days' );
			}

			check_ajax_referer( 'dismissible-notice', 'nonce' );
			set_site_transient( $option_name, $dismissible_length, $transient );
			wp_die();
		}

		/**
		 * Is admin notice active?
		 *
		 * @param string $arg data-dismissible content of notice.
		 *
		 * @return bool
		 */
		public static function is_admin_notice_active( $arg ) {
			$array       = explode( '-', $arg );
			$length      = array_pop( $array );
			$option_name = implode( '-', $array );
			$db_record   = get_site_transient( $option_name );

			if ( 'forever' == $db_record ) {
				return false;
			} elseif ( absint( $db_record ) >= time() ) {
				return false;
			} else {
				return true;
			}
		}

	}

}
