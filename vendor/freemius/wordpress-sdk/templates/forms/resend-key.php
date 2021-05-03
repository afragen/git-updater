<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.2.0
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * @var array $VARS
	 * @var Freemius $fs
	 */
	$fs   = freemius( $VARS['id'] );

	$slug = $fs->get_slug();

	$send_button_text          = fs_text_inline( 'Send License Key', 'send-license-key', $slug );
	$cancel_button_text        = fs_text_inline( 'Cancel', 'cancel', $slug );
	$email_address_placeholder = fs_esc_attr_inline( 'Email address', 'email-address', $slug );
	$other_text                = fs_text_inline( 'Other', 'other', $slug );

	$is_freemium = $fs->is_freemium();

	$send_button_text_html = esc_html($send_button_text);

	$button_html = <<< HTML
<div class="button-container">
    <a href="#" class="button button-primary button-send-license-key" tabindex="2">{$send_button_text_html}</a>
</div>
HTML;

	if ( $is_freemium ) {
		$current_user          = Freemius::_get_current_wp_user();
		$email                 = $current_user->user_email;
		$esc_email             = esc_attr( $email );
		$form_html      = <<< HTML
<div class="email-address-container">
    <label><input name="email-address" type="radio" checked="checked" tabindex="1" value="{$esc_email}"> {$email}</label>
    <label><input name="email-address" type="radio" tabindex="1" value="other">{$other_text}: <input class="email-address" type="text" placeholder="{$email_address_placeholder}"></label>
</div>
{$button_html}
HTML;
	} else {
		$email = '';
		$form_html      = <<< HTML
{$button_html}
<div class="email-address-container">
    <input class="email-address" type="text" placeholder="{$email_address_placeholder}" tabindex="1">
</div>
HTML;
	}

	$message_above_input_field = fs_esc_html_inline( "Enter the email address you've used for the upgrade below and we will resend you the license key.", 'ask-for-upgrade-email-address', $slug );
	$modal_content_html = <<< HTML
    <div class="notice notice-error inline license-resend-message"><p></p></div>
    <p>{$message_above_input_field}</p>
    <div class="input-container">
        {$form_html}
    </div>
HTML;

	fs_enqueue_local_style( 'fs_dialog_boxes', '/admin/dialog-boxes.css' );
?>
<script type="text/javascript">
	(function ($) {
		$(document).ready(function () {
			var contentHtml      = <?php echo json_encode( $modal_content_html ); ?>,
			    modalHtml        =
				    '<div class="fs-modal fs-modal-license-key-resend <?php echo $is_freemium ? 'fs-freemium' : 'fs-premium' ?>">'
				    + ' <div class="fs-modal-dialog">'
				    + '     <div class="fs-modal-header">'
				    + '         <h4><?php echo esc_js( $send_button_text ) ?></h4>'
				    + '         <a href="#!" class="fs-close" tabindex="3" title="Close"><i class="dashicons dashicons-no" title="<?php echo esc_js( fs_text_x_inline( 'Dismiss', 'as close a window', 'dismiss', $slug ) ) ?>"></i></a>'
				    + '     </div>'
				    + '     <div class="fs-modal-body">'
				    + '         <div class="fs-modal-panel active">' + contentHtml + '</div>'
				    + '     </div>'
				    + ' </div>'
				    + '</div>',
			    $modal           = $(modalHtml),
			    $sendButton      = $modal.find('.button-send-license-key'),
			    $emailInput      = $modal.find('input.email-address'),
			    $feedbackMessage = $modal.find('.license-resend-message'),
			    isFreemium       = <?php echo json_encode( $is_freemium ) ?>,
			    userEmail        = <?php echo json_encode( $email ) ?>,
			    moduleID         = '<?php echo $fs->get_id() ?>',
			    isChild          = false;


			$modal.appendTo($('body'));

			function registerEventHandlers() {
				$('a.show-license-resend-modal-<?php echo $fs->get_unique_affix() ?>').click(function (evt) {
					evt.preventDefault();

					showModal();
				});

				if (isFreemium) {
					$modal.on('change', 'input[type=radio][name=email-address]', function () {
						updateButtonState();
					});

					$modal.on('focus', 'input.email-address', function () {
						// Check custom email radio button on email input focus.
						$($modal.find('input[type=radio]')[1]).prop('checked', true);

						updateButtonState();
					});
				}

				$modal.on('input propertychange', 'input.email-address', function () {
					updateButtonState();
				});

				$modal.on('blur', 'input.email-address', function () {
					updateButtonState();
				});

				$modal.on('click', '.fs-close', function (){
					closeModal();
					return false;
				});

				$modal.on('click', '.button', function (evt) {
					evt.preventDefault();

					if ($(this).hasClass('disabled')) {
						return;
					}

					var email = getEmail();

					disableButton();

					if (!(-1 < email.indexOf('@'))) {
						return;
					}

					$.ajax({
						url       : ajaxurl,
						method    : 'POST',
						data      : {
							action     : '<?php echo $fs->get_ajax_action( 'resend_license_key' ) ?>',
							security   : '<?php echo $fs->get_ajax_security( 'resend_license_key' ) ?>',
							module_id  : moduleID,
							email      : email
						},
						beforeSend: function () {
							$sendButton.text('<?php fs_esc_js_echo_inline( 'Sending license key', 'sending-license-key', $slug ) ?>...');
						},
						success   : function (result) {
							var resultObj = $.parseJSON(result);
							if (resultObj.success) {
								closeModal();
							} else {
								showError(resultObj.error);
								resetButton();
							}
						}
					});
				});
			}

			registerEventHandlers();

			resetButton();

			function showModal() {
				resetModal();

				// Display the dialog box.
				$modal.addClass('active');

				if (!isFreemium)
					$emailInput.focus();

				var $body = $('body');

				isChild = $body.hasClass('has-fs-modal');
				if (isChild) {
					return;
				}

				$body.addClass('has-fs-modal');
			}

			function closeModal() {
				$modal.removeClass('active');

				// If child modal, do not remove the "has-fs-modal" class of the <body> element to keep its scrollbars hidden.
				if (isChild) {
					return;
				}

				$('body').removeClass('has-fs-modal');
			}

			function resetButton() {
				updateButtonState();
				$sendButton.text(<?php echo json_encode($send_button_text) ?>);
			}

			function resetModal() {
				hideError();
				resetButton();
				$emailInput.val('');
			}

			function getEmail() {
				var email = $emailInput.val().trim();

				if (isFreemium) {
					if ('other' != $modal.find('input[type=radio][name=email-address]:checked').val()) {
						email = userEmail;
					}
				}

				return email;
			}

			function updateButtonState() {
				/**
				 * If email address is not empty, enable the send license key button.
				 */
				$sendButton.toggleClass('disabled', !( -1 < getEmail().indexOf('@') ));
			}

			function disableButton() {
				$sendButton.addClass('disabled');
			}

			function hideError() {
				$feedbackMessage.hide();
			}

			function showError(msg) {
				$feedbackMessage.find(' > p').html(msg);
				$feedbackMessage.show();
			}
		});
	})(jQuery);
</script>
