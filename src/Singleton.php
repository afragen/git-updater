<?php
/**
 * Singleton Factory
 *
 * @package   Singleton Factory
 * @author    Andy Fragen
 * @license   MIT
 * @link      https://github.com/afragen/singleton-factory
 */

namespace Fragen;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'Fragen\\Singleton' ) ) {

	/**
	 * Class Singleton
	 *
	 * A static proxy for creating Singletons from passed class names.
	 *
	 * @package Fragen
	 */
	final class Singleton {

		/**
		 * @param string               $class_name
		 * @param null|array|\stdClass $options
		 *
		 * @return array
		 */
		public static function get_instance( $class_name, $options = null ) {
			static $instance = null;
			$backtrace = debug_backtrace();
			$class     = isset( $backtrace[1]['class'] ) ? $backtrace[1]['class'] : null;

			try {
				$reflection = new \ReflectionClass( $class );
				$class      = $reflection->getNamespaceName() . '\\' . $class_name;
			} catch ( \ReflectionException $Exception ) {
				die( '<table>' . $Exception->xdebug_message . '</table>' );
			}

			if ( null === $instance || ! isset( $instance[ $class ] ) ) {
				$instance[ $class ] = new $class( $options );
			}

			// Add calling object.
			$instance[ $class ]->caller = isset( $backtrace[1]['object'] ) ? $backtrace[1]['object'] : null;

			return $instance[ $class ];
		}
	}

}
