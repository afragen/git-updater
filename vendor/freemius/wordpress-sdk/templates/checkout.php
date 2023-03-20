<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.0.3
	 */

	/**
	 * Note for WordPress.org Theme/Plugin reviewer:
	 *  Freemius is an SDK for plugin and theme developers. Since the core
	 *  of the SDK is relevant both for plugins and themes, for obvious reasons,
	 *  we only develop and maintain one code base.
	 *
	 *  This code (and page) will not run for wp.org themes (only plugins).
	 *
	 *  In addition, this page loads an i-frame. We intentionally named it 'frame'
	 *  so it will pass the "Theme Check" that is looking for the string "i" . "frame".
	 *
	 * UPDATE:
	 *  After ongoing conversations with the WordPress.org TRT we received
	 *  an official approval for including i-frames in the theme's WP Admin setting's
	 *  page tab (the SDK will never add any i-frames on the sitefront). i-frames
	 *  were never against the guidelines, but we wanted to get the team's blessings
	 *  before we move forward. For the record, I got the final approval from
	 *  Ulrich Pogson (@grapplerulrich), a team lead at the TRT during WordCamp
	 *  Europe 2017 (June 16th, 2017).
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
	fs_enqueue_local_script( 'postmessage', 'nojquery.ba-postmessage.min.js' );
	fs_enqueue_local_script( 'fs-postmessage', 'postmessage.js' );
	fs_enqueue_local_style( 'fs_common', '/admin/common.css' );

	fs_enqueue_local_style( 'fs_checkout', '/admin/checkout.css' );

	/**
	 * @var array    $VARS
	 * @var Freemius $fs
	 */
	$fs   = freemius( $VARS['id'] );
	$slug = $fs->get_slug();

	$timestamp = time();

	$context_params = array(
		'plugin_id'      => $fs->get_id(),
		'public_key'     => $fs->get_public_key(),
		'plugin_version' => $fs->get_plugin_version(),
		'mode'           => 'dashboard',
		'trial'          => fs_request_get_bool( 'trial' ),
		'is_ms'          => ( fs_is_network_admin() && $fs->is_network_active() ),
	);

	$plan_id = fs_request_get( 'plan_id' );
	if ( FS_Plugin_Plan::is_valid_id( $plan_id ) ) {
		$context_params['plan_id'] = $plan_id;
	}

	$licenses = fs_request_get( 'licenses' );
	if ( $licenses === strval( intval( $licenses ) ) && $licenses > 0 ) {
		$context_params['licenses'] = $licenses;
	}

	$plugin_id = fs_request_get( 'plugin_id' );
	if ( ! FS_Plugin::is_valid_id( $plugin_id ) ) {
		$plugin_id = $fs->get_id();
	}

	if ( $plugin_id == $fs->get_id() ) {
		$is_premium = $fs->is_premium();

        $bundle_id = $fs->get_bundle_id();
        if ( ! is_null( $bundle_id ) ) {
            $context_params['bundle_id'] = $bundle_id;
        }
    } else {
		// Identify the module code version of the checkout context module.
		if ( $fs->is_addon_activated( $plugin_id ) ) {
			$fs_addon   = Freemius::get_instance_by_id( $plugin_id );
			$is_premium = $fs_addon->is_premium();
		} else {
			// If add-on isn't activated assume the premium version isn't installed.
			$is_premium = false;
		}
	}

	// Get site context secure params.
	if ( $fs->is_registered() ) {
		$site = $fs->get_site();

		if ( $plugin_id != $fs->get_id() ) {
			if ( $fs->is_addon_activated( $plugin_id ) ) {
                $fs_addon   = Freemius::get_instance_by_id( $plugin_id );
                $addon_site = $fs_addon->get_site();
                if ( is_object( $addon_site ) ) {
                    $site = $addon_site;
                }
			}
		}

		$context_params = array_merge( $context_params, FS_Security::instance()->get_context_params(
			$site,
			$timestamp,
			'checkout'
		) );
	} else {
		$current_user = Freemius::_get_current_wp_user();

		// Add site and user info to the request, this information
		// is NOT being stored unless the user complete the purchase
		// and agrees to the TOS.
		$context_params = array_merge( $context_params, array(
			'user_firstname' => $current_user->user_firstname,
			'user_lastname'  => $current_user->user_lastname,
			'user_email'     => $current_user->user_email,
			'home_url'       => home_url(),
		) );

		$fs_user = Freemius::_get_user_by_email( $current_user->user_email );

		if ( is_object( $fs_user ) && $fs_user->is_verified() ) {
			$context_params = array_merge( $context_params, FS_Security::instance()->get_context_params(
				$fs_user,
				$timestamp,
				'checkout'
			) );
		}
	}

	if ( $fs->is_payments_sandbox() ) {
		// Append plugin secure token for sandbox mode authentication.
		$context_params['sandbox'] = FS_Security::instance()->get_secure_token(
			$fs->get_plugin(),
			$timestamp,
			'checkout'
		);

		/**
		 * @since 1.1.7.3 Add security timestamp for sandbox even for anonymous user.
		 */
		if ( empty( $context_params['s_ctx_ts'] ) ) {
			$context_params['s_ctx_ts'] = $timestamp;
		}
	}

	$return_url = $fs->_get_sync_license_url( $plugin_id );

	$can_user_install = (
		( $fs->is_plugin() && current_user_can( 'install_plugins' ) ) ||
		( $fs->is_theme() && current_user_can( 'install_themes' ) )
	);

	$query_params = array_merge( $context_params, $_GET, array(
		// Current plugin version.
		'plugin_version' => $fs->get_plugin_version(),
		'sdk_version'    => WP_FS__SDK_VERSION,
		'is_premium'     => $is_premium ? 'true' : 'false',
		'can_install'    => $can_user_install ? 'true' : 'false',
		'return_url'     => $return_url,
	) );

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
			// http://stackoverflow.com/questions/4583703/jquery-post-request-not-ajax
			jQuery(function ($) {
				$.extend({
					form: function (url, data, method) {
						if (method == null) method = 'POST';
						if (data == null) data = {};

						var form = $('<form>').attr({
							method: method,
							action: url
						}).css({
							display: 'none'
						});

						var addData = function (name, data) {
							if ($.isArray(data)) {
								for (var i = 0; i < data.length; i++) {
									var value = data[i];
									addData(name + '[]', value);
								}
							} else if (typeof data === 'object') {
								for (var key in data) {
									if (data.hasOwnProperty(key)) {
										addData(name + '[' + key + ']', data[key]);
									}
								}
							} else if (data != null) {
								form.append($('<input>').attr({
									type : 'hidden',
									name : String(name),
									value: String(data)
								}));
							}
						};

						for (var key in data) {
							if (data.hasOwnProperty(key)) {
								addData(key, data[key]);
							}
						}

						return form.appendTo('body');
					}
				});
			});

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
						$.form('<?php echo fs_nonce_url( $fs->_get_admin_page_url( 'account', array(
							'fs_action' => $fs->get_unique_affix() . '_activate_new',
							'plugin_id' => $plugin_id
						) ), $fs->get_unique_affix() . '_activate_new' ) ?>', requestData).submit();
					});

					FS.PostMessage.receiveOnce('pending_activation', function (data) {
						var requestData = {
							user_email           : data.user_email,
                            support_email_address: data.support_email_address
						};

						if (true === data.auto_install)
							requestData.auto_install = true;

						$.form('<?php echo fs_nonce_url( $fs->_get_admin_page_url( 'account', array(
							'fs_action'           => $fs->get_unique_affix() . '_activate_new',
							'plugin_id'           => $plugin_id,
							'pending_activation'  => true,
                            'has_upgrade_context' => true,
						) ), $fs->get_unique_affix() . '_activate_new' ) ?>', requestData).submit();
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
						frame.css('min-height', $(document.body).height() + 'px');
					};

					$(document).ready(updateHeight);

					$(window).resize(updateHeight);
				});
			})(jQuery);
		</script>
	</div>