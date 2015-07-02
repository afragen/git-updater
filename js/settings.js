jQuery(document).ready( function($) {
	// Hide non-default (Bitbucket) settings on page load
	$( 'input.bitbucket_setting' ).parents( 'tr' ).hide();

	// When the api selector changes
	$( 'select[name="github_updater_api"]' ).on( 'change', function() {

		// Find all input-like elements in the settings form table
		var inputs = $( '.form-table :input' );

		// Show/hide all settings that have the selected api's class
		// this.value equals either 'github' or 'bitbucket'
		inputs.find( '.' + ( 'github' === this.value ? 'bitbucket' : 'github' ) + '_setting' ).parents( 'tr' ).hide();
		inputs.find( '.' + this.value + '_setting' ).parents( 'tr' ).show();
	});
});
