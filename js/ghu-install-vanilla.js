/**
 * Javascript to show and hide the API specific settings
 * for the remote install feature.
 *
 * @class  Fragen\GitHub_Updater\Install
 * @since  4.6.0
 * @access public
 *
 * Was working, then stopped working.
 * Seems not to get all 3 bitbucket_setting elements.
 */
(function () {

	// Hide non-default (Bitbucket & GitLab) settings on page load.
	var nonDefault = ['bitbucket', 'gitlab', 'gitea'];

	nonDefault.forEach(function (item) {
		var parents = getParents(item, 'tr');
		parents[0].style.display = 'none';
	});

	// When the api selector changes.
	var selects = document.querySelector('select[ name="github_updater_api" ]');

	selects.addEventListener('change', function () {
		var defaults = ['github', 'bitbucket', 'gitlab', 'gitea'];

		// Create difference array.
		var hideMe = remove(defaults, this.value);

		// Hide items with unselected api's classes.
		hideMe.forEach(function (item) {
			var parents = getParents(item, 'tr');
			parents[0].style.display = 'none';
		});

		// Show selected setting.
		[this.value].forEach(function (item) {
			var parents = getParents(item, 'tr');
			parents[0].style.display = '';
		});
	});

	// Remove selected element from array and return array.
	function remove(array, element) {
		const index = array.indexOf(element);
		if (index !== -1) {
			array.splice(index, 1);
		}

		return array;
	}

	// Return query and selector for `$(query).parents.(selector)`
	function getParents(item, selector) {
		return vanillaParents(document.querySelector('input.'.concat(item, '_setting')), selector);
	}

	// Vanilla JS version of jQuery `$(query).parents(selector)`
	function vanillaParents(element, selector) {
		var parents = [];
		while (element = element.parentElement.closest(selector))
			parents.push(element);

		return parents;
	}

})();
