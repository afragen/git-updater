<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  GPL-3.0-or-later
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

namespace Fragen\Git_Updater\API;

use stdClass;

/**
 * Interface API_Interface
 */
interface API_Interface {
	/**
	 * Read the remote file and parse headers.
	 *
	 * @access public
	 *
	 * @param string $file Filename.
	 *
	 * @return mixed
	 */
	public function get_remote_info( $file );

	/**
	 * Get remote info for tags.
	 *
	 * @access public
	 *
	 * @return mixed
	 */
	public function get_remote_tag();

	/**
	 * Read the remote CHANGES.md file.
	 *
	 * @access public
	 *
	 * @param string $changes Changelog filename - (deprecated).
	 *
	 * @return mixed
	 */
	public function get_remote_changes( $changes );

	/**
	 * Read and parse remote readme.txt.
	 *
	 * @access public
	 *
	 * @return mixed
	 */
	public function get_remote_readme();

	/**
	 * Read the repository meta from API.
	 *
	 * @access public
	 *
	 * @return mixed
	 */
	public function get_repo_meta();

	/**
	 * Create array of branches and download links as array.
	 *
	 * @access public
	 *
	 * @return bool
	 */
	public function get_remote_branches();

	/**
	 * Get release asset URL.
	 *
	 * @return string|bool
	 */
	public function get_release_asset();

	/**
	 * Construct $this->type->download_link using Repository Contents API.
	 *
	 * @access public
	 *
	 * @param bool $branch_switch For direct branch switching. Defaults to false.
	 *
	 * @return string URL for download link.
	 */
	public function construct_download_link( $branch_switch = false );

	/**
	 * Create endpoints.
	 *
	 * @access public
	 *
	 * @param GitHub_API $git      Git host specific API.
	 * @param string     $endpoint Endpoint.
	 *
	 * @return string $endpoint
	 */
	public function add_endpoints( $git, $endpoint );

	/**
	 * Parse API response call and return only array of tag numbers.
	 *
	 * @access public
	 *
	 * @param stdClass|array<string, mixed> $response API response.
	 *
	 * @return list<string>|stdClass
	 */
	public function parse_tag_response( $response );

	/**
	 * Parse API response and return array of meta variables.
	 *
	 * @access public
	 *
	 * @param stdClass|array<string, mixed> $response API response.
	 *
	 * @return array<string, mixed>|stdClass
	 */
	public function parse_meta_response( $response );

	/**
	 * Parse API response and return array with changelog.
	 *
	 * @access public
	 *
	 * @param stdClass|array<string, mixed> $response API response.
	 *
	 * @return array<string, string>|stdClass
	 */
	public function parse_changelog_response( $response );

	/**
	 * Parse API response and return array of branch data.
	 *
	 * @access public
	 *
	 * @param stdClass $response API response.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function parse_branch_response( $response );

	/**
	 * Add values for individual repo add_setting_field().
	 *
	 * @return array<string, mixed>
	 */
	public function add_repo_setting_field();

	/**
	 * Add settings for each API.
	 *
	 * @param array<string, bool> $auth_required Array of what needs authentication.
	 *
	 * @return void
	 */
	public function add_settings( $auth_required );

	/**
	 * Add remote install settings fields.
	 *
	 * @param string $type plugin|theme.
	 * @return void
	 */
	public function add_install_settings_fields( $type );

	/**
	 *  Add remote install feature, create endpoint.
	 *
	 * @param array<string, string> $headers Array of headers.
	 * @param array<string, mixed>  $install Array of install data.
	 *
	 * @return array<string, mixed>
	 */
	public function remote_install( $headers, $install );
}
