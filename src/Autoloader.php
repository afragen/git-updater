<?php
/**
 * Contains autoloading functionality.
 *
 * @package   Fragen\Autoloader
 * @author    Andy Fragen <andy@thefragens.com>
 * @license   GPL-2.0+
 * @link      http://github.com/afragen/autoloader
 * @copyright 2015 Andy Fragen
 */

namespace Fragen;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'Fragen\\Autoloader' ) ) {
	/**
	 * Class Autoloader
	 *
	 * To use with different plugins be sure to create a new namespace.
	 *
	 * @package   Fragen\Autoloader
	 * @author    Andy Fragen <andy@thefragens.com>
	 * @author    Barry Hughes <barry@codingkillsme.com>
	 * @link      http://github.com/afragen/autoloader
	 * @copyright 2015 Andy Fragen
	 * @version   2.0.0
	 */
	class Autoloader {
		/**
		 * Roots to scan when autoloading.
		 *
		 * @var array
		 */
		protected $roots = array();

		/**
		 * List of class names and locations in filesystem, for situations
		 * where they deviate from convention etc.
		 *
		 * @var array
		 */
		protected $map = array();


		/**
		 * Constructor
		 *
		 * @access public
		 *
		 * @param array      $roots      Roots to scan when autoloading.
		 * @param array|null $static_map List of classes that deviate from convention. Defaults to null.
		 */
		public function __construct( array $roots, array $static_map = null ) {
			$this->roots = $roots;
			if ( null !== $static_map ) {
				$this->map = $static_map;
			}
			spl_autoload_register( array( $this, 'autoload' ) );
		}

		/**
		 * Load classes
		 *
		 * @access protected
		 *
		 * @param string $class The class name to autoload.
		 *
		 * @return void
		 */
		protected function autoload( $class ) {
			// Check for a static mapping first of all.
			if ( isset( $this->map[ $class ] ) && file_exists( $this->map[ $class ] ) ) {
				include_once $this->map[ $class ];

				return;
			}

			// Else scan the namespace roots.
			foreach ( $this->roots as $namespace => $root_dir ) {
				// If the class doesn't belong to this namespace, move on to the next root.
				if ( 0 !== strpos( $class, $namespace ) ) {
					continue;
				}

				// Determine the possible path to the class.
				$path = substr( $class, strlen( $namespace ) + 1 );
				$path = str_replace( '\\', DIRECTORY_SEPARATOR, $path );
				$path = $root_dir . DIRECTORY_SEPARATOR . $path . '.php';

				// Test for its existence and load if present.
				if ( file_exists( $path ) ) {
					include_once $path;
				}
			}
		}
	}
}
