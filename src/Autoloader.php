<?php
/**
 * Contains autoloading functionality.
 *
 * @author    Andy Fragen <andy@thefragens.com>
 * @license   GPL-2.0+
 * @link      http://github.com/afragen/autoloader
 * @copyright 2015 Andy Fragen
 * @package   autoloader
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
	 * @author    Andy Fragen <andy@thefragens.com>
	 * @author    Barry Hughes <barry@codingkillsme.com>
	 * @link      http://github.com/afragen/autoloader
	 * @copyright 2015 Andy Fragen
	 * @version   2.2.0
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
		 * Constructor.
		 *
		 * @access public
		 *
		 * @param array      $roots      Roots to scan when autoloading.
		 * @param array|null $static_map Array of classes that deviate from convention.
		 *                               Defaults to null.
		 */
		public function __construct( array $roots, array $static_map = null ) {
			$this->roots = $roots;
			if ( null !== $static_map ) {
				$this->map = $static_map;
			}
			spl_autoload_register( array( $this, 'autoload' ) );
		}

		/**
		 * Load classes.
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

				$psr4_fname = substr( $class, strlen( $namespace ) + 1 );
				$psr4_fname = str_replace( '\\', DIRECTORY_SEPARATOR, $psr4_fname );

				// Determine the possible path to the class, include all subdirectories.
				$objects = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root_dir ), \RecursiveIteratorIterator::SELF_FIRST );
				foreach ( $objects as $name => $object ) {
					if ( is_dir( $name ) ) {
						$directories[] = rtrim( $name, './' );
					}
				}
				$directories = array_unique( $directories );

				$paths = $this->get_paths( $directories, array( $psr4_fname ) );

				// Test for its existence and load if present.
				foreach ( $paths as $path ) {
					if ( file_exists( $path ) ) {
						include_once $path;
						break;
					}
				}
			}
		}

		/**
		 * Get and return an array of possible file paths.
		 *
		 * @param array $dirs       Array of plugin directories and subdirectories.
		 * @param array $file_names Array of possible file names.
		 *
		 * @return mixed
		 */
		private function get_paths( $dirs, $file_names ) {
			foreach ( $file_names as $file_name ) {
				$paths[] = array_map(
					function ( $dir ) use ( $file_name ) {
						return $dir . DIRECTORY_SEPARATOR . $file_name . '.php';
					}, $dirs
				);
			}

			return call_user_func_array( 'array_merge', $paths );
		}
	}
}
