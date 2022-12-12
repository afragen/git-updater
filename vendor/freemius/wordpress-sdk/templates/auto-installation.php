<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.2.1.5
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * @var array    $VARS
	 * @var Freemius $fs
	 */
    $slug      = $VARS['slug'];
    $plugin_id = $VARS['target_module_id'];

    $fs = freemius( $VARS['id'] );

	$action = $fs->is_tracking_allowed() ?
		'stop_tracking' :
		'allow_tracking';

	$title = $fs->get_plugin_title();

	if ( $plugin_id != $fs->get_id() ) {
		$addon = $fs->get_addon( $plugin_id );

		if ( is_object( $addon ) ) {
			$title = $addon->title . ' ' . fs_text_inline( 'Add-On', 'addon', $slug );
		}
	}

	$plugin_title = sprintf(
		'<strong>%s</strong>',
		esc_html( $title )
	);

	$sec_countdown  = 30;
	$countdown_html = sprintf(
		esc_js(
			/* translators: %s: Number of seconds */
			fs_text_inline( '%s sec', 'x-sec', $slug )
		),
		sprintf( '<span class="fs-countdown">%s</span>', $sec_countdown )
	);

	fs_enqueue_local_style( 'fs_dialog_boxes', '/admin/dialog-boxes.css' );
	fs_enqueue_local_style( 'fs_common', '/admin/common.css' );

	$params      = array();
	$loader_html = fs_get_template( 'ajax-loader.php', $params );

	// Pass unique auto installation URL if WP_Filesystem is needed.
	$install_url = $fs->_get_sync_license_url(
		$plugin_id,
		true,
		array( 'auto_install' => 'true' )
	);


	ob_start();

	$method = ''; // Leave blank so WP_Filesystem can populate it as necessary.

	$credentials = request_filesystem_credentials(
		esc_url_raw( $install_url ),
		$method,
		false,
		WP_PLUGIN_DIR,
		array()
	);

	$credentials_form = ob_get_clean();

	$require_credentials = ! empty( $credentials_form );
?>
<div class="fs-modal fs-modal-auto-install">
	<div class="fs-modal-dialog">
		<div class="fs-modal-header">
			<h4><?php echo esc_js( fs_text_inline( 'Automatic Installation', 'auto-installation', $slug ) ) ?></h4>
		</div>
		<div class="fs-modal-body">
			<div class="fs-notice-error" style="display: none"><p></p></div>
			<?php if ( $require_credentials ) : ?>
				<div id="request-filesystem-credentials-dialog">
					<?php echo $credentials_form ?>
				</div>
			<?php else : ?>
				<p class="fs-installation-notice"><?php echo sprintf(
						fs_esc_html_inline( 'An automated download and installation of %s (paid version) from %s will start in %s. If you would like to do it manually - click the cancellation button now.', 'installing-in-n', $slug ),
						$plugin_title,
						sprintf(
							'<a href="%s" target="_blank" rel="noopener">%s</a>',
							'https://freemius.com',
							'freemius.com'
						),
						$countdown_html
					) ?></p>
			<?php endif ?>
			<p class="fs-installing"
			   style="display: none"><?php echo sprintf( fs_esc_html_inline( 'The installation process has started and may take a few minutes to complete. Please wait until it is done - do not refresh this page.', 'installing-module-x', $slug ), $plugin_title ) ?></p>
		</div>
		<div class="fs-modal-footer">
			<?php echo $loader_html ?>
			<button
				class="button button-secondary button-cancel"><?php fs_esc_html_echo_inline( 'Cancel Installation', 'cancel-installation', $slug ) ?><?php if ( ! $require_credentials ) : ?> (<?php echo $countdown_html ?>)<?php endif ?></button>
			<button
				class="button button-primary"><?php fs_esc_html_echo_inline( 'Install Now', 'install-now', $slug ) ?></button>
		</div>
	</div>
</div>'

<script type="text/javascript">
	(function ($) {
		$(document).ready(function () {
			var $modal             = $('.fs-modal-auto-install'),
			    $body              = $('body'),
			    $countdown         = $modal.find('.fs-countdown'),
			    requireCredentials = <?php echo json_encode( $require_credentials ) ?>,
			    $credentialsForm   = $('#request-filesystem-credentials-dialog'),
			    $errorNotice       = $modal.find('.fs-notice-error'),
			    installing         = false;

			$modal.appendTo($body);

			var startAutoInstall = function () {
				if (installing)
					return;

				installing = true;

				// Start auto-install.
				$modal.addClass('fs-warn');
				if (requireCredentials) {
					$credentialsForm.hide();
				} else {
					$modal.find('.fs-installation-notice').hide();
				}

				$errorNotice.hide();
				$modal.find('.fs-installing').show();
				$modal.find('button').hide();
				$modal.find('.fs-ajax-loader').show();

				var data = {
					action          : '<?php echo $fs->get_ajax_action( 'install_premium_version' ) ?>',
					security        : '<?php echo $fs->get_ajax_security( 'install_premium_version' ) ?>',
					slug            : '<?php echo $slug ?>',
					module_id       : '<?php echo $fs->get_id() ?>',
                    target_module_id: '<?php echo $plugin_id ?>'
				};

				if (requireCredentials) {
					// Add filesystem credentials.
					data.hostname = $('#hostname').val();
					data.username = $('#username').val();
					data.password = $('#password').val();
					data.connection_type = $('input[name="connection_type"]:checked').val();
					data.public_key = $('#public_key').val();
					data.private_key = $('#private_key').val();
				}

				$.ajax({
					url    : <?php echo Freemius::ajax_url() ?>,
					method : 'POST',
					data   : data,
					success: function (resultObj) {
						var reloadAccount = false;

						if (resultObj.success) {
							// Reload account page to show new data.
							reloadAccount = true;
						} else {
							switch (resultObj.error.code) {
								case 'invalid_module_id':
								case 'premium_installed':
									reloadAccount = true;
									break;
								case 'invalid_license':
								case 'premium_version_missing':
								case 'unable_to_connect_to_filesystem':
								default:
									$modal.removeClass('fs-warn');
									$modal.find('.fs-installing').hide();
									$modal.find('.fs-ajax-loader').hide();
									$modal.find('.button-cancel').html(<?php fs_json_encode_echo_inline( 'Cancel Installation', 'cancel-installation', $slug ) ?>);
									$modal.find('button').show();

									$errorNotice.find('p').text(resultObj.error.message);
									$errorNotice.addClass('notice notice-alt notice-error').show();
									if (requireCredentials) {
										$credentialsForm.show();
									}
									break;
							}
						}

						if (reloadAccount) {
							window.location = '<?php echo $fs->get_account_url() ?>';
						}

						installing = false;
					}
				});
			};

			var clearCountdown = function () {
				clearInterval(countdownInterval);
				countdownInterval = null;
			};

			var cancelAutoInstall = function () {
				$modal.fadeOut(function () {
					$modal.remove();
					$body.removeClass('has-fs-modal');
				});
			};

			var countdown         = <?php echo $sec_countdown ?>,
			    countdownInterval = requireCredentials ? null : setInterval(function () {
				    $countdown.html(--countdown);
				    if (0 == countdown) {
					    clearCountdown();
					    startAutoInstall();
				    }
			    }, 1000);

			$modal.addClass('active');
			$body.addClass('has-fs-modal');

			$modal.find('.button-primary').click(function () {
				clearCountdown();
				startAutoInstall();
			});

			$modal.find('.button-cancel').click(function () {
				clearCountdown();
				cancelAutoInstall();
			});

			if (requireCredentials) {

			}
		});
	})(jQuery);
</script>
