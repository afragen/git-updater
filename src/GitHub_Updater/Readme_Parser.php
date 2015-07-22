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
 * @package Fragen\GitHub_Updater
 */
class Readme_Parser extends \Automattic_Readme {

	/**
	 * Constructor
	 */
	public function __construct() {}

	/**
	 * @param $file_contents
	 *
	 * @return array
	 */
	public function parse_readme( $file_contents ) {
		return $this->parse_readme_contents( $file_contents );
	}

	/**
	 * @param      $text
	 * @param bool $markdown
	 *
	 * @return mixed|string
	 */
	public function filter_text( $text, $markdown = false ) { // fancy, Markdown
		$text = trim($text);
		$text = call_user_func( array( get_parent_class( $this ), 'code_trick' ), $text, $markdown ); // A better parser than Markdown's for: backticks -> CODE

		if ( $markdown ) { // Parse markdown.
			$parser = new \Parsedown;
			$text   = $parser->text( $text );
		}

		$allowed = array(
			'a' => array(
				'href' => array(),
				'title' => array(),
				'rel' => array()),
			'blockquote' => array('cite' => array()),
			'br' => array(),
			'cite' => array(),
			'p' => array(),
			'code' => array(),
			'pre' => array(),
			'em' => array(),
			'strong' => array(),
			'ul' => array(),
			'ol' => array(),
			'li' => array(),
			'h3' => array(),
			'h4' => array()
		);

		$text = balanceTags($text);

		$text = wp_kses( $text, $allowed );
		$text = trim($text);
		return $text;
	}

}
