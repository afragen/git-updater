<?php
namespace WordPressdotorg\Plugin_Directory\Readme;

use WordPressdotorg\Plugin_Directory\Markdown;

/**
 * WordPress.org Plugin Readme Parser.
 *
 * Based on Baikonur_ReadmeParser from https://github.com/rmccue/WordPress-Readme-Parser
 *
 * @package WordPressdotorg\Plugin_Directory\Readme
 */
class Parser {

	/**
	 * @var string
	 */
	public $name = '';

	/**
	 * @var array
	 */
	public $tags = array();

	/**
	 * @var string
	 */
	public $requires = '';

	/**
	 * @var string
	 */
	public $tested = '';

	/**
	 * @var string
	 */
	public $requires_php = '';

	/**
	 * @var array
	 */
	public $contributors = array();

	/**
	 * @var string
	 */
	public $stable_tag = '';

	/**
	 * @var string
	 */
	public $donate_link = '';

	/**
	 * @var string
	 */
	public $short_description = '';

	/**
	 * @var string
	 */
	public $license = '';

	/**
	 * @var string
	 */
	public $license_uri = '';

	/**
	 * @var array
	 */
	public $sections = array();

	/**
	 * @var array
	 */
	public $upgrade_notice = array();

	/**
	 * @var array
	 */
	public $screenshots = array();

	/**
	 * @var array
	 */
	public $faq = array();

	/**
	 * Warning flags which indicate specific parsing failures have occured.
	 *
	 * @var array
	 */
	public $warnings = array();

	/**
	 * These are the readme sections that we expect.
	 *
	 * @var array
	 */
	public $expected_sections = array(
		'description',
		'installation',
		'faq',
		'screenshots',
		'changelog',
		'upgrade_notice',
		'other_notes',
	);

	/**
	 * We alias these sections, from => to
	 *
	 * @var array
	 */
	public $alias_sections = array(
		'frequently_asked_questions' => 'faq',
		'change_log'                 => 'changelog',
		'screenshot'                 => 'screenshots',
	);

	/**
	 * These are the valid header mappings for the header.
	 *
	 * @var array
	 */
	public $valid_headers = array(
		'tested'            => 'tested',
		'tested up to'      => 'tested',
		'requires'          => 'requires',
		'requires at least' => 'requires',
		'requires php'      => 'requires_php',
		'tags'              => 'tags',
		'contributors'      => 'contributors',
		'donate link'       => 'donate_link',
		'stable tag'        => 'stable_tag',
		'license'           => 'license',
		'license uri'       => 'license_uri',
	);

	/**
	 * These plugin tags are ignored.
	 *
	 * @var array
	 */
	public $ignore_tags = array(
		'plugin',
		'wordpress',
	);

	/**
	 * The maximum field lengths for the readme.
	 *
	 * @var array
	 */
	public $maximum_field_lengths = array(
		'short_description' => 150,
		'section'           => 2500,
		'section-changelog' => 5000,
		'section-faq'       => 5000,
	);

	/**
	 * The raw contents of the readme file.
	 *
	 * @var string
	 */
	public $raw_contents = '';

	/**
	 * Parser constructor.
	 *
	 * @param string $string A Filepath, URL, or contents of a readme to parse.
	 *
	 * Note: data:text/plain streams are URLs and need to pass through
	 * the parse_readme() function, not the parse_readme_contents() function, so
	 * that they can be turned from a URL into plain text via the stream.
	 */
	public function __construct( $string = '' ) {
		if (
			(
				// If it's longer than the Filesystem path limit or contains newlines, it's not worth a file_exists() check.
				strlen( $string ) <= PHP_MAXPATHLEN
				&& false === strpos( $string, "\n" )
				&& file_exists( $string )
			)
			|| preg_match( '!^https?://!i', $string ) 
			|| preg_match( '!^data:text/plain!i', $string) ) 
		{
			$this->parse_readme( $string );
		} elseif ( $string ) {
			$this->parse_readme_contents( $string );
		}
	}

