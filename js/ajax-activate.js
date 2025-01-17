(function( $ ) {
	var $pluginFilter = $( "#plugin-filter, #plugin-information-footer" ),
		__            = wp.i18n.__;
		_x            = wp.i18n._x,

		$pluginFilter.off( "click" );
		$pluginFilter.on( "click", ".activate-now", function( event ) {
			event.preventDefault();
			var $activateButton = $( event.target );

			if ( $activateButton.hasClass( "activating-message" ) || $activateButton.hasClass( "button-disabled" ) ) {
				return;
			}

			$activateButton
				.removeClass( "activate-now button-primary" )
				.addClass( "activating-message" )
				.attr(
					"aria-label",
					sprintf(
						/* translators: %s: Plugin name. */
						$activateButton.data( "name" ),
						_x( "Activating %s", "plugin" ),
			)
		)
		.text( __( "Activating..." ) );

		wp.updates.activatePlugin(
			{
				name: $activateButton.data( "name" ),
				slug: $activateButton.data( "slug" ),
				plugin: $activateButton.data( "plugin" )
			}
		);
	});
})( jQuery, window.wp, window._wpUpdatesSettings );
