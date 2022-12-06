<?php
    /**
    * @package   Freemius
    * @copyright Copyright (c) 2015, Freemius, Inc.
    * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
    * @since     2.3.2
    */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    /**
     * @var array    $VARS
     *
     * @var Freemius $fs
     */
    $fs   = freemius( $VARS['id'] );
    $slug = $fs->get_slug();

    /**
     * @var object[] $license_owners
     */
    $license_owners = $VARS['license_owners'];

    $change_user_message                  = fs_text_inline( 'By changing the user, you agree to transfer the account ownership to:', 'change-user--message', $slug );
    $header_title                         = fs_text_inline( 'Change User', 'change-user', $slug );
    $user_change_button_text              = fs_text_inline( 'I Agree - Change User', 'agree-change-user', $slug );
    $other_text                           = fs_text_inline( 'Other', 'other', $slug );
    $enter_email_address_placeholder_text = fs_text_inline( 'Enter email address', 'enter-email-address', $slug );

    $user_change_options_html = <<< HTML
    <div class="fs-user-change-options-container">
        <table>
            <tbody>
HTML;

        foreach ( $license_owners as $license_owner ) {
            $user_change_options_html .= <<< HTML
                <tr class="fs-email-address-container">
                    <td><input id="fs_email_address_{$license_owner->id}" type="radio" name="fs_email_address" value="{$license_owner->id}"></td>
                    <td><label for="fs_email_address_{$license_owner->id}">{$license_owner->email}</label></td>
                </tr>
HTML;
        }

        $user_change_options_html .= <<< HTML
                <tr>
                    <td><input id="fs_other_email_address_radio" type="radio" name="fs_email_address" value="other"></td>
                    <td class="fs-other-email-address-container">
                        <div>
                            <label for="fs_email_address">{$other_text}: </label>
                            <div>
                                <input id="fs_other_email_address_text_field" class="fs-email-address" type="text" placeholder="{$enter_email_address_placeholder_text}" tabindex="1">
                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
HTML;

    $modal_content_html = <<< HTML
    <div class="notice notice-error inline fs-change-user-result-message"><p></p></div>
    <p>{$change_user_message}</p>
    {$user_change_options_html}
HTML;

    fs_enqueue_local_style( 'fs_dialog_boxes', '/admin/dialog-boxes.css' );
