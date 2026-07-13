/**
 * Vanilla Javascript to show and hide the API specific settings
 * for the remote install feature, with GitHub OAuth autocomplete support.
 *
 * @class  Fragen\GitHub_Updater\Install
 * @since  8.5.0
 * @access public
 * @package	git-updater
 */

(function () {

	// polyfill for NodeList.forEach browsers that supports ES5.
	if (window.NodeList && ! NodeList.prototype.forEach) {
		NodeList.prototype.forEach = function (callback, thisArg) {
			thisArg    = thisArg || window;
			var length = this.length;
			for (var i = 0; i < length; i++) {
				callback.call( thisArg, this[i], i, this );
			}
		};
	}

	// polyfill for Element.matches and Element.closest in IE.
	if ( ! Element.prototype.matches) {
		Element.prototype.matches = Element.prototype.msMatchesSelector ||
					Element.prototype.webkitMatchesSelector;
	}
	if ( ! Element.prototype.closest) {
		Element.prototype.closest = function(s) {
			var el = this;
			if ( ! document.documentElement.contains( el )) {
				return null;
			}
			do {
				if (el.matches( s )) {
					return el;
				}
				el = el.parentElement || el.parentNode;
			} while (el !== null && el.nodeType == 1);
			return null;
		};
	}

	// Hide non-default (Bitbucket & GitLab) settings on page load.
	let nonDefault = ['bitbucket', 'gitlab', 'gitea', 'zipfile', 'gist'];

	nonDefault.forEach(
		function (item) {
			let parents = getParents( item, 'tr' );
			displayNone( parents );
		}
	);

	// When the api selector changes.
	let selects = document.querySelector( 'select[ name="git_updater_api" ]' );

	// Only run when on proper tab.
	if (selects !== null) {
		selects.addEventListener(
			'change',
			function () {
				let defaults = ['github', 'bitbucket', 'gitlab', 'gitea', 'zipfile', 'gist'];

				// Create difference array.
				let hideMe = remove( defaults, this.value );

				// Hide items with unselected api's classes.
				hideMe.forEach(
					function (item) {
						let parents = getParents( item, 'tr' );
						displayNone( parents );
					}
				);

				// Show selected setting.
				[this.value].forEach(
					function (item) {
						let parents = getParents( item, 'tr' );
						display( parents );
					}
				);
			}
		);
	}

	// -------------------------------------------------------------------------
	// GitHub OAuth autocomplete for Plugin/Theme URI field
	// -------------------------------------------------------------------------

	var guData        = window.guInstallData || {};
	var githubOauth   = guData.github_oauth === '1';
	var githubUser    = guData.github_username || '';
	var githubOrgs    = Array.isArray( guData.github_orgs ) ? guData.github_orgs.map( function (o) { return o.toLowerCase(); } ) : [];
	var ajaxUrl       = guData.ajaxurl || '';
	var ajaxNonce     = guData.nonce || '';

	var repoInput  = document.getElementById( 'git_updater_repo' );
	var branchInput = document.getElementById( 'git_updater_branch' );

	if ( repoInput && githubOauth && ajaxUrl ) {
		setupRepoAutocomplete( repoInput );
		repoInput.addEventListener( 'change', onRepoInputChange );
		repoInput.addEventListener( 'blur', onRepoInputChange );
	}

	if ( branchInput && githubOauth && ajaxUrl ) {
		setupBranchAutocomplete( branchInput );
	}

	/**
	 * Wire up autocomplete on the repo URI input.
	 *
	 * @param {HTMLInputElement} input
	 */
	function setupRepoAutocomplete( input ) {
		var list = document.createElement( 'ul' );
		list.className = 'gu-autocomplete-list';
		list.setAttribute( 'role', 'listbox' );
		list.style.cssText = [
			'position:absolute',
			'background:#fff',
			'border:1px solid #8c8f94',
			'border-top:none',
			'border-radius:0 0 3px 3px',
			'list-style:none',
			'margin:0',
			'padding:0',
			'z-index:9999',
			'min-width:350px',
			'max-height:200px',
			'overflow-y:auto',
			'box-shadow:0 2px 6px rgba(0,0,0,.15)',
		].join( ';' );
		input.setAttribute( 'role', 'combobox' );
		input.setAttribute( 'aria-autocomplete', 'list' );
		input.setAttribute( 'aria-expanded', 'false' );
		input.setAttribute( 'aria-haspopup', 'listbox' );
		input.parentNode.style.position = 'relative';
		input.parentNode.insertBefore( list, input.nextSibling );

		var debounceTimer = null;
		var activeIndex   = -1;

		function closeList() {
			list.innerHTML = '';
			list.style.display = 'none';
			input.setAttribute( 'aria-expanded', 'false' );
			input.removeAttribute( 'aria-activedescendant' );
			activeIndex = -1;
		}

		function setActive( index ) {
			var items = list.querySelectorAll( 'li' );
			if ( ! items.length ) {
				return;
			}
			activeIndex = Math.max( -1, Math.min( index, items.length - 1 ) );
			items.forEach( function ( item, i ) {
				var on = i === activeIndex;
				item.style.background = on ? '#2271b1' : '';
				item.style.color      = on ? '#fff' : '';
				item.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			} );
			if ( activeIndex >= 0 ) {
				var activeId = 'gu-repo-option-' + activeIndex;
				items[ activeIndex ].id = activeId;
				input.setAttribute( 'aria-activedescendant', activeId );
				items[ activeIndex ].scrollIntoView( { block: 'nearest' } );
			} else {
				input.removeAttribute( 'aria-activedescendant' );
			}
		}

		input.addEventListener( 'input', function () {
			clearTimeout( debounceTimer );
			var query = this.value.trim();
			activeIndex = -1;
			showSpinner( list, input );
			debounceTimer = setTimeout(
				function () {
					fetchRepos(
						query,
						function ( repos ) {
							renderAutocomplete( list, repos, input );
						}
					);
				},
				250
			);
		} );

		input.addEventListener( 'keydown', function ( e ) {
			var items = list.querySelectorAll( 'li' );
			if ( ! items.length || list.style.display === 'none' ) {
				return;
			}
			if ( e.key === 'ArrowDown' ) {
				e.preventDefault();
				setActive( activeIndex + 1 );
			} else if ( e.key === 'ArrowUp' ) {
				e.preventDefault();
				setActive( activeIndex - 1 );
			} else if ( e.key === 'Enter' ) {
				if ( activeIndex >= 0 && items[ activeIndex ] && items[ activeIndex ]._guRepo ) {
					e.preventDefault();
					selectRepo( input, list, items[ activeIndex ]._guRepo );
					activeIndex = -1;
				}
			} else if ( e.key === 'Escape' ) {
				closeList();
			}
		} );

		// Close list on outside click.
		document.addEventListener( 'click', function ( e ) {
			if ( e.target !== input ) {
				closeList();
			}
		} );

		/**
		 * Render autocomplete dropdown items.
		 *
		 * @param {HTMLUListElement} listEl
		 * @param {Array}            repos
		 * @param {HTMLInputElement} inputEl
		 */
		function renderAutocomplete( listEl, repos, inputEl ) {
			listEl.innerHTML = '';
			activeIndex = -1;
			if ( ! repos.length ) {
				listEl.style.display = 'none';
				inputEl.setAttribute( 'aria-expanded', 'false' );
				return;
			}

			repos.forEach( function ( repo, i ) {
				var li = document.createElement( 'li' );
				li.setAttribute( 'role', 'option' );
				li.setAttribute( 'aria-selected', 'false' );
				li.id      = 'gu-repo-option-' + i;
				li._guRepo = repo;

				li.textContent = repo.full_name + ( repo.private ? ' 🔒' : '' );
				li.style.cssText = 'padding:6px 10px;cursor:pointer;font-size:13px;border-bottom:1px solid #f0f0f1;';

				li.addEventListener( 'mouseover', function () {
					if ( li !== list.querySelectorAll( 'li' )[ activeIndex ] ) {
						this.style.background = '#f6f7f7';
						this.style.color      = '';
					}
				} );
				li.addEventListener( 'mouseout', function () {
					if ( li !== list.querySelectorAll( 'li' )[ activeIndex ] ) {
						this.style.background = '';
					}
				} );
				li.addEventListener( 'mousedown', function ( e ) {
					e.preventDefault(); // prevent blur before click.
					selectRepo( inputEl, listEl, repo );
					activeIndex = -1;
				} );
				listEl.appendChild( li );
			} );

			listEl.style.display = 'block';
			inputEl.setAttribute( 'aria-expanded', 'true' );
		}
	}

	/**
	 * Fetch matching repos from the AJAX endpoint.
	 *
	 * @param {string}   query
	 * @param {Function} callback
	 */
	function fetchRepos( query, callback ) {
		var url = ajaxUrl + '?action=gu_github_repos&nonce=' + encodeURIComponent( ajaxNonce ) + '&q=' + encodeURIComponent( query );
		var xhr = new XMLHttpRequest();
		xhr.open( 'GET', url, true );
		xhr.onreadystatechange = function () {
			if ( xhr.readyState === 4 && xhr.status === 200 ) {
				try {
					var data = JSON.parse( xhr.responseText );
					if ( data.success && Array.isArray( data.data ) ) {
						callback( data.data );
					}
				} catch (e) {}
			}
		};
		xhr.send();
	}

	/**
	 * Fill the URI field and trigger downstream autofill when a repo is selected.
	 *
	 * @param {HTMLInputElement} input
	 * @param {HTMLUListElement} list
	 * @param {Object}           repo
	 */
	function selectRepo( input, list, repo ) {
		input.value = repo.html_url;
		list.innerHTML = '';
		list.style.display = 'none';
		input.setAttribute( 'aria-expanded', 'false' );
		input.removeAttribute( 'aria-activedescendant' );
		applyConnectedRepoState( repo.html_url, repo.default_branch );
	}

	/**
	 * Wire up branch name autocomplete on the branch input.
	 *
	 * @param {HTMLInputElement} input
	 */
	function setupBranchAutocomplete( input ) {
		var list = document.createElement( 'ul' );
		list.className = 'gu-autocomplete-list gu-branch-list';
		list.setAttribute( 'role', 'listbox' );
		list.style.cssText = [
			'position:absolute',
			'background:#fff',
			'border:1px solid #8c8f94',
			'border-top:none',
			'border-radius:0 0 3px 3px',
			'list-style:none',
			'margin:0',
			'padding:0',
			'z-index:9999',
			'min-width:220px',
			'max-height:180px',
			'overflow-y:auto',
			'box-shadow:0 2px 6px rgba(0,0,0,.15)',
		].join( ';' );
		input.setAttribute( 'role', 'combobox' );
		input.setAttribute( 'aria-autocomplete', 'list' );
		input.setAttribute( 'aria-expanded', 'false' );
		input.setAttribute( 'aria-haspopup', 'listbox' );
		input.parentNode.style.position = 'relative';
		input.parentNode.insertBefore( list, input.nextSibling );

		var debounceTimer = null;
		var activeIndex   = -1;

		function closeList() {
			list.innerHTML = '';
			list.style.display = 'none';
			input.setAttribute( 'aria-expanded', 'false' );
			input.removeAttribute( 'aria-activedescendant' );
			activeIndex = -1;
		}

		function setActive( index ) {
			var items = list.querySelectorAll( 'li' );
			if ( ! items.length ) {
				return;
			}
			activeIndex = Math.max( -1, Math.min( index, items.length - 1 ) );
			items.forEach( function ( item, i ) {
				var on = i === activeIndex;
				item.style.background = on ? '#2271b1' : '';
				item.style.color      = on ? '#fff' : '';
				item.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			} );
			if ( activeIndex >= 0 ) {
				var activeId = 'gu-branch-option-' + activeIndex;
				items[ activeIndex ].id = activeId;
				input.setAttribute( 'aria-activedescendant', activeId );
				items[ activeIndex ].scrollIntoView( { block: 'nearest' } );
			} else {
				input.removeAttribute( 'aria-activedescendant' );
			}
		}

		input.addEventListener( 'input', function () {
			clearTimeout( debounceTimer );
			var ownerRepo = extractOwnerRepo( repoInput ? repoInput.value : '' );
			if ( ! ownerRepo ) {
				closeList();
				return;
			}
			var query = this.value.trim();
			activeIndex = -1;
			showSpinner( list, input );
			debounceTimer = setTimeout(
				function () {
					fetchBranches(
						ownerRepo,
						function ( branches ) {
							renderBranchList( list, branches, query, input );
						}
					);
				},
				250
			);
		} );

		input.addEventListener( 'keydown', function ( e ) {
			var items = list.querySelectorAll( 'li' );
			if ( ! items.length || list.style.display === 'none' ) {
				return;
			}
			if ( e.key === 'ArrowDown' ) {
				e.preventDefault();
				setActive( activeIndex + 1 );
			} else if ( e.key === 'ArrowUp' ) {
				e.preventDefault();
				setActive( activeIndex - 1 );
			} else if ( e.key === 'Enter' ) {
				if ( activeIndex >= 0 && items[ activeIndex ] && items[ activeIndex ]._guBranch ) {
					e.preventDefault();
					input.value = items[ activeIndex ]._guBranch;
					closeList();
				}
			} else if ( e.key === 'Escape' ) {
				closeList();
			}
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( e.target !== input ) {
				closeList();
			}
		} );

		/**
		 * Render filtered branch list dropdown.
		 *
		 * @param {HTMLUListElement} listEl
		 * @param {string[]}         branches
		 * @param {string}           query
		 * @param {HTMLInputElement} inputEl
		 */
		function renderBranchList( listEl, branches, query, inputEl ) {
			listEl.innerHTML = '';
			activeIndex = -1;
			var filtered = query
				? branches.filter( function ( b ) { return b.toLowerCase().indexOf( query.toLowerCase() ) !== -1; } )
				: branches;

			if ( ! filtered.length ) {
				listEl.style.display = 'none';
				inputEl.setAttribute( 'aria-expanded', 'false' );
				return;
			}

			filtered.forEach( function ( branch, i ) {
				var li = document.createElement( 'li' );
				li.textContent = branch;
				li.setAttribute( 'role', 'option' );
				li.setAttribute( 'aria-selected', 'false' );
				li.id        = 'gu-branch-option-' + i;
				li._guBranch = branch;
				li.style.cssText = 'padding:5px 10px;cursor:pointer;font-size:13px;border-bottom:1px solid #f0f0f1;';
				li.addEventListener( 'mouseover', function () {
					if ( i !== activeIndex ) {
						this.style.background = '#f6f7f7';
						this.style.color      = '';
					}
				} );
				li.addEventListener( 'mouseout', function () {
					if ( i !== activeIndex ) {
						this.style.background = '';
					}
				} );
				li.addEventListener( 'mousedown', function ( e ) {
					e.preventDefault();
					inputEl.value = branch;
					closeList();
				} );
				listEl.appendChild( li );
			} );
			listEl.style.display = 'block';
			inputEl.setAttribute( 'aria-expanded', 'true' );
		}
	}

	/**
	 * Fetch branches for owner/repo from AJAX endpoint.
	 *
	 * @param {string}   ownerRepo
	 * @param {Function} callback
	 */
	function fetchBranches( ownerRepo, callback ) {
		var url = ajaxUrl + '?action=gu_github_branches&nonce=' + encodeURIComponent( ajaxNonce ) + '&repo=' + encodeURIComponent( ownerRepo );
		var xhr = new XMLHttpRequest();
		xhr.open( 'GET', url, true );
		xhr.onreadystatechange = function () {
			if ( xhr.readyState === 4 && xhr.status === 200 ) {
				try {
					var data = JSON.parse( xhr.responseText );
					if ( data.success && Array.isArray( data.data ) ) {
						callback( data.data );
					}
				} catch (e) {}
			}
		};
		xhr.send();
	}

	/**
	 * Handle repo URI field change: autofill branch + toggle connected-account UI state.
	 */
	function onRepoInputChange() {
		var uri = repoInput.value.trim();
		if ( ! uri ) {
			return;
		}
		applyConnectedRepoState( uri, null );
	}

	/**
	 * Given a URI, determine if it belongs to the connected account.
	 * If so: autofill the default branch (if not already set), select 'github'
	 * in the host dropdown, and hide the host + token fields.
	 *
	 * @param {string}      uri
	 * @param {string|null} knownDefaultBranch Pass when already known (e.g. from autocomplete selection).
	 */
	function applyConnectedRepoState( uri, knownDefaultBranch ) {
		var ownerRepo = extractOwnerRepo( uri );
		if ( ! ownerRepo ) {
			showHostAndTokenFields();
			return;
		}

		var owner = ownerRepo.split( '/' )[0];
		var isConnectedRepo = githubUser && (
			owner.toLowerCase() === githubUser.toLowerCase() ||
			githubOrgs.indexOf( owner.toLowerCase() ) !== -1
		);

		if ( ! isConnectedRepo ) {
			showHostAndTokenFields();
			return;
		}

		// Set the host dropdown to 'github' if present, triggering its change handler
		// to show/hide API-specific token fields for other hosts.
		var apiSelect = document.getElementById( 'git_updater_api' );
		if ( apiSelect ) {
			apiSelect.value = 'github';
			var evt = document.createEvent( 'HTMLEvents' );
			evt.initEvent( 'change', true, false );
			apiSelect.dispatchEvent( evt );
		}

		// Hide host/token fields AFTER the change event (which would re-show github rows).
		hideHostAndTokenFields();

		// Autofill branch from known value or fetch.
		if ( knownDefaultBranch !== null ) {
			maybeSetBranch( knownDefaultBranch );
		} else {
			fetchRepoInfo(
				ownerRepo,
				function ( info ) {
					maybeSetBranch( info.default_branch );
				}
			);
		}
	}

	/**
	 * Set branch field to defaultBranch if the field is empty or set to 'master'
	 * and defaultBranch differs from 'master'.
	 *
	 * @param {string} defaultBranch
	 */
	function maybeSetBranch( defaultBranch ) {
		if ( ! branchInput || ! defaultBranch ) {
			return;
		}
		var current = branchInput.value.trim();
		if ( ( current === '' || current === 'master' ) && defaultBranch !== 'master' ) {
			branchInput.value = defaultBranch;
		}
	}

	/**
	 * Fetch repo info (default_branch, etc.) from AJAX.
	 *
	 * @param {string}   ownerRepo
	 * @param {Function} callback
	 */
	function fetchRepoInfo( ownerRepo, callback ) {
		var url = ajaxUrl + '?action=gu_github_repo_info&nonce=' + encodeURIComponent( ajaxNonce ) + '&repo=' + encodeURIComponent( ownerRepo );
		var xhr = new XMLHttpRequest();
		xhr.open( 'GET', url, true );
		xhr.onreadystatechange = function () {
			if ( xhr.readyState === 4 && xhr.status === 200 ) {
				try {
					var data = JSON.parse( xhr.responseText );
					if ( data.success && data.data ) {
						callback( data.data );
					}
				} catch (e) {}
			}
		};
		xhr.send();
	}

	/**
	 * Extract "owner/repo" from a GitHub URI or bare "owner/repo" string.
	 *
	 * Handles:
	 *   https://github.com/owner/repo
	 *   https://github.com/owner/repo.git
	 *   owner/repo
	 *
	 * @param  {string} uri
	 * @return {string|null}
	 */
	function extractOwnerRepo( uri ) {
		if ( ! uri ) {
			return null;
		}
		uri = uri.trim().replace( /\.git$/, '' );

		// Full GitHub URL.
		var m = uri.match( /github\.com\/([^\/]+\/[^\/]+)/ );
		if ( m ) {
			return m[1];
		}

		// Bare owner/repo.
		if ( /^[^\/\s]+\/[^\/\s]+$/.test( uri ) ) {
			return uri;
		}

		return null;
	}

	/**
	 * Hide the Remote Repository Host and GitHub Access Token rows.
	 */
	function hideHostAndTokenFields() {
		setFieldRowVisibility( 'git_updater_api', 'none' );
		setFieldRowVisibility( 'github_access_token', 'none' );
	}

	/**
	 * Restore visibility of the Remote Repository Host and GitHub Access Token rows.
	 */
	function showHostAndTokenFields() {
		setFieldRowVisibility( 'git_updater_api', '' );
		setFieldRowVisibility( 'github_access_token', '' );
	}

	/**
	 * Set the display of the <tr> that wraps a given field ID.
	 *
	 * @param {string} fieldId
	 * @param {string} display CSS display value.
	 */
	function setFieldRowVisibility( fieldId, display ) {
		var el = document.getElementById( fieldId );
		if ( ! el ) {
			return;
		}
		var row = el.closest( 'tr' );
		if ( row ) {
			row.style.display = display;
		}
	}

	// -------------------------------------------------------------------------
	// Utility helpers (preserved from original)
	// -------------------------------------------------------------------------

	/**
	 * Show a loading spinner inside an autocomplete list.
	 *
	 * @param {HTMLUListElement} listEl
	 * @param {HTMLInputElement} inputEl
	 */
	function showSpinner( listEl, inputEl ) {
		listEl.innerHTML = '';
		var li = document.createElement( 'li' );
		li.setAttribute( 'aria-live', 'polite' );
		li.style.cssText = 'padding:8px 10px;font-size:13px;color:#646970;display:flex;align-items:center;gap:8px;';

		var spinner = document.createElement( 'span' );
		spinner.setAttribute( 'aria-hidden', 'true' );
		spinner.style.cssText = [
			'display:inline-block',
			'width:14px',
			'height:14px',
			'border:2px solid #c3c4c7',
			'border-top-color:#2271b1',
			'border-radius:50%',
			'animation:gu-spin .6s linear infinite',
			'flex-shrink:0',
		].join( ';' );

		// Inject keyframes once.
		if ( ! document.getElementById( 'gu-spin-style' ) ) {
			var style = document.createElement( 'style' );
			style.id = 'gu-spin-style';
			style.textContent = '@keyframes gu-spin{to{transform:rotate(360deg)}}';
			document.head.appendChild( style );
		}

		li.appendChild( spinner );
		li.appendChild( document.createTextNode( 'Searching\u2026' ) );
		listEl.appendChild( li );
		listEl.style.display = 'block';
		inputEl.setAttribute( 'aria-expanded', 'true' );
	}

	// Remove selected element from array and return array.
	function remove(array, element) {
		const index = array.indexOf( element );
		if (index !== -1) {
			array.splice( index, 1 );
		}
		return array;
	}

	// Hide element.
	function displayNone(array) {
		array.forEach(
			function (item) {
				item.style.display = 'none';
			}
		);
	}

	// Display element.
	function display(array) {
		array.forEach(
			function (item) {
				item.style.display = '';
			}
		);
	}

	// Return query and selector for `$(query).parents.(selector)`.
	function getParents(item, selector) {
		return vanillaParents( document.querySelectorAll( 'input.'.concat( item, '_setting' ) ), selector );
	}

	// Vanilla JS version of jQuery `$(query).parents(selector)`.
	function vanillaParents(element, selector) {
		let parents = [];
		if (NodeList.prototype.isPrototypeOf( element )) {
			element.forEach(
				function (item) {
					element = item.parentElement.closest( selector );
					parents.push( element );
				}
			);
		} else {
			element = item.parentElement.closest( selector );
			parents.push( element );
		}
		return parents;
	}

})();
