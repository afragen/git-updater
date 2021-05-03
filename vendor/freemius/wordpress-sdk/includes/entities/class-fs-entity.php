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

	/**
	 * Get object's public variables.
	 *
	 * @author Vova Feldman (@svovaf)
	 * @since  1.0.0
	 *
	 * @param object $object
	 *
	 * @return array
	 */
	function fs_get_object_public_vars( $object ) {
		return get_object_vars( $object );
	}

	class FS_Entity {
		/**
		 * @var number
		 */
		public $id;
		/**
		 * @var string Datetime value in 'YYYY-MM-DD HH:MM:SS' format.
		 */
		public $updated;
		/**
		 * @var string Datetime value in 'YYYY-MM-DD HH:MM:SS' format.
		 */
		public $created;

		/**
		 * @param bool|object $entity
		 */
		function __construct( $entity = false ) {
            if ( ! ( $entity instanceof stdClass ) && ! ( $entity instanceof FS_Entity ) ) {
                return;
            }

			$props = fs_get_object_public_vars( $this );

			foreach ( $props as $key => $def_value ) {
				$this->{$key} = isset( $entity->{$key} ) ?
					$entity->{$key} :
					$def_value;
			}
		}

		static function get_type() {
			return 'type';
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.6
		 *
		 * @param FS_Entity $entity1
		 * @param FS_Entity $entity2
		 *
		 * @return bool
		 */
		static function equals( $entity1, $entity2 ) {
			if ( is_null( $entity1 ) && is_null( $entity2 ) ) {
				return true;
			} else if ( is_object( $entity1 ) && is_object( $entity2 ) ) {
				return ( $entity1->id == $entity2->id );
			} else if ( is_object( $entity1 ) ) {
				return is_null( $entity1->id );
			} else {
				return is_null( $entity2->id );
			}
		}

		private $_is_updated = false;

		/**
		 * Update object property.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.9
		 *
		 * @param  string|array[string]mixed $key
		 * @param string|bool $val
		 *
		 * @return bool
		 */
		function update( $key, $val = false ) {
			if ( ! is_array( $key ) ) {
				$key = array( $key => $val );
			}

			$is_updated = false;

			foreach ( $key as $k => $v ) {
				if ( $this->{$k} === $v ) {
					continue;
				}

				if ( ( is_string( $this->{$k} ) && is_numeric( $v ) ||
				       ( is_numeric( $this->{$k} ) && is_string( $v ) ) ) &&
				     $this->{$k} == $v
				) {
					continue;
				}

				// Update value.
				$this->{$k} = $v;

				$is_updated = true;
			}

			$this->_is_updated = $is_updated;

			return $is_updated;
		}

		/**
		 * Checks if entity was updated.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.9
		 *
		 * @return bool
		 */
		function is_updated() {
			return $this->_is_updated;
		}

		/**
		 * @param $id
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.2
		 *
		 * @return bool
		 */
		static function is_valid_id($id){
			return is_numeric($id);
		}

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.1
         *
         * @return string
         */
        public static function get_class_name() {
            return get_called_class();
        }
	}