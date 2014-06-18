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
 * Update a WordPress plugin from a GitHub repo.
 *
 * @package GitHub_Plugin_Updater
 * @author  Andy Fragen
 * @author  Codepress
 * @link    https://github.com/codepress/github-plugin-updater
 */
class GitHub_Plugin_Updater extends GitHub_Updater {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 */
	public function __construct() {

		// This MUST come before we get details about the plugins so the headers are correctly retrieved
		add_filter( 'extra_plugin_headers', array( $this, 'add_plugin_headers' ) );

		// Get details of GitHub-sourced plugins
		$this->config = $this->get_plugin_meta();
		
		if ( empty( $this->config ) ) { return false; }
		if ( isset( $_GET['force-check'] ) && '1' === $_GET['force-check'] ) { $this->delete_all_transients( 'plugins' ); }

		foreach ( (array) $this->config as $plugin ) {
			switch( $plugin->type ) {
				case 'github_plugin':
					$repo_api = new GitHub_Updater_GitHub_API( $plugin );
					break;
				case 'bitbucket_plugin':
					$repo_api = new GitHub_Updater_BitBucket_API( $plugin );
					break;
			}

			$this->{$plugin->type} = $plugin;
			$this->set_defaults( $plugin->type );

			if ( $repo_api->get_remote_info( basename( $plugin->slug ) ) ) {
				$repo_api->get_repo_meta();
				$repo_api->get_remote_tag();
				$repo_api->get_remote_changes( 'CHANGES.md' );
				$plugin->download_link = $repo_api->construct_download_link();
			}
		}

		$this->make_force_check_transient( 'plugins' );

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'pre_set_site_transient_update_plugins' ) );
		add_filter( 'plugins_api', array( $this, 'plugins_api' ), 99, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'upgrader_source_selection' ), 10, 3 );
		add_filter( 'http_request_args', array( $this, 'no_ssl_http_request_args' ), 10, 2 );
	}

	/**
	 * Put changelog in plugins_api, return WP.org data as appropriate
	 *
	 * @since 2.0.0
	 * @param $false
	 * @param $action
	 * @param $response
	 *
	 * @return mixed
	 */
	public function plugins_api( $false, $action, $response ) {
		if ( ! ( 'plugin_information' === $action ) ) { return $false; }

		$wp_repo_data = get_site_transient( 'ghu-' . md5( $response->slug . 'wporg' ) );
		if ( ! $wp_repo_data ) {
			$wp_repo_data = wp_remote_get( 'http://api.wordpress.org/plugins/info/1.0/' . $response->slug );
			if ( is_wp_error( $wp_repo_data ) ) { return false; }
			set_site_transient( 'ghu-' . md5( $response->slug . 'wporg' ), $wp_repo_data, ( 12 * HOUR_IN_SECONDS ) );
		}

		if ( ! empty( $wp_repo_data['body'] ) ) {
			$wp_repo_body = unserialize( $wp_repo_data['body'] );
			if ( is_object( $wp_repo_body ) ) {
				$response = $wp_repo_body;
			}
		}

		foreach ( (array) $this->config as $plugin ) {
			if ( $response->slug === $plugin->repo ) {
				if ( is_object( $wp_repo_body ) && 'master' === $plugin->branch ) {
					return $response;
				}
				$response->slug          = $plugin->repo;
				$response->plugin_name   = $plugin->name;
				$response->name          = $plugin->name;
				$response->author        = $plugin->author;
				$response->homepage      = $plugin->uri;
				$response->version       = $plugin->remote_version;
				$response->sections      = $plugin->sections;
				$response->requires      = $plugin->requires;
				$response->tested        = $plugin->tested;
				$response->downloaded    = $plugin->downloaded;
				$response->last_updated  = $plugin->last_updated;
				$response->rating        = $plugin->rating;
				$response->num_ratings   = $plugin->num_ratings;
				$response->download_link = $plugin->download_link;
			}
		}
		return $response;
	}

	/**
	 * Hook into pre_set_site_transient_update_plugins to update from GitHub.
	 *
	 * @since 1.0.0
	 *
	 * @param $transient
	 *
	 * @return mixed
	 */
	public function pre_set_site_transient_update_plugins( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		foreach ( (array) $this->config as $plugin ) {
			$remote_is_newer = ( 1 === version_compare( $plugin->remote_version, $plugin->local_version ) );

			if ( $remote_is_newer ) {
				$response = array(
					'slug'        => dirname( $plugin->slug ),
					'new_version' => $plugin->remote_version,
					'url'         => $plugin->uri,
					'package'     => $plugin->download_link,
				);

				// if branch is 'master' and plugin is in wp.org repo then pull update from wp.org
				if ( isset( $transient->response[ $plugin->slug]->id ) && 'master' === $plugin->branch ) { continue; }

				$transient->response[ $plugin->slug ] = (object) $response;
			}
		}
		return $transient;
	}

}
