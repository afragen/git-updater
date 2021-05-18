<?php
	/**
	 * Add "&trial=true" to pricing menu item href when running in trial
	 * promotion context.
	 *
	 * @package     Freemius
	 * @copyright   Copyright (c) 2016, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.2.1.5
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * @var Freemius $fs
	 */
	$fs = freemius( $VARS['id'] );
?>
<script type="text/javascript">
	(function ($) {
		$(document).ready(function () {
			var $pricingMenu = $('.fs-submenu-item.<?php echo $fs->get_unique_affix() ?>.pricing'),
				$pricingMenuLink = $pricingMenu.parents('a');

			// Add trial querystring param.
			$pricingMenuLink.attr('href', $pricingMenuLink.attr('href') + '&trial=true');
		});
	})(jQuery);
</script>