	/**
	 * @param string $file_or_url
	 * @return bool
	 */
	protected function parse_readme( $file_or_url ) {
		$context = stream_context_create( array(
			'http' => array(
				'user_agent' => 'WordPress.org Plugin Readme Parser',
			)
		) );

		$contents = file_get_contents( $file_or_url, false, $context );

		return $this->parse_readme_contents( $contents );
	}

	/**
	 * @param string $contents The contents of the readme to parse.
	 * @return bool
	 */
	protected function parse_readme_contents( $contents ) {
		$this->raw_contents = $contents;

		if ( preg_match( '!!u', $contents ) ) {
			$contents = preg_split( '!\R!u', $contents );
		} else {
			$contents = preg_split( '!\R!', $contents ); // regex failed due to invalid UTF8 in $contents, see #2298
		}
		$contents = array_map( array( $this, 'strip_newlines' ), $contents );

		// Strip UTF8 BOM if present.
		if ( 0 === strpos( $contents[0], "\xEF\xBB\xBF" ) ) {
			$contents[0] = substr( $contents[0], 3 );
		}

		// Convert UTF-16 files.
		if ( 0 === strpos( $contents[0], "\xFF\xFE" ) ) {
			foreach ( $contents as $i => $line ) {
				$contents[ $i ] = mb_convert_encoding( $line, 'UTF-8', 'UTF-16' );
			}
		}

		$line       = $this->get_first_nonwhitespace( $contents );
		$this->name = $this->sanitize_text( trim( $line, "#= \t\0\x0B" ) );

		// It's possible to leave the plugin name header off entirely.. 
		if ( $this->parse_possible_header( $line, true /* only valid headers */ ) ) {
			array_unshift( $contents, $line );

			$this->warnings['invalid_plugin_name_header'] = true;
			$this->name                                   = false;
		}

		// Strip Github style header\n==== underlines.
		if ( ! empty( $contents ) && '' === trim( $contents[0], '=-' ) ) {
			array_shift( $contents );
		}

		// Handle readme's which do `=== Plugin Name ===\nMy SuperAwesomePlugin Name\n...`
		if ( 'plugin name' == strtolower( $this->name ) ) {
			$this->warnings['invalid_plugin_name_header'] = true;

			$this->name = false;
			$line       = $this->get_first_nonwhitespace( $contents );

			// Ensure that the line read doesn't look like a description.
			if ( strlen( $line ) < 50 && ! $this->parse_possible_header( $line, true /* only valid headers */ ) ) {
				$this->name = $this->sanitize_text( trim( $line, "#= \t\0\x0B" ) );
			} else {
				// Put it back on the stack to be processed.
				array_unshift( $contents, $line );
			}
		}

		// Parse headers.
		$headers = array();

		$line                = $this->get_first_nonwhitespace( $contents );
		$last_line_was_blank = false;
		do {
			$value  = null;
			$header = $this->parse_possible_header( $line );

			// If it doesn't look like a header value, maybe break to the next section.
			if ( ! $header ) {
				if ( empty( $line ) ) {
					// Some plugins have line-breaks within the headers...
					$last_line_was_blank = true;
					continue;
				} else {
					// We've hit a line that is not blank, but also doesn't look like a header, assume the Short Description and end Header parsing.
					break;
				}
			}

			list( $key, $value ) = $header;

			if ( isset( $this->valid_headers[ $key ] ) ) {
				$headers[ $this->valid_headers[ $key ] ] = $value;
			} elseif ( $last_line_was_blank ) {
				// If we skipped over a blank line, and then ended up with an unexpected header, assume we parsed too far and ended up in the Short Description.
				// This final line will be added back into the stack after the loop for further parsing.
				break;
			}

			$last_line_was_blank = false;
		} while ( ( $line = array_shift( $contents ) ) !== null );
		array_unshift( $contents, $line );

		if ( ! empty( $headers['tags'] ) ) {
			$this->tags = explode( ',', $headers['tags'] );
			$this->tags = array_map( 'trim', $this->tags );
			$this->tags = array_filter( $this->tags );

			if ( array_intersect( $this->tags, $this->ignore_tags ) ) {
				$this->warnings['ignored_tags'] = array_intersect( $this->tags, $this->ignore_tags );
				$this->tags                     = array_diff( $this->tags, $this->ignore_tags );
			}

			if ( count( $this->tags ) > 5 ) {
				$this->warnings['too_many_tags'] = array_slice( $this->tags, 5 );
				$this->tags                      = array_slice( $this->tags, 0, 5 );
			}
		}
		if ( ! empty( $headers['requires'] ) ) {
			$this->requires = $this->sanitize_requires_version( $headers['requires'] );
		}
		if ( ! empty( $headers['tested'] ) ) {
			$this->tested = $this->sanitize_tested_version( $headers['tested'] );
		}
		if ( ! empty( $headers['requires_php'] ) ) {
			$this->requires_php = $this->sanitize_requires_php( $headers['requires_php'] );
		}
		if ( ! empty( $headers['contributors'] ) ) {
			$this->contributors = explode( ',', $headers['contributors'] );
			$this->contributors = array_map( 'trim', $this->contributors );
			$this->contributors = $this->sanitize_contributors( $this->contributors );
		}
		if ( ! empty( $headers['stable_tag'] ) ) {
			$this->stable_tag = $this->sanitize_stable_tag( $headers['stable_tag'] );
		}
		if ( ! empty( $headers['donate_link'] ) ) {
			$this->donate_link = $headers['donate_link'];
		}
		if ( ! empty( $headers['license'] ) ) {
			// Handle the many cases of "License: GPLv2 - http://..."
			if ( empty( $headers['license_uri'] ) && preg_match( '!(https?://\S+)!i', $headers['license'], $url ) ) {
				$headers['license_uri'] = trim( $url[1], " -*\t\n\r\n(" );
				$headers['license']     = trim( str_replace( $url[1], '', $headers['license'] ), " -*\t\n\r\n(" );
			}

			$this->license = $headers['license'];
		}
		if ( ! empty( $headers['license_uri'] ) ) {
			$this->license_uri = $headers['license_uri'];
		}

		// Validate the license specified.
		if ( ! $this->license ) {
			$this->warnings['license_missing'] = true;
		} else {
			$license_error = $this->validate_license( $this->license );
			if ( true !== $license_error ) {
				$this->warnings[ $license_error ] = $this->license;
			}
		}

		// Parse the short description.
		while ( ( $line = array_shift( $contents ) ) !== null ) {
			$trimmed = trim( $line );
			if ( empty( $trimmed ) ) {
				$this->short_description .= "\n";
				continue;
			}
			if ( ( '=' === $trimmed[0] && isset( $trimmed[1] ) && '=' === $trimmed[1] ) ||
				 ( '#' === $trimmed[0] && isset( $trimmed[1] ) && '#' === $trimmed[1] )
			) {

				// Stop after any Markdown heading.
				array_unshift( $contents, $line );
				break;
			}

			$this->short_description .= $line . "\n";
		}
		$this->short_description = trim( $this->short_description );

		/*
		 * Parse the rest of the body.
		 * Pre-fill the sections, we'll filter out empty sections later.
		 */
		$this->sections = array_fill_keys( $this->expected_sections, '' );
		$current        = $section_name = $section_title = '';
		while ( ( $line = array_shift( $contents ) ) !== null ) {
			$trimmed = trim( $line );
			if ( empty( $trimmed ) ) {
				$current .= "\n";
				continue;
			}

			// Stop only after a ## Markdown header, not a ###.
			if ( ( '=' === $trimmed[0] && isset( $trimmed[1] ) && '=' === $trimmed[1] ) ||
				 ( '#' === $trimmed[0] && isset( $trimmed[1] ) && '#' === $trimmed[1] && isset( $trimmed[2] ) && '#' !== $trimmed[2] )
			) {

				if ( ! empty( $section_name ) ) {
					$this->sections[ $section_name ] .= trim( $current );
				}

				$current       = '';
				$section_title = trim( $line, "#= \t" );
				$section_name  = strtolower( str_replace( ' ', '_', $section_title ) );

				if ( isset( $this->alias_sections[ $section_name ] ) ) {
					$section_name = $this->alias_sections[ $section_name ];
				}

				// If we encounter an unknown section header, include the provided Title, we'll filter it to other_notes later.
				if ( ! in_array( $section_name, $this->expected_sections ) ) {
					$current     .= '<h3>' . $section_title . '</h3>';
					$section_name = 'other_notes';
				}
				continue;
			}

			$current .= $line . "\n";
		}

		if ( ! empty( $section_name ) ) {
			$this->sections[ $section_name ] .= trim( $current );
		}

		// Filter out any empty sections.
		$this->sections = array_filter( $this->sections );

		// Use the short description for the description section if not provided.
		if ( empty( $this->sections['description'] ) ) {
			$this->sections['description'] = $this->short_description;
		}

		// Suffix the Other Notes section to the description.
		if ( ! empty( $this->sections['other_notes'] ) ) {
			$this->sections['description'] .= "\n" . $this->sections['other_notes'];
			unset( $this->sections['other_notes'] );
		}

		// Parse out the Upgrade Notice section into it's own data.
		if ( isset( $this->sections['upgrade_notice'] ) ) {
			$this->upgrade_notice = $this->parse_section( $this->sections['upgrade_notice'] );
			$this->upgrade_notice = array_map( array( $this, 'sanitize_text' ), $this->upgrade_notice );
			unset( $this->sections['upgrade_notice'] );
		}

		foreach ( $this->sections as $section => $content ) {
			$max_length = "section-{$section}";
			if ( ! isset( $this->maximum_field_lengths[ $max_length ] ) ) {
				$max_length = 'section';
			}

			$this->sections[ $section ] = $this->trim_length( $content, $max_length, 'words' );

			if ( $content !== $this->sections[ $section ] ) {
				$this->warnings["trimmed_section_{$section}"] = true;
			}
		}

		// Display FAQs as a definition list.
		if ( isset( $this->sections['faq'] ) ) {
			$this->faq             = $this->parse_section( $this->sections['faq'] );
			$this->sections['faq'] = '';
		}

		// Markdownify!
		$this->sections       = array_map( array( $this, 'parse_markdown' ), $this->sections );
		$this->upgrade_notice = array_map( array( $this, 'parse_markdown' ), $this->upgrade_notice );
		$this->faq            = array_map( array( $this, 'parse_markdown' ), $this->faq );

		// Use the first line of the description for the short description if not provided.
		if ( ! $this->short_description && ! empty( $this->sections['description'] ) ) {
			$this->short_description = array_filter( explode( "\n", $this->sections['description'] ) )[0];
			$this->warnings['no_short_description_present'] = true;
		}

		// Sanitize and trim the short_description to match requirements.
		$this->short_description = $this->sanitize_text( $this->short_description );
		$this->short_description = $this->parse_markdown( $this->short_description );
		$this->short_description = wp_strip_all_tags( $this->short_description );
		$short_description       = $this->trim_length( $this->short_description, 'short_description' );
		if ( $short_description !== $this->short_description ) {
			if ( empty( $this->warnings['no_short_description_present'] ) ) {
				$this->warnings['trimmed_short_description'] = true;
			}
			$this->short_description = $short_description;
		}

		if ( isset( $this->sections['screenshots'] ) ) {
			preg_match_all( '#<li>(.*?)</li>#is', $this->sections['screenshots'], $screenshots, PREG_SET_ORDER );
			if ( $screenshots ) {
				$i = 1; // Screenshots start from 1.
				foreach ( $screenshots as $ss ) {
					$this->screenshots[ $i++ ] = $this->filter_text( $ss[1] );
				}
			}
			unset( $this->sections['screenshots'] );
		}

		if ( ! empty( $this->faq ) ) {
			// If the FAQ contained data we couldn't parse, we'll treat it as freeform and display it before any questions which are found.
			if ( isset( $this->faq[''] ) ) {
				$this->sections['faq'] .= $this->faq[''];
				unset( $this->faq[''] );
			}

			if ( $this->faq ) {
				$this->sections['faq'] .= "\n<dl>\n";
				foreach ( $this->faq as $question => $answer ) {
					$question_slug          = rawurlencode( trim( strtolower( $question ) ) );
					$this->sections['faq'] .= "<dt id='{$question_slug}'><h3>{$question}</h3></dt>\n<dd>{$answer}</dd>\n";
				}
				$this->sections['faq'] .= "\n</dl>\n";
			}
		}

		// Filter the HTML.
		$this->sections = array_map( array( $this, 'filter_text' ), $this->sections );

		return true;
	}

