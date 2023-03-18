/**
 * Vanilla Javascript to show and hide the API specific settings
 * for the remote install feature.
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

				console.log( 'selected', this.value );
				console.log( 'hideMe', hideMe );
			}
		);
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
