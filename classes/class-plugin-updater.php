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
		add_filter( 'extra_plugin_headers', array( $this, 'add_plugin_headers' ) );

		// Get details of GitHub-sourced plugins
		$this->config = $this->get_plugin_meta();
		if ( empty( $this->config ) ) return;

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'pre_set_site_transient_update_plugins' ) );
		add_filter( 'upgrader_source_selection', array( $this, 'upgrader_source_selection' ), 10, 3 );
		add_action( 'http_request_args', array( $this, 'no_ssl_http_request_args' ) );
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
	 * Uses a transient to limit the calls to the API.
	 *
	 * @since 1.7.0
	 *
	 * @return string latest tag.
	 */
	protected function get_remote_tag() {
		$url = '/repos/' . trailingslashit( $this->github_plugin['owner'] ) . trailingslashit( $this->github_plugin['repo'] ) . 'tags';

		$response = get_site_transient( md5( $this->github_plugin['slug'] . 'tags' ) );

		if ( ! $response ) {
			$response = $this->api( $url );

			if ( $response )
				set_site_transient( md5( $this->github_plugin['slug'] . 'tags' ), $response, HOUR_IN_SECONDS );
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
	public function pre_set_site_transient_update_plugins( $transient ) {
		if ( empty( $transient->checked ) )
			return $transient;

		foreach ( $this->config as $plug ) {
			$this->github_plugin = $plug;
			$local_version  = $this->get_local_version( $this->github_plugin );
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
	public function upgrader_source_selection( $source, $remote_source = null, $upgrader = null ) {

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
				__( 'Renaming %s to %s&#8230;', 'github-updater' ),
				'<span class="code">' . basename( $source ) . '</span>',
				'<span class="code">' . basename( $corrected_source ) . '</span>'
			)
		);

		// If we can rename, do so and return the new name
		if ( $wp_filesystem->move( $source, $corrected_source, true ) ) {
			$upgrader->skin->feedback( __( 'Rename successful&#8230;', 'github-updater' ) );
			return $corrected_source;
		}

		// Otherwise, return an error
		$upgrader->skin->feedback( __( 'Unable to rename downloaded plugin.', 'github-updater' ) );
		return new WP_Error();
	}

}