	/**
	 * @access protected
	 *
	 * @param string $contents
	 * @return string
	 */
	protected function get_first_nonwhitespace( &$contents ) {
		while ( ( $line = array_shift( $contents ) ) !== null ) {
			$trimmed = trim( $line );
			if ( ! empty( $trimmed ) ) {
				break;
			}
		}

		return $line ?? '';
	}

	/**
	 * @access protected
	 *
	 * @param string $line
	 * @return string
	 */
	protected function strip_newlines( $line ) {
		return rtrim( $line, "\r\n" );
	}

	/**
	 * @access protected
	 *
	 * @param string $desc
	 * @param int    $length
	 * @param string $type   The type of the length, 'char' or 'words'.
	 * @return string
	 */
	protected function trim_length( $desc, $length = 150, $type = 'char' ) {
		if ( is_string( $length ) ) {
			$length = $this->maximum_field_lengths[ $length ] ?? $length;
		}

		if ( 'words' === $type ) {
			// Split by whitespace, capturing it so we can put it back together.
			$pieces = @preg_split( '/(\s+)/u', $desc, -1, PREG_SPLIT_DELIM_CAPTURE );

			// In the event of an error (Likely invalid UTF8 data), perform the same split, this time in a non-UTF8 safe manner, as a fallback.
			if ( $pieces === false ) {
				$pieces = preg_split( '/(\s+)/', $desc, -1, PREG_SPLIT_DELIM_CAPTURE );
			}

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
			$desc = mb_substr( $desc, 0, $length );

			// If not a full sentence...
			if ( '.' !== mb_substr( $desc, -1 ) ) {
				// ..and one ends within 20% of the end, trim it to that.
				if ( ( $pos = mb_strrpos( $desc, '.' ) ) > ( 0.8 * $length ) ) {
					$desc = mb_substr( $desc, 0, $pos + 1 );
				} else {
					// ..else mark it as being trimmed.
					$desc .= ' &hellip;';
				}
			}
		}

		return trim( $desc );
	}