?>
<script type="text/javascript">
(function( $ ) {
    $( document ).ready(function() {
        var modalContentHtml            = <?php echo json_encode( $modal_content_html ) ?>,
            modalHtml                   =
                '<div class="fs-modal fs-modal-change-user fs-modal-change-user-<?php echo $fs->get_unique_affix() ?>">'
                + '	<div class="fs-modal-dialog">'
                + '		<div class="fs-modal-header">'
                + '		    <h4><?php echo esc_js( $header_title ) ?></h4>'
                + '         <a href="!#" class="fs-close"><i class="dashicons dashicons-no" title="<?php echo esc_js( fs_text_x_inline( 'Dismiss', 'close window', 'dismiss', $slug ) ) ?>"></i></a>'
                + '		</div>'
                + '		<div class="fs-modal-body">'
                + '			<div class="fs-modal-panel active">' + modalContentHtml + '</div>'
                + '		</div>'
                + '		<div class="fs-modal-footer">'
                + '			<button class="button button-secondary button-close" tabindex="4"><?php fs_esc_js_echo_inline( 'Cancel', 'cancel', $slug ) ?></button>'
                + '			<button class="button button-primary fs-user-change-button" tabindex="3"><?php echo esc_js( $user_change_button_text ) ?></button>'
                + '		</div>'
                + '	</div>'
                + '</div>',
            $modal                      = $( modalHtml ),
            $userChangeButton           = $modal.find( '.fs-user-change-button' ),
            $otherEmailAddressRadio     = $modal.find( '#fs_other_email_address_radio' ),
            $changeUserResultMessage    = $modal.find( '.fs-change-user-result-message' ),
            $otherEmailAddressContainer = $modal.find( '.fs-other-email-address-container' ),
            $otherEmailAddressTextField = $modal.find( '#fs_other_email_address_text_field' ),
            $licenseOwners              = $modal.find( 'input[type="radio"][name="fs_email_address"]' );

        $modal.appendTo( $( 'body' ) );

        var previousEmailAddress = null;

        function registerEventHandlers() {
            $licenseOwners.change( function() {
                var otherEmailAddress           = $otherEmailAddressTextField.val().trim(),
                    otherEmailAddressIsSelected = isOtherEmailAddressSelected();

                if ( otherEmailAddressIsSelected ) {
                    $otherEmailAddressTextField.focus();
                }

                if ( otherEmailAddress.length > 0 || ! otherEmailAddressIsSelected ) {
                    enableUserChangeButton();
                } else {
                    disableUserChangeButton();
                }
            } );

            $otherEmailAddressContainer.click( function () {
                $otherEmailAddressRadio.click();
            } );

            // Handle for the "Change User" button on the "Account" page.
            $( '#fs_change_user' ).click( function ( evt ) {
                evt.preventDefault();

                showModal( evt );
            } );

            /**
             * Disables the "Change User" button when the email address is empty.
             */
            $modal.on( 'keyup paste delete cut', 'input#fs_other_email_address_text_field', function () {
                setTimeout( function () {
                    var emailAddress = $otherEmailAddressRadio.val().trim();

                    if ( emailAddress === previousEmailAddress ) {
                        return;
                    }

                    if ( '' === emailAddress ) {
                        disableUserChangeButton();
                    } else {
                        enableUserChangeButton();
                    }

                    previousEmailAddress = emailAddress;
                }, 100 );
            } ).focus();

            $modal.on( 'input propertychange', 'input#fs_other_email_address_text_field', function () {
                var emailAddress = $( this ).val().trim();

                /**
                 * If email address is not empty, enable the "Change User" button.
                 */
                if ( emailAddress.length > 0 ) {
                    enableUserChangeButton();
                }
            } );

            $modal.on( 'blur', 'input#fs_other_email_address_text_field', function( evt ) {
                var emailAddress = $( this ).val().trim();

                /**
                 * If email address is empty, disable the "Change User" button.
                 */
                if ( 0 === emailAddress.length ) {
                   disableUserChangeButton();
                }
            } );

            $modal.on( 'click', '.fs-user-change-button', function ( evt ) {
                evt.preventDefault();

                if ( $( this ).hasClass( 'disabled' ) ) {
                    return;
                }

                var emailAddress   = '',
                    licenseOwnerID = null;

                if ( ! isOtherEmailAddressSelected() ) {
                    licenseOwnerID = $licenseOwners.filter( ':checked' ).val();
                } else {
                    emailAddress = $otherEmailAddressTextField.val().trim();

                    if ( 0 === emailAddress.length ) {
                        return;
                    }
                }

                disableUserChangeButton();

                $.ajax( {
                    url       : <?php echo Freemius::ajax_url() ?>,
                    method    : 'POST',
                    data      : {
                        action       : '<?php echo $fs->get_ajax_action( 'change_user' ) ?>',
                        security     : '<?php echo $fs->get_ajax_security( 'change_user' ) ?>',
                        email_address: emailAddress,
                        user_id      : licenseOwnerID,
                        module_id    : '<?php echo $fs->get_id() ?>'
                    },
                    beforeSend: function () {
                        $userChangeButton
                            .text( '<?php fs_esc_js_echo_inline( 'Changing user, please wait', 'changing-user-please-wait', $slug ) ?>...' )
                            .prepend('<i class="fs-ajax-spinner"></i>');

                        $(document.body).css({'cursor': 'wait'});
                    },
                    success   : function( result ) {
                        if ( result.success ) {
                            // Redirect to the "Account" page.
                            window.location.reload();
                        } else {
                            $(document.body).css({'cursor': 'auto'});

                            showError( result.error.message ? result.error.message : result.error );
                            resetUserChangeButton();
                        }
                    },
                    error     : function () {
                        $(document.body).css({'cursor': 'auto'});

                        showError( '<?php fs_esc_js_echo_inline( 'Unexpected error, try again in 5 minutes. If the error persists, please contact support.', 'unexpected-error', $slug ) ?>' );

                        resetUserChangeButton();
                    }
                } );
            } );

            // If the user has clicked outside the window, close the modal.
            $modal.on( 'click', '.fs-close, .button-secondary', function () {
                closeModal();
                return false;
            } );
        }

        registerEventHandlers();

        /**
         * @returns {Boolean}
         */
        function isOtherEmailAddressSelected() {
            return ( 'other' === $licenseOwners.filter( ':checked' ).val() );
        }

        function showModal() {
            resetModal();

            // Display the dialog box.
            $modal.addClass( 'active' );
            $( 'body' ).addClass( 'has-fs-modal' );

            // Select the first radio button.
            $licenseOwners.get( 0 ).click();

            $otherEmailAddressTextField.val( '' );
        }

        function closeModal() {
            $modal.removeClass( 'active' );
            $( 'body' ).removeClass( 'has-fs-modal' );
        }

        function resetUserChangeButton() {
            enableUserChangeButton();
            $userChangeButton.text( <?php echo json_encode( $user_change_button_text ) ?> );
        }

        function resetModal() {
            hideError();
            resetUserChangeButton();
        }

        function enableUserChangeButton() {
            $userChangeButton.removeClass( 'disabled' );
        }

        function disableUserChangeButton() {
            $userChangeButton.addClass( 'disabled' );
        }

        function hideError() {
            $changeUserResultMessage.hide();
        }

        function showError( msg ) {
            $changeUserResultMessage.find( ' > p' ).html( msg );
            $changeUserResultMessage.show();
        }
    });
})( jQuery );
</script>
