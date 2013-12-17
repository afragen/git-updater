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
 * Get remote data from a GitHub repo.
 *
 * @package GitHub_Updater_API
 * @author  Andy Fragen
 */
class GitHub_Updater_GitHub_API extends GitHub_Updater {

	/**
	 * Variable for setting update transient hours
	 *
	 * @var integer
	 */
	 protected static $hours = 4;
	 
	/**
	 * Return an instance of this class.
	 *
	 * @since     2.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
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
			'owner' => $this->{$this->type}->owner,
			'repo'  => $this->{$this->type}->repo,
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

		if ( ! empty( $this->{$this->type}->access_token ) )
			$endpoint = add_query_arg( 'access_token', $this->{$this->type}->access_token, $endpoint );

		// If a branch has been given, only check that for the remote info.
		// If it's not been given, GitHub will use the Default branch.
		if ( ! empty( $this->{$this->type}->branch ) )
			$endpoint = add_query_arg( 'ref', $this->{$this->type}->branch, $endpoint );

		return 'https://api.github.com' . $endpoint;
	}

	/**
	 * Read the remote file.
	 *
	 * Uses a transient to limit the calls to the API.
	 *
	 * @since 1.0.0
	 */
	protected function get_remote_info( $file ) {
		$remote = get_site_transient( md5( $this->{$this->type}->repo . $file ) );
		if ( ! $remote ) {
			$remote = $this->api( '/repos/:owner/:repo/contents/' . $file );

			if ( $remote ) {
				self::$hours = apply_filters( 'github_updater_set_transient_hours', self::$hours );
				set_site_transient( md5( $this->{$this->type}->repo . $file ), $remote, ( self::$hours * HOUR_IN_SECONDS ) );
			}
		}

		if ( ! $remote ) return;

		preg_match( '/^[ \t\/*#@]*Version\:\s*(.*)$/im', base64_decode( $remote->content ), $matches );

		if ( ! empty( $matches[1] ) )
			$this->{$this->type}->remote_version = $matches[1];

		$this->{$this->type}->branch = $this->get_default_branch( $remote );
	}

	/**
	 * Parse the remote info to find what the default branch is.
	 *
	 * If we've had to call this method, we know that a branch header has not been provided.
	 * As such the remote info was retrieved with a ?ref=... query argument.
	 *
	 * @since 1.5.0
	 * @param array API object
	 *
	 * @return string Default branch name.
	 */
	protected function get_default_branch( $response ) {
		if ( ! empty( $this->{$this->type}->branch ) )
			return $this->{$this->type}->branch;

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
		$response = get_site_transient( md5( $this->{$this->type}->repo . 'tags' ) );

		if ( ! $response ) {
			$response = $this->api( '/repos/:owner/:repo/tags' );

			if ( $response ) {
				self::$hours = apply_filters( 'github_updater_set_transient_hours', self::$hours );
				set_site_transient( md5( $this->{$this->type}->repo . 'tags' ), $response, ( self::$hours * HOUR_IN_SECONDS ) );
			}
		}

		// Sort and get latest tag
		$tags = array();
		if ( false !== $response )
			foreach ( $response as $num => $tag ) {
				if ( isset( $tag->name ) ) $tags[] = $tag->name;
			}

		if ( empty( $tags ) ) return;  // no tags are present, exit early

		usort( $tags, 'version_compare' );
		
		// check and generate download link
		$newest_tag     = null;
		$newest_tag_key = key( array_slice( $tags, -1, 1, true ) );
		$newest_tag     = $tags[ $newest_tag_key ];

		$this->{$this->type}->newest_tag    = $newest_tag;
		$this->{$this->type}->download_link =  $this->{$this->type}->uri . '/archive/' . $this->{$this->type}->newest_tag . '.zip';
		$this->{$this->type}->tags          = $tags;
	}

	/**
	 * Construct $download_link
	 *
	 * @since 1.9.0
	 *
	 * @param stdClass plugin data
	 */
	protected function construct_download_link() {

		// just in case user started using tags then stopped.
		if ( $this->{$this->type}->remote_version && $this->{$this->type}->newest_tag && version_compare( $this->{$this->type}->newest_tag, $this->{$this->type}->remote_version, '>=' ) ) {							
			$download_link = $this->{$this->type}->uri . '/archive/' . $this->{$this->type}->newest_tag . '.zip';
		} else {
			$download_link = $this->{$this->type}->uri . '/archive/' . $this->{$this->type}->branch . '.zip';
		}
		return $download_link;
	}

	/**
	 * Read the remote CHANGES.md file
	 *
	 * Uses a transient to limit calls to the API.
	 *
	 * @since 1.9.0
	 * @return base64 decoded CHANGES.md or false
	 */
	protected function get_remote_changes() {

		$remote = get_site_transient( md5( $this->{$this->type}->repo . 'changes' ) );

		if ( ! $remote ) {
			$remote = $this->api( '/repos/:owner/:repo/contents/CHANGES.md' );

			if ( $remote ) {
				self::$hours = apply_filters( 'github_updater_set_transient_hours', self::$hours );
				set_site_transient( md5( $this->{$this->type}->repo . 'changes' ), $remote, ( self::$hours * HOUR_IN_SECONDS ) );				
			}
		}
		
		if ( false != $remote ) {
			$this->{$this->type}->sections['changelog'] = '<pre>' . base64_decode( $remote->content ) . '</pre>';
		}

	}

}