	/**
	 * Parse a line to see if it's a header.
	 *
	 * @access protected
	 *
	 * @param string $line       The line from the readme to parse.
	 * @param bool   $only_valid Whether to only return a valid known header.
	 * @return false|array
	 */
	protected function parse_possible_header( $line, $only_valid = false ) {
		if ( ! str_contains( $line, ':' ) || str_starts_with( $line, '#' ) || str_starts_with( $line, '=' ) ) {
			return false;
		}

		list( $key, $value ) = explode( ':', $line, 2 );
		$key                 = strtolower( trim( $key, " \t*-\r\n" ) );
		$value               = trim( $value, " \t*-\r\n" );

		if ( $only_valid && ! isset( $this->valid_headers[ $key ] ) ) {
			return false;
		}

		return [ $key, $value ];
	}

	/**
	 * @access protected
	 *
	 * @param string $text
	 * @return string
	 */
	protected function filter_text( $text ) {
		$text = trim( $text );

		$allowed = array(
			'a'          => array(
				'href'  => true,
				'title' => true,
				'rel'   => true,
			),
			'blockquote' => array(
				'cite' => true,
			),
			'br'         => array(),
			'p'          => array(),
			'code'       => array(),
			'pre'        => array(),
			'em'         => array(),
			'strong'     => array(),
			'ul'         => array(),
			'ol'         => array(),
			'dl'         => array(),
			'dt'         => array(
				'id' => true,
			),
			'dd'         => array(),
			'li'         => array(),
			'h3'         => array(),
			'h4'         => array(),
		);

		$text = force_balance_tags( $text );
		// TODO: make_clickable() will act inside shortcodes.
		// $text = make_clickable( $text );
		$text = wp_kses( $text, $allowed );

		// wpautop() will eventually replace all \n's with <br>s, and that isn't what we want (The text may be line-wrapped in the readme, we don't want that, we want paragraph-wrapped text)
		// TODO: This incorrectly also applies within `<code>` tags which we don't want either.
		// $text = preg_replace( "/(?<![> ])\n/", ' ', $text );
		$text = trim( $text );

		return $text;
	}

