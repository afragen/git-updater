<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  MIT
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 * @uses     https://meta.trac.wordpress.org/browser/sites/trunk/wordpress.org/public_html/wp-content/plugins/plugin-directory/readme
 */

namespace Fragen\Git_Updater;

use WordPressdotorg\Plugin_Directory\Readme\Parser;
use Parsedown;
use Fragen\Git_Updater\Traits\GU_Trait;

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
	use GU_Trait;

	/**
	 * Repository assets.
	 *
	 * @var array
	 */
	private $assets;

	/**
	 * Constructor.
	 *
	 * @param string $readme Readme contents.
	 * @param string $slug   Repository slug.
	 */
	public function __construct( $readme, $slug ) {
		$this->assets = $this->get_repo_cache( $slug )['assets'];
		parent::__construct( $readme );
	}

	/**
	 * Parse text into markdown.
	 *
	 * @param string $text Text to process.
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
		$data = $this->readme_section_as_h4( 'changelog', $data );
		$data = $this->readme_section_as_h4( 'description', $data );
		$data = $this->readme_section_as_h4( 'installation', $data );
		$data = $this->screenshots_as_list( $data );

		return $data;
	}

	/**
	 * Sanitize contributors.
	 *
	 * @param array $users Array of users.
	 *
	 * @return array
	 */
	protected function sanitize_contributors( $users ) {
		return $users;
	}

	/**
	 * Create contributor data.
	 *
	 * @param array $users Array of users.
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
			if ( version_compare( $wp_version, '5.1-alpha', '<' ) ) {
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
	 * Converts wp.org readme section items to h4 style.
	 *
	 * @param string $section Readme section.
	 * @param array  $data    Array of parsed readme data.
	 *
	 * @return array $data
	 */
	public function readme_section_as_h4( $section, $data ) {
		if ( empty( $data['sections'][ $section ] ) || str_contains( $data['sections'][ $section ], '<h4>' ) ) {
			return $data;
		}
		$pattern = '~<p>=(.*)=</p>~';
		$replace = '<h4>$1</h4>';

		$data['sections'][ $section ] = preg_replace( $pattern, $replace, $data['sections'][ $section ] );

		return $data;
	}

	/**
	 * Create ordered list for screenshots.
	 *
	 * @param  array $data Array of parsed readme data.
	 *
	 * @return array
	 */
	public function screenshots_as_list( $data ) {
		if ( empty( $data['screenshots'] ) ) {
			return $data;
		}

		unset( $data['sections']['screenshots'] );
		$assets      = (array) $this->assets;
		$screenshots = array_filter( $assets, fn( $url, $file ) => str_starts_with( $file, 'screenshot-' ), ARRAY_FILTER_USE_BOTH );

		$data['sections']['screenshots'] = '<ol>';
		foreach ( $data['screenshots'] as $file_num => $caption ) {
			foreach ( $screenshots as $file => $url ) {
				$url     = esc_url( $url );
				$alt     = esc_attr( $caption );
				$caption = esc_html( $caption );
				if ( str_starts_with( $file, 'screenshot-' . $file_num ) ) {
					$data['sections']['screenshots'] .= "<li><a href=\"{$url}\"><img src=\"{$url}\" alt=\"{$alt}\"></a><p>{$caption}</p></li>";
					break;
				}
			}
		}
		$data['sections']['screenshots'] .= '</ol>';

		return $data;
	}

	/**
	 * Replace parent method as some users don't have `mb_strrpos()`.
	 *
	 * @access protected
	 *
	 * @param string $desc   Description.
	 * @param int    $length Number of characters.
	 * @param string $type   The type of the length, 'char' or 'words'.
	 *
	 * @return string
	 */
	protected function trim_length( $desc, $length = 150, $type = 'char' ) {
		if ( is_string( $length ) ) {
			$length = $this->maximum_field_lengths[ $length ] ?? $length;
		}

		if ( 'words' === $type ) {
			// Split by whitespace, capturing it so we can put it back together.
			$pieces = preg_split( '/(\s+)/u', $desc, -1, PREG_SPLIT_DELIM_CAPTURE );

			$word_count_with_spaces = $length * 2;

			if ( count( $pieces ) < $word_count_with_spaces ) {
				return $desc;
			}

			$pieces = array_slice( $pieces, 0, $word_count_with_spaces );

			return implode( '', $pieces ) . ' &hellip;';
		}

		// Apply the length restriction without counting html entities.
		$str_length = mb_strlen( html_entity_decode( $desc ) ?: $desc );

		if ( $str_length > $length ) {
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
