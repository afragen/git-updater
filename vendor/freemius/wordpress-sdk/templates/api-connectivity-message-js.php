<?php
    /**
     * @package   Freemius
     * @copyright Copyright (c) 2015, Freemius, Inc.
     * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
     * @since     2.5.4
     */
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }
?>
<script type="text/javascript">
    ( function( $ ) {
        $( document ).ready(function() {
            var $parent = $( '.fs-notice, #fs_connect, .fs-modal' );

            $parent.on( 'click', '.fs-api-request-error-show-details-link', function () {
                var $error_details_container = $parent.find( '.fs-api-request-error-details' );

                $error_details_container.toggle();

                $( this ).find( 'span' ).prop( 'class',
                    $error_details_container.is( ':visible' ) ?
                        'dashicons dashicons-arrow-up-alt2' :
                        'dashicons dashicons-arrow-down-alt2'
                );

                return false;
            } );
        } );
    } )( jQuery );
</script>