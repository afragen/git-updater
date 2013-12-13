<?php
/**
 * GitHub Updater
 *
 * @package   GitHub_Updater
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 */

/**
 * Update a WordPress theme from a GitHub repo.
 *
 * @package   GitHub_Theme_Updater
 * @author    Andy Fragen
 * @author    Seth Carstens
 * @link      https://github.com/scarstens/Github-Theme-Updater
 * @author    UCF Web Communications
 * @link      https://github.com/UCF/Theme-Updater
 */
class GitHub_Theme_Updater extends GitHub_Updater {

	public function __construct() {

		// This MUST come before we get details about the plugins so the headers are correctly retrieved
		add_filter( 'extra_theme_headers', array( $this, 'add_theme_headers' ) );

		// Get details of GitHub-sourced themes
		$this->config = $this->get_themes_meta();
		if ( empty( $this->config ) ) return;

		foreach ( $this->config as $theme ) {
			$this->set_defaults( $theme );
			$this->get_remote_tag( $theme );
			$this->get_remote_info( $theme->uri );
		}

		if ( ! empty($_GET['action'] ) && ( $_GET['action'] == 'do-core-reinstall' || $_GET['action'] == 'do-core-upgrade') ); else {
			add_filter( 'pre_set_site_transient_update_themes', array( $this, 'pre_set_site_transient_update_themes' ) );
		}

		add_filter( 'upgrader_source_selection', array( $this, 'upgrader_source_selection' ), 10, 3 );
		add_action( 'http_request_args', array( $this, 'no_ssl_http_request_args' ) );
	}

	/**
	 * Set default values for theme
	 *
	 * @since 1.9.0
	 */
	protected function set_defaults( $theme ) {
		$theme->remote_version = '0.0.0'; //set default value
		$theme->newest_tag     = '0.0.0'; //set default value
	}

	/**
	 * Call the GitHub API and return a json decoded body.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url
	 * @see http://developer.github.com/v3/
	 * @return boolean|object
	 */
	protected function api( $url ) {

		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != '200' )
			return false;

		return json_decode( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Reads the remote theme file.
	 *
	 * Uses a transient to limit the calls to the API.
	 *
	 * @since 1.0.0
	 */
	protected function get_remote_info( $url ) {

		$remote = get_site_transient( md5( $url . 'theme' ) ) ;

		if ( ! $remote ) {
			$remote = $this->api( $url . '/style.css' );

			if ( $remote )
				set_site_transient( md5( $url . 'theme' ), $remote, HOUR_IN_SECONDS );
		}

		return $remote;
	}

	/**
	 * Parse the remote info to find most recent tag if tags exist
	 *
	 * Uses a transient to limit the calls to the API.
	 *
	 * @since 1.7.0
	 *
	 * @return string latest tag.
	 */
	protected function get_remote_tag( $theme ) {

		$response = get_site_transient( md5( $theme->repo . 'tags' ) );

		if ( ! $response ) {
			$response = $this->api( $theme->api . '/tags' );

			if ( $response )
				set_site_transient( md5( $theme->repo . 'tags' ), $response, HOUR_IN_SECONDS );
		}

		// Sort and get latest tag
		$tags = array();
		if ( false !== $response )
			foreach ( $response as $num => $tag ) {
				if ( isset( $tag->name ) ) $tags[] = $tag->name;
			}

		if ( empty( $tags ) ) return false;  // no tags are present, exit early

		usort( $tags, 'version_compare' );
		
		// check and generate download link
		$newest_tag     = null;
		$newest_tag_key = key( array_slice( $tags, -1, 1, true ) );
		$newest_tag     = $tags[ $newest_tag_key ];

		$theme->newest_tag    = $newest_tag;
		$theme->download_link =  $theme->uri . '/archive/' . $theme->newest_tag . '.zip';
		$theme->tags          = $tags;
	}

	/**
	 * Hook into pre_set_site_transient_update_themes to update from GitHub.
	 *
	 * Finds newest tag and compares to current tag
	 *
	 * @since 1.0.0
	 *
	 * @param array $data
	 * @return array|object
	 */
	public function pre_set_site_transient_update_themes( $data ){

		foreach ( $this->config as $theme ) {
			if ( empty( $theme->api ) ) continue;
			
			// setup update array to append version info
			$update = array();
			$update['new_version'] = $theme->newest_tag;
			$update['url']         = $theme->uri;
			$update['package']     = $theme->download_link;

			if ( version_compare( $theme->local_version,  $theme->newest_tag, '>=' ) ) {
				// up-to-date!
				$data->up_to_date[ $theme->repo ]['rollback'] = $theme->tags;
				$data->up_to_date[ $theme->repo ]['response'] = $update;
			} else {
				$data->response[ $theme->repo ] = $update;
			}
		}
		return $data;
	}

}
