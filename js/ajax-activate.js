(function( $, wp ) {
	var $document     = $( document ),
		$pluginFilter = $( "#plugin-filter, #plugin-information-footer" ),
		__            = wp.i18n.__;
		_x            = wp.i18n._x,

		$document.off( 'click', '#plugin-information-footer .activate-now' );

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

	/**
	 * Pulls available jobs from the queue and runs them.
	 *
	 * @since 4.2.0
	 * @since 4.6.0 Can handle multiple job types.
	 */
	wp.updates.queueChecker = function() {
		var job;

		if ( wp.updates.ajaxLocked || ! wp.updates.queue.length ) {
			return;
		}

		job = wp.updates.queue.shift();

		// Handle a queue job.
		switch ( job.action ) {
			case 'install-plugin':
				wp.updates.installPlugin( job.data );
				break;

			case 'update-plugin':
				wp.updates.updatePlugin( job.data );
				break;

			case 'delete-plugin':
				wp.updates.deletePlugin( job.data );
				break;

			case 'install-theme':
				wp.updates.installTheme( job.data );
				break;

			case 'update-theme':
				wp.updates.updateTheme( job.data );
				break;

			case 'delete-theme':
				wp.updates.deleteTheme( job.data );
				break;

			case 'check_plugin_dependencies':
				wp.updates.checkPluginDependencies( job.data );
				break;

			default:
				break;
		}
	};
})( jQuery, window.wp, window._wpUpdatesSettings );
