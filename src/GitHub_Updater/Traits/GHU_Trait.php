<?php
/**
 * GitHub Updater
 *
 * @package   GitHub_Updater
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 */

namespace Fragen\GitHub_Updater\Traits;

use Fragen\Singleton;

/**
 * Trait GHU_Trait
 *
 * @package Fragen\GitHub_Updater
 */
trait GHU_Trait {

	/**
	 * Getter for class variables.
	 * Uses ReflectionProperty->isStatic() for testing.
	 *
	 * @param string $var Name of variable.
	 *
	 * @return mixed
	 */
	public function get_class_vars( $class_name, $var ) {
		$class = Singleton::get_instance( $class_name, $this );
		try {
			$prop = new \ReflectionProperty( get_class( $class ), $var );
		} catch ( \ReflectionException $Exception ) {
			die( '<table>' . $Exception->xdebug_message . '</table>' );
		}
		$static = $prop->isStatic();

		return $static ? $class::${$var} : $class->$var;
	}

}
