<?php
	/**
	 * @package     Freemius for EDD Add-On
	 * @copyright   Copyright (c) 2016, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.0.0
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	class FS_Billing extends FS_Entity {

		#region Properties

		/**
		 * @var int
		 */
		public $entity_id;
		/**
		 * @var string (Enum) Linked entity type. One of: developer, plugin, user, install
		 */
		public $entity_type;
		/**
		 * @var string
		 */
		public $business_name;
		/**
		 * @var string
		 */
		public $first;
		/**
		 * @var string
		 */
		public $last;
		/**
		 * @var string
		 */
		public $email;
		/**
		 * @var string
		 */
		public $phone;
		/**
		 * @var string
		 */
		public $website;
		/**
		 * @var string Tax or VAT ID.
		 */
		public $tax_id;
		/**
		 * @var string
		 */
		public $address_street;
		/**
		 * @var string
		 */
		public $address_apt;
		/**
		 * @var string
		 */
		public $address_city;
		/**
		 * @var string
		 */
		public $address_country;
		/**
		 * @var string Two chars country code.
		 */
		public $address_country_code;
		/**
		 * @var string
		 */
		public $address_state;
		/**
		 * @var number Numeric ZIP code (cab be with leading zeros).
		 */
		public $address_zip;

		#endregion Properties


		/**
		 * @param object|bool $event
		 */
		function __construct( $event = false ) {
			parent::__construct( $event );
		}

		static function get_type() {
			return 'billing';
		}
	}