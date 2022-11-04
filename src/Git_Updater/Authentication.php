<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  MIT
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

namespace Fragen\Git_Updater;

use Fragen\Git_Updater\API\API;

/**
 * Class Authentication
 */
class Authentication extends API {

	/**
	 * Let's get going.
	 */
	public function run() {
		$this->add_settings_subtab();
		$this->settings_hook( $this );
	}

	/**
	 * Add subtab to Settings page.
	 */
	private function add_settings_subtab() {
		add_filter(
			'gu_add_settings_subtabs',
			function ( $subtabs ) {
				return array_merge( [ 'authentication' => esc_html__( 'Authentication', 'git-updater' ) ], $subtabs );
			},
			5,
			1
		);
	}

	/**
	 * Add settings for Bitbucket Username and Password.
	 *
	 * @param array $auth_required Array of authorization data.
	 *
	 * @return void
	 */
	public function add_settings( $auth_required ) {
		add_settings_section(
			'authentication',
			esc_html__( 'Authentication for Git Hosts', 'git-updater' ),
			[ $this, 'print_section_authentication' ],
			'git_updater_authentication_install_settings'
		);
	}

	/**
	 * Print Authentication section
	 *
	 * @return void
	 */
	public function print_section_authentication() {
		esc_html_e( 'An active license is required to set authentication tokens.', 'git-updater' );
		echo '<br><br><br>';
	}
}
