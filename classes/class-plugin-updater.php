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
		$extra_headers = array( 'GitHub Plugin URI', 'GitHub Access Token', 'GitHub Branch' );
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

		$plugins = get_plugins();

		foreach ( $plugins as $plugin => $headers ) {
			$git_repo = $this->get_repo_info( $headers );
			if ( empty( $git_repo['owner'] ) )
				continue;
			$git_repo['slug'] = $plugin;
			$github_plugins[] = $git_repo;
		}

		return $github_plugins;
	}

	/**
	* Parse extra headers to determine repo type and populate info
	*
	* @since 1.6.0
	* @param array of extra headers
	* @return array of repo information
	*
	* parse_url( ..., PHP_URL_PATH ) is either clever enough to handle the short url format
	* (in addition to the long url format), or it's coincidentally returning all of the short
	* URL string, which is what we want anyway.
	*
	*/
	protected function get_repo_info( $headers ) {
		$extra_headers = $this->add_headers( null );

		foreach ( $extra_headers as $key => $value ) {
			switch( $value ) {
				case 'GitHub Plugin URI':
					if ( empty( $headers['GitHub Plugin URI'] ) )
						return;

					$owner_repo        = parse_url( $headers['GitHub Plugin URI'], PHP_URL_PATH );
					$owner_repo        = trim( $owner_repo, '/' );  // strip surrounding slashes
					$git_repo['uri']   = 'https://github.com/' . $owner_repo;
					$owner_repo        = explode( '/', $owner_repo );
					$git_repo['owner'] = $owner_repo[0];
					$git_repo['repo']  = $owner_repo[1];
					break;
				case 'GitHub Access Token':
					$git_repo['access_token'] = $headers['GitHub Access Token'];
					break;
				case 'GitHub Branch':
					$git_repo['branch'] = $headers['GitHub Branch'];
					break;
			}
		}

		return $git_repo;
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

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != '200' )
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
			'owner' => $this->github_plugin['owner'],
			'repo'  => $this->github_plugin['repo'],
		);

		/**
 		 * Add or filter the available segments that are used to replace placeholders.
		 *
		 * @since 1.5.0
		 *
		 * @param array $segments List of segments.
		 */
		$segments = apply_filters( 'github_updater_api_segments', $segments );

		foreach ( $segments as $segment => $value ) {
			$endpoint = str_replace( '/:' . $segment, '/' . $value, $endpoint );
		}

		if ( ! empty( $this->github_plugin['access_token'] ) )
			$endpoint = add_query_arg( 'access_token', $this->github_plugin['access_token'], $endpoint );

		// If a branch has been given, only check that for the remote info.
		// If it's not been given, GitHub will use the Default branch.
		if ( ! empty( $this->github_plugin['branch'] ) )
			$endpoint = add_query_arg( 'ref', $this->github_plugin['branch'], $endpoint );

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

		if ( ! $remote ) {
			$remote = $this->api( '/repos/:owner/:repo/contents/' . basename( $this->github_plugin['slug'] ) );

			if ( $remote )
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

		if ( ! empty( $data['Version'] ) )
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
		if ( ! $response )
			return false;

		preg_match( '/^[ \t\/*#@]*Version\:\s*(.*)$/im', base64_decode( $response->content ), $matches );

		if ( ! empty( $matches[1] ) )
			return $matches[1];

		return false;
	}

	/**
	 * Parse the remote info to find what the default branch is.
	 *
	 * @since 1.5.0
	 *
	 * @return string Default branch name.
	 */
	protected function get_default_branch() {
		// If we've had to call this default branch method, we know that a branch header has not been provided. As such
		// the remote info was retrieved without a ?ref=... query argument.
		$response = $this->get_remote_info();

		// If we can't contact GitHub API, then assume a sensible default in case the non-API part of GitHub is working.
		if ( ! $response )
			return 'master';

		// Assuming we've got some remote info, parse the 'url' field to get the last bit of the ref query string
		$components = parse_url( $response->url, PHP_URL_QUERY );
		parse_str( $components );
		return $ref;
	}


	/**
	 * Parse the remote info to find most recent tag if tags exist
	 *
	 * @since 1.7.0
	 *
	 * @return string latest tag.
	 */
	protected function get_remote_tag() {
		$url = '/repos/' . trailingslashit( $this->github_plugin['owner'] ) . trailingslashit( $this->github_plugin['repo'] ) . 'tags';
		$response = $this->api( $url );

		// Sort and get latest tag
		$tags = array();
		if ( false !== $response )
			foreach ( $response as $num => $tag ) {
				if ( isset( $tag->name ) ) $tags[] = $tag->name;
			}
		usort( $tags, "version_compare" );

		// check and generate download link
		$newest_tag = null;
		$newest_tag_key = key( array_slice( $tags, -1, 1, true ) );

		if ( $newest_tag_key )
			$newest_tag = $tags[ $newest_tag_key ];

		// if no tag set then abort
		if ( empty( $newest_tag ) )
			return false;

		return $newest_tag;
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
		if ( empty( $transient->checked ) )
			return $transient;

		foreach ( $this->config as $plug ) {
			$this->github_plugin = $plug;
			$local_version  = $this->get_local_version();
			$remote_version = $this->get_remote_version();

			$branch = $this->github_plugin['branch'] ? $this->github_plugin['branch'] : $this->get_default_branch();

			$newest_tag = $this->get_remote_tag();

			// just in case user started using tags then stopped.
			if ( $remote_version && $newest_tag && version_compare( $newest_tag, $remote_version, '>=' ) ) {
				$download_link = trailingslashit( $this->github_plugin['uri'] ) . 'archive/' . $newest_tag . '.zip';
			} else {
				$download_link = trailingslashit( $this->github_plugin['uri'] ) . 'archive/' . $branch . '.zip';
			}

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
	 * Rename the zip folder to be the same as the existing plugin folder.
	 *
	 * Github delivers zip files as <Repo>-<Branch>.zip
	 *
	 * @since 1.0.0
	 *
	 * @global WP_Filesystem $wp_filesystem
	 *
	 * @param string $source
	 * @param string $remote_source Optional.
	 * @param object $upgrader      Optional.
	 *
	 * @return string
	 */
	public function upgrader_source_selection_filter( $source, $remote_source = null, $upgrader = null ) {

		global $wp_filesystem;
		$update = array( 'update-selected', 'update-selected-themes', 'upgrade-theme', 'upgrade-plugin' );

		if ( isset( $source ) ) {
			for ( $i = 0; $i < count( $this->config ); $i++ ) {
				if ( stristr( basename( $source ), $this->config[$i]['repo'] ) )
					$plugin = $this->config[$i]['repo'];
			}
		}

		// If there's no action set, or not one we recognise, abort
		if ( ! isset( $_GET['action'] ) || ! in_array( $_GET['action'], $update, true ) )
			return $source;

		// If the values aren't set, or it's not GitHub-sourced, abort
		if ( ! isset( $source, $remote_source, $plugin ) || false === stristr( basename( $source ), $plugin ) )
			return $source;

		$corrected_source = trailingslashit( $remote_source ) . trailingslashit( $plugin );
		$upgrader->skin->feedback(
			sprintf(
				__( 'Renaming %s to %s...', 'github-updater' ),
				'<span class="code">' . basename( $source ) . '</span>',
				'<span class="code">' . basename( $corrected_source ) . '</span>'
			)
		);

		// If we can rename, do so and return the new name
		if ( $wp_filesystem->move( $source, $corrected_source, true ) ) {
			$upgrader->skin->feedback( __( 'Rename successful...', 'github-updater' ) );
			return $corrected_source;
		}

		// Otherwise, return an error
		$upgrader->skin->feedback( __( 'Unable to rename downloaded plugin.', 'github-updater' ) );
		return new WP_Error();
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
