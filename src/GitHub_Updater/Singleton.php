<?php
/**
 * GitHub Updater
 *
 * @package   GitHub_Updater
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 */

namespace Fragen\GitHub_Updater;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Singleton
 *
 * A static proxy for creating Singletons from passed class names.
 *
 * @package Fragen\GitHub_Updater
 */
final class Singleton {

	/**
	 * @param  string              $class
	 * @param null|array|\stdClass $options
	 *
	 * @return array
	 */
	public static function get_instance( $class, $options = null ) {
		static $instance = null;

		$class = __NAMESPACE__ . '\\' . $class;

		if ( null === $instance || ! isset( $instance[ $class ] ) ) {
			$instance[ $class ] = new $class( $options );
		}

		// Stores calling class for use in class Basic_Auth_Loader.
		if ( $instance[ $class ] instanceof Basic_Auth_Loader ) {
			$backtrace                  = debug_backtrace();
			$instance[ $class ]->caller = isset( $backtrace[1]['object'] ) ? $backtrace[1]['object'] : null;
		}

		return $instance[ $class ];
	}

}
