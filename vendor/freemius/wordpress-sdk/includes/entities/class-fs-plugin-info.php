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

	class FS_Plugin_Info extends FS_Entity {
		public $plugin_id;
		public $description;
		public $short_description;
		public $banner_url;
		public $card_banner_url;
		public $selling_point_0;
		public $selling_point_1;
		public $selling_point_2;
		public $screenshots;

		/**
		 * @param stdClass|bool $plugin_info
		 */
		function __construct( $plugin_info = false ) {
			parent::__construct( $plugin_info );
		}

		static function get_type() {
			return 'plugin';
		}
	}