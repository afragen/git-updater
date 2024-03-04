<?php
    /**
     * @package   Freemius
     * @copyright Copyright (c) 2015, Freemius, Inc.
     * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
     * @since     2.5.0
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }
?>
<script type="text/javascript">
    ( function( $ ) {
        var $errorMessage = null;

        $( document ).ready( function() {
            var $cloneResolutionNotice = $( 'div[data-id="clone_resolution_options_notice"], div[data-id="temporary_duplicate_notice"]' );

            if ( 0 === $cloneResolutionNotice.length ) {
                return;
            }

            $errorMessage = $cloneResolutionNotice.find( '#fs_clone_resolution_error_message' );

            /**
             * Triggers an AJAX request when the license activation link or any of the buttons on the clone resolution options notice is clicked. The AJAX request will then handle the action the user has chosen.
             */
            $cloneResolutionNotice.on( 'click', '.button, #fs_temporary_duplicate_license_activation_link', function( evt ) {
                evt.preventDefault();

                var $this = $( this );

                if ( $this.hasClass( 'disabled' ) ) {
                    return;
                }

                var $body             = $( 'body' ),
                    $optionsContainer = $this.parents( '.fs-clone-resolution-options-container' ),
                    cursor            = $body.css( 'cursor' ),
                    beforeUnload      = function() {
                        return '<?php fs_esc_js_echo_inline( 'Please wait', 'please-wait' ) ?>';
                    };

                $.ajax( {
                    // Get the parent options container from the child as `$cloneResolutionNotice` can have different AJAX URLs if both the manual clone resolution options and temporary duplicate notices are shown (for different subsites in a multisite network).
                    url       : $optionsContainer.data( 'ajax-url' ),
                    method    : 'POST',
                    data      : {
                        action      : '<?php echo $VARS['ajax_action'] ?>',
                        security    : '<?php echo wp_create_nonce( $VARS['ajax_action'] ) ?>',
                        clone_action: $this.data( 'clone-action' ),
                        blog_id     : $optionsContainer.data( 'blog-id' )
                    },
                    beforeSend: function() {
                        $body.css( { cursor: 'wait' } );

                        $this.addClass( 'disabled' );

                        if ( $this.attr( 'id' ) === 'fs_temporary_duplicate_license_activation_link' ) {
                            $this.append( '<i class="fs-ajax-spinner"></i>' );
                        }

                        $( window ).on( 'beforeunload', beforeUnload );
                    },
                    success   : function( resultObj ) {
                        $( window ).off( 'beforeunload', beforeUnload );

                        if (
                            resultObj.data &&
                            resultObj.data.redirect_url &&
                            '' !== resultObj.data.redirect_url
                        ) {
                            window.location = resultObj.data.redirect_url;
                        } else {
                            window.location.reload();
                        }
                    },
                    complete  : function() {
                        $body.css( { cursor: cursor } );
                        $this.removeClass( 'disabled' );

                        $this.parent().find( '.fs-ajax-spinner' ).remove();
                    }
                } );
            } );
        } );
    } )( jQuery );
</script>