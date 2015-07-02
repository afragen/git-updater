/**
 * Javascript to show and hide the API specific settings
 * for the remote install feature.
 *
 * @class  Fragen\GitHub_Updater\Install
 * @since  4.6.0
 * @access public
 */
jQuery(document).ready( function($) {
	// Hide non-default (Bitbucket & GitLab) settings on page load
	$( 'input.bitbucket_setting' ).parents( 'tr' ).hide();
	$( 'input.gitlab_setting' ).parents( 'tr' ).hide();

	// When the api selector changes
	$( 'select[name="github_updater_api"]' ).on( 'change', function() {

		/*
		 * Show/hide all settings that have the selected api's class.
		 * this.value equals either 'github', 'bitbucket', or 'gitlab'.
		 */
		if( 'github' === this.value ) {
			$( 'input.bitbucket_setting' ).parents( 'tr' ).hide();
			$( 'input.gitlab_setting' ).parents( 'tr' ).hide();
			$( 'input.github_setting' ).parents( 'tr' ).show();
		} else if ( 'bitbucket' === this.value ) {
			$( 'input.github_setting' ).parents( 'tr' ).hide();
			$( 'input.gitlab_setting' ).parents( 'tr' ).hide();
			$( 'input.bitbucket_setting' ).parents( 'tr' ).show();

		} else if ( 'gitlab' === this.value ) {
			$( 'input.bitbucket_setting' ).parents( 'tr').hide();
			$( 'input.github_setting' ).parents( 'tr' ).hide();
			$( 'input.gitlab_setting' ).parents( 'tr' ).show();
		}

	});
});
