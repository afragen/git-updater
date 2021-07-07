<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  MIT
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

namespace Fragen\Git_Updater;

/**
 * Class Shim
 *
 * Provides PHP 5.6 compatible shims.
 */
class Shim {

	/**
	 * Shim for `dirname()`
	 *
	 * @param string $path File path for dirname().
	 * @param int    $level Level of file path, added in PHP 7.0.
	 *
	 * @return string
	 */
	public static function dirname( $path, $level = 1 ) {
		if ( version_compare( phpversion(), '7.0', '>=' ) ) {
			return dirname( $path, $level );
		} else {
			switch ( $level ) {
				case 2:
					return dirname( dirname( $path ) );
				case 3:
					return dirname( dirname( dirname( $path ) ) );
				default:
					return dirname( $path );
			}
		}
	}
}
