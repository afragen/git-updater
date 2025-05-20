<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2024, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       2.9.0
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * @var array    $VARS
	 * @var Freemius $fs
	 */
	$fs          = freemius( $VARS['id'] );
	$fs_checkout = FS_Checkout_Manager::instance();

	$plugin_id = fs_request_get( 'plugin_id' );
	if ( ! FS_Plugin::is_valid_id( $plugin_id ) ) {
		$plugin_id = $fs->get_id();
	}

	$fs_checkout->verify_checkout_redirect_nonce( $fs );

	wp_enqueue_script( 'jquery' );
	wp_enqueue_script( 'json2' );
	fs_enqueue_local_script( 'fs-form', 'jquery.form.js', array( 'jquery' ) );

	$action = fs_request_get( '_fs_checkout_action' );
	$data   = json_decode( fs_request_get_raw( '_fs_checkout_data' ) );
?>
<div class="fs-checkout-process-redirect">
    <div class="fs-checkout-process-redirect__loader">
	    <?php fs_include_template( 'ajax-loader.php' ); ?>
    </div>

    <div class="fs-checkout-process-redirect__content">
        <p>
		    <?php echo esc_html( fs_text_inline( 'Processing, please wait and do not close or refresh this window...' ) ); ?>
        </p>
    </div>
</div>

<script type="text/javascript">
    jQuery(function ($) {
        var $loader = $( '.fs-checkout-process-redirect .fs-ajax-loader' ),
            action = <?php echo wp_json_encode( $action ); ?>,
            data = <?php echo wp_json_encode( $data ); ?>;

        $loader.show();

        // This remains compatible with the same filter in /templates/checkout/frame.php.
        // You can return a promise to make the successive redirection wait until your own processing is completed.
        // However for most cases, we recommend sending a beacon request {https://developer.mozilla.org/en-US/docs/Web/API/Navigator/sendBeacon}
        var processPurchaseEvent = (<?php echo $fs->apply_filters('checkout/purchaseCompleted', 'function (data) {
            console.log("checkout", "purchaseCompleted");
        }'); ?>)(data.purchaseData);

        if (typeof Promise !== 'undefined' && processPurchaseEvent instanceof Promise) {
            processPurchaseEvent.finally(function () {
                finishProcessing(action, data);
            });
        } else {
            finishProcessing(action, data);
        }

        function finishProcessing(action, data) {
            switch ( action ) {
                case 'install':
                    processInstall( data );
                    break;
                case 'pending_activation':
                    processPendingActivation( data );
                    break;
                case 'return_without_sync':
                    goToAccount();
                    break;
                default:
                    syncLicense( data );
                    break;
            }
        }

        function processInstall( data ) {
            var requestData = {
                user_id           : data.user.id,
                user_secret_key   : data.user.secret_key,
                user_public_key   : data.user.public_key,
                install_id        : data.install.id,
                install_secret_key: data.install.secret_key,
                install_public_key: data.install.public_key
            };

            if ( true === data.auto_install )
                requestData.auto_install = true;

            // Post data to activation URL.
            $.form( '<?php echo $fs_checkout->get_install_url( $fs, $plugin_id ); ?>', requestData ).submit();
        }

        function processPendingActivation( data ) {
            var requestData = {
                user_email           : data.user_email,
                support_email_address: data.support_email_address
            };

            if ( true === data.auto_install )
                requestData.auto_install = true;

            $.form( '<?php echo $fs_checkout->get_pending_activation_url( $fs, $plugin_id ); ?>', requestData ).submit();
        }

        function syncLicense(data) {
            var redirectUrl = new URL( <?php echo wp_json_encode( $fs->_get_sync_license_url( $plugin_id ) ); ?> );

            if (true === data.auto_install) {
                redirectUrl.searchParams.set( 'auto_install', 'true' );
            }

            window.location.href = redirectUrl.toString();
        }

        function goToAccount() {
            window.location.href = <?php echo wp_json_encode( $fs->get_account_url() ) ?>;
        }
    });
</script>
