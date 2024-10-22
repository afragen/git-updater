<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2024, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       2.9.0
	 */

	/**
	 * @var array    $VARS
	 * @var Freemius $fs
	 */
	$fs = freemius( $VARS['id'] );

	if ( fs_request_get_bool( 'redirect' ) ) {
		fs_require_template( 'checkout/redirect.php', $VARS );
	} else if ( fs_request_get_bool( 'process_redirect' ) ) {
		fs_require_template( 'checkout/process-redirect.php', $VARS );
	} else {
		$fs = freemius( $VARS['id'] );

		if ( $fs->is_premium() ) {
			fs_require_template( 'checkout/frame.php', $VARS );
		} else {
			fs_require_template( 'checkout/redirect.php', $VARS );
		}
	}