	/**
	 * @access protected
	 *
	 * @param string $text
	 * @return string
	 */
	protected function sanitize_text( $text ) {
		// not fancy
		$text = strip_tags( $text );
		$text = esc_html( $text );
		$text = trim( $text );

		return $text;
	}

	/**
	 * Sanitize provided contributors to valid WordPress users
	 *
	 * @param array $users Array of user_login's or user_nicename's.
	 * @return array Array of user_logins.
	 */
	protected function sanitize_contributors( $users ) {
		foreach ( $users as $i => $name ) {
			// Trim any leading `@` off the name, in the event that someone uses `@joe-bloggs`.
			$name = ltrim( $name, '@' );

			// Contributors should be listed by their WordPress.org Login name (Example: 'Joe Bloggs')
			$user = get_user_by( 'login', $name );

			// Or failing that, by their user_nicename field (Example: 'joe-bloggs')
			if ( ! $user ) {
				$user = get_user_by( 'slug', $name );
			}

			// In the event that something invalid is used, we'll ignore it (Example: 'Joe Bloggs (Australian Translation)')
			if ( ! $user ) {
				$this->warnings['contributor_ignored'] ??= [];
				$this->warnings['contributor_ignored'][] = $name;
				unset( $users[ $i ] );
				continue;
			}

			// Overwrite whatever the author has specified with the sanitized nicename.
			$users[ $i ] = $user->user_nicename;
		}
		return $users;
	}

