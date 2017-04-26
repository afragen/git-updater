<?php
/**
 * GitHub Updater
 *
 * @package   Fragen\GitHub_Updater
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 */

namespace Fragen\GitHub_Updater;


/**
 * Interface API_Interface
 *
 * @package Fragen\GitHub_Updater
 */
interface API_Interface {

	/**
	 * Read the remote file and parse headers.
	 *
	 * @param string $file Filename.
	 *
	 * @return mixed
	 */
	public function get_remote_info( $file );

	/**
	 * Get remote info for tags.
	 *
	 * @return mixed
	 */
	public function get_remote_tag();

	/**
	 * Read the remote CHANGES.md file.
	 *
	 * @param string $changes Changelog filename.
	 *
	 * @return mixed
	 */
	public function get_remote_changes( $changes );

	/**
	 * Read and parse remote readme.txt.
	 *
	 * @return mixed
	 */
	public function get_remote_readme();

	/**
	 * Read the repository meta from API.
	 *
	 * @return mixed
	 */
	public function get_repo_meta();

	/**
	 * Create array of branches and download links as array.
	 *
	 * @return bool
	 */
	public function get_remote_branches();

	/**
	 * Construct $this->type->download_link using Repository Contents API.
	 *
	 * @param bool $rollback      For theme rollback.
	 * @param bool $branch_switch For direct branch switching.
	 *
	 * @return string URL for download link.
	 */
	public function construct_download_link( $rollback = false, $branch_switch = false );

	/**
	 * Create endpoints.
	 *
	 * @param object $git
	 * @param string $endpoint
	 *
	 * @return string $endpoint
	 */
	public function add_endpoints( $git, $endpoint );

	/**
	 * Parse API response call and return only array of tag numbers.
	 *
	 * @param object $response API response.
	 *
	 * @return array|object Array of tag numbers, object is error.
	 */
	public function parse_tag_response( $response );

	/**
	 * Parse API response and return array of meta variables.
	 *
	 * @param object $response API response.
	 *
	 * @return mixed Array of meta variables.
	 */
	public function parse_meta_response( $response );

	/**
	 * Parse API response and return array with changelog.
	 *
	 * @param object $response API response.
	 *
	 * @return array|object $arr Array of changes in base64, object if error.
	 */
	public function parse_changelog_response( $response );

}
