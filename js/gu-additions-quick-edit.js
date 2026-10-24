/**
 * Vanilla Javascript to handle Quick Edit on the Addition Packages list table.
 *
 * @package git-updater
 */

/* global guAdditionsQuickEdit */
(function () {
	const settings = window.guAdditionsQuickEdit || {};
	const fields   = [ 'type', 'slug', 'uri', 'primary_branch', 'release_asset', 'private_package', 'uses_lite' ];

	/**
	 * Close any open editor and restore the row it replaced.
	 *
	 * @param {boolean} focus Whether to return focus to the row's Quick Edit button.
	 */
	function closeEditor( focus ) {
		const editor = document.querySelector( 'tr.gu-addition-editor' );
		if ( ! editor ) {
			return;
		}
		const row = document.getElementById( editor.dataset.rowId );
		editor.remove();
		if ( row ) {
			row.style.display = '';
			const button = row.querySelector( '.gu-quick-edit' );
			if ( button ) {
				button.setAttribute( 'aria-expanded', 'false' );
				if ( focus ) {
					button.focus();
				}
			}
		}
	}

	/**
	 * Replace a row with a populated copy of the Quick Edit template.
	 *
	 * @param {HTMLTableRowElement} row The list table row to edit.
	 */
	function openEditor( row ) {
		const template = document.getElementById( 'gu-addition-inline-edit' );
		if ( ! template ) {
			return;
		}
		closeEditor( false );

		const data   = JSON.parse( row.dataset.guAddition || '{}' );
		const editor = template.cloneNode( true );
		editor.removeAttribute( 'id' );
		editor.classList.add( 'gu-addition-editor' );
		editor.dataset.rowId      = row.id;
		editor.dataset.originalId = data.ID || '';

		fields.forEach( function ( field ) {
			const input = editor.querySelector( '[data-field="' + field + '"]' );
			if ( ! input ) {
				return;
			}
			if ( 'checkbox' === input.type ) {
				input.checked = !! data[ field ];
				return;
			}
			const value = data[ field ] || '';
			// Keep a saved type selectable even if it is no longer registered.
			if ( 'SELECT' === input.tagName && value && ! input.querySelector( 'option[value="' + CSS.escape( value ) + '"]' ) ) {
				input.add( new Option( value, value ) );
			}
			input.value = value;
		} );

		row.style.display = 'none';
		row.after( editor );
		editor.style.display = '';
		row.querySelector( '.gu-quick-edit' ).setAttribute( 'aria-expanded', 'true' );
		editor.querySelector( '[data-field="slug"]' ).focus();
	}

	/**
	 * Show an error message inside the editor.
	 *
	 * @param {HTMLTableRowElement} editor  The open editor row.
	 * @param {string}              message The message to display.
	 */
	function showError( editor, message ) {
		const notice = editor.querySelector( '.notice-error' );
		notice.querySelector( '.error' ).textContent = message;
		notice.classList.remove( 'hidden' );
	}

	/**
	 * Save the editor's values and swap in the re-rendered row.
	 *
	 * @param {HTMLTableRowElement} editor The open editor row.
	 */
	function save( editor ) {
		const spinner = editor.querySelector( '.spinner' );
		const button  = editor.querySelector( '.save' );
		if ( button.disabled ) {
			return;
		}

		const body = new FormData();
		body.append( 'action', 'gu_additions_quick_edit' );
		body.append( '_ajax_nonce', settings.nonce );
		body.append( 'original_id', editor.dataset.originalId );
		fields.forEach( function ( field ) {
			const input = editor.querySelector( '[data-field="' + field + '"]' );
			if ( ! input ) {
				return;
			}
			if ( 'checkbox' === input.type ) {
				if ( input.checked ) {
					body.append( 'git_updater_additions[' + field + ']', '1' );
				}
				return;
			}
			body.append( 'git_updater_additions[' + field + ']', input.value );
		} );

		editor.querySelector( '.notice-error' ).classList.add( 'hidden' );
		spinner.classList.add( 'is-active' );
		button.disabled = true;

		fetch( settings.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( response ) {
				if ( ! response || ! response.success ) {
					showError( editor, ( response && response.data && response.data.message ) || settings.error );
					return;
				}
				const template = document.createElement( 'template' );
				template.innerHTML = response.data.row.trim();
				const newRow = template.content.firstElementChild;
				const oldRow = document.getElementById( editor.dataset.rowId );
				editor.remove();
				if ( oldRow ) {
					oldRow.replaceWith( newRow );
				}
				const quickEdit = newRow.querySelector( '.gu-quick-edit' );
				if ( quickEdit ) {
					quickEdit.focus();
				}
			} )
			.catch( function () {
				showError( editor, settings.error );
			} )
			.finally( function () {
				spinner.classList.remove( 'is-active' );
				button.disabled = false;
			} );
	}

	document.addEventListener(
		'click',
		function ( event ) {
			const quickEdit = event.target.closest( '.gu-quick-edit' );
			if ( quickEdit ) {
				event.preventDefault();
				openEditor( quickEdit.closest( 'tr' ) );
				return;
			}

			const editor = event.target.closest( 'tr.gu-addition-editor' );
			if ( ! editor ) {
				return;
			}
			if ( event.target.closest( '.cancel' ) ) {
				event.preventDefault();
				closeEditor( true );
			} else if ( event.target.closest( '.save' ) ) {
				event.preventDefault();
				save( editor );
			}
		}
	);

	document.addEventListener(
		'keydown',
		function ( event ) {
			const editor = event.target.closest && event.target.closest( 'tr.gu-addition-editor' );
			if ( ! editor ) {
				return;
			}
			if ( 'Escape' === event.key ) {
				event.preventDefault();
				closeEditor( true );
			} else if ( 'Enter' === event.key && 'INPUT' === event.target.tagName ) {
				// Keep Enter from submitting the surrounding list table form.
				event.preventDefault();
				save( editor );
			}
		}
	);
} )();
