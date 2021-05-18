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
    $fs   = freemius( $VARS['id'] );
    $slug = $fs->get_slug();

    $plugin_data     = $fs->get_plugin_data();
    $plugin_name     = $plugin_data['Name'];
    $plugin_basename = $fs->get_plugin_basename();

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

    $message = sprintf(
        fs_text_inline( 'There is a new version of %s available.', 'new-version-available-message', $slug ) .
        fs_text_inline( ' %s to access version %s security & feature updates, and support.', 'x-for-updates-and-support', $slug ),
        '<span id="plugin_name"></span>',
        sprintf(
            '<a id="pricing_url" href="">%s</a>',
            is_object( $license ) ?
                fs_text_inline( 'Renew your license now', 'renew-license-now', $slug ) :
                fs_text_inline( 'Buy a license now', 'buy-license-now', $slug )
        ),
        '<span id="new_version"></span>'
    );

    $modal_content_html = "<p>{$message}</p>";

    $header_title = fs_text_inline( 'New Version Available', 'new-version-available', $slug );

    $renew_license_button_text = is_object( $license ) ?
        fs_text_inline( 'Renew license', 'renew-license', $slug ) :
        fs_text_inline( 'Buy license', 'buy-license', $slug );

    fs_enqueue_local_style( 'fs_dialog_boxes', '/admin/dialog-boxes.css' );
?>
<script type="text/javascript">
(function( $ ) {
    $( document ).ready(function() {
        if ( 0 === $( '.license-expired' ).length ) {
            return;
        }

        var modalContentHtml = <?php echo json_encode( $modal_content_html ) ?>,
            modalHtml        =
                '<div class="fs-modal fs-modal-upgrade-premium-version">'
                + ' <div class="fs-modal-dialog">'
                + '     <div class="fs-modal-header">'
                + '         <h4><?php echo esc_js( $header_title ) ?></h4>'
                + '         <a href="!#" class="fs-close"><i class="dashicons dashicons-no" title="<?php echo esc_js( fs_text_x_inline( 'Dismiss', 'close a window', 'dismiss', $slug ) ) ?>"></i></a>'
                + '     </div>'
                + '     <div class="fs-modal-body">'
                + '         <div class="fs-modal-panel active">' + modalContentHtml + '</div>'
                + '     </div>'
                + '     <div class="fs-modal-footer">'
                + '         <a class="button button-primary button-renew-license" tabindex="3" href="<?php echo $purchase_url ?>"><?php echo esc_js( $renew_license_button_text ) ?></a>'
                + '         <button class="button button-secondary button-close" tabindex="4"><?php fs_esc_js_echo_inline( 'Cancel', 'cancel', $slug ) ?></button>'
                + '     </div>'
                + ' </div>'
                + '</div>',
            $modal           = $( modalHtml ),
            isPluginsPage    = <?php echo Freemius::is_plugins_page() ? 'true' : 'false' ?>;

        $modal.appendTo( $( 'body' ) );

        function registerEventHandlers() {
            $( 'body' ).on( 'click', '.license-expired', function( evt ) {
                var $this = $( this );

                if ( ! $this.is( ':checked' ) ||
                    (
                        isPluginsPage &&
                        'update-selected' !== $( '#bulk-action-selector-top' ).val() &&
                        'update-selected' !== $( '#bulk-action-selector-bottom' ).val()
                    )
                ) {
                    return true;
                }

                evt.preventDefault();
                evt.stopImmediatePropagation();

                showModal( $this );
            });

            // If the user has clicked outside the window, close the modal.
            $modal.on( 'click', '.fs-close, .button-secondary', function() {
                closeModal();
                return false;
            });

            if ( isPluginsPage ) {
                $( 'body' ).on( 'change', 'select[id*="bulk-action-selector"]', function() {
                    if ( 'update-selected' === $( this ).val() ) {
                        setTimeout(function() {
                            $( '.license-expired' ).prop( 'checked', false );
                            $( '[id*="select-all"]' ).prop( 'checked', false );
                        }, 0);
                    }
                });
            }

            $( 'body' ).on( 'click', '[id*="select-all"]', function( evt ) {
                var $this = $( this );

                if ( ! $this.is( ':checked' ) ) {
                    return true;
                }

                if ( isPluginsPage ) {
                    if ( 'update-selected' !== $( '#bulk-action-selector-top' ).val() &&
                        'update-selected' !== $( '#bulk-action-selector-bottom' ).val() ) {
                        return true;
                    }
                }

                var $table                       = $this.closest( 'table' ),
                    controlChecked               = $this.prop( 'checked' ),
                    toggle                       = ( event.shiftKey || $this.data( 'wp-toggle' ) ),
                    $modules                     = $table.children( 'tbody' ).filter( ':visible' ).children().children( '.check-column' ).find( ':checkbox' ),
                    $modulesWithNonActiveLicense = $modules.filter( '.license-expired' );

                if ( 0 === $modulesWithNonActiveLicense.length ) {
                    /**
                     * It's possible that the context HTML table element doesn't have checkboxes with
                     * ".license-expired" class if for example only the themes table has such checkboxes and the user
                     * clicks on a "Select All" checkbox on the plugins table which has no such checkboxes.
                     *
                     * @author Leo Fajardo (@leorw)
                     */
                    return true;
                } else if ( 1 === $modulesWithNonActiveLicense.length ) {
                    showModal( $modulesWithNonActiveLicense );
                }

                /**
                 * Prevent the default WordPress handler from checking all checkboxes.
                 *
                 * @author Leo Fajardo (@leorw)
                 */
                evt.stopImmediatePropagation();

                $modules.filter( ':not(.license-expired)' )
                    .prop( 'checked', function() {
                        if ( $( this ).is( ':hidden,:disabled' ) ) {
                            return false;
                        }

                        if ( toggle ) {
                            return ! $( this ).prop( 'checked' );
                        } else if ( controlChecked ) {
                            return true;
                        }

                        return false;
                    });

                return false;
            });
        }

        registerEventHandlers();

        function showModal( $module ) {
            $modal.find( '#plugin_name' ).text( $module.data( 'plugin-name' ) );
            $modal.find( '#pricing_url' ).attr( 'href', $module.data( 'pricing-url' ) );
            $modal.find( '#new_version' ).text( $module.data( 'new-version' ) );

            // Display the dialog box.
            $modal.addClass( 'active' );
            $( 'body' ).addClass( 'has-fs-modal' );
        }

        function closeModal() {
            $modal.removeClass( 'active' );
            $( 'body' ).removeClass( 'has-fs-modal' );
        }
    });
})( jQuery );
</script>