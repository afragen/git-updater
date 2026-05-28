<?php
/**
 * Shim: define the FAIR namespace function so Bootstrap::check_update_api_redirect()
 * can exercise its true branch in tests.
 */
namespace Fair\Default_Repo;

if ( ! function_exists( 'Fair\Default_Repo\get_default_repo_domain' ) ) {
	function get_default_repo_domain(): string {
		return 'https://packages.fair.io';
	}
}
