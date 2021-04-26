<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       2.1.0
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

    /**
     * @var array $VARS
     */
    $fs = freemius( $VARS['id'] );
?>
<script type="text/javascript">
    (function( $ ) {
        $( document ).ready(function() {
            var $gdprOptinNotice = $( 'div[data-id^="gdpr_optin_actions"]' );
            if ( 0 === $gdprOptinNotice.length ) {
                return;
            }

            $gdprOptinNotice.on( 'click', '.button', function() {
                var
                    $this          = $( this ),
                    allowMarketing = $this.hasClass( 'allow-marketing' ),
                    cursor         = $this.css( 'cursor' ),
                    $products      = $gdprOptinNotice.find( 'span[data-plugin-id]' ),
                    pluginIDs      = [];

                if ( $products.length > 0 ) {
                    $products.each(function() {
                        pluginIDs.push( $( this ).data( 'plugin-id' ) );
                    });
                }

                $.ajax({
                    url       : ajaxurl + '?' + $.param({
                        action   : '<?php echo $fs->get_ajax_action( 'gdpr_optin_action' ) ?>',
                        security : '<?php echo $fs->get_ajax_security( 'gdpr_optin_action' ) ?>',
                        module_id: '<?php echo $fs->get_id() ?>'
                    }),
                    method    : 'POST',
                    data      : {
                        is_marketing_allowed: allowMarketing,
                        plugin_ids          : pluginIDs
                    },
                    beforeSend: function() {
                        $this.text( <?php fs_json_encode_echo_inline( 'Thanks, please wait', 'thanks-please-wait', $fs->get_slug() ) ?> + '...' );
                        $this.css({'cursor': 'wait'});

                        $gdprOptinNotice.find( '.button' ).addClass( 'disabled' );
                    },
                    complete  : function() {
                        $this.css({'cursor': cursor});

                        $gdprOptinNotice.remove();
                    }
                });
            });
        });
    })( jQuery );
</script>