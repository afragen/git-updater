/**
 * Vanilla Javascript to handle the repo-cache flush button on the
 * Git Updater Settings page.
 *
 * @package git-updater
 */

(function () {
	document.addEventListener(
		'click',
		function ( event ) {
			const button = event.target.closest( '.gu-flush-repo' );
			if ( ! button ) {
				return;
			}
			event.preventDefault();

			const url = button.getAttribute( 'data-flush-url' );
			if ( ! url ) {
				return;
			}

			button.classList.add( 'gu-flush-disabled' );
			fetch( url, { credentials: 'same-origin' } )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( data ) {
					if ( data && data.success ) {
						button.title = 'Cache flushed';
						// Reveal the broken indicator now that the cache was cleared
						// and the repo will need to re-fetch its remote version.
						const broken = button.parentNode.querySelector( '.gu-repo-broken' );
						if ( broken ) {
							broken.style.display = '';
						}
					} else {
						button.title = 'Flush failed';
					}
				} )
				.catch( function () {
					button.title = 'Flush failed';
				} )
				.finally( function () {
					button.classList.remove( 'gu-flush-disabled' );
				} );
		}
	);
} )();