	/**
	 * Sanitize the provided stable tag to something we expect.
	 *
	 * @param string $stable_tag the raw Stable Tag line from the readme.
	 * @return string The sanitized $stable_tag.
	 */
	protected function sanitize_stable_tag( $stable_tag ) {
		$stable_tag = trim( $stable_tag );
		$stable_tag = trim( $stable_tag, '"\'' ); // "trunk"
		$stable_tag = preg_replace( '!^/?tags/!i', '', $stable_tag ); // "tags/1.2.3"
		$stable_tag = preg_replace( '![^a-z0-9_.-]!i', '', $stable_tag );

		// If the stable_tag begins with a ., we treat it as 0.blah.
		if ( '.' == substr( $stable_tag, 0, 1 ) ) {
			$stable_tag = "0{$stable_tag}";
		}

		return $stable_tag;
	}

	/**
	 * Sanitizes the Requires PHP header to ensure that it's a valid version header.
	 *
	 * @param string $version
	 * @return string The sanitized $version
	 */
	protected function sanitize_requires_php( $version ) {
		$version = trim( $version );

		// x.y or x.y.z
		if ( $version && ! preg_match( '!^\d+(\.\d+){1,2}$!', $version ) ) {
			$this->warnings['requires_php_header_ignored'] = true;
			// Ignore the readme value.
			$version = '';
		}

		return $version;
	}

