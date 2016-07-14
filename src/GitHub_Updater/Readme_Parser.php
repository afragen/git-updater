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
 * Class Readme_Parser
 *
 * @package Fragen\GitHub_Updater
 */
class Readme_Parser extends \Baikonur_ReadmeParser {

	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/**
	 * @param $file_contents
	 *
	 * @return array
	 */
	public static function parse_readme( $file_contents ) {
		return (array) parent::parse_readme_contents( $file_contents );
	}

	/**
	 * @param $text
	 *
	 * @return string
	 */
	protected static function parse_markdown( $text ) {
		$parser = new \Parsedown();
		$text   = parent::code_trick( $text );
		$text   = preg_replace( '/^[\s]*=[\s]+(.+?)[\s]+=/m', "\n" . '<h4>$1</h4>' . "\n", $text );
		$text   = $parser->text( trim( $text ) );

		return trim( $text );
	}

}
