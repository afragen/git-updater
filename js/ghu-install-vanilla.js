/**
 * Vanilla Javascript to show and hide the API specific settings
 * for the remote install feature.
 *
 * @class  Fragen\GitHub_Updater\Install
 * @since  8.5.0
 * @access public
 * @package	github-updater
 */

(function () {

	// Hide non-default (Bitbucket & GitLab) settings on page load.
	let nonDefault = ['bitbucket', 'gitlab', 'gitea', 'zipfile'];

	nonDefault.forEach(function (item) {
		let parents = getParents(item, 'tr');
		displayNone(parents);
	});

	// When the api selector changes.
	let selects = document.querySelector('select[ name="github_updater_api" ]');

	// Only run when on proper tab.
	if (selects !== null) {
		selects.addEventListener('change', function () {
			let defaults = ['github', 'bitbucket', 'gitlab', 'gitea', 'zipfile'];

			// Create difference array.
			let hideMe = remove(defaults, this.value);

			// Hide items with unselected api's classes.
			hideMe.forEach(function (item) {
				let parents = getParents(item, 'tr');
				displayNone(parents);
			});

			// Show selected setting.
			[this.value].forEach(function (item) {
				let parents = getParents(item, 'tr');
				display(parents);
			});
		});
	}

	// Remove selected element from array and return array.
	function remove(array, element) {
		const index = array.indexOf(element);
		if (index !== -1) {
			array.splice(index, 1);
		}
		return array;
	}

	// Hide element.
	function displayNone(array) {
		array.forEach((item) => {
			item.style.display = 'none';
		});
	}

	// Display element.
	function display(array) {
		array.forEach((item) => {
			item.style.display = '';
		});
	}

	// Return query and selector for `$(query).parents.(selector)`.
	function getParents(item, selector) {
		return vanillaParents(document.querySelectorAll('input.'.concat(item, '_setting')), selector);
	}

	// Vanilla JS version of jQuery `$(query).parents(selector)`.
	function vanillaParents(element, selector) {
		let parents = [];
		if (NodeList.prototype.isPrototypeOf(element)) {
			element.forEach((item) => {
				element = item.parentElement.closest(selector);
				parents.push(element);
			});
		} else {
			element = item.parentElement.closest(selector);
			parents.push(element);
		}
		return parents;
	}

})();
