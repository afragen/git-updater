<?php

// namespace must be unique to each plugin
namespace GitHub_Updater;

/**
 * Class Autoloader - generic autoload class
 *
 * To use with different plugins be sure to create a new namespace.
 *
 * @package   Autoloader
 * @author    Andy Fragen <andy@thefragens.com>
 * @license   GPL-2.0+
 * @link      http://github.com/afragen/autoloader
 * @copyright 2015 Andy Fragen
 * @version   1.1.0
 */

class Autoloader {

	/**
	 * Constructor
	 */
	public function __construct() {
		spl_autoload_register( array( $this, 'autoload' ) );
	}

	/**
	 * Autoloader
	 *
	 * @param $class
	 */
	protected function autoload( $class ) {
		$classes = array();

		// 4 directories deep, add more as needed
		$directories = array( '', '*/', '*/*/', '*/*/*/', '*/*/*/*/' );

		foreach ( $directories as $directory ) {
			foreach ( glob( trailingslashit( __DIR__ ) . $directory . '*.php' ) as $file ) {
				$base       = __DIR__;
				$class_dir  = dirname( $file );
				$class_dir  = str_replace( $base, '', $class_dir );
				$class_dir  = ltrim( $class_dir, '/' );
				$class_dir  = str_replace( '/', '_', $class_dir );
				$class_name = str_replace( '.php', '', basename( $file ) );
				if ( ! empty( $class_dir ) ) {
					$class_name = $class_dir . '_' . $class_name;
				}
				$classes[ strtolower( $class_name ) ] = $file;
			}
		}

		$cn = strtolower( $class );

		if ( isset( $classes[ $cn ] ) ) {
			require_once( $classes[ $cn ] );
		}
	}
}

new Autoloader();
