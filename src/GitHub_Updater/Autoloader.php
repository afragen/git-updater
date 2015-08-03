<?php

// namespace must be unique to each plugin
namespace Fragen\GitHub_Updater;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 *
 * To use with different plugins be sure to create a new namespace.
 *
 * Class      Autoloader
 * @package   Fragen\GitHub_Updater
 * @author    Andy Fragen <andy@thefragens.com>
 * @author    Barry Hughes <barry@codingkillsme.com>
 * @license   GPL-2.0+
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
	 * List of classnames and locations in filesystem, for situations
	 * where they deviate from convention etc.
	 *
	 * @var array
	 */
	protected $map   = array();


	/**
	 * Constructor
	 *
	 * @param array $roots
	 * @param array $static_map
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
	 * @param $class
	 */
	protected function autoload( $class ) {
		// Check for a static mapping first of all
		if ( isset( $this->map[ $class ] ) && file_exists( $this->map[ $class ] ) ) {
			include $this->map[ $class ];
			return;
		}

		// Else scan the namespace roots
		foreach ( $this->roots as $namespace => $root_dir ) {
			// If the class doesn't belong to this namespace, move on to the next root
			if ( 0 !== strpos( $class, $namespace ) ) {
				continue;
			}

			// Determine the possible path to the class
			$path = substr( $class, strlen( $namespace ) + 1 );
			$path = str_replace( '\\', DIRECTORY_SEPARATOR, $path );
			$path = $root_dir . DIRECTORY_SEPARATOR . $path . '.php';

			// Test for its existence and load if present
			if ( file_exists( $path ) ) {
				include $path;
			}
		}
	}
}
