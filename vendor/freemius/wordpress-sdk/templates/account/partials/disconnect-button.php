<?php
    /**
     * @package     Freemius
     * @copyright   Copyright (c) 2016, Freemius, Inc.
     * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
     * @since       2.5.0
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    /**
     * @var array $VARS
     * @var Freemius $fs
     */
    $fs = $VARS['freemius'];

    /**
     * @var FS_Plugin_License $license
     */
    $license = $VARS['license'];
    /**
     * @var FS_Plugin_Plan $license_paid_plan
     */
    $license_paid_plan = $VARS['license_paid_plan'];

    $license_subscription = ( is_object( $license ) && is_object( $license_paid_plan ) ) ?
        $fs->_get_subscription( $license->id ) :
        null;

    $has_active_subscription = (
        is_object( $license_subscription ) &&
        $license_subscription->is_active()
    );

    $button_id = "fs_disconnect_button_{$fs->get_id()}";

    $website_link = sprintf( '<a href="#" tabindex="-1">%s</a>', fs_strip_url_protocol( untrailingslashit( Freemius::get_unfiltered_site_url() ) ) );
?>
<script type="text/javascript">
    // Wrap in a IFFE to prevent leaking global variables.
    ( function( $ ) {
        $( document ).ready( function() {
            var $modal = $( '#fs_modal_delete_site' );

            $( '#<?php echo $button_id ?>' ).on( 'click', function( e ) {
                // Prevent the form being submitted.
                e.preventDefault();

                $( document.body ).append( $modal );
                $modal.show();
            } );

            $modal.on( 'click', '.button-close', function ( evt ) {
                $modal.hide();
            } );

            $modal.on( 'click', '.button-primary', function ( evt ) {
                $( '#<?php echo $button_id ?>' ).closest( 'form' )[0].submit();
            } );
        } );
    } )( jQuery );
</script>
<div id="fs_modal_delete_site" class="fs-modal active" style="display: none">
    <div class="fs-modal-dialog">
        <div class="fs-modal-header">
            <h4><?php echo $fs->esc_html_inline( 'Disconnect', 'disconnect' ) ?></h4>
        </div>
        <div class="fs-modal-body">
            <?php if ( ! is_object( $license ) ) : ?>
            <p><?php echo
                    // translators: %1$s is replaced with the website's homepage address, %2$s is replaced with the plugin name.
                    sprintf( esc_html( $fs->get_text_inline( 'By disconnecting the website, previously shared diagnostic data about %1$s will be deleted and no longer visible to %2$s.', 'disconnect-intro-paid' ) ), $website_link, '<b>' . $fs->get_plugin_title() . '</b>' ) ?></p>
            <?php else : ?>
                <p><?php echo
                        // translators: %s is replaced with the website's homepage address.
                        sprintf( esc_html( $fs->get_text_inline( 'Disconnecting the website will permanently remove %s from your User Dashboard\'s account.', 'disconnect-intro-paid' ) ), $website_link ) ?></p>
            <?php endif ?>

            <?php if ( $has_active_subscription ) : ?>
                <p><?php echo
                    // translators: %1$s is replaced by the paid plan name, %2$s is replaced with an anchor link with the text "User Dashboard".
                        sprintf( esc_html( $fs->get_text_inline( 'If you wish to cancel your %1$s plan\'s subscription instead, please navigate to the %2$s and cancel it there.', 'disconnect-subscription-disclaimer' ) ), $license_paid_plan->title, sprintf( '<a href="https://users.freemius.com" target="_blank" rel="noreferrer noopener nofollow">%s</a>', $fs->get_text_inline( 'User Dashboard', 'user-dashboard' ) )
                    ) ?></p>
            <?php endif ?>

            <p><?php echo esc_html( $fs->get_text_inline( 'Are you sure you would like to proceed with the disconnection?', 'disconnect-confirm' ) ) ?></p>
        </div>
        <div class="fs-modal-footer">
            <button class="button button-primary<?php if ( is_object( $license ) ) : ?> warn<?php endif ?>" tabindex="2"><?php echo $fs->esc_html_inline( 'Yes', 'yes' ) . ' - ' .  $fs->esc_html_inline( 'Disconnect', 'disconnect' ) ?></button>
            <button class="button button-secondary button-close" tabindex="1"><?php echo esc_html( $fs->get_text_inline( 'Cancel', 'cancel' ) ) ?></button>
        </div>
    </div>
</div>
<form action="<?php echo esc_attr( $fs->_get_admin_page_url( 'account' ) ); ?>" method="POST">
    <input type="hidden" name="fs_action" value="delete_account">
    <?php wp_nonce_field( 'delete_account' ) ?>

    <a id="<?php echo $button_id ?>" href="#" class="fs-button-inline">
        <i class="dashicons dashicons-no"></i>
        <?php echo $fs->esc_html_inline( 'Disconnect', 'disconnect' ) ?>
    </a>
</form>