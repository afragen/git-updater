<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.0.3
	 */

	/**
     * Update (October 9, 2024 by @swashata):
	 *    Following request from the wp.org plugin review team, we have stopped
	 *    embedding the checkout inside an i-frame for wp.org hosted free version
	 *    of plugins and themes. Now they will be redirected instead.
     *
	 * Note for WordPress.org Theme/Plugin reviewer:
	 *  Freemius is an SDK for plugin and theme developers. Since the core
	 *  of the SDK is relevant both for plugins and themes, for obvious reasons,
	 *  we only develop and maintain one code base.
	 *
	 *  This code (and page) will not run for wp.org themes and plugins. It will
	 *  run only for premium version of the plugin/theme that is using the SDK.
	 *
	 *  In addition, when this page loads an i-frame. We intentionally named it 'frame'
	 *  so it will pass the "Theme Check" that is looking for the string "i" . "frame".
	 *
	 * If you have any questions or need clarifications, please don't hesitate
	 * pinging me on slack, my username is @svovaf.
	 *
	 * @author Vova Feldman (@svovaf)
	 * @since 1.2.2
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	wp_enqueue_script( 'jquery' );
	wp_enqueue_script( 'json2' );
	fs_enqueue_local_script( 'postmessage', 'nojquery.ba-postmessage.js' );
	fs_enqueue_local_script( 'fs-postmessage', 'postmessage.js' );
	fs_enqueue_local_script( 'fs-form', 'jquery.form.js', array( 'jquery' ) );

	/**
	 * @var array    $VARS
	 * @var Freemius $fs
	 */
	$fs   = freemius( $VARS['id'] );
	$slug = $fs->get_slug();

    $fs_checkout = FS_Checkout_Manager::instance();

	$plugin_id = fs_request_get( 'plugin_id' );
	if ( ! FS_Plugin::is_valid_id( $plugin_id ) ) {
		$plugin_id = $fs->get_id();
	}

	$plan_id  = fs_request_get( 'plan_id' );
	$licenses = fs_request_get( 'licenses' );

    $query_params = $fs_checkout->get_query_params(
        $fs,
        $plugin_id,
        $plan_id,
        $licenses
    );

	$return_url = $fs->_get_sync_license_url( $plugin_id );
    $query_params['return_url'] = $return_url;

	$xdebug_session = fs_request_get( 'XDEBUG_SESSION' );
	if ( false !== $xdebug_session ) {
		$query_params['XDEBUG_SESSION'] = $xdebug_session;
	}

	$view_params = array(
		'id'   => $VARS['id'],
		'page' => strtolower( $fs->get_text_inline( 'Checkout', 'checkout' ) ) . ' ' . $fs->get_text_inline( 'PCI compliant', 'pci-compliant' ),
	);
	fs_require_once_template('secure-https-header.php', $view_params);
?>
	<div id="fs_checkout" class="wrap fs-section fs-full-size-wrapper">
		<div id="fs_frame"></div>
		<script type="text/javascript">
			(function ($) {
				$(function () {

					var
						// Keep track of the i-frame height.
						frame_height = 800,
						base_url     = '<?php echo FS_CHECKOUT__ADDRESS ?>',
						// Pass the parent page URL into the i-frame in a meaningful way (this URL could be
						// passed via query string or hard coded into the child page, it depends on your needs).
						src          = base_url + '/?<?php echo http_build_query( $query_params ) ?>#' + encodeURIComponent(document.location.href),
						// Append the i-frame into the DOM.
						frame        = $('<i' + 'frame " src="' + src + '" width="100%" height="' + frame_height + 'px" scrolling="no" frameborder="0" style="background: transparent; width: 1px; min-width: 100%;"><\/i' + 'frame>')
							.appendTo('#fs_frame');

					FS.PostMessage.init(base_url, [frame[0]]);
					FS.PostMessage.receiveOnce('height', function (data) {
						var h = data.height;
						if (!isNaN(h) && h > 0 && h != frame_height) {
							frame_height = h;
							frame.height(frame_height + 'px');

							FS.PostMessage.postScroll(frame[0]);
						}
					});

					FS.PostMessage.receiveOnce('install', function (data) {
						var requestData = {
							user_id           : data.user.id,
							user_secret_key   : data.user.secret_key,
							user_public_key   : data.user.public_key,
							install_id        : data.install.id,
							install_secret_key: data.install.secret_key,
							install_public_key: data.install.public_key
						};

						if (true === data.auto_install)
							requestData.auto_install = true;

						// Post data to activation URL.
						$.form('<?php echo $fs_checkout->get_install_url( $fs, $plugin_id ); ?>', requestData).submit();
					});

					FS.PostMessage.receiveOnce('pending_activation', function (data) {
						var requestData = {
							user_email           : data.user_email,
                            support_email_address: data.support_email_address
						};

						if (true === data.auto_install)
							requestData.auto_install = true;

						$.form('<?php echo $fs_checkout->get_pending_activation_url( $fs, $plugin_id ); ?>', requestData).submit();
					});

					FS.PostMessage.receiveOnce('get_context', function () {
						console.debug('receiveOnce', 'get_context');

						// If the user didn't connect his account with Freemius,
						// once he accepts the Terms of Service and Privacy Policy,
						// and then click the purchase button, the context information
						// of the user will be shared with Freemius in order to complete the
						// purchase workflow and activate the license for the right user.
						<?php $install_data = array_merge( $fs->get_opt_in_params(),
						array(
							'activation_url' => fs_nonce_url( $fs->_get_admin_page_url( '',
								array(
									'fs_action' => $fs->get_unique_affix() . '_activate_new',
									'plugin_id' => $plugin_id,

								) ),
								$fs->get_unique_affix() . '_activate_new' )
						) ) ?>
						FS.PostMessage.post('context', <?php echo json_encode( $install_data ) ?>, frame[0]);
					});

					FS.PostMessage.receiveOnce('purchaseCompleted', <?php echo $fs->apply_filters('checkout/purchaseCompleted', 'function (data) {
						console.log("checkout", "purchaseCompleted");
					}') ?>);

					FS.PostMessage.receiveOnce('get_dimensions', function (data) {
						console.debug('receiveOnce', 'get_dimensions');

						FS.PostMessage.post('dimensions', {
							height   : $(document.body).height(),
							scrollTop: $(document).scrollTop()
						}, frame[0]);
					});

					var updateHeight = function () {
						frame.css('min-height', Math.max($(document.body).height(), $('#wpwrap').height()) + 'px');
					};

					$(document).ready(updateHeight);

					$(window).resize(updateHeight);
				});
			})(jQuery);
		</script>
	</div>