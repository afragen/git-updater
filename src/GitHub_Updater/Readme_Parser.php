<?php
/**
 * GitHub Updater
 *
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 * @package   github-updater
 * @uses      http://meta.svn.wordpress.org/sites/trunk/wordpress.org/public_html/wp-content/plugins/plugin-directory/readme/class-parser.php
 */

namespace Fragen\GitHub_Updater;

use WordPressdotorg\Plugin_Directory\Readme\Parser;
use Parsedown;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Readme_Parser
 */
class Readme_Parser extends Parser {

	/**
	 * Constructor.
	 *
	 * Convert file contents string to temporary file.
	 * Pass file path into class-parser.php.
	 * Delete temporary file when finished.
	 *
	 * @param string $file
	 *
	 * @return void
	 */
	public function __construct( $file ) {
		$file_path = WP_CONTENT_DIR . '/tmp-readme.txt';
		$file_path = file_put_contents( $file_path, $file ) ? $file_path : false;
		parent::__construct( $file_path );
	}

	/**
	 * Parse text into markdown.
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	protected function parse_markdown( $text ) {
		static $markdown = null;

		if ( null === $markdown ) {
			$markdown = new Parsedown();
		}

		return $markdown->text( $text );
	}

	/**
	 * Return parsed readme.txt as array.
	 *
	 * @return array $data
	 */
	public function parse_data() {
		$data = [];
		foreach ( get_object_vars( $this ) as $key => $value ) {
			$data[ $key ] = 'contributors' === $key ? $this->create_contributors( $value ) : $value;
		}
		$data = $this->faq_as_h4( $data );

		return $data;
	}

	/**
	 * @param array $users
	 *
	 * @return array
	 */
	protected function sanitize_contributors( $users ) {
		return $users;
	}

	/**
	 * Create contributor data.
	 *
	 * @param array $users
	 *
	 * @return array $contributors
	 */
	private function create_contributors( $users ) {
		global $wp_version;
		$contributors = [];
		foreach ( (array) $users as $contributor ) {
			$contributors[ $contributor ]['display_name'] = $contributor;
			$contributors[ $contributor ]['profile']      = '//profiles.wordpress.org/' . $contributor;
			$contributors[ $contributor ]['avatar']       = 'https://wordpress.org/grav-redirect.php?user=' . $contributor;
			if ( $wp_version < '5.0-alpha-42631' ) {
				$contributors[ $contributor ] = '//profiles.wordpress.org/' . $contributor;
			}
		}

		return $contributors;
	}

	/**
	 * Converts FAQ from dictionary list to h4 style.
	 *
	 * @param array $data Array of parsed readme data.
	 *
	 * @return array $data
	 */
	public function faq_as_h4( $data ) {
		if ( empty( $data['faq'] ) ) {
			return $data;
		}
		unset( $data['sections']['faq'] );
		$data['sections']['faq'] = '';
		foreach ( $data['faq'] as $question => $answer ) {
			$data['sections']['faq'] .= "<h4>{$question}</h4>\n{$answer}\n";
		}

		return $data;
	}

	/**
	 * Replace parent method as some users don't have `mb_strrpos()`.
	 *
	 * @access protected
	 *
	 * @param string $desc
	 * @param int    $length
	 *
	 * @return string
	 */
	protected function trim_length( $desc, $length = 150 ) {
		if ( mb_strlen( $desc ) > $length ) {
			$desc = mb_substr( $desc, 0, $length ) . ' &hellip;';

			// If not a full sentence, and one ends within 20% of the end, trim it to that.
			if ( function_exists( 'mb_strrpos' ) ) {
				$pos = mb_strrpos( $desc, '.' );
			} else {
				$pos = strrpos( $desc, '.' );
			}
			if ( $pos > ( 0.8 * $length ) && '.' !== mb_substr( $desc, -1 ) ) {
				$desc = mb_substr( $desc, 0, $pos + 1 );
			}
		}

		return trim( $desc );
	}
}