	/**
	 * Sanitizes the Tested header to ensure that it's a valid version header.
	 *
	 * @param string $version
	 * @return string The sanitized $version
	 */
	protected function sanitize_tested_version( $version ) {
		$version = trim( $version );

		if ( $version ) {

			// Handle the edge-case of 'WordPress 5.0' and 'WP 5.0' for historical purposes.
			$strip_phrases = [
				'WordPress',
				'WP',
			];
			$version = trim( str_ireplace( $strip_phrases, '', $version ) );

			// Strip off any -alpha, -RC, -beta suffixes, as these complicate comparisons and are rarely used.
			list( $version, ) = explode( '-', $version );

			if (
				// x.y or x.y.z
				! preg_match( '!^\d+\.\d(\.\d+)?$!', $version ) ||
				// Allow plugins to mark themselves as compatible with Stable+0.1 (trunk/master) but not higher
				(
					defined( 'WP_CORE_STABLE_BRANCH' ) &&
					version_compare( (float)$version, (float)WP_CORE_STABLE_BRANCH+0.1, '>' )
				)
			 ) {
				$this->warnings['tested_header_ignored'] = true;
				// Ignore the readme value.
				$version = '';
			}
		}

		return $version;
	}

	/**
	 * Sanitizes the Requires at least header to ensure that it's a valid version header.
	 *
	 * @param string $version
	 * @return string The sanitized $version
	 */
	protected function sanitize_requires_version( $version ) {
		$version = trim( $version );

		if ( $version ) {

			// Handle the edge-case of 'WordPress 5.0' and 'WP 5.0' for historical purposes.
			$strip_phrases = [
				'WordPress',
				'WP',
				'or higher',
				'and above',
				'+',
			];
			$version = trim( str_ireplace( $strip_phrases, '', $version ) );

			// Strip off any -alpha, -RC, -beta suffixes, as these complicate comparisons and are rarely used.
			list( $version, ) = explode( '-', $version );

			if (
				// x.y or x.y.z
				! preg_match( '!^\d+\.\d(\.\d+)?$!', $version ) ||
				// Allow plugins to mark themselves as requireing Stable+0.1 (trunk/master) but not higher
				defined( 'WP_CORE_STABLE_BRANCH' ) && ( (float)$version > (float)WP_CORE_STABLE_BRANCH+0.1 )
			 ) {
				$this->warnings['requires_header_ignored'] = true;
				// Ignore the readme value.
				$version = '';
			}
		}

		return $version;
	}

	/**
	 * Parses a slice of lines from the file into an array of Heading => Content.
	 *
	 * We assume that every heading encountered is a new item, and not a sub heading.
	 * We support headings which are either `= Heading`, `# Heading` or `** Heading`.
	 *
	 * @param string|array $lines The lines of the section to parse.
	 * @return array
	 */
	protected function parse_section( $lines ) {
		$key    = $value = '';
		$return = array();

		if ( ! is_array( $lines ) ) {
			$lines = explode( "\n", $lines );
		}
		$trimmed_lines = array_map( 'trim', $lines );

		/*
		 * The heading style being matched in the block. Can be 'heading' or 'bold'.
		 * Standard Markdown headings (## .. and == ... ==) are used, but if none are present.
		 * full line bolding will be used as a heading style.
		 */
		$heading_style = 'bold'; // 'heading' or 'bold'
		foreach ( $trimmed_lines as $trimmed ) {
			if ( $trimmed && ( $trimmed[0] == '#' || $trimmed[0] == '=' ) ) {
				$heading_style = 'heading';
				break;
			}
		}

		$line_count = count( $lines );
		for ( $i = 0; $i < $line_count; $i++ ) {
			$line    = &$lines[ $i ];
			$trimmed = &$trimmed_lines[ $i ];
			if ( ! $trimmed ) {
				$value .= "\n";
				continue;
			}

			$is_heading = false;
			if ( 'heading' == $heading_style && ( $trimmed[0] == '#' || $trimmed[0] == '=' ) ) {
				$is_heading = true;
			} elseif ( 'bold' == $heading_style && ( substr( $trimmed, 0, 2 ) == '**' && substr( $trimmed, -2 ) == '**' ) ) {
				$is_heading = true;
			}

			if ( $is_heading ) {
				if ( $value ) {
					$return[ $key ] = trim( $value );
				}

				$value = '';
				// Trim off the first character of the line, as we know that's the heading style we're expecting to remove.
				$key = trim( $line, $trimmed[0] . " \t" );
				continue;
			}

			$value .= $line . "\n";
		}

		if ( $key || $value ) {
			$return[ $key ] = trim( $value );
		}

		return $return;
	}

