/**
 * Javascript to show and hide the API specific settings
 * for the remote install feature.
 *
 * @class  Fragen\GitHub_Updater\Install
 * @since  4.6.0
 * @access public
 */
jQuery( document ).ready( function( $ ) {
	// Hide non-default (Bitbucket & GitLab) settings on page load
	$.each( [ 'bitbucket', 'gitlab' ], function() {
		$( 'input.'.concat( this, '_setting') ).parents( 'tr').hide();
	});

	// When the api selector changes
	$( 'select[ name="github_updater_api" ]' ).on( 'change', function() {

		// create difference array
		var hideMe = $( [ 'github', 'bitbucket', 'gitlab'] ).not( [ this.value ] ).get();

		/*
		 * Show/hide all settings that have the selected api's class.
		 * this.value equals either 'github', 'bitbucket', or 'gitlab'.
		 */
		$.each( hideMe, function() {
			$( 'input.'.concat( this, '_setting' ) ).parents( 'tr' ).hide();
		});

		$( 'input.'.concat( this.value, '_setting' ) ).parents( 'tr' ).show();

	});
});
