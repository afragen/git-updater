<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.0.3
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	class FS_User extends FS_Scope_Entity {

		#region Properties

		/**
		 * @var string
		 */
		public $email;
		/**
		 * @var string
		 */
		public $first;
		/**
		 * @var string
		 */
		public $last;
		/**
		 * @var bool
		 */
		public $is_verified;
		/**
		 * @var string|null
		 */
		public $customer_id;
		/**
		 * @var float
		 */
		public $gross;

		#endregion Properties

		/**
		 * @param object|bool $user
		 */
		function __construct( $user = false ) {
			parent::__construct( $user );
		}

		/**
		 * This method removes the deprecated 'is_beta' property from the serialized data.
		 * Should clean up the serialized data to avoid PHP 8.2 warning on next execution.
		 *
		 * @return void
		 */
		function __wakeup() {
			if ( property_exists( $this, 'is_beta' ) ) {
				// If we enter here, and we are running PHP 8.2, we already had the warning. But we sanitize data for next execution.
				unset( $this->is_beta );
			}
		}

		function get_name() {
			return trim( ucfirst( trim( is_string( $this->first ) ? $this->first : '' ) ) . ' ' . ucfirst( trim( is_string( $this->last ) ? $this->last : '' ) ) );
		}

		function is_verified() {
			return ( isset( $this->is_verified ) && true === $this->is_verified );
		}

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.4.2
         *
         * @return bool
         */
        function is_beta() {
            // Return `false` since this is just for backward compatibility.
            return false;
        }

        static function get_type() {
			return 'user';
		}
	}