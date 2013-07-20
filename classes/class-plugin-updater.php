<?php


/**
 * Update a WordPress plugin via GitHub
 *
 *
 * @version 1.0
 */
class GitHub_Plugin_Updater {

	/**
	 * Stores the config.
	 *
	 * @since 1.0
	 * @var type
	 */
	protected $config;
	protected $github_plugin;

	/**
	 * Constructor.
	 *
	 * @since 1.0
	 * @param array $config
	 */
	public function __construct() {

		add_filter( 'extra_plugin_headers', array( $this, 'add_headers' ) );

		$this->config = $this->get_plugin_meta();
		
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'update_available' ) );
		add_filter( 'upgrader_source_selection', array( $this, 'upgrader_source_selection_filter' ), 10, 3 );
		add_action( 'http_request_args', array( $this, 'no_ssl_http_request_args' ), 10, 2 );
	}

	/**
	 * Add extra header from plugin 'GitHub Plugin URI'
	 *
	 */
	public function add_headers( $extra_headers ) {
		$extra_headers = array( 'GitHub Plugin URI', 'GitHub Access Token' );
		return $extra_headers;
	}

	protected function get_plugin_meta() {
		include_once( ABSPATH . '/wp-admin/includes/plugin.php' );
		$plugins = get_plugins();
		$i = 0;

		foreach ( $plugins as $plugin => $headers ) {
			if ( ! empty($headers['GitHub Plugin URI']) ) {
				$repo = explode( '/', ltrim( parse_url( $headers['GitHub Plugin URI'], PHP_URL_PATH ), '/' ) );
				$arr[$i]['owner']        = $repo[0];
				$arr[$i]['repo']         = $repo[1];
				$arr[$i]['slug']         = $plugin;
				$arr[$i]['uri']          = $headers['GitHub Plugin URI'];
				$arr[$i]['access_token'] = $headers['GitHub Access Token'];
				$i++;
			}
		}
		return $arr;
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

		$response = wp_remote_get( $this->get_api_url( $url ) );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != '200' )
			return false;

		return json_decode( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Return API url.
	 *
	 * @todo Maybe allow a filter to add or modify segments.
	 * @since 1.0
	 * @param string $endpoint
	 * @return string
	 */
	protected function get_api_url( $endpoint ) {

		$segments = array(
			'owner'          => $this->github_plugin['owner'],
			'repo'           => $this->github_plugin['repo'],
			'archive_format' => 'zipball',
		);

		foreach ( $segments as $segment => $value ) {
			$endpoint = str_replace( '/:' . $segment, '/' . $value, $endpoint );
		}

		if ( ! empty( $this->github_plugin['access_token'] ) )
			$endpoint = add_query_arg( 'access_token', $this->github_plugin['access_token'] );

		return 'https://api.github.com' . $endpoint;
	}

	/**
	 * Reads the remote plugin file.
	 *
	 * Uses a transient to limit the calls to the API.
	 *
	 * @since 1.0
	 */
	protected function get_remote_info() {

		$remote = get_site_transient( md5( $this->github_plugin['slug'] ) );

		if ( ! $remote ) {
			$remote = $this->api( '/repos/:owner/:repo/contents/' . basename( $this->github_plugin['slug'] ) );

			if ( $remote )
				set_site_transient( md5( $this->github_plugin['slug'] ), $remote, 60 * 60 );
		}
		return $remote;
	}

	/**
	 * Retrieves the local version from the file header of the plugin
	 *
	 * @since 1.0
	 * @return string|boolean
	 */
	protected function get_local_version() {

		$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $this->github_plugin['slug'] );

		if ( ! empty( $data['Version'] ) )
			return $data['Version'];

		return false;
	}

	/**
	 * Retrieves the remote version from the file header of the plugin
	 *
	 * @since 1.0
	 * @return string|boolean
	 */
	protected function get_remote_version() {

		$response = $this->get_remote_info();
		if ( ! $response )
			return false;

		preg_match( '#^\s*Version\:\s*(.*)$#im', base64_decode( $response->content ), $matches );

		if ( ! empty( $matches[1] ) )
			return $matches[1];

		return false;
	}

	/**
	 * Hooks into pre_set_site_transient_update_plugins to update from GitHub.
	 *
	 * @since 1.0
	 * @todo fill url with value from remote repostory
	 * @param $transient
	 * @return $transient If all goes well, an updated one.
	 */
	public function update_available( $transient ) {

		if ( empty( $transient->checked ) )
			return $transient;

		foreach ( $this->config as $plug ) {
			$this->github_plugin = $plug;
			$local_version  = $this->get_local_version();
			$remote_version = $this->get_remote_version();
			$download_link = trailingslashit( $this->github_plugin['uri'] ) . 'archive/master.zip';

			if ( $local_version && $remote_version && version_compare( $remote_version, $local_version, '>' ) ) {
				$plugin = array(
					'slug'        => dirname( $this->github_plugin['slug'] ),
					'new_version' => $remote_version,
					'url'         => null,
					'package'     => $download_link,
				);

				$transient->response[ $this->github_plugin['slug'] ] = (object) $plugin;
			}
		}
		return $transient;
	}

	
	/**
	 *	Github delivers zip files as <Username>-<TagName>.zip
	 *	must rename this zip file to the accurate plugin folder
	 * 
	 * @since 1.0
	 * @param string
	 * @return string 
	 */
	public function upgrader_source_selection_filter( $source, $remote_source=NULL, $upgrader=NULL ) {

		if( isset( $source ) )
			for ( $i = 0; $i < count( $this->config ); $i++ ) {
				if( stristr( basename( $source ), $this->config[$i]['repo'] ) )
					$plugin = $this->config[$i]['repo'];
			}

		if( isset( $_GET['action'] ) && stristr( $_GET['action'], 'update-selected' ) )
			if( isset( $source, $remote_source, $plugin ) && stristr( basename( $source ), $plugin ) ) {
				$upgrader->skin->feedback( "Trying to customize plugin folder name..." );
				$corrected_source = trailingslashit( $remote_source ) . trailingslashit( $plugin );
				if( @rename( $source, $corrected_source ) ) {
					$upgrader->skin->feedback( "Plugin folder name corrected to: " . $plugin );
					return $corrected_source;
				} else {
					$upgrader->skin->feedback( "Unable to rename downloaded plugin." );
					return new WP_Error();
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

new GitHub_Plugin_Updater();