	/**
	 * @param string $text
	 * @return string
	 */
	protected function parse_markdown( $text ) {
		static $markdown = null;

		// Return early if the Markdown processor isn't available.
		if ( ! class_exists( '\WordPressdotorg\Plugin_Directory\Markdown' ) ) {
			return $text;
		}

		if ( is_null( $markdown ) ) {
			$markdown = new Markdown();
		}

		return $markdown->transform( $text );
	}

	/**
	 * Validate whether the license specified appears to be valid or not.
	 *
	 * NOTE: This does not require a SPDX license to be specified, but it should be a valid license nonetheless.
	 *
	 * @param string $license The specified license.
	 * @return string|bool True if it looks good, error code on failure.
	 */
	public function validate_license( $license ) {
		/*
		 * This is a shortlist of keywords that are expected to be found in a valid license field.
		 * See https://www.gnu.org/licenses/license-list.en.html for possible compatible licenses.
		 */
		$probably_compatible = [
			'GPL', 'General Public License',
			// 'GNU 2', 'GNU Public', 'GNU Version 2' explicitely not included, as it's not a specific license.
			'MIT',
			'ISC',
			'Expat',
			'Apache 2', 'Apache License 2',
			'X11', 'Modified BSD', 'New BSD', '3 Clause BSD', 'BSD 3',
			'FreeBSD', 'Simplified BSD', '2 Clause BSD', 'BSD 2',
			'MPL', 'Mozilla Public License',
			strrev( 'LPFTW' ), strrev( 'kcuf eht tahw od' ), // To avoid some code scanners..
			'Public Domain', 'CC0', 'Unlicense',
			'CC BY', // Note: BY-NC & BY-ND are a no-no. See below.
			'zlib',
		];

		/*
		 * This is a shortlist of keywords that are likely related to a non-GPL  compatible license.
		 * See https://www.gnu.org/licenses/license-list.en.html for possible explanations.
		 */
		$probably_incompatible = [
			'4 Clause BSD', 'BSD 4 Clause', 
			'Apache 1',
			'CC BY-NC', 'CC-NC', 'NonCommercial',
			'CC BY-ND', 'NoDerivative',
			'EUPL',
			'OSL',
			'Personal use', 'without permission', 'without prior auth', 'you may not',
			'Proprietery', 'proprietary',
		];

		$sanitize_license = static function( $license ) {
			$license = strtolower( $license );

			// Localised or verbose licences.
			$license = str_replace( 'licence', 'license', $license );
			$license = str_replace( 'clauses', 'clause', $license ); // BSD
			$license = str_replace( 'creative commons', 'cc', $license );

			// If it looks like a full GPL statement, trim it back, for this function.
			if ( 0 === stripos( $license, 'GNU GENERAL PUBLIC LICENSE Version 2, June 1991 Copyright (C) 1989' ) ) {
				$license = 'gplv2';
			}

			// Replace 'Version 9' & v9 with '9' for simplicity.
			$license = preg_replace( '/(version |v)([0-9])/i', '$2', $license );

			// Remove unexpected characters
			$license = preg_replace( '/(\s*[^a-z0-9. ]+\s*)/i', '', $license );

			// Remove all spaces
			$license = preg_replace( '/\s+/', '', $license );

			return $license;
		};

		$probably_compatible   = array_map( $sanitize_license, $probably_compatible );
		$probably_incompatible = array_map( $sanitize_license, $probably_incompatible );
		$license               = $sanitize_license( $license );

		// First check to see if it's most probably an incompatible license.
		foreach ( $probably_incompatible as $match ) {
			if ( str_contains( $license, $match ) ) {
				return 'invalid_license';
			}
		}

		// Check to see if it's likely compatible.
		foreach ( $probably_compatible as $match ) {
			if ( str_contains( $license, $match ) ) {
				return true;
			}
		}

		// If we've made it this far, it's neither likely incompatible, or likely compatible, so unknown.
		return 'unknown_license';
	}

}
