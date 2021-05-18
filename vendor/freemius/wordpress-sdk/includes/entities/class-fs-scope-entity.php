<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.0.4
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	class FS_Scope_Entity extends FS_Entity {
		/**
		 * @var string
		 */
		public $public_key;
		/**
		 * @var string
		 */
		public $secret_key;

		/**
		 * @param bool|stdClass $scope_entity
		 */
		function __construct( $scope_entity = false ) {
			parent::__construct( $scope_entity );
		}
	}