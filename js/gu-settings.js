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

			const slug = button.getAttribute( 'data-slug' );
			if ( ! slug || ! window.gitUpdaterSettings ) {
				return;
			}

			button.classList.add( 'gu-flush-disabled' );

			const formData = new FormData();
			formData.append( 'action', 'git_updater_flush_repo_cache' );
			formData.append( '_ajax_nonce', window.gitUpdaterSettings.flushNonce );
			formData.append( 'slug', slug );

			fetch( window.gitUpdaterSettings.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: formData,
			} )
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
