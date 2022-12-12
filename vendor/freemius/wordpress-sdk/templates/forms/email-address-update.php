<?php
	/**
	 * @package   Freemius
	 * @copyright Copyright (c) 2015, Freemius, Inc.
	 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
     *
     * @author Leo Fajardo (@leorw)
	 * @since 2.5.0
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * @var array $VARS
	 */
	$fs   = freemius( $VARS['id'] );
	$slug = $fs->get_slug();

	$user                  = $fs->get_user();
	$current_email_address = $user->email;

	fs_enqueue_local_style( 'fs_dialog_boxes', '/admin/dialog-boxes.css' );
?>
<script type="text/javascript">
( function ( $ ) {
	var modalHtml                             =
		    '<div class="fs-modal fs-modal-email-address-update">'
		    + '	<div class="fs-modal-dialog">'
		    + '		<div class="fs-modal-header">'
		    + '		    <h4><?php fs_esc_attr_echo_inline( 'Email address update', 'email-address-update', $slug ) ?></h4>'
            + '         <a href="!#" class="fs-close"><i class="dashicons dashicons-no" title="<?php echo esc_js( fs_text_x_inline( 'Dismiss', 'close window', 'dismiss', $slug ) ) ?>"></i></a>'
		    + '		</div>'
		    + '		<div class="fs-modal-body">'
		    + '			<div class="fs-modal-panel active">'
            + '             <div class="notice notice-error inline fs-update-error-message"><p></p></div>'
            + '             <p><?php fs_echo_inline( 'Enter the new email address', 'enter-new-email-address' , $slug ) ?>:</p>'
            + '	    	    <input type="text" class="fs-new-email-address-input" />'
            + '             <div class="fs-email-addresses-ownership-options" style="display: none">'
            + '                 <p><?php echo sprintf(
                                    fs_esc_html_inline( 'Are both %s and %s your email addresses?', 'email-addresses-ownership-confirmation' , $slug ),
                                    sprintf( '<strong>%s</strong>', $current_email_address ),
                                    '<strong class="fs-new-email-address"></strong>'
                                ) ?></p>'
            + '                 <ul>'
            + '                     <li>'
            + '                         <label>'
            + '                             <span><input type="radio" name="email-addresses-ownership" value="both"/></span>'
            + '                             <span><?php fs_echo_inline( 'Yes - both addresses are mine', 'both-addresses-are-mine', $slug ); ?></span>'
            + '                         </label>'
            + '                     </li>'
            + '                     <li>'
            + '                         <label>'
            + '                             <span><input type="radio" name="email-addresses-ownership" value="current"/></span>'
            + '                             <div><?php echo sprintf(
                                                fs_esc_html_inline( "%s is my client's email address", 'client-email-address-confirmation', $slug ),
                                                '<strong class="fs-new-email-address"></strong>'
                                            ) ?></span>'
            + '                         </label>'
            + '                     </li>'
            + '                     <li>'
            + '                         <label>'
            + '                             <span><input type="radio" name="email-addresses-ownership" value="new"/></span>'
            + '                             <div><?php echo sprintf(
                                                fs_esc_html_inline( "%s is my email address", 'email-address-ownership-confirmation', $slug ),
                                                '<strong class="fs-new-email-address"></strong>'
                                            ) ?></span>'
            + '                         </label>'
            + '                     </li>'
            + '                 </ul>'
            + '             </div>'
            + '             <div class="fs-assets-transfership-options" style="display: none">'
            + '                 <p><?php echo sprintf(
                                    fs_esc_html_inline( 'Would you like to merge %s into %s?', 'accounts-merge-confirmation' , $slug ),
                                    sprintf( '<strong>%s</strong>', $current_email_address ),
                                    '<strong class="fs-new-email-address"></strong>'
                                ) ?></p>'
            + '                 <ul>'
            + '                     <li>'
            + '                         <label>'
            + '                             <span><input type="radio" name="assets-transfer-type" value="all" /></span>'
            + '                             <span><?php echo sprintf(
                                                fs_esc_html_inline( 'Yes - move all my data and assets from %s to %s', 'move-all-data-and-assets-into-new-account', $slug ),
                                                sprintf( '<strong>%s</strong>', $current_email_address ),
                                                '<strong class="fs-new-email-address"></strong>'
                                            ) ?></span>'
            + '                         </label>'
            + '                     </li>'
            + '                     <li>'
            + '                         <label>'
            + '                             <span><input type="radio" name="assets-transfer-type" value="plugin" /></span>'
            + '                             <span><?php echo sprintf(
                                                fs_esc_html_inline( "No - only move this site's data to %s", 'move-only-plugin-data-into-new-account', $slug ),
                                                '<strong class="fs-new-email-address"></strong>'
                                            ) ?></span>'
            + '                         </label>'
            + '                     </li>'
            + '                 </ul>'
            + '             </div>'
            + '         </div>'
		    + '		</div>'
		    + '		<div class="fs-modal-footer">'
		    + '			<button class="button button-primary button-update" disabled><?php fs_esc_js_echo_inline( 'Update', 'update-email-address', $slug ) ?></button>'
		    + '			<button class="button button-secondary button-close"><?php fs_esc_js_echo_inline( 'Cancel', 'cancel', $slug ) ?></button>'
		    + '		</div>'
		    + '	</div>'
		    + '</div>',
	    $modal                                = $( modalHtml ),
        $updateButton                         = $modal.find( '.button-update' ),
        $updateResultMessage                  = $modal.find( '.fs-update-error-message' ),
        selectedEmailAddressesOwnershipOption = null,
        selectedAssetsTransfershipOption      = null,
        previousEmailAddress                  = '',
        $body                                 = $( 'body' );

	$modal.appendTo( $body );

	registerEventHandlers();

	function registerEventHandlers() {
        $body.on( 'click', '#fs_account_details .button-edit-email-address', function ( evt ) {
            evt.preventDefault();

            showModal( evt );
        } );

        $modal.on( 'input propertychange keyup paste delete cut', '.fs-new-email-address-input', function () {
            var emailAddress = $( this ).val().trim();

            if ( emailAddress === previousEmailAddress ) {
                return;
            }

            var isValidEmailAddressInput = isValidEmailAddress( emailAddress );

            toggleOptions( isValidEmailAddressInput );

            if ( ! isValidEmailAddressInput ) {
                disableUpdateButton();
            } else {
                $modal.find( '.fs-new-email-address').text( emailAddress );

                maybeEnableUpdateButton();
            }

            previousEmailAddress = emailAddress;
        } );

        $modal.on( 'blur', '.fs-new-email-address-input', function() {
            var emailAddress             = $( this ).val().trim(),
                isValidEmailAddressInput = isValidEmailAddress( emailAddress );

            toggleOptions( isValidEmailAddressInput );

            if ( ! isValidEmailAddressInput ) {
                disableUpdateButton();
            }
        } );

        $modal.on( 'click', '.fs-close, .button-secondary', function () {
            closeModal();
            return false;
        } );

		$modal.on( 'click', '.fs-modal-footer .button-update', function ( evt ) {
            if ( ! isValidEmailAddress( previousEmailAddress ) ) {
                return;
            }

            if ( previousEmailAddress === '<?php echo $current_email_address ?>' ) {
                closeModal();
                return;
            }

			var transferType = 'transfer';

			if ( 'current' === selectedEmailAddressesOwnershipOption ) {
			    transferType = 'transfer_to_client';
            } else if (
                'both' === selectedEmailAddressesOwnershipOption &&
                'all' === selectedAssetsTransfershipOption
            ) {
                transferType = 'merge';
            }

            $.ajax( {
                url       : <?php echo Freemius::ajax_url() ?>,
                method    : 'POST',
                data      : {
                    action       : '<?php echo $fs->get_ajax_action( 'update_email_address' ) ?>',
                    security     : '<?php echo $fs->get_ajax_security( 'update_email_address' ) ?>',
                    module_id    : '<?php echo $fs->get_id() ?>',
                    transfer_type: transferType,
                    email_address: previousEmailAddress
                },
                beforeSend: function () {
                    disableUpdateButton();

                    $updateButton.find( '.fs-modal-footer .button' ).prop( 'disabled', true );
                    $updateButton.text( 'Processing...' );
                },
                success   : function( result ) {
                    if ( result.success ) {
                        // Redirect to the "Account" page.
                        window.location.reload();
                    } else {
                        if ('change_ownership' === result.error.code) {
                            window.location = result.error.url;
                        } else {
                            showError(result.error.message ? result.error.message : result.error);
                            resetUpdateButton();
                        }
                    }
                },
                error     : function () {
                    showError( '<?php fs_esc_js_echo_inline( 'Unexpected error, try again in 5 minutes. If the error persists, please contact support.', 'unexpected-error', $slug ) ?>' );

                    resetUpdateButton();
                }
            } );
		} );

		$modal.on( 'click', 'input[type="radio"]', function () {
			var $selectedOption     = $( this ),
                selectedOptionValue = $selectedOption.val();
        
			// If the selection has not changed, do not proceed.
			if (
			    selectedEmailAddressesOwnershipOption === selectedOptionValue ||
                selectedAssetsTransfershipOption === selectedOptionValue
            ) {
                return;
            }

			if ( 'assets-transfer-type' === $selectedOption.attr( 'name' ) ) {
                selectedAssetsTransfershipOption = selectedOptionValue;
            } else {
                selectedEmailAddressesOwnershipOption = selectedOptionValue;

                if ( 'both' !== selectedEmailAddressesOwnershipOption ) {
                    $modal.find( '.fs-assets-transfership-options' ).hide();
                } else {
                    $modal.find( '.fs-assets-transfership-options' ).show();
                    $modal.find( '.fs-assets-transfership-options input[type="radio"]' ).prop('checked', false);

                    selectedAssetsTransfershipOption = null;

                    disableUpdateButton();
                }
            }

			if ( isValidEmailAddress( $( '.fs-new-email-address-input' ).val().trim() ) ) {
                maybeEnableUpdateButton();
            }
		});
	}

	function showModal() {
		resetModal();

		// Display the dialog box.
		$modal.addClass( 'active' );
		$modal.find( '.fs-new-email-address-input' ).focus();

		$( 'body' ).addClass( 'has-fs-modal' );
	}

	function closeModal() {
        selectedEmailAddressesOwnershipOption = null;

        disableUpdateButton();

		$modal.removeClass( 'active' );

		$( 'body' ).removeClass( 'has-fs-modal' );
	}

	function resetModal() {
        hideError();

        // Deselect all radio buttons.
        $modal.find( 'input[type="radio"]' ).prop( 'checked', false );

        // Clear the value of the email address text field.
        $modal.find( 'input[type="text"]' ).val( '' );

        toggleOptions( false );

		disableUpdateButton();

        $updateButton.text( <?php echo json_encode( fs_text_inline( 'Update', 'update-email-address', $slug ) ) ?> );
	}

    function resetUpdateButton() {
        maybeEnableUpdateButton();

        $updateButton.text( <?php echo json_encode( fs_text_inline( 'Update', 'update-email-address', $slug ) ) ?> );
    }

	function maybeEnableUpdateButton() {
	    if ( null === selectedEmailAddressesOwnershipOption ) {
	        return;
        }

	    if (
	        'both' === selectedEmailAddressesOwnershipOption &&
            null === selectedAssetsTransfershipOption
        ) {
	        return;
        }

        $updateButton.prop( 'disabled', false );
	}

	function disableUpdateButton() {
		$updateButton.prop( 'disabled', true );
	}

    function hideError() {
        $updateResultMessage.hide();
    }

    function showError( msg ) {
        $updateResultMessage.find( ' > p' ).html( msg );
        $updateResultMessage.show();
    }

    function isValidEmailAddress( emailAddress ) {
	    if ( '' === emailAddress ) {
	        return false;
        }

        return /[0-9a-zA-Z][a-zA-Z\+0-9\.\_\-]*@[0-9a-zA-Z\-]+(\.[a-zA-Z]{2,24}){1,3}/.test( emailAddress );
    }

    function toggleOptions( show ) {
	    $modal.find( '.fs-email-addresses-ownership-options' ).toggle( show );

	    if ( ! show ) {
            $modal.find( '.fs-assets-transfership-options' ).hide();
        } else if ( 'both' === selectedEmailAddressesOwnershipOption ) {
            $modal.find( '.fs-assets-transfership-options' ).show();
        }
    }
} )( jQuery );
</script>