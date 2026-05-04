<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  GPL-3.0-or-later
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 * @uses     https://meta.trac.wordpress.org/browser/sites/trunk/wordpress.org/public_html/wp-content/plugins/plugin-directory/readme
 */

namespace Fragen\Git_Updater;

use Fragen\WP_Readme_Parser\Parser;
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
	 * @var array<string, mixed>
	 */
	private $assets;

	/**
	 * Constructor.
	 *
	 * @param string $readme Readme contents.
	 * @param string $slug   Repository slug.
	 */
	public function __construct( $readme, $slug ) {
		$this->assets = $this->get_repo_cache( $slug, false )['assets'] ?? [];
		parent::__construct( $readme );
	}

	/**
	 * Return parsed readme.txt as array.
	 *
	 * @return array<string, mixed> $data
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
	 * @param array<int, string> $users Array of users.
	 *
	 * @return array<int, string>
	 */
	protected function sanitize_contributors( $users ) {
		return $users;
	}

	/**
	 * Create contributor data.
	 *
	 * @param array<int, string> $users Array of users.
	 *
	 * @return array<string, mixed> $contributors
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
	 * @param array<string, mixed> $data Array of parsed readme data.
	 *
	 * @return array<string, mixed> $data
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
	 * @param string               $section Readme section.
	 * @param array<string, mixed> $data    Array of parsed readme data.
	 *
	 * @return array<string, mixed> $data
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
	 * @param  array<string, mixed> $data Array of parsed readme data.
	 *
	 * @return array<string, mixed>
	 */
	public function screenshots_as_list( $data ) {
		if ( empty( $data['screenshots'] ) ) {
			return $data;
		}

		$assets = (array) $this->assets;
		if ( empty( $assets ) ) {
			return $data;
		}

		unset( $data['sections']['screenshots'] );
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
}
