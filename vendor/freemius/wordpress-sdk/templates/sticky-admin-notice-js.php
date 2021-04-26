<?php
	/**
	 * Sticky admin notices JavaScript handler for dismissing notice messages
	 * by sending AJAX call to the server in order to remove the message from the Database.
	 *
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.0.7
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}
?>
<script type="text/javascript" >
	jQuery( document ).ready(function( $ ) {
		$( '.fs-notice.fs-sticky .fs-close' ).click(function() {
			var
				notice           = $( this ).parents( '.fs-notice' ),
				id               = notice.attr( 'data-id' ),
				ajaxActionSuffix = notice.attr( 'data-manager-id' ).replace( ':', '-' );

			notice.fadeOut( 'fast', function() {
				var data = {
					action    : 'fs_dismiss_notice_action_' + ajaxActionSuffix,
					message_id: id
				};

				// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
				$.post( ajaxurl, data, function( response ) {

				});

				notice.remove();
			});
		});
	});
</script>