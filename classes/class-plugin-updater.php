<?php
/**
 * GitHub Updater
 *
 * @package   GitHubUpdater
 * @author    Andy Fragen, Codepress
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 */

/**
 * Update a WordPress plugin from a GitHub repo.
 *
 * @package GitHubUpdater
 * @author  Andy Fragen, Codepress
 */
class GitHub_Plugin_Updater {

	/**
	 * Store details of all GitHub-sourced plugins that are installed.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * Store details for one GitHub-sourced plugin during the update procedure.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected $github_plugin;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array $config
	 */
	public function __construct() {
		// This MUST come before we get details about the plugins so the headers are correctly retrieved
		add_filter( 'extra_plugin_headers', array( $this, 'add_headers' ) );

		// Get details of GitHub-sourced plugins
		$this->config = $this->get_plugin_meta();

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'update_available' ) );
		add_filter( 'upgrader_source_selection', array( $this, 'upgrader_source_selection_filter' ), 10, 3 );
		add_action( 'http_request_args', array( $this, 'no_ssl_http_request_args' ) );
	}

	/**
	 * Add extra header from plugin 'GitHub Plugin URI'
	 *
	 * @since 1.0.0
	 */
	public function add_headers( $extra_headers ) {
		$extra_headers = array( 'GitHub Plugin URI', 'GitHub Access Token' );
		return $extra_headers;
	}

	/**
	 * Get details of GitHub-sourced plugins from those that are installed.
	 *
	 * @since 1.0.0
	 *
	 * @return array Indexed array of associative arrays of plugin details.
	 */
	protected function get_plugin_meta() {
		// Ensure get_plugins() function is available.
		include_once( ABSPATH . '/wp-admin/includes/plugin.php' );

		$plugins        = get_plugins();
		$github_plugins = array();
		$i              = 0;

		foreach( $plugins as $plugin => $headers ) {
			if ( empty( $headers['GitHub Plugin URI'] ) )
				continue;

			$repo = explode( '/', ltrim( parse_url( $headers['GitHub Plugin URI'], PHP_URL_PATH ), '/' ) );
			$github_plugins[$i]['owner']        = $repo[0];
			$github_plugins[$i]['repo']         = $repo[1];
			$github_plugins[$i]['slug']         = $plugin;
			$github_plugins[$i]['uri']          = $headers['GitHub Plugin URI'];
			$github_plugins[$i]['access_token'] = $headers['GitHub Access Token'];
			$i++;
		}
		return $github_plugins;
	}

	/**
	 * Call the GitHub API and return a json decoded body.
	 *
	 * @since 1.0.0
	 *
	 * @see http://developer.github.com/v3/
	 *
	 * @param string $url
	 *
	 * @return boolean|object
	 */
	protected function api( $url ) {
		$response = wp_remote_get( $this->get_api_url( $url ) );

		if( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != '200' )
			return false;

		return json_decode( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Return API url.
	 *
	 * @since 1.0.0
	 *
	 * @param string $endpoint
	 *
	 * @return string
	 */
	protected function get_api_url( $endpoint ) {
		$segments = array(
			'owner'          => $this->github_plugin['owner'],
			'repo'           => $this->github_plugin['repo'],
		);

		/**
 		 * Add or filter the available segments that are used to replace placeholders.
		 *
		 * @since 1.4.4
		 *
		 * @param array $segments List of segments.
		 */
		$segments = apply_filters( 'github_updater_api_segments', $segments );

		foreach( $segments as $segment => $value ) {
			$endpoint = str_replace( '/:' . $segment, '/' . $value, $endpoint );
		}

		if( ! empty( $this->github_plugin['access_token'] ) )
			$endpoint = add_query_arg( 'access_token', $this->github_plugin['access_token'], $endpoint );

		return 'https://api.github.com' . $endpoint;
	}

	/**
	 * Read the remote plugin file.
	 *
	 * Uses a transient to limit the calls to the API.
	 *
	 * @since 1.0.0
	 */
	protected function get_remote_info() {
		$remote = get_site_transient( md5( $this->github_plugin['slug'] ) );

		if( ! $remote ) {
			$remote = $this->api( '/repos/:owner/:repo/contents/' . basename( $this->github_plugin['slug'] ) );

			if( $remote )
				set_site_transient( md5( $this->github_plugin['slug'] ), $remote, HOUR_IN_SECONDS );
		}
		return $remote;
	}

	/**
	 * Retrieves the local version from the file header of the plugin
	 *
	 * @since 1.0.0
	 *
	 * @return string|boolean Version of installed plugin, false if not determined.
	 */
	protected function get_local_version() {
		$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $this->github_plugin['slug'] );

		if( ! empty( $data['Version'] ) )
			return $data['Version'];

		return false;
	}

	/**
	 * Retrieve the remote version from the file header of the plugin
	 *
	 * @since 1.0.0
	 *
	 * @return string|boolean Version of remote plugin, false if not determined.
	 */
	protected function get_remote_version() {
		$response = $this->get_remote_info();
		if( ! $response )
			return false;

		preg_match( '/^[ \t\/*#@]*Version\:\s*(.*)$/im', base64_decode( $response->content ), $matches );

		if( ! empty( $matches[1] ) )
			return $matches[1];

		return false;
	}

	/**
	 * Hook into pre_set_site_transient_update_plugins to update from GitHub.
	 *
	 * The branch to download is hard-coded as the Master branch. Consider using Git-Flow so that Master is always clean.
	 *
	 * @todo fill url with value from remote repostory
	 *
	 * @since 1.0.0
	 *
	 * @param object $transient Original transient.
	 *
	 * @return $transient If all goes well, an updated transient that may include details of a plugin update.
	 */
	public function update_available( $transient ) {
		if( empty( $transient->checked ) )
			return $transient;

		foreach( $this->config as $plug ) {
			$this->github_plugin = $plug;
			$local_version  = $this->get_local_version();
			$remote_version = $this->get_remote_version();
			$download_link = trailingslashit( $this->github_plugin['uri'] ) . 'archive/master.zip';

			if( $local_version && $remote_version && version_compare( $remote_version, $local_version, '>' ) ) {
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
	 * Rename the zip folder to be the same as the existing plugin folder.
	 *
	 * Github delivers zip files as <Repo>-<Branch>.zip
	 *
	 * @since 1.0.0
	 *
	 * @param string $source
	 * @param string $remote_source Optional.
	 * @param object $upgrader      Optional.
	 *
	 * @return string
	 */
	public function upgrader_source_selection_filter( $source, $remote_source = null, $upgrader = null ) {
		if( isset( $source ) ) {
			for( $i = 0; $i < count( $this->config ); $i++ ) {
				if( stristr( basename( $source ), $this->config[$i]['repo'] ) )
					$plugin = $this->config[$i]['repo'];
			}
		}

		if( isset( $_GET['action'] ) && stristr( $_GET['action'], 'update-selected' ) ) {
			if( isset( $source, $remote_source, $plugin ) && stristr( basename( $source ), $plugin ) ) {
				$upgrader->skin->feedback( __( 'Trying to customize plugin folder name...', 'github-updater' ) );
				$corrected_source = trailingslashit( $remote_source ) . trailingslashit( $plugin );
				if( @rename( $source, $corrected_source ) ) {
					$upgrader->skin->feedback( __( 'Plugin folder name corrected to: ', 'github-updater' ) . $plugin );
					return $corrected_source;
				} else {
					$upgrader->skin->feedback( __( 'Unable to rename downloaded plugin.', 'github-updater' ) );
					return new WP_Error();
				}
			}
		}

		return $source;
	}

	/**
	 * Fixes {@link https://github.com/UCF/Theme-Updater/issues/3}.
	 *
	 * @since 1.0.0
	 *
	 * @param  array $args Existing HTTP Request arguments.
	 *
	 * @return array Amended HTTP Request arguments.
	 */
	public function no_ssl_http_request_args( $args ) {
		$args['sslverify'] = false;
		return $args;
	}
}
