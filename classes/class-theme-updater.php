<?php

class GitHub_Theme_Updater {

	protected $config;

	public function __construct() {
		add_filter( 'extra_theme_headers', array( $this, 'add_headers') );
		$this->get_github_themes();

		if( !empty($_GET['action'] ) && ( $_GET['action'] == 'do-core-reinstall' || $_GET['action'] == 'do-core-upgrade') ); else {
			add_filter( 'site_transient_update_themes', array( $this, 'transient_update_themes_filter') );
		}

		add_filter( 'upgrader_source_selection', array( $this, 'upgrader_source_selection_filter' ), 10, 3 );
		add_action( 'http_request_args', array( $this, 'no_ssl_http_request_args' ), 10, 2 );
	}

	/**
	 * Add GitHub headers to wp_get_theme
	 *
	 * @since 1.0
	 * @return array
	 */
	public function add_headers( $extra_headers ) {
		$extra_headers = array( 'GitHub Theme URI' );
		return $extra_headers;
	}

	/**
	 * Reads in headers of every theme's style.css to get version info.
	 * Populates variable array
	 *
	 * @since 1.0
	 */
	private function get_github_themes() {

		$this->config = array();
		$themes = wp_get_themes();

		foreach ( $themes as $theme ) {
			//regex for standard URI, only special character '-'
			$github_header_regex = '#s[\:0-9]+\"(GitHub Theme URI)\";s[\:0-9]+\"([a-z0-9_\:\/\.-]+)#i';
			$serialized_theme = serialize( $theme );
			preg_match( $github_header_regex, $serialized_theme, $matches );

			if ( ! empty( $matches[2] ) ) {
				$this->config['theme'][]                                = $theme->stylesheet;
				$this->config[ $theme->stylesheet ]['theme_key']        = $theme->stylesheet;
				$this->config[ $theme->stylesheet ]['GitHub_Theme_URI'] = $matches[2];
				$this->config[ $theme->stylesheet ]['GitHub_API_URI']   = 'https://api.github.com/repos' . parse_url( $matches[2], PHP_URL_PATH );
				$this->config[ $theme->stylesheet ]['theme-data']       = wp_get_theme( $theme->stylesheet );
			}
		}
	}

	/**
	 * Call the GitHub API and return a json decoded body.
	 *
	 * @since 1.0
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
	 * Reads the remote plugin file.
	 *
	 * Uses a transient to limit the calls to the API.
	 *
	 * @since 1.0
	 */
	protected function get_remote_info( $url ) {

		$remote = get_site_transient( md5( $url ) ) ;

		if ( ! $remote ) {
			$remote = $this->api( $url );
			if ( $remote )
				set_site_transient( md5( $url ), $remote, 60*60 );

		}
		return $remote;
	}

	/**
	 * Finds newest tag and compares to current tag
	 *
	 * @since 1.0
	 * @param array $data
	 * @return array|object
	 */
	public function transient_update_themes_filter( $data ){

		foreach ( $this->config as $theme => $theme_data ) {
			if( empty( $theme_data['GitHub_API_URI'] ) ) continue;
			$url = trailingslashit( $theme_data['GitHub_API_URI'] ) . 'tags';
			$response = $this->get_remote_info( $url );

			// Sort and get latest tag
			$tags = array();
			if( !($response === false) )
				foreach( $response as $num => $tag ) {
					if( isset( $tag->name ) ) $tags[] = $tag->name;
				}
			usort( $tags, "version_compare" );

			// check and generate download link
			$newest_tag = end( array_values( $tags ) );
			$download_link = trailingslashit( $theme_data['GitHub_Theme_URI'] ) . trailingslashit( 'archive' ) . $newest_tag . '.zip';

			if( !empty( $newest_tag ) ) {
				// setup update array to append version info
				$update = array();
				$update['new_version']     = $newest_tag;
				$update['url']             = $theme_data['GitHub_Theme_URI'];
				$update['package']         = $download_link;

				if( !is_null($theme_data['theme-data']->Version) )
					if( version_compare( $theme_data['theme-data']->Version,  $newest_tag, '>=' ) ) {
						// up-to-date!
						$data->up_to_date[ $theme_data['theme_key'] ]['rollback'] = $tags;
						$data->up_to_date[ $theme_data['theme_key'] ]['response'] = $update;
					} else {
						$data->response[ $theme_data['theme_key'] ] = $update;
					}
				}
			}
		return $data;
	}

	/**
	 *	Github delivers zip files as <Username>-<TagName>-<Hash>.zip
	 *	must rename this zip file to the accurate theme folder
	 * 
	 * @since 1.0
	 * @param string
	 * @return string 
	 */
	public function upgrader_source_selection_filter( $source, $remote_source=NULL, $upgrader=NULL ) {

		if( isset( $source ) )
			for ( $i = 0; $i < count( $this->config['theme'] ); $i++ ) {
				if( strpos( $source, $this->config['theme'][$i] ) ) {
					$theme = $this->config['theme'][$i];

					if( isset( $_GET['action'] ) && stristr( $_GET['action'], 'theme' ) ) {
						$upgrader->skin->feedback( "Trying to customize theme folder name..." );
						if( isset( $source, $remote_source ) && stristr( $source, $theme ) ){
							$corrected_source = trailingslashit( $remote_source ) . trailingslashit( $theme );
							if( @rename( $source, $corrected_source ) ) {
								$upgrader->skin->feedback( "Theme folder name corrected to: " . $theme );
								return $corrected_source;
							} else {
								$upgrader->skin->feedback( "Unable to rename downloaded theme." );
								return new WP_Error();
							}
						}
					}
				}
			}

	return $source;
	}

	/* https://github.com/UCF/Theme-Updater/issues/3 */
	public function no_ssl_http_request_args( $args, $url ) {
		$args['sslverify'] = false;
		return $args;
	}

}

new GitHub_Theme_Updater();
