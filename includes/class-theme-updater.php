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
class GitHub_Theme_Updater extends GitHub_Updater_GitHub_API {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array $config
	 */
	public function __construct() {

		// This MUST come before we get details about the plugins so the headers are correctly retrieved
		add_filter( 'extra_theme_headers', array( $this, 'add_theme_headers' ) );

		// Get details of GitHub-sourced themes
		$this->config = $this->get_theme_meta();
		if ( empty( $this->config ) ) return;

		foreach ( $this->config as $theme ) {
			$this->{$this->type} = $theme;
			$this->set_defaults();
			$this->get_remote_info( 'style.css' );
			$this->get_remote_tag();
			$this->{$this->type}->download_link = $this->construct_download_link();
		}

		$update = array( 'do-core-reinstall', 'do-core-upgrade' );
		if (  empty( $_GET['action'] ) || ! in_array( $_GET['action'], $update, true ) )
			add_filter( 'pre_set_site_transient_update_themes', array( $this, 'pre_set_site_transient_update_themes' ) );

		add_filter( 'upgrader_source_selection', array( $this, 'upgrader_source_selection' ), 10, 3 );
		add_action( 'http_request_args', array( $this, 'no_ssl_http_request_args' ) );
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
			if ( empty( $theme->uri ) ) continue;
			
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
