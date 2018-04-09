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
	 * @version 1.0.0
	 */
	final class Singleton {

		/**
		 * Get instance of class.
		 *
		 * @param string               $class_name
		 * @param object               $caller Originating object.
		 * @param null|array|\stdClass $options
		 *
		 * @return array $instance
		 */
		public static function get_instance( $class_name, $caller = null, $options = null ) {
			static $instance = null;

			$class = get_class( $caller );
			$class = self::get_class( $class_name, $class );

			if ( ! $class ) {
				self::get_error( $class_name );
			}

			if ( null === $instance || ! isset( $instance[ $class ] ) ) {
				$instance[ $class ] = new $class( $options );
			}

			// Add calling object.
			$instance[ $class ]->caller = $caller;

			return $instance[ $class ];
		}

		/**
		 * Determine correct class name with namespace and return.
		 *
		 * @param string $class_name
		 * @param string $class
		 *
		 * @return string Namespaced class name.
		 */
		private static function get_class( $class_name, $class ) {
			$reflection      = self::get_reflection( $class );
			$namespace       = $reflection->getNamespaceName();
			$namespace_parts = explode( '\\', $namespace );
			$count           = count( $namespace_parts );
			$classes[ - 1 ]  = null;

			for ( $i = 0; $i < $count; $i ++ ) {
				$classes[ $i ] = ltrim( $classes[ $i - 1 ] . '\\' . $namespace_parts[ $i ], '\\' );
			}

			$classes = array_reverse( $classes );
			foreach ( $classes as $namespace ) {
				$namespaced_class = $namespace . '\\' . $class_name;
				if ( class_exists( $namespaced_class ) ) {
					return $namespaced_class;
				}
			}

			return false;
		}

		/**
		 * Get ReflectionClass of passed class name.
		 *
		 * @param string $class
		 *
		 * @return \ReflectionClass $reflection
		 */
		private static function get_reflection( $class ) {
			try {
				$reflection = new \ReflectionClass( $class );
			} catch ( \ReflectionException $Exception ) {
				die( '<table>' . $Exception->xdebug_message . '</table>' );
			}

			return $reflection;
		}

		/**
		 * Returns error message for not finding a class.
		 *
		 * @param string $class_name
		 */
		private static function get_error( $class_name ) {
			$error     = '<tr><td><pre><strong>PHP Fatal:</strong> Undefined class "' . $class_name . '"</pre></td></tr>';
			$Exception = new \Exception( $error );
			$trace     = $Exception->getTrace();
			$trace     = array_reverse( $trace );
			$message   = $Exception->getMessage();
			$message   .= '<tr><td><pre>PHP Stack Trace:</pre></td></tr>';
			$i         = 0;
			foreach ( $trace as $err ) {
				$i ++;
				$message .= '<tr><td><pre>';
				$message .= sprintf( $i . '. %1$s called from %2$s:%3$s',
					'<strong>' . $err['class'] . $err['type'] . $err['function'] . '()</strong>',
					'<strong>' . $err['file'] . '</strong>',
					$err['line'] . '</pre></td></tr>'
				);
			}
			die( '<table>' . $message . '</table>' );
		}
	}
}
