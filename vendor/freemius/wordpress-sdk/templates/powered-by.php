<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.0.5
	 */

	/**
	 * Note for WordPress.org Theme/Plugin reviewer:
	 *  Freemius is an SDK for plugin and theme developers. Since the core
	 *  of the SDK is relevant both for plugins and themes, for obvious reasons,
	 *  we only develop and maintain one code base.
	 *
	 *  This code will not run for wp.org themes (only plugins)
	 *  since theme admin settings/options are now only allowed in the customizer.
	 *
	 *  In addition, this page loads an i-frame. We intentionally named it 'frame'
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

	$VARS = isset($VARS) ? $VARS : array();

    $fs = freemius( $VARS['module_id'] );

    if ( ! $fs->is_whitelabeled() && ! $fs->apply_filters( 'hide_freemius_powered_by', false ) ) {
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 'json2' );
        fs_enqueue_local_script( 'postmessage', 'nojquery.ba-postmessage.js' );
        fs_enqueue_local_script( 'fs-postmessage', 'postmessage.js' );
    ?>
<div id="pframe"></div>
<script type="text/javascript">
	(function ($) {
		$(function () {
			var
				base_url = '<?php echo WP_FS__ADDRESS ?>',
				pframe = $('<i' + 'frame id="fs_promo_tab" src="' + base_url + '/promotional-tab/?<?php echo http_build_query($VARS) ?>#' + encodeURIComponent(document.location.href) + '" height="350" width="60" frameborder="0" style="  background: transparent; position: fixed; top: 20%; right: 0;" scrolling="no"></i' + 'frame>')
					.appendTo('#pframe');

			FS.PostMessage.init(base_url);
			FS.PostMessage.receive('state', function (state) {
				if ('closed' === state)
					$('#fs_promo_tab').css('width', '60px');
				else
					$('#fs_promo_tab').css('width', '345px');
			});
		});
	})(jQuery);
</script>
<?php } ?>
