<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.2.1.6
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Retrieve the translation of $text.
	 *
	 * @since 1.2.1.6
	 *
	 * @param string $text
	 * 
	 * @return string
	 */
	function _fs_text( $text ) {
		// Avoid misleading Theme Check warning.
		$fn = 'translate';
		return $fn( $text, 'freemius' );
	}

	/**
	 * Retrieve translated string with gettext context.
	 *
	 * Quite a few times, there will be collisions with similar translatable text
	 * found in more than two places, but with different translated context.
	 *
	 * By including the context in the pot file, translators can translate the two
	 * strings differently.
	 *
	 * @since 1.2.1.6
	 *
	 * @param string $text
	 * @param string $context 
	 * 
	 * @return string
	 */
	function _fs_x( $text, $context ) {
		// Avoid misleading Theme Check warning.
		$fn = 'translate_with_gettext_context';
		return $fn( $text, $context, 'freemius' );
	}
