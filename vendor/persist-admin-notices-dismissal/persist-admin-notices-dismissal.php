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
 * @version 1.0.0
 */

/**
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Don't run during heartbeat.
 */
if ( isset( $_REQUEST['action'] ) && 'heartbeat' === $_REQUEST['action'] ) {
	return;
}

if ( ! class_exists( 'PAnD' ) ) {

	/**
	 * Class PAnD
	 */
	class PAnD {

		/**
		 * Singleton variable.
		 *
		 * @var bool
		 */
		private static $instance = false;

		/**
		 * Singleton.
		 *
		 * @return bool|\PAnD
		 */
		public static function instance() {
			if ( false === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Init hooks.
		 */
		public function init() {
			add_action( 'admin_enqueue_scripts', array( $this, 'load_script' ) );
			add_action( 'wp_ajax_dismiss_admin_notice', array( $this, 'dismiss_admin_notice' ) );
		}

		/**
		 * Enqueue javascript and variables.
		 */
		public function load_script() {
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
					'nonce' => wp_create_nonce( 'PAnD-dismissible-notice' ),
				)
			);
		}

		/**
		 * Handles Ajax request to persist notices dismissal.
		 */
		public function dismiss_admin_notice() {
			$option_name        = sanitize_text_field( $_POST['option_name'] );
			$dismissible_length = sanitize_text_field( $_POST['dismissible_length'] );

			if ( 'forever' != $dismissible_length ) {
				$dismissible_length = time() + strtotime( absint( $dismissible_length ) . 'days' );
			}

			if ( is_integer( wp_verify_nonce( $_REQUEST['nonce'], 'PAnD-dismissible-notice' ) ) && ( false !== strpos( $option_name, 'data-' ) ) ) {
				add_option( $option_name, $dismissible_length );
			}

			wp_die();
		}

		/**
		 * Is admin notice active?
		 *
		 * @param string $arg data-dismissible content of notice.
		 *
		 * @return bool
		 */
		public function is_admin_notice_active( $arg ) {
			$array       = explode( '-', $arg );
			$length      = array_pop( $array );
			$option_name = implode( '-', $array );

			$db_record = get_option( $option_name );

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
