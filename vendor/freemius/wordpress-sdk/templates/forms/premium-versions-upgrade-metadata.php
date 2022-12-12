<?php
    /**
     * @package   Freemius
     * @copyright Copyright (c) 2015, Freemius, Inc.
     * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
     * @since     2.0.2
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    /**
     * @var Freemius $fs
     */
    $fs = freemius( $VARS['id'] );

    $license = $fs->_get_license();

    if ( ! is_object( $license ) ) {
        $purchase_url = $fs->pricing_url();
    } else {
        $subscription = $fs->_get_subscription( $license->id );

        $purchase_url = $fs->checkout_url(
            is_object( $subscription ) ?
                ( 1 == $subscription->billing_cycle ? WP_FS__PERIOD_MONTHLY : WP_FS__PERIOD_ANNUALLY ) :
                WP_FS__PERIOD_LIFETIME,
            false,
            array( 'licenses' => $license->quota )
        );
    }

    $plugin_data = $fs->get_plugin_data();
?>
<script type="text/javascript">
(function( $ ) {
    $( document ).ready(function() {
        var $premiumVersionCheckbox = $( 'input[type="checkbox"][value="<?php echo $fs->get_plugin_basename() ?>"]' );

        $premiumVersionCheckbox.addClass( 'license-expired' );
        $premiumVersionCheckbox.data( 'plugin-name', <?php echo json_encode( $plugin_data['Name'] ) ?> );
        $premiumVersionCheckbox.data( 'pricing-url', <?php echo json_encode( $purchase_url ) ?> );
        $premiumVersionCheckbox.data( 'new-version', <?php echo json_encode( $VARS['new_version'] ) ?> );
    });
})( jQuery );
</script>