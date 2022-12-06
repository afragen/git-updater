<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.2.2.7
	 */
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}
?>
<script type="text/javascript">
	(function ($) {
		if ($.fn.contentChange)
			return;

		/**
		 * Content change event listener.
		 *
		 * @url http://stackoverflow.com/questions/3233991/jquery-watch-div/3234646#3234646
		 *
		 * @param {function} callback
		 *
		 * @returns {object[]}
		 */
		$.fn.contentChange = function (callback) {
			var elements = $(this);

			elements.each(function () {
				var element = $(this);

				element.data("lastContents", element.html());

				window.watchContentChange = window.watchContentChange ?
					window.watchContentChange :
					[];

				window.watchContentChange.push({
					"element" : element,
					"callback": callback
				});
			});

			return elements;
		};

        setInterval(function() {
            if ( window.watchContentChange ) {
                for ( var i in window.watchContentChange ) {
                    if ( window.watchContentChange[ i ].element.data( 'lastContents' ) !== window.watchContentChange[ i ].element.html() ) {
                        window.watchContentChange[ i ].callback.apply( undefined, [ false ] );
                        window.watchContentChange[ i ].element.data( 'lastContents', window.watchContentChange[ i ].element.html() )
                    }
                }
            }
        }, 500 );
	})(jQuery);
</script>