<?php
	/**
	 * @package   Freemius
	 * @copyright Copyright (c) 2015, Freemius, Inc.
	 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since     2.3.1
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
     * @var array $VARS
     *
	 * @var Freemius $fs
	 */
	$fs                       = freemius( $VARS['id'] );
	$slug                     = $fs->get_slug();
    $unique_affix             = $fs->get_unique_affix();
    $last_license_user_id     = $fs->get_last_license_user_id();
    $has_last_license_user_id = FS_User::is_valid_id( $last_license_user_id );
    
	$message_above_input_field = ( ! $has_last_license_user_id ) ?
		fs_text_inline( 'Please enter the license key to enable the debug mode:', 'submit-developer-license-key-message', $slug ) :
		sprintf(
			fs_text_inline( 'To enter the debug mode, please enter the secret key of the license owner (UserID = %d), which you can find in your "My Profile" section of your User Dashboard:', 'submit-addon-developer-key-message', $slug ),
			$last_license_user_id
		);

    $processing_text          = ( fs_esc_js_inline( 'Processing', 'processing', $slug ) . '...' );
    $submit_button_text       = fs_text_inline( 'Submit', 'submit', $slug );
    $debug_license_link_text  = fs_esc_html_inline( 'Start Debug', 'start-debug-license', $slug );
    $license_or_user_key_text = ( ! $has_last_license_user_id ) ?
        fs_text_inline( 'License key', 'license-key' , $slug ) :
        fs_text_inline( 'User key', 'user-key' , $slug );
    $input_html               = "<input class='fs-license-or-user-key' type='password' placeholder='{$license_or_user_key_text}' tabindex='1' />";

	$modal_content_html = <<< HTML
	<div class="notice notice-error inline license-or-user-key-submission-message"><p></p></div>
	<p>{$message_above_input_field}</p>
	{$input_html}
HTML;

	fs_enqueue_local_style( 'fs_dialog_boxes', '/admin/dialog-boxes.css' );
?>
<script type="text/javascript">
( function( $ ) {
	$( document ).ready( function() {
		var modalContentHtml          = <?php echo json_encode( $modal_content_html ) ?>,
			modalHtml                 =
				'<div class="fs-modal fs-modal-developer-license-debug-mode fs-modal-developer-license-debug-mode-<?php echo $unique_affix ?>">'
				+ '	<div class="fs-modal-dialog">'
				+ '		<div class="fs-modal-body">'
				+ '			<div class="fs-modal-panel active">' + modalContentHtml + '</div>'
				+ '		</div>'
				+ '		<div class="fs-modal-footer">'
				+ '			<button class="button button-secondary button-close" tabindex="4"><?php fs_esc_js_echo_inline( 'Cancel', 'cancel', $slug ) ?></button>'
				+ '			<button class="button button-primary button-submit-license-or-user-key"  tabindex="3"><?php echo esc_js( $submit_button_text ) ?></button>'
				+ '		</div>'
				+ '	</div>'
				+ '</div>',
			$modal                             = $( modalHtml ),
            $debugLicenseLink                  = $( '.debug-license-trigger' ),
			$submitKeyButton                   = $modal.find( '.button-submit-license-or-user-key' ),
			$licenseOrUserKeyInput             = $modal.find( 'input.fs-license-or-user-key' ),
			$licenseOrUserKeySubmissionMessage = $modal.find( '.license-or-user-key-submission-message' ),
            isDebugMode                        = <?php echo $fs->is_data_debug_mode() ? 'true' : 'false' ?>;

		$modal.appendTo( $( 'body' ) );

		function registerEventHandlers() {
            $debugLicenseLink.click(function (evt) {
                evt.preventDefault();

                if ( isDebugMode ) {
                    setDeveloperLicenseDebugMode();
                    return true;
                }

                showModal( evt );
            });

			$modal.on( 'input propertychange', 'input.fs-license-or-user-key', function () {
				var licenseOrUserKey = $( this ).val().trim();

				/**
				 * If license or user key is not empty, enable the submission button.
				 */
				if ( licenseOrUserKey.length > 0 ) {
					enableSubmitButton();
				}
			});

			$modal.on( 'blur', 'input.fs-license-or-user-key', function () {
				var licenseOrUserKey = $( this ).val().trim();

                /**
                 * If license or user key is empty, disable the submission button.
                 */
                if ( 0 === licenseOrUserKey.length ) {
                   disableSubmitButton();
                }
			});

			$modal.on( 'click', '.button-submit-license-or-user-key', function ( evt ) {
				evt.preventDefault();

				if ( $( this ).hasClass( 'disabled' ) ) {
					return;
				}

				var licenseOrUserKey = $licenseOrUserKeyInput.val().trim();

				disableSubmitButton();

				if ( 0 === licenseOrUserKey.length ) {
					return;
				}

                setDeveloperLicenseDebugMode( licenseOrUserKey );
			});

			// If the user has clicked outside the window, close the modal.
			$modal.on( 'click', '.fs-close, .button-secondary', function () {
				closeModal();
				return false;
			} );
		}

		registerEventHandlers();

		function setDeveloperLicenseDebugMode( licenseOrUserKey ) {
            var data = {
                action             : '<?php echo $fs->get_ajax_action( 'set_data_debug_mode' ) ?>',
                security           : '<?php echo $fs->get_ajax_security( 'set_data_debug_mode' ) ?>',
                license_or_user_key: licenseOrUserKey,
                is_debug_mode      : isDebugMode,
                module_id          : '<?php echo $fs->get_id() ?>'
            };

            $.ajax( {
                url       : <?php echo Freemius::ajax_url() ?>,
                method    : 'POST',
                data      : data,
                beforeSend: function () {
                    $debugLicenseLink.find('span').text( '<?php echo $processing_text ?>' );
                    $submitKeyButton.text( '<?php echo $processing_text ?>' );
                },
                success   : function ( result ) {
                    if ( result.success ) {
                        closeModal();

                        // Reload the "Account" page so that the pricing/upgrade link will be properly hidden/shown.
                        window.location.reload();
                    } else {
                        showError( result.error.message ? result.error.message : result.error );
                        resetButtons();
                    }
                },
                error     : function () {
                    showError( <?php echo json_encode( fs_text_inline( 'An unknown error has occurred.', 'unknown-error', $slug ) ) ?> );
                    resetButtons();
                }
            });
        }

		function showModal( evt ) {
			resetModal();

			// Display the dialog box.
			$modal.addClass( 'active' );
			$( 'body' ).addClass( 'has-fs-modal' );

            $licenseOrUserKeyInput.val( '' );
            $licenseOrUserKeyInput.focus();
		}

		function closeModal() {
			$modal.removeClass( 'active' );
			$( 'body' ).removeClass( 'has-fs-modal' );
		}

		function resetButtons() {
			enableSubmitButton();
			$submitKeyButton.text( <?php echo json_encode( $submit_button_text ) ?> );
			$debugLicenseLink.find('span').text( <?php echo json_encode( $debug_license_link_text ) ?> );
		}

		function resetModal() {
			hideError();
			resetButtons();
		}

		function enableSubmitButton() {
			$submitKeyButton.removeClass( 'disabled' );
		}

		function disableSubmitButton() {
			$submitKeyButton.addClass( 'disabled' );
		}

		function hideError() {
			$licenseOrUserKeySubmissionMessage.hide();
		}

		function showError( msg ) {
			$licenseOrUserKeySubmissionMessage.find( ' > p' ).html( msg );
			$licenseOrUserKeySubmissionMessage.show();
		}
	} );
} )( jQuery );
</